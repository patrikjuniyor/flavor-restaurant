// Offline cache + menu fallback tests (prompt 66).
//
// The menu feed is persisted on every successful load and restored when the
// network fails; the error view only remains when there is nothing cached.
// Success payloads come from the real API fixtures captured in prompt 65.
import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flavor_mobile/core/api/api_client.dart';
import 'package:flavor_mobile/core/storage/offline_cache.dart';
import 'package:flavor_mobile/core/storage/secure_storage_service.dart';
import 'package:flavor_mobile/models/category_model.dart';
import 'package:flavor_mobile/models/dish_model.dart';
import 'package:flavor_mobile/repositories/menu_repository.dart';
import 'package:flavor_mobile/state/menu_state.dart';

String fixture(String name) => File('test/fixtures/$name.json').readAsStringSync();

/// Routes API paths to the real fixture envelopes; [failWhen] decides which
/// calls die with a network error (http.ClientException — what package/http
/// throws on a real device when the connection fails).
class Harness {
  bool Function(String path) failWhen;
  int calls = 0;
  final StorageService storage = StorageService();
  late final ApiClient api;

  Harness({this.failWhen = _failNothing}) {
    api = ApiClient(
      httpClient: MockClient((request) async {
        calls++;
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

  static bool _failNothing(String path) => false;

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
}

bool failMenuPaths(String path) =>
    path.contains('/menu') ||
    path.contains('categories') ||
    path.contains('featured') ||
    path.contains('special-offers');

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
  });

  group('OfflineCache', () {
    test('saveJson/readJson round trip', () async {
      final cache = OfflineCache();
      await cache.saveJson('k', {'a': 1, 'b': [1, 2], 'c': 'سه'});
      final back = await cache.readJson('k');
      expect(back, isNotNull);
      expect(back!['a'], 1);
      expect(back['b'], [1, 2]);
      expect(back['c'], 'سه');
    });

    test('missing key reads back as null', () async {
      expect(await OfflineCache().readJson('never.saved'), isNull);
    });

    test('corrupt JSON degrades to null instead of throwing', () async {
      SharedPreferences.setMockInitialValues({'bad': '{not json at all'});
      expect(await OfflineCache().readJson('bad'), isNull);
    });

    test('non-map JSON degrades to null', () async {
      SharedPreferences.setMockInitialValues({'list': '[1,2,3]'});
      expect(await OfflineCache().readJson('list'), isNull);
    });

    test('remove drops the blob', () async {
      final cache = OfflineCache();
      await cache.saveJson('k', {'a': 1});
      await cache.remove('k');
      expect(await cache.readJson('k'), isNull);
    });
  });

  group('MenuProvider offline behaviour', () {
    test('successful load persists the feed to the cache', () async {
      final harness = Harness();
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());

      await provider.loadInitial(1);

      expect(provider.errorMessage, isNull);
      expect(provider.isOfflineData, isFalse);
      expect(provider.dishes, isNotEmpty);
      expect(provider.categories, isNotEmpty);

      final prefs = await SharedPreferences.getInstance();
      final blob = jsonDecode(prefs.getString(OfflineCache.menuKey)!);
      expect(blob['branch_id'], 1);
      expect(blob['fetched_at'], isNotEmpty);
      expect((blob['dishes'] as List).length, provider.dishes.length);
      expect((blob['categories'] as List).length, provider.categories.length);
    });

    test('failed load restores the cached feed and flags offline mode', () async {
      final harness = Harness();
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());
      await provider.loadInitial(1); // seeds the cache
      final liveDishes = provider.dishes.length;
      final liveCats = provider.categories.length;

      harness.failWhen = (path) => true; // network dies
      await provider.loadInitial(1);

      expect(provider.errorMessage, isNull, reason: 'cache hit must suppress the error view');
      expect(provider.isOfflineData, isTrue);
      expect(provider.dishes.length, liveDishes);
      expect(provider.categories.length, liveCats);
      expect(provider.dishes.first.name, 'چلو کباب کوبیده مخصوص زعفرانی');
    });

    test('failed load with no cache keeps the Persian network error', () async {
      final harness = Harness(failWhen: (path) => true);
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());

      await provider.loadInitial(1);

      // Pins the ClientException → ApiException.networkError() mapping too:
      expect(provider.errorMessage,
          'عدم دسترسی به اینترنت. لطفاً اتصال شبکه خود را بررسی کنید.');
      expect(provider.isOfflineData, isFalse);
      expect(provider.dishes, isEmpty);
    });

    test('corrupt cache falls back to the error path', () async {
      SharedPreferences.setMockInitialValues({OfflineCache.menuKey: '###corrupt###'});
      final harness = Harness(failWhen: (path) => true);
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());

      await provider.loadInitial(1);

      expect(provider.errorMessage, isNotNull);
      expect(provider.isOfflineData, isFalse);
    });

    test('recovery clears the offline flag and refreshes the cache', () async {
      final harness = Harness();
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());
      await provider.loadInitial(1);

      harness.failWhen = (path) => true;
      await provider.loadInitial(1);
      expect(provider.isOfflineData, isTrue);

      harness.failWhen = (path) => false; // network returns
      await provider.loadInitial(1);
      expect(provider.isOfflineData, isFalse);
      expect(provider.errorMessage, isNull);
      expect(provider.dishes, isNotEmpty);
    });

    test('category reload failure never overwrites good live dishes', () async {
      final harness = Harness();
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());
      await provider.loadInitial(1);
      final liveCount = provider.dishes.length;

      harness.failWhen = failMenuPaths;
      await provider.selectCategory(2, 1);

      expect(provider.dishes.length, liveCount, reason: 'live dishes stay on screen');
      expect(provider.isOfflineData, isFalse);
      expect(provider.errorMessage, isNotNull);
    });

    test('category reload failure with empty list restores the cache', () async {
      // Simulates first paint offline on the menu screen with a warm cache.
      final harness = Harness();
      final seeder = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());
      await seeder.loadInitial(1); // cache now warm

      final harness2 = Harness(failWhen: failMenuPaths);
      final provider = MenuProvider(menuRepo: harness2.menuRepo(), cache: OfflineCache());
      await provider.selectCategory(1, 1);

      expect(provider.errorMessage, isNull);
      expect(provider.isOfflineData, isTrue);
      expect(provider.dishes, isNotEmpty);
    });

    test('cache blob survives a full model round trip', () async {
      final harness = Harness();
      final provider = MenuProvider(menuRepo: harness.menuRepo(), cache: OfflineCache());
      await provider.loadInitial(1);

      // Read the blob back through the models exactly like _restoreCache does.
      final prefs = await SharedPreferences.getInstance();
      final blob = jsonDecode(prefs.getString(OfflineCache.menuKey)!) as Map<String, dynamic>;
      final dishes = (blob['dishes'] as List)
          .whereType<Map<String, dynamic>>()
          .map(DishModel.fromJson)
          .toList();
      final cats = (blob['categories'] as List)
          .whereType<Map<String, dynamic>>()
          .map(CategoryModel.fromJson)
          .toList();

      expect(dishes.length, provider.dishes.length);
      expect(dishes.first.name, provider.dishes.first.name);
      expect(dishes.first.price, provider.dishes.first.price);
      expect(dishes.first.priceHtml, provider.dishes.first.priceHtml);
      expect(dishes.first.ratingAvg, provider.dishes.first.ratingAvg);
      expect(cats.first.name, provider.categories.first.name);
    });
  });
}
