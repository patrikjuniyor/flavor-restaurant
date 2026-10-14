// Widget tests for the prompt 68 deep link chain: a raw URI (the shape the
// Android/iOS engine and notification taps hand to the app) must travel
// DeepLinkService.dispatch / AppRoutes.generateRoute and open the RIGHT
// screen WITH the RIGHT arguments — including the auth guard that parks a
// guarded destination and resumes it after login.
//
// The real-chain tests drive the app's actual navigator (navigatorKey +
// onGenerateRoute from main.dart) so the assertions cover the whole pipe,
// not just the parser (the exhaustive parser table lives in
// test/unit/deep_link_test.dart).
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
import 'package:flavor_mobile/core/navigation/deep_link_service.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/repositories/cart_repository.dart';
import 'package:flavor_mobile/repositories/menu_repository.dart';
import 'package:flavor_mobile/repositories/order_repository.dart';
import 'package:flavor_mobile/state/cart_state.dart';
import 'package:flavor_mobile/state/config_state.dart';
import 'package:flavor_mobile/state/favorites_state.dart';
import 'package:flavor_mobile/state/menu_state.dart';
import 'package:flavor_mobile/state/order_state.dart';
import 'package:flavor_mobile/ui/screens/menu/dish_detail_screen.dart';
import 'package:flavor_mobile/ui/screens/menu/menu_screen.dart';
import 'package:flavor_mobile/ui/screens/orders/order_tracking_screen.dart';

String fixture(String name) => File('test/fixtures/$name.json').readAsStringSync();

/// Fixture-backed client that also records every request URI so tests can
/// pin the arguments that reached the wire (guest_token, order id, …).
class DeepLinkHarness {
  final List<Uri> requests = [];
  final StorageService storage = StorageService();
  late final ApiClient api;

  DeepLinkHarness() {
    api = ApiClient(
      httpClient: MockClient((request) async {
        requests.add(request.url);
        return http.Response(
          fixture(_route(request.url.path)),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      }),
      storage: storage,
    );
  }

  static String _route(String path) {
    // Specific-first, exactly like the error-states harness: the collection
    // routes live UNDER /dishes/ (dishes/featured, dishes/categories, …), so
    // a bare '/dishes/' match first would serve dish_detail for all of them.
    if (path.contains('/track')) return 'order_track';
    if (path.contains('special-offers')) return 'menu';
    if (path.contains('featured')) return 'menu';
    if (path.contains('categories')) return 'categories';
    if (path.contains('reviews')) return 'dish_reviews';
    if (path.contains('/dishes/')) return 'dish_detail';
    if (path.contains('/menu')) return 'menu';
    if (path.contains('/auth/me')) return 'user_profile';
    if (path.contains('/orders')) return 'orders_list';
    if (path.contains('/cart')) return 'cart';
    return 'app_bootstrap';
  }

  MenuRepository menuRepo() => MenuRepository(apiClient: api, storage: storage);
  CartRepository cartRepo() => CartRepository(apiClient: api);
  OrderRepository orderRepo() => OrderRepository(apiClient: api);
}

Future<void> settle(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final service = DeepLinkService.instance;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    service.clearPendingDestination();
  });

  group('Deep link dispatch (prompt 68)', () {
    testWidgets('dispatch pushes the parsed route with args; the auth guard parks and resumes',
        (tester) async {
      final recorded = <(String, Object?)>[];
      await tester.pumpWidget(MaterialApp(
        navigatorKey: service.navigatorKey,
        initialRoute: '/stub-home',
        onGenerateRoute: (settings) {
          recorded.add((settings.name ?? '/', settings.arguments));
          return MaterialPageRoute<void>(
            builder: (_) => Scaffold(body: Text('stub ${settings.name}')),
            settings: settings,
          );
        },
      ));
      await settle(tester);
      // Navigator pushes every prefix of initialRoute ('/' then the leaf),
      // so the stub baseline is the LAST generated route, not the only one.
      expect(recorded.last.$1, '/stub-home');
      expect(recorded.first.$1, '/');

      // A plain link pushes the destination route with parsed arguments.
      expect(service.dispatch('flavor://dish/105', isAuthenticated: true), isTrue);
      await settle(tester);
      expect(recorded.last.$1, AppRoutes.dishDetail);
      expect(recorded.last.$2, 105);

      // A map-argument link keeps its whole payload.
      expect(
        service.dispatch('https://restaurant.example.com/order/9?guest_token=gt',
            isAuthenticated: true),
        isTrue,
      );
      await settle(tester);
      expect(recorded.last.$1, AppRoutes.orderTracking);
      expect(recorded.last.$2, {'order_id': 9, 'guest_token': 'gt'});

      // An auth-guarded link while logged out parks the destination and
      // sends the customer to login instead of the guarded screen.
      expect(
        service.dispatch('https://restaurant.example.com/orders', isAuthenticated: false),
        isTrue,
      );
      await settle(tester);
      expect(recorded.last.$1, AppRoutes.login);
      expect(service.pendingDestination, isNotNull);
      expect(service.pendingDestination!.route, AppRoutes.orders);
      expect(service.pendingDestination!.requiresAuth, isTrue);

      // After login the parked destination resumes exactly where it stood.
      expect(service.resumePendingDestination(), isTrue);
      await settle(tester);
      expect(recorded.last.$1, AppRoutes.orders);
      expect(service.pendingDestination, isNull);

      // Garbage never reaches the navigator.
      final before = recorded.length;
      expect(service.dispatch('flavor://no-such-thing', isAuthenticated: true), isFalse);
      await settle(tester);
      expect(recorded.length, before);
    });

    testWidgets('flavor://menu?category=3 opens MenuScreen preselected on category 3',
        (tester) async {
      final harness = DeepLinkHarness();
      final menu = MenuProvider(menuRepo: harness.menuRepo());
      await menu.loadInitial(1);
      expect(menu.categories, isNotEmpty, reason: 'the fixture feed is live before the link');

      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider.value(value: menu),
          ChangeNotifierProvider(create: (_) => CartProvider(cartRepo: harness.cartRepo())),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: MaterialApp(
          navigatorKey: service.navigatorKey,
          // A stub landing page: the point is what the dispatch PUSHES on
          // top of it through the app's real onGenerateRoute.
          home: const Scaffold(body: Text('stub-home')),
          onGenerateRoute: AppRoutes.generateRoute,
        ),
      ));
      await settle(tester);

      // The engine hands the raw URI to onGenerateRoute — dispatch it.
      expect(service.dispatch('flavor://menu?category=3', isAuthenticated: true), isTrue);
      await settle(tester);

      expect(find.byType(MenuScreen), findsOneWidget);
      expect(menu.selectedCategoryId, 3,
          reason: 'the category argument survived URI -> generateRoute -> initState');
    });

    testWidgets('https order link opens tracking with its order id AND guest token on the wire',
        (tester) async {
      final harness = DeepLinkHarness();
      final orders = OrderProvider(orderRepo: harness.orderRepo());

      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider.value(value: orders),
        ],
        child: MaterialApp(
          navigatorKey: service.navigatorKey,
          // Cold start from a Universal Link: the raw URI IS the initial
          // route, exactly like the platform engine delivers it.
          initialRoute: 'https://restaurant.example.com/order/1001?guest_token=gt-77',
          onGenerateRoute: AppRoutes.generateRoute,
        ),
      ));
      await settle(tester);

      expect(find.byType(OrderTrackingScreen), findsOneWidget);
      expect(orders.trackedOrder, isNotNull);
      expect(orders.trackedOrder!.id, 1001,
          reason: 'the path argument became the ctor orderId');
      final trackCall = harness.requests.firstWhere((u) => u.path.contains('/track'));
      expect(trackCall.queryParameters['guest_token'], 'gt-77',
          reason: 'the query argument reached the API as the guest token');
    });

    testWidgets('flavor://dish/105 opens DishDetailScreen (never the home fallback)',
        (tester) async {
      final harness = DeepLinkHarness();
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(create: (_) => CartProvider(cartRepo: harness.cartRepo())),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: MaterialApp(
          navigatorKey: service.navigatorKey,
          initialRoute: 'flavor://dish/105',
          onGenerateRoute: AppRoutes.generateRoute,
        ),
      ));
      await settle(tester);

      // generateRoute builds the screen with its DEFAULT repositories, which
      // hit the test binding's 400-everything HTTP stub — the P66 error path
      // renders, but the point here is the destination: the dish screen
      // opened from the URI, not the unknown-route home fallback.
      expect(find.byType(DishDetailScreen), findsOneWidget);
      expect(
        jsonDecode(fixture('dish_detail'))['data']['id'],
        isNotNull,
        reason: 'fixture sanity: the dish payload carries an id',
      );
    });
  });
}
