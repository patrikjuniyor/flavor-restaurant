import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/models/dish_model.dart';
import 'package:flavor_mobile/repositories/auth_repository.dart';
import 'package:flavor_mobile/repositories/cart_repository.dart';
import 'package:flavor_mobile/repositories/menu_repository.dart';
import 'package:flavor_mobile/repositories/order_repository.dart';

/// Every mocked endpoint below replays a REAL payload captured from the live
/// PHP controllers (flavor-core) into test/fixtures/ — prompt 65 replaced the
/// hand-invented JSON that used to drift from the API contract (fake `roles`
/// key, `remaining_capacity`-style inventions, made-up tracking steps).
String fixture(String name) => File('test/fixtures/$name.json').readAsStringSync();

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
  });
}
