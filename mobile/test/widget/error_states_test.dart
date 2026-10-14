// Widget tests for the offline/error states of every customer screen
// (prompt 66). Each screen that loads data must show ErrorView with the
// «تلاش مجدد» button when the network fails, recover on retry, and — for the
// menu feed — fall back to the persisted cache under an OfflineBanner.
//
// Network failure is simulated with http.ClientException, which is what
// package/http really throws on a device with no connectivity; ApiClient maps
// it to ApiException.networkError(), so every assertion below pins the exact
// Persian message a customer sees offline.
import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/storage/offline_cache.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/models/dish_model.dart';
import 'package:flavor_mobile/repositories/auth_repository.dart';
import 'package:flavor_mobile/repositories/branch_repository.dart';
import 'package:flavor_mobile/repositories/cart_repository.dart';
import 'package:flavor_mobile/repositories/menu_repository.dart';
import 'package:flavor_mobile/repositories/order_repository.dart';
import 'package:flavor_mobile/repositories/reservation_repository.dart';
import 'package:flavor_mobile/repositories/review_repository.dart';
import 'package:flavor_mobile/state/auth_state.dart';
import 'package:flavor_mobile/state/cart_state.dart';
import 'package:flavor_mobile/state/config_state.dart';
import 'package:flavor_mobile/state/favorites_state.dart';
import 'package:flavor_mobile/state/menu_state.dart';
import 'package:flavor_mobile/state/order_state.dart';
import 'package:flavor_mobile/state/reservation_state.dart';
import 'package:flavor_mobile/ui/common/error_view.dart';
import 'package:flavor_mobile/ui/common/offline_banner.dart';
import 'package:flavor_mobile/ui/screens/cart/cart_screen.dart';
import 'package:flavor_mobile/ui/screens/checkout/checkout_screen.dart';
import 'package:flavor_mobile/ui/screens/home/home_screen.dart';
import 'package:flavor_mobile/ui/screens/menu/dish_detail_screen.dart';
import 'package:flavor_mobile/ui/screens/menu/menu_screen.dart';
import 'package:flavor_mobile/ui/screens/orders/orders_screen.dart';
import 'package:flavor_mobile/ui/screens/reservation/reservation_screen.dart';

String fixture(String name) => File('test/fixtures/$name.json').readAsStringSync();

/// The Persian message of ApiException.networkError() — the single source of
/// truth every screen must surface when the device is offline.
const networkMessage = 'عدم دسترسی به اینترنت. لطفاً اتصال شبکه خود را بررسی کنید.';

class Harness {
  bool Function(String path) failWhen;
  final StorageService storage = StorageService();
  late final ApiClient api;

  Harness({this.failWhen = _never}) {
    api = ApiClient(
      httpClient: MockClient((request) async {
        final path = request.url.path;
        if (failWhen(path)) {
          throw http.ClientException('Connection failed while sending');
        }
        return http.Response(
          fixture(route(path)),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      }),
      storage: storage,
    );
  }

  static bool _never(String path) => false;

  static String route(String path) {
    if (path.contains('special-offers')) return 'menu';
    if (path.contains('featured')) return 'menu';
    if (path.contains('categories')) return 'categories';
    if (path.contains('/menu')) return 'menu';
    if (path.contains('reviews')) return 'dish_reviews';
    if (path.contains('/dishes/')) return 'dish_detail';
    if (path.contains('tables')) return 'tables';
    if (path.contains('slots')) return 'reservation_slots';
    if (path.contains('/auth/me')) return 'user_profile';
    if (path.contains('/orders')) return 'orders_list';
    if (path.contains('/cart')) return 'cart';
    if (path.contains('bootstrap')) return 'app_bootstrap';
    return 'menu';
  }

  MenuRepository menuRepo() => MenuRepository(apiClient: api, storage: storage);
  CartRepository cartRepo() => CartRepository(apiClient: api);
  OrderRepository orderRepo() => OrderRepository(apiClient: api);
  ReservationRepository reservationRepo() => ReservationRepository(apiClient: api);
  BranchRepository branchRepo() => BranchRepository(apiClient: api);
  ReviewRepository reviewRepo() => ReviewRepository(apiClient: api);
  AuthRepository authRepo() => AuthRepository(apiClient: api, storage: storage);
}

bool failMenu(String path) =>
    path.contains('/menu') ||
    path.contains('categories') ||
    path.contains('featured') ||
    path.contains('special-offers');

/// Flushes pending futures/microtasks without pumpAndSettle (shimmer and
/// spinners animate forever while a load is in flight).
Future<void> settle(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
  });

  group('Menu screen errors', () {
    testWidgets('network failure shows ErrorView; retry recovers live', (tester) async {
      final harness = Harness(failWhen: (_) => true);
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(
            create: (_) => MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache()),
          ),
          ChangeNotifierProvider(create: (_) => CartProvider()),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: const MaterialApp(home: MenuScreen()),
      ));
      await settle(tester);

      expect(find.byType(ErrorView), findsOneWidget);
      expect(find.text(networkMessage), findsOneWidget);
      final retry = find.text('تلاش مجدد');
      expect(retry, findsOneWidget);

      // One press on retry brings the live menu back.
      harness.failWhen = (_) => false;
      await tester.tap(retry);
      await settle(tester);

      expect(find.byType(ErrorView), findsNothing);
      expect(find.text('چلو کباب کوبیده مخصوص زعفرانی'), findsWidgets);
      expect(find.byType(OfflineBanner), findsNothing);
    });

    testWidgets('warm cache + dead network renders cached menu under OfflineBanner', (tester) async {
      // Warm the cache through a successful load first.
      final warm = Harness();
      final seeder = MenuProvider(menuRepo: warm.menuRepo(), cache: OfflineCache());
      await seeder.loadInitial(1);
      expect(seeder.dishes, isNotEmpty);

      // Same preferences (mock store is process-wide), network now dead.
      final dead = Harness(failWhen: (_) => true);
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(
            create: (_) => MenuProvider(menuRepo: dead.menuRepo(), cache: OfflineCache()),
          ),
          ChangeNotifierProvider(create: (_) => CartProvider()),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: const MaterialApp(home: MenuScreen()),
      ));
      await settle(tester);

      // No error view — the customer still sees the last real menu.
      expect(find.byType(ErrorView), findsNothing);
      expect(find.byType(OfflineBanner), findsOneWidget);
      expect(find.text('چلو کباب کوبیده مخصوص زعفرانی'), findsWidgets);
    });

    testWidgets('cold cache + dead network falls through to ErrorView', (tester) async {
      final dead = Harness(failWhen: (_) => true);
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(
            create: (_) => MenuProvider(menuRepo: dead.menuRepo(), cache: OfflineCache()),
          ),
          ChangeNotifierProvider(create: (_) => CartProvider()),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: const MaterialApp(home: MenuScreen()),
      ));
      await settle(tester);

      expect(find.byType(ErrorView), findsOneWidget);
      expect(find.byType(OfflineBanner), findsNothing);
      expect(find.text('تلاش مجدد'), findsOneWidget);
    });
  });

  group('Home feed errors', () {
    testWidgets('failed feed with cold cache shows ErrorView; retry restores the feed',
        (tester) async {
      final harness = Harness(failWhen: failMenu);
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(create: (_) => AuthProvider(authRepo: harness.authRepo())),
          ChangeNotifierProvider(
            create: (_) => MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache()),
          ),
          ChangeNotifierProvider(create: (_) => CartProvider(cartRepo: harness.cartRepo())),
          ChangeNotifierProvider(create: (_) => OrderProvider(orderRepo: harness.orderRepo())),
          ChangeNotifierProvider(
            create: (_) => ReservationProvider(resRepo: harness.reservationRepo()),
          ),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: const MaterialApp(home: HomeScreen()),
      ));
      await settle(tester);

      // The visible feed tab must not masquerade as an empty restaurant.
      expect(find.text(networkMessage), findsWidgets);
      expect(find.text('تلاش مجدد'), findsWidgets);
      expect(find.text('سفارش آنلاین و تحویل سریع'), findsNothing);

      harness.failWhen = (_) => false;
      await tester.tap(find.text('تلاش مجدد').first);
      await settle(tester);

      expect(find.text('سفارش آنلاین و تحویل سریع'), findsOneWidget);
      expect(find.text('چلو کباب کوبیده مخصوص زعفرانی'), findsWidgets);
    });

    testWidgets('warm cache shows the offline banner instead of an error', (tester) async {
      final warm = Harness();
      final seeder = MenuProvider(menuRepo: warm.menuRepo(), cache: OfflineCache());
      await seeder.loadInitial(1);

      final dead = Harness(failWhen: failMenu);
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(create: (_) => AuthProvider(authRepo: dead.authRepo())),
          ChangeNotifierProvider(
            create: (_) => MenuProvider(menuRepo: dead.menuRepo(), cache: OfflineCache()),
          ),
          ChangeNotifierProvider(create: (_) => CartProvider(cartRepo: dead.cartRepo())),
          ChangeNotifierProvider(create: (_) => OrderProvider(orderRepo: dead.orderRepo())),
          ChangeNotifierProvider(
            create: (_) => ReservationProvider(resRepo: dead.reservationRepo()),
          ),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: const MaterialApp(home: HomeScreen()),
      ));
      await settle(tester);

      expect(find.byType(OfflineBanner), findsWidgets);
      expect(find.text('سفارش آنلاین و تحویل سریع'), findsOneWidget);
    });
  });

  group('Cart screen errors', () {
    testWidgets('failed cart load shows ErrorView; retry loads the real cart', (tester) async {
      final harness = Harness(failWhen: (p) => p.contains('/cart'));
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => CartProvider(cartRepo: harness.cartRepo())),
        ],
        child: const MaterialApp(home: CartScreen()),
      ));
      await settle(tester);

      expect(find.byType(ErrorView), findsOneWidget);
      expect(find.text(networkMessage), findsOneWidget);
      expect(find.text('سبد خرید شما خالی است'), findsNothing,
          reason: 'a failed load must not read as an empty cart');

      harness.failWhen = (_) => false;
      await tester.tap(find.text('تلاش مجدد'));
      await settle(tester);

      expect(find.byType(ErrorView), findsNothing);
      expect(find.textContaining('چلو کباب کوبیده'), findsWidgets);
    });
  });

  group('Orders screen errors', () {
    testWidgets('authenticated + failed history load shows ErrorView; retry recovers',
        (tester) async {
      FlutterSecureStorage.setMockInitialValues({'flavor_access_token': 'test-access-token'});
      final harness = Harness(failWhen: (p) => p.contains('/orders'));
      final auth = AuthProvider(authRepo: harness.authRepo());
      await auth.checkAuth();
      expect(auth.isAuthenticated, isTrue);

      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: auth),
          ChangeNotifierProvider(create: (_) => OrderProvider(orderRepo: harness.orderRepo())),
        ],
        child: const MaterialApp(home: OrdersScreen()),
      ));
      await settle(tester);

      expect(find.byType(ErrorView), findsOneWidget);
      expect(find.text(networkMessage), findsOneWidget);

      harness.failWhen = (_) => false;
      await tester.tap(find.text('تلاش مجدد'));
      await settle(tester);

      expect(find.byType(ErrorView), findsNothing);
      expect(find.textContaining('کوبیده'), findsWidgets,
          reason: 'orders_list items_summary renders after retry');
    });
  });

  group('Checkout screen errors', () {
    testWidgets('failed table load surfaces ErrorView in the dine-in section; retry recovers',
        (tester) async {
      final harness = Harness(failWhen: (p) => p.contains('tables'));
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(create: (_) => AuthProvider(authRepo: harness.authRepo())),
          ChangeNotifierProvider(create: (_) => CartProvider(cartRepo: harness.cartRepo())),
          ChangeNotifierProvider(create: (_) => OrderProvider(orderRepo: harness.orderRepo())),
        ],
        child: MaterialApp(home: CheckoutScreen(branchRepo: harness.branchRepo())),
      ));
      await settle(tester);

      // Switch to dine-in: the swallowed table failure must now be visible.
      await tester.tap(find.text('سر میز (سالن)'));
      await tester.pump();

      expect(find.byType(ErrorView), findsOneWidget);
      expect(find.text(networkMessage), findsOneWidget);

      harness.failWhen = (_) => false;
      await tester.tap(find.text('تلاش مجدد'));
      await settle(tester);

      expect(find.byType(ErrorView), findsNothing);
      expect(find.text('انتخاب میز'), findsOneWidget,
          reason: 'the table dropdown renders after a successful retry');
    });
  });

  group('Reservation screen errors', () {
    testWidgets('failed slot load shows ErrorView instead of a false “no slots”; retry recovers',
        (tester) async {
      final harness = Harness(failWhen: (p) => p.contains('slots'));
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(create: (_) => AuthProvider(authRepo: harness.authRepo())),
          ChangeNotifierProvider(
            create: (_) => ReservationProvider(resRepo: harness.reservationRepo()),
          ),
        ],
        child: const MaterialApp(home: ReservationScreen()),
      ));
      await settle(tester);

      expect(find.byType(ErrorView), findsOneWidget);
      expect(find.text(networkMessage), findsOneWidget);
      expect(find.text('اسلات زمانی در این تاریخ در دسترس نیست.'), findsNothing,
          reason: 'a dead network must not blame the restaurant');

      harness.failWhen = (_) => false;
      final retry = find.text('تلاش مجدد');
      // The slots section sits below the fold in the 800x600 test viewport —
      // scroll it into view so the tap actually lands.
      await tester.ensureVisible(retry);
      await tester.pump();
      await tester.tap(retry);
      await settle(tester);

      expect(find.byType(ErrorView), findsNothing);
      expect(find.text('۱۲:۰۰'), findsOneWidget, reason: 'slot chips render after retry');
    });
  });

  group('Dish detail errors', () {
    testWidgets('failed dish load shows ErrorView; retry renders the dish', (tester) async {
      final harness = Harness(
        failWhen: (p) => p.contains('/dishes/') || p.contains('reviews'),
      );
      await tester.pumpWidget(MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => ConfigProvider()),
          ChangeNotifierProvider(create: (_) => CartProvider(cartRepo: harness.cartRepo())),
          ChangeNotifierProvider(create: (_) => FavoritesProvider()),
        ],
        child: MaterialApp(
          home: DishDetailScreen(
            dishId: 1,
            menuRepo: harness.menuRepo(),
            reviewRepo: harness.reviewRepo(),
          ),
        ),
      ));
      await settle(tester);

      expect(find.byType(ErrorView), findsOneWidget);
      expect(find.text(networkMessage), findsOneWidget);

      harness.failWhen = (_) => false;
      await tester.tap(find.text('تلاش مجدد'));
      await settle(tester);

      expect(find.byType(ErrorView), findsNothing);
      expect(find.text('چلو کباب کوبیده مخصوص زعفرانی'), findsWidgets);
    });
  });

  group('Cache blob shape', () {
    test('menu cache stores API-compatible model JSON', () async {
      final harness = Harness();
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());
      await provider.loadInitial(1);

      final prefs = await SharedPreferences.getInstance();
      final blob = jsonDecode(prefs.getString(OfflineCache.menuKey)!) as Map<String, dynamic>;
      final dishes = (blob['dishes'] as List)
          .whereType<Map<String, dynamic>>()
          .map(DishModel.fromJson)
          .toList();
      expect(dishes.first.name, 'چلو کباب کوبیده مخصوص زعفرانی');
      expect(dishes.first.priceHtml, contains('تومان'));
    });
  });
}
