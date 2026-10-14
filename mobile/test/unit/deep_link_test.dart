import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/config/routes.dart';
import 'package:flavor_mobile/core/navigation/deep_link_service.dart';

String repr(String s) => "'$s'";

void main() {
  group('DeepLinkService Unit Tests', () {
    late DeepLinkService service;

    setUp(() {
      service = DeepLinkService.instance;
      service.clearPendingDestination();
    });

    test('Parses custom scheme flavor://menu and category query param', () {
      final dest = service.parseUri('flavor://menu?category=4');
      expect(dest, isNotNull);
      expect(dest!.route, AppRoutes.menu);
      expect(dest.args, 4);
      expect(dest.requiresAuth, isFalse);
    });

    test('Parses custom scheme flavor://dish/105', () {
      final dest = service.parseUri('flavor://dish/105');
      expect(dest, isNotNull);
      expect(dest!.route, AppRoutes.dishDetail);
      expect(dest.args, 105);
      expect(dest.requiresAuth, isFalse);
    });

    test('Parses custom scheme flavor://order/789 with guest_token parameter', () {
      final dest = service.parseUri('flavor://order/789?guest_token=abc123token');
      expect(dest, isNotNull);
      expect(dest!.route, AppRoutes.orderTracking);
      expect(dest.args, isA<Map<String, dynamic>>());
      final map = dest.args as Map<String, dynamic>;
      expect(map['order_id'], 789);
      expect(map['guest_token'], 'abc123token');
      expect(dest.requiresAuth, isFalse);
    });

    test('Parses auth-guarded routes: flavor://profile and flavor://reservation-history', () {
      final profileDest = service.parseUri('flavor://profile');
      expect(profileDest, isNotNull);
      expect(profileDest!.route, AppRoutes.profile);
      expect(profileDest.requiresAuth, isTrue);

      final resDest = service.parseUri('flavor://reservation-history');
      expect(resDest, isNotNull);
      expect(resDest!.route, AppRoutes.reservationHistory);
      expect(resDest.requiresAuth, isTrue);
    });

    test('Parses table QR deep link: flavor://table?branch_id=2&table_number=14', () {
      final dest = service.parseUri('flavor://table?branch_id=2&table_number=14');
      expect(dest, isNotNull);
      expect(dest!.route, AppRoutes.menu);
      expect(dest.args, isA<Map<String, dynamic>>());
      final map = dest.args as Map<String, dynamic>;
      expect(map['branch_id'], 2);
      expect(map['table_number'], '14');
    });

    test('Parses Universal Link: https://restaurant.example.com/product/250', () {
      final dest = service.parseUri('https://restaurant.example.com/product/250');
      expect(dest, isNotNull);
      expect(dest!.route, AppRoutes.dishDetail);
      expect(dest.args, 250);
    });

    test('Parses Universal Link with guest token: https://restaurant.example.com/order/555?guest_token=sec_token', () {
      final dest = service.parseUri('https://restaurant.example.com/order/555?guest_token=sec_token');
      expect(dest, isNotNull);
      expect(dest!.route, AppRoutes.orderTracking);
      final map = dest.args as Map<String, dynamic>;
      expect(map['order_id'], 555);
      expect(map['guest_token'], 'sec_token');
    });

    test('Preserves pending destination for unauthenticated flow and consumes on login', () {
      expect(service.pendingDestination, isNull);

      const guarded = DeepLinkDestination(route: AppRoutes.profile, requiresAuth: true);
      service.setPendingDestination(guarded);

      expect(service.pendingDestination, equals(guarded));

      final consumed = service.consumePendingDestination();
      expect(consumed, equals(guarded));
      expect(service.pendingDestination, isNull);
    });

    test('AppRoutes.parseDeepLink returns backward compatible map', () {
      final map = AppRoutes.parseDeepLink('flavor://dish/42');
      expect(map, isNotNull);
      expect(map!['route'], AppRoutes.dishDetail);
      expect(map['args'], 42);
      expect(map['requiresAuth'], isFalse);
    });

    // ---- Prompt 68: every routes.dart destination opens from a URI ----

    test('every routes.dart destination opens from flavor:// with its arguments', () {
      // (uri, expected route, expected args, expected requiresAuth)
      final table = <List<dynamic>>[
        ['flavor://home', AppRoutes.home, null, false],
        ['flavor://menu?category=7', AppRoutes.menu, 7, false],
        ['flavor://menu/7', AppRoutes.menu, 7, false],
        ['flavor://menu', AppRoutes.menu, null, false],
        ['flavor://dish/105', AppRoutes.dishDetail, 105, false],
        ['flavor://product/105', AppRoutes.dishDetail, 105, false],
        ['flavor://search', AppRoutes.search, null, false],
        ['flavor://cart', AppRoutes.cart, null, false],
        ['flavor://checkout', AppRoutes.checkout, null, false],
        ['flavor://orders', AppRoutes.orders, null, true],
        ['flavor://reservation', AppRoutes.reservation, null, false],
        ['flavor://reservation-history', AppRoutes.reservationHistory, null, true],
        ['flavor://profile', AppRoutes.profile, null, true],
        ['flavor://profile-edit', AppRoutes.editProfile, null, true],
        ['flavor://addresses', AppRoutes.addresses, null, true],
        ['flavor://favorites', AppRoutes.favorites, null, false],
        ['flavor://restaurant-info', AppRoutes.restaurantInfo, null, false],
        ['flavor://notifications', AppRoutes.notifications, null, false],
        ['flavor://login', AppRoutes.login, null, false],
        ['flavor://otp-verify?mobile=09123456789', AppRoutes.otpVerify, '09123456789', false],
        ['flavor://table?branch_id=2&table_number=14', AppRoutes.menu,
          {'table_number': '14', 'branch_id': 2}, false],
        ['flavor://qr?branch_id=1&table=5', AppRoutes.menu,
          {'table_number': '5', 'branch_id': 1}, false],
      ];
      for (final row in table) {
        final dest = service.parseUri(row[0] as String);
        expect(dest, isNotNull, reason: '${row[0]} must parse');
        expect(dest!.route, row[1], reason: '${row[0]} route');
        expect(dest.args.toString(), (row[2]).toString(), reason: '${row[0]} args');
        expect(dest.requiresAuth, row[3], reason: '${row[0]} requiresAuth');
      }
    });

    test('every routes.dart destination opens from https:// with its arguments', () {
      const h = 'https://restaurant.example.com';
      final table = <List<dynamic>>[
        ['$h/', AppRoutes.home, null, false],
        ['$h/home', AppRoutes.home, null, false],
        ['$h/menu?category=3', AppRoutes.menu, 3, false],
        ['$h/product/250', AppRoutes.dishDetail, 250, false],
        ['$h/dish/250', AppRoutes.dishDetail, 250, false],
        ['$h/search', AppRoutes.search, null, false],
        ['$h/cart', AppRoutes.cart, null, false],
        ['$h/checkout', AppRoutes.checkout, null, false],
        ['$h/orders', AppRoutes.orders, null, true],
        ['$h/order/555?guest_token=sec', AppRoutes.orderTracking,
          {'order_id': 555, 'guest_token': 'sec'}, false],
        ['$h/order/555', AppRoutes.orderTracking,
          {'order_id': 555, 'guest_token': null}, false],
        ['$h/reservation', AppRoutes.reservation, null, false],
        ['$h/reservations', AppRoutes.reservationHistory, null, true],
        ['$h/my-reservations', AppRoutes.reservationHistory, null, true],
        ['$h/profile', AppRoutes.profile, null, true],
        ['$h/my-account', AppRoutes.profile, null, true],
        ['$h/profile/edit', AppRoutes.editProfile, null, true],
        ['$h/profile/addresses', AppRoutes.addresses, null, true],
        ['$h/profile/favorites', AppRoutes.favorites, null, false],
        ['$h/favorites', AppRoutes.favorites, null, false],
        ['$h/restaurant-info', AppRoutes.restaurantInfo, null, false],
        ['$h/notifications', AppRoutes.notifications, null, false],
        ['$h/login', AppRoutes.login, null, false],
        ['$h/otp-verify?mobile=09123456789', AppRoutes.otpVerify, '09123456789', false],
        ['$h/qr?branch_id=2&table_number=14', AppRoutes.menu,
          {'table_number': '14', 'branch_id': 2}, false],
        ['$h/table?branch_id=1&table=9', AppRoutes.menu,
          {'table_number': '9', 'branch_id': 1}, false],
      ];
      for (final row in table) {
        final dest = service.parseUri(row[0] as String);
        expect(dest, isNotNull, reason: '${row[0]} must parse');
        expect(dest!.route, row[1], reason: '${row[0]} route');
        expect(dest.args.toString(), (row[2]).toString(), reason: '${row[0]} args');
        expect(dest.requiresAuth, row[3], reason: '${row[0]} requiresAuth');
      }
    });

    test('garbage, empty and unknown-scheme URIs parse to null; dispatch refuses them', () {
      for (final bad in ['', '   ', 'not a uri', 'ftp://files.example.com/x',
          'flavor://no-such-destination', 'https://restaurant.example.com/no/such/path']) {
        expect(service.parseUri(bad), isNull, reason: repr(bad));
        expect(service.dispatch(bad, isAuthenticated: true), isFalse,
            reason: 'dispatch(${repr(bad)}) must refuse without a navigator push');
      }
      // A malformed numeric argument degrades to id 0, never to a crash.
      final dish = service.parseUri('flavor://dish/abc');
      expect(dish, isNotNull);
      expect(dish!.args, 0);
    });

    test('generateRoute builds a real page route for every routes.dart name and argument shape', () {
      Route<dynamic> gen(String name, [Object? args]) =>
          AppRoutes.generateRoute(RouteSettings(name: name, arguments: args));

      expect(gen(AppRoutes.splash), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.home), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.login), isA<MaterialPageRoute<dynamic>>());
      // otp-verify takes the parser's String argument.
      expect(gen(AppRoutes.otpVerify, '09123456789'), isA<MaterialPageRoute<dynamic>>());
      // menu accepts both the int and the {'category_id'} map shapes.
      expect(gen(AppRoutes.menu, 7), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.menu, {'category_id': '7'}), isA<MaterialPageRoute<dynamic>>());
      // dish-detail accepts int and String ids.
      expect(gen(AppRoutes.dishDetail, 105), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.dishDetail, '105'), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.search), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.cart), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.checkout), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.orders), isA<MaterialPageRoute<dynamic>>());
      // order-tracking accepts the int and the parser's map shape.
      expect(gen(AppRoutes.orderTracking, 9), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.orderTracking, {'order_id': 9, 'guest_token': 'gt'}),
          isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.reservation), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.reservationHistory), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.profile), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.editProfile), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.addresses), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.favorites), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.restaurantInfo), isA<MaterialPageRoute<dynamic>>());
      expect(gen(AppRoutes.notifications), isA<MaterialPageRoute<dynamic>>());

      // The engine hands the RAW incoming URI to onGenerateRoute (that is how
      // Android/iOS deep links enter the app): generateRoute must re-parse it
      // instead of falling through to the default branch.
      expect(gen('flavor://dish/105'), isA<MaterialPageRoute<dynamic>>());
      expect(gen('https://restaurant.example.com/order/555?guest_token=sec'),
          isA<MaterialPageRoute<dynamic>>());
      // Unknown names still fall back to a route (home), never an exception.
      expect(gen('/totally-unknown'), isA<MaterialPageRoute<dynamic>>());
    });

    test('AASA template paths and the https parser stay in sync (both directions)', () {
      // provision_brand.py renders this template into the hosted
      // apple-app-site-association; if a path pattern and the Dart parser
      // drift apart, iOS either opens the app to a dead end or keeps the
      // customer in Safari for a link the app could handle.
      final tpl = jsonDecode(File('assets/deeplinks/apple-app-site-association.template.json')
          .readAsStringSync()) as Map<String, dynamic>;
      final details = (tpl['applinks'] as Map<String, dynamic>)['details'] as List;
      final paths = ((details.first as Map<String, dynamic>)['paths'] as List)
          .map((e) => e.toString())
          .toList();

      // pattern -> (sample URI path, route the parser must produce)
      const samples = <String, (String, String)>{
        '/product/*': ('/product/1', AppRoutes.dishDetail),
        '/dish/*': ('/dish/1', AppRoutes.dishDetail),
        '/order/*': ('/order/1', AppRoutes.orderTracking),
        '/orders*': ('/orders', AppRoutes.orders),
        '/menu*': ('/menu', AppRoutes.menu),
        '/search*': ('/search', AppRoutes.search),
        '/reservation*': ('/reservation', AppRoutes.reservation),
        '/reservations/*': ('/reservations/upcoming', AppRoutes.reservationHistory),
        '/cart*': ('/cart', AppRoutes.cart),
        '/checkout*': ('/checkout', AppRoutes.checkout),
        '/profile*': ('/profile', AppRoutes.profile),
        '/favorites*': ('/favorites', AppRoutes.favorites),
        '/restaurant-info*': ('/restaurant-info', AppRoutes.restaurantInfo),
        '/notifications*': ('/notifications', AppRoutes.notifications),
        '/qr*': ('/qr?table_number=5&branch_id=2', AppRoutes.menu),
        '/table*': ('/table?table_number=5&branch_id=2', AppRoutes.menu),
        '/login*': ('/login', AppRoutes.login),
        '/otp-verify*': ('/otp-verify?mobile=09123456789', AppRoutes.otpVerify),
        '/home*': ('/home', AppRoutes.home),
      };

      // Direction 1: every hosted pattern is handled by the parser.
      for (final pattern in paths) {
        final sample = samples[pattern];
        expect(sample, isNotNull,
            reason: 'AASA pattern $pattern has no parser sample — sync the test table');
        final dest = service.parseUri('https://restaurant.example.com${sample!.$1}');
        expect(dest, isNotNull, reason: 'parser drops AASA pattern $pattern');
        expect(dest!.route, sample.$2, reason: 'AASA pattern $pattern lands on the wrong route');
      }
      // Direction 2: every parser segment is allowed by the hosted file.
      for (final pattern in samples.keys) {
        expect(paths, contains(pattern),
            reason: 'parser handles $pattern but the AASA template does not allow it');
      }
    });
  });
}
