import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/api/api_exception.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/core/utils/idempotency.dart';
import 'package:flavor_mobile/models/dish_model.dart';
import 'package:flavor_mobile/repositories/auth_repository.dart';
import 'package:flavor_mobile/repositories/cart_repository.dart';
import 'package:flavor_mobile/repositories/menu_repository.dart';
import 'package:flavor_mobile/repositories/order_repository.dart';
import 'package:flavor_mobile/state/order_state.dart';

/// Every mocked endpoint below replays a REAL payload captured from the live
/// PHP controllers (flavor-core) into test/fixtures/ — prompt 65 replaced the
/// hand-invented JSON that used to drift from the API contract (fake `roles`
/// key, `remaining_capacity`-style inventions, made-up tracking steps).
String fixture(String name) => File('test/fixtures/$name.json').readAsStringSync();

const Map<String, String> jsonHeaders = {
  'content-type': 'application/json; charset=utf-8',
};

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  SharedPreferences.setMockInitialValues({});
  FlutterSecureStorage.setMockInitialValues({});

  group('Full Customer Journey Integration Test Flow', () {
    test('Login -> Browse Menu -> Add to Cart -> Checkout -> Track Order', () async {
      final mockHttpClient = MockClient((request) async {
        final path = request.url.path;

        if (path.contains('/auth/otp/verify')) {
          return http.Response(
            fixture('auth_otp_verify'),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('categories')) {
          return http.Response(
            fixture('categories'),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/cart/items')) {
          return http.Response(
            fixture('cart_add_item'),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/orders') && request.method == 'POST') {
          return http.Response(
            fixture('order_create'),
            201,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        if (path.contains('/orders/1001/track') || path.contains('/orders/1001')) {
          return http.Response(
            fixture('order_track'),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }

        return http.Response(jsonEncode({'success': true, 'data': {}}), 200);
      });

      final storage = StorageService();
      final apiClient = ApiClient(httpClient: mockHttpClient, storage: storage);

      final authRepo = AuthRepository(apiClient: apiClient, storage: storage);
      final menuRepo = MenuRepository(apiClient: apiClient, storage: storage);
      final cartRepo = CartRepository(apiClient: apiClient);
      final orderRepo = OrderRepository(apiClient: apiClient);

      // 1. Verify Authentication State
      expect(await authRepo.isAuthenticated(), false);

      // 2. OTP Verification Flow (real envelope: tokens + user + is_new_user)
      final user = await authRepo.verifyOtp('09125554433', '12345', name: 'علی رضایی');
      expect(user.displayName, 'علی رضایی');
      expect(user.mobile, '09125554433');
      // OTP-only accounts get their synthetic @otp.flavor.local mail stripped
      // server-side — the model must surface an empty string, not a fake mail.
      expect(user.email, '');
      // Loyalty follows PointsManager::summary(), not invented tier keys.
      expect(user.loyalty.points, 0);
      expect(user.loyalty.stampTarget, 10);
      expect(user.loyalty.freeItem, false);
      expect(await authRepo.isAuthenticated(), true);

      // 3. Browse Menu & Categories (three real seeded categories)
      final categories = await menuRepo.getCategories();
      expect(categories, isA<List>());
      expect(categories.length, 3);
      expect(categories.first.name, 'کباب و گریل');

      // 4. Customise Dish and Add to Cart
      const testDish = DishModel(
        id: 1,
        name: 'چلو کباب کوبیده مخصوص زعفرانی',
        slug: 'dish-1',
        price: 2800000,
        priceHtml: '۲۸۰,۰۰۰ تومان',
      );

      final cart = await cartRepo.addItem(
        productId: testDish.id,
        quantity: 2,
        modifierIds: ['size-large', 'extra_rice'],
        instructions: 'لطفاً سماق قرمز جداگانه ارسال شود.',
      );
      expect(cart.items.isNotEmpty, true);
      expect(cart.count, 2);
      // Real modifier selection rows survive the round trip.
      final line = cart.items.first;
      expect(line.modifiers.length, 2);
      expect(line.modifiers.first.id, 'size-large');
      expect(line.modifiers.first.typeLabel, 'اندازه');
      expect(line.modifiers.first.price, 50000);
      // Authoritative storage-unit numerics (base + extras, ×quantity).
      expect(line.unitPrice, 2875000);
      expect(line.lineTotal, 5750000);
      expect(line.instructions, 'لطفاً سماق قرمز جداگانه ارسال شود.');
      expect(cart.subtotal, 5750000);
      expect(cart.total, 5750000);
      expect(cart.supportsItemEdit, true);

      // 5. Checkout & Submit Order (real create response: ok/order_id/...)
      final orderResult = await orderRepo.createOrder(
        orderMode: 'dine_in',
        branchId: 1,
        name: user.displayName,
        mobile: user.mobile,
        address: {'address': 'تهران، زعفرانیه', 'unit': '۴'},
        paymentMethod: 'flavor_pay_at_counter',
      );
      expect(orderResult['ok'], true);
      expect(orderResult['order_id'], isNotNull);

      final orderId = int.parse(orderResult['order_id'].toString());
      expect(orderId, 1001);

      // 6. Live Track Order Progress (real track payload: current_status +
      //    the four canonical steps received/preparing/ready/completed)
      final tracking = await orderRepo.trackOrder(orderId);
      expect(tracking.id, 1001);
      expect(tracking.status, 'new');
      expect(tracking.steps.length, 4);
      expect(tracking.steps.map((s) => s.step).toList(),
          ['received', 'preparing', 'ready', 'completed']);
      expect(tracking.steps.first.completed, true);
      expect(tracking.isCancelled, false);
    });

    // ---- Prompt 67: the checkout idempotency contract, end to end ----
    // Fixtures order_create_replay / order_replay_unpaid / order_pay_failed_400
    // / order_conflict_409 were captured from the REAL CheckoutService (the
    // same harness that backs run-e2e-integration.php), not hand-written.

    test('Checkout derives a stable, server-legal idempotency key and sends it on POST /orders', () async {
      final bodies = <Map<String, dynamic>>[];
      final mock = MockClient((request) async {
        if (request.url.path.contains('/orders') && request.method == 'POST') {
          bodies.add(jsonDecode(request.body) as Map<String, dynamic>);
          return http.Response(fixture('order_create'), 201, headers: jsonHeaders);
        }
        return http.Response(jsonEncode({'success': true, 'data': {}}), 200, headers: jsonHeaders);
      });
      final apiClient = ApiClient(httpClient: mock, storage: StorageService());
      final orderRepo = OrderRepository(apiClient: apiClient);

      const sigA = '{"lines":["1:2:size-large,extra_rice:"],"coupons":[],"subtotal":5750000,"total":5750000}';
      String key({String sig = sigA, String table = 'T-01'}) => checkoutIdempotencyKey(
            cartSignature: sig,
            orderMode: 'dine_in',
            branchId: 1,
            tableNumber: table,
            paymentMethod: 'flavor_pay_at_counter',
            mobile: '09351112233',
          );

      final k1 = key();
      // CheckoutService::sanitize_idempotency_key(): [A-Za-z0-9_-], max 64.
      expect(k1, matches(RegExp(r'^[A-Za-z0-9_-]{1,64}$')));
      expect(k1.length, 64);
      expect(key(), k1, reason: 'identical submission => identical key (retries dedupe)');
      expect(key(table: 'T-02'), isNot(k1), reason: 'different table => genuinely new order');
      expect(key(sig: '{"lines":["1:3:size-large,extra_rice:"],"coupons":[],"subtotal":8625000,"total":8625000}'),
          isNot(k1), reason: 'edited cart rotates the key');

      await orderRepo.createOrder(
          orderMode: 'dine_in', branchId: 1, tableNumber: 'T-01',
          mobile: '09351112233', paymentMethod: 'flavor_pay_at_counter',
          idempotencyKey: k1);
      await orderRepo.createOrder(
          orderMode: 'dine_in', branchId: 1, tableNumber: 'T-01',
          mobile: '09351112233', paymentMethod: 'flavor_pay_at_counter',
          idempotencyKey: k1);
      expect(bodies, hasLength(2));
      expect(bodies[0]['idempotency_key'], k1);
      expect(bodies[1]['idempotency_key'], k1);

      // Omitting the key leaves the field out entirely — the server then
      // derives its own auto key from the cart fingerprint.
      await orderRepo.createOrder(
          orderMode: 'dine_in', branchId: 1, paymentMethod: 'flavor_pay_at_counter');
      expect(bodies[2].containsKey('idempotency_key'), isFalse);
    });

    test('Duplicate submit replays the SAME order (real 201 envelope, replay=true)', () async {
      final mock = MockClient((request) async =>
          http.Response(fixture('order_create_replay'), 201, headers: jsonHeaders));
      final orderRepo = OrderRepository(
          apiClient: ApiClient(httpClient: mock, storage: StorageService()));

      final res = await orderRepo.createOrder(
          orderMode: 'dine_in', branchId: 1, paymentMethod: 'flavor_pay_at_counter',
          idempotencyKey: 'fixture-replay-0001');
      expect(res['replay'], true);
      expect(res['ok'], true);
      expect(res['order_id'], 1001);
      expect(res['guest_token'].toString(), isNotEmpty,
          reason: 'a verified replay re-exposes the guest token for tracking');

      // The exact acceptance gate checkout_screen applies: a replay must be
      // treated as success and land on the ORIGINAL order.
      final orderId = int.tryParse(res['order_id'].toString()) ?? 0;
      expect(orderId > 0 && (res['ok'] == true || res['replay'] == true), isTrue);
    });

    test('Unpaid replay keeps the existing order visible; failed payment 400 stays Persian', () async {
      final mock = MockClient((request) async =>
          http.Response(fixture('order_replay_unpaid'), 201, headers: jsonHeaders));
      final orderRepo = OrderRepository(
          apiClient: ApiClient(httpClient: mock, storage: StorageService()));

      // Retry after a failed online gateway: ok=false but the order EXISTS
      // (recoverable) — the screen must show order 1002, not invite another
      // duplicate attempt with an error snackbar.
      final res = await orderRepo.createOrder(
          orderMode: 'dine_in', branchId: 1, paymentMethod: 'online_gateway',
          idempotencyKey: 'fixture-fail-0001');
      expect(res['replay'], true);
      expect(res['ok'], false);
      expect(res['payment_status'], 'failed');
      expect(res['recoverable'], true);
      final orderId = int.tryParse(res['order_id'].toString()) ?? 0;
      expect(orderId, 1002);
      expect(orderId > 0 && (res['ok'] == true || res['replay'] == true), isTrue,
          reason: 'replay gate accepts the unpaid duplicate');

      // The first-attempt failure itself: Persian bank message + the
      // recoverable/order_id details survive the envelope parsing.
      final failMock = MockClient((request) async =>
          http.Response(fixture('order_pay_failed_400'), 400, headers: jsonHeaders));
      final failRepo = OrderRepository(
          apiClient: ApiClient(httpClient: failMock, storage: StorageService()));
      await expectLater(
        failRepo.createOrder(
            orderMode: 'dine_in', branchId: 1, paymentMethod: 'online_gateway',
            idempotencyKey: 'fixture-fail-0001'),
        throwsA(isA<ApiException>()
            .having((e) => e.statusCode, 'statusCode', 400)
            .having((e) => e.code, 'code', 'flavor_pay_failed')
            .having((e) => e.message, 'message', 'تراکنش توسط بانک رد شد.')
            .having((e) => (e.details as Map)['recoverable'], 'recoverable', true)
            .having((e) => (e.details as Map)['order_id'], 'details.order_id', 1002)),
      );
    });

    test('409 idempotency conflict surfaces the Persian server message, not a raw error', () async {
      const conflictMessage = 'این سفارش قبلاً با نشانی دیگری ثبت شده است.';
      final mock = MockClient((request) async =>
          http.Response(fixture('order_conflict_409'), 409, headers: jsonHeaders));
      final orderRepo = OrderRepository(
          apiClient: ApiClient(httpClient: mock, storage: StorageService()));

      await expectLater(
        orderRepo.createOrder(
            orderMode: 'dine_in', branchId: 1, paymentMethod: 'flavor_pay_at_counter',
            idempotencyKey: 'fixture-conflict-0001'),
        throwsA(isA<ApiException>()
            .having((e) => e.statusCode, 'statusCode', 409)
            .having((e) => e.code, 'code', 'flavor_idempotency_conflict')
            .having((e) => e.toString(), 'toString — this is what reaches the SnackBar',
                conflictMessage)),
      );

      // Provider path used by checkout_screen: null result, Persian
      // errorMessage, and the typed exception kept for code-level branching.
      final provider = OrderProvider(orderRepo: orderRepo);
      final res = await provider.submitOrder(
          orderMode: 'dine_in', branchId: 1, mobile: '09351112233',
          paymentMethod: 'flavor_pay_at_counter', idempotencyKey: 'fixture-conflict-0001');
      expect(res, isNull);
      expect(provider.errorMessage, conflictMessage);
      expect(provider.lastException, isA<ApiException>());
      expect((provider.lastException! as ApiException).code, 'flavor_idempotency_conflict');
    });
  });
}
