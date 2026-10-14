// Widget tests for the prompt 67 checkout idempotency contract.
//
// The wire envelopes below (order_create_replay, order_replay_unpaid,
// order_conflict_409, order_pay_failed_400) were captured from the REAL
// CheckoutService through the e2e mock harness — the same responses a device
// receives when a double tap, a timeout retry or a second identity replays an
// idempotency key. The screen must:
//  - send a derived idempotency_key on POST /orders,
//  - land on the ORIGINAL order's tracking screen for any replay (paid or
//    recoverable-unpaid), clearing the cart,
//  - show the Persian server message for 409 conflicts and failed payments,
//    never a raw error, and never navigate.
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/config/routes.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/api/api_exception.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/repositories/auth_repository.dart';
import 'package:flavor_mobile/repositories/branch_repository.dart';
import 'package:flavor_mobile/repositories/cart_repository.dart';
import 'package:flavor_mobile/repositories/order_repository.dart';
import 'package:flavor_mobile/state/auth_state.dart';
import 'package:flavor_mobile/state/cart_state.dart';
import 'package:flavor_mobile/state/config_state.dart';
import 'package:flavor_mobile/state/order_state.dart';
import 'package:flavor_mobile/ui/screens/checkout/checkout_screen.dart';

String fixture(String name) => File('test/fixtures/$name.json').readAsStringSync();

const Map<String, String> jsonHeaders = {
  'content-type': 'application/json; charset=utf-8',
};

/// Routes like the error-states harness, but POST /orders answers with a
/// configurable captured envelope and records every request body so tests can
/// pin the exact wire contract (idempotency_key presence and shape).
class CheckoutHarness {
  String orderFixture = 'order_create';
  int orderStatus = 201;
  final List<Map<String, dynamic>> orderBodies = [];
  final StorageService storage = StorageService();
  late final ApiClient api;

  CheckoutHarness() {
    api = ApiClient(
      httpClient: MockClient((request) async {
        final path = request.url.path;
        if (path.contains('/orders') && request.method == 'POST') {
          orderBodies.add(jsonDecode(request.body) as Map<String, dynamic>);
          return http.Response(
            fixture(orderFixture),
            orderStatus,
            headers: jsonHeaders,
          );
        }
        if (request.method == 'DELETE' && path.contains('/cart')) {
          // CartRepository.clearCart(): the server answers with the now-empty
          // cart; an empty data object parses to an empty CartModel.
          return http.Response(
            jsonEncode({'success': true, 'data': <String, dynamic>{}, 'errors': []}),
            200,
            headers: jsonHeaders,
          );
        }
        return http.Response(fixture(_route(path)), 200, headers: jsonHeaders);
      }),
      storage: storage,
    );
  }

  static String _route(String path) {
    if (path.contains('tables')) return 'tables';
    if (path.contains('/auth/me')) return 'user_profile';
    if (path.contains('/cart')) return 'cart';
    return 'cart';
  }

  CartRepository cartRepo() => CartRepository(apiClient: api);
  OrderRepository orderRepo() => OrderRepository(apiClient: api);
  BranchRepository branchRepo() => BranchRepository(apiClient: api);
  AuthRepository authRepo() => AuthRepository(apiClient: api, storage: storage);
}

/// Flushes pending futures without pumpAndSettle (spinners animate forever).
Future<void> settle(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump(const Duration(milliseconds: 400));
}

class SubmitOutcome {
  final bool reachedTracking;
  final Object? trackingArgs;
  final OrderProvider orders;
  final CartProvider cart;

  SubmitOutcome({
    required this.reachedTracking,
    required this.trackingArgs,
    required this.orders,
    required this.cart,
  });
}

/// Pumps CheckoutScreen with an authenticated session (the real /auth/me
/// fixture prefills the mobile field) and a loaded cart, switches to dine-in
/// and taps «ثبت نهایی سفارش». The order-tracking route is stubbed so the
/// navigation argument (the order id the customer lands on) is observable.
Future<SubmitOutcome> pumpCheckoutAndSubmit(
  WidgetTester tester,
  CheckoutHarness harness,
) async {
  FlutterSecureStorage.setMockInitialValues({
    'flavor_access_token': 'test-access-token',
  });
  final auth = AuthProvider(authRepo: harness.authRepo());
  await auth.checkAuth();
  expect(auth.isAuthenticated, isTrue);
  final cart = CartProvider(cartRepo: harness.cartRepo());
  await cart.loadCart();
  expect(cart.isNotEmpty, isTrue, reason: 'the cart fixture has real items');
  final orders = OrderProvider(orderRepo: harness.orderRepo());

  bool reachedTracking = false;
  Object? trackingArgs;

  await tester.pumpWidget(MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => ConfigProvider()),
      ChangeNotifierProvider.value(value: auth),
      ChangeNotifierProvider.value(value: cart),
      ChangeNotifierProvider.value(value: orders),
    ],
    child: MaterialApp(
      home: CheckoutScreen(branchRepo: harness.branchRepo()),
      routes: {
        AppRoutes.orderTracking: (ctx) {
          reachedTracking = true;
          trackingArgs = ModalRoute.of(ctx)!.settings.arguments;
          return const Scaffold(body: Text('tracking-stub'));
        },
      },
    ),
  ));
  await settle(tester);

  // Dine-in: only the mobile field is validated (already prefilled), so the
  // submit tap reaches POST /orders without address or table pickers.
  await tester.tap(find.text('سر میز (سالن)'));
  await settle(tester);

  await tester.tap(find.text('ثبت نهایی سفارش'));
  await settle(tester);

  return SubmitOutcome(
    reachedTracking: reachedTracking,
    trackingArgs: trackingArgs,
    orders: orders,
    cart: cart,
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
  });

  group('Checkout idempotency (prompt 67)', () {
    testWidgets('a replayed duplicate lands on the SAME order and clears the cart',
        (tester) async {
      final harness = CheckoutHarness()..orderFixture = 'order_create_replay';
      final outcome = await pumpCheckoutAndSubmit(tester, harness);

      expect(harness.orderBodies, hasLength(1));
      final body = harness.orderBodies.single;
      // The derived key obeys the server sanitizer: [A-Za-z0-9_-], max 64.
      expect(body['idempotency_key'], matches(RegExp(r'^[A-Za-z0-9_-]{1,64}$')));
      expect(body['order_mode'], 'dine_in');

      expect(outcome.reachedTracking, isTrue,
          reason: 'replay=true with an order_id is a success, not an error');
      expect(outcome.trackingArgs, 1001,
          reason: 'the customer lands on the ORIGINAL order, never a duplicate');
      expect(outcome.cart.isEmpty, isTrue,
          reason: 'the cart is consumed exactly once, replay or not');
    });

    testWidgets('a recoverable unpaid replay still shows the existing order',
        (tester) async {
      final harness = CheckoutHarness()..orderFixture = 'order_replay_unpaid';
      final outcome = await pumpCheckoutAndSubmit(tester, harness);

      // ok=false BUT replay=true and the order row exists (payment failed,
      // recoverable): the old ok-only gate stranded the customer on an error
      // snackbar and invited a third duplicate attempt.
      expect(outcome.reachedTracking, isTrue);
      expect(outcome.trackingArgs, 1002);
    });

    testWidgets('409 identity conflict shows the Persian server message and never navigates',
        (tester) async {
      const conflictMessage = 'این سفارش قبلاً با نشانی دیگری ثبت شده است.';
      final harness = CheckoutHarness()
        ..orderFixture = 'order_conflict_409'
        ..orderStatus = 409;
      final outcome = await pumpCheckoutAndSubmit(tester, harness);

      expect(outcome.reachedTracking, isFalse);
      expect(find.text(conflictMessage), findsOneWidget,
          reason: 'the SnackBar shows the server Persian text verbatim');
      expect(outcome.orders.errorMessage, conflictMessage);
      expect(outcome.orders.lastException, isA<ApiException>());
      expect((outcome.orders.lastException! as ApiException).code,
          'flavor_idempotency_conflict');
      expect(outcome.cart.isNotEmpty, isTrue,
          reason: 'a rejected submit must keep the cart intact');
    });

    testWidgets('a failed online payment shows the Persian bank message, not a raw error',
        (tester) async {
      final harness = CheckoutHarness()
        ..orderFixture = 'order_pay_failed_400'
        ..orderStatus = 400;
      final outcome = await pumpCheckoutAndSubmit(tester, harness);

      expect(outcome.reachedTracking, isFalse);
      expect(find.text('تراکنش توسط بانک رد شد.'), findsOneWidget);
      expect(outcome.orders.errorMessage, 'تراکنش توسط بانک رد شد.');
      expect(outcome.cart.isNotEmpty, isTrue);
    });
  });
}
