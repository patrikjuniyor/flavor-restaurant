import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/config/routes.dart';
import 'package:flavor_mobile/core/navigation/deep_link_service.dart';

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
  });
}
