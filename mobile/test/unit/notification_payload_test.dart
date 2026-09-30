import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/core/navigation/deep_link_service.dart';
import 'package:flavor_mobile/core/notifications/notification_service.dart';

/// In-memory fake gateway — no platform channels, no Firebase import.
class FakeMessagingGateway implements PushMessagingGateway {
  final StreamController<String> tokenRefresh = StreamController<String>();
  final StreamController<Map<String, dynamic>> foreground =
      StreamController<Map<String, dynamic>>();
  final StreamController<Map<String, dynamic>> taps =
      StreamController<Map<String, dynamic>>();

  String? nextToken;
  bool permissionRequested = false;
  int tokenCalls = 0;

  @override
  Future<void> requestPermission() async {
    permissionRequested = true;
  }

  @override
  Future<String?> getToken() async {
    tokenCalls++;
    return nextToken;
  }

  @override
  Future<String?> getAPNSToken() async => 'apns-fake';

  @override
  Stream<String> get onTokenRefresh => tokenRefresh.stream;

  @override
  Stream<Map<String, dynamic>> get onForegroundMessage => foreground.stream;

  @override
  Stream<Map<String, dynamic>> get onMessageTap => taps.stream;

  @override
  Future<Map<String, dynamic>?> getInitialMessage() async => null;
}

void main() {
  group('NotificationPayloadParser — payload contract', () {
    test('order_status resolves to order deep link', () {
      expect(
        NotificationPayloadParser.resolveDeepLink(
            {'type': 'order_status', 'order_id': '1001', 'status': 'preparing'}),
        'flavor://order/1001',
      );
    });

    test('order_status accepts legacy id key', () {
      expect(
        NotificationPayloadParser.resolveDeepLink(
            {'type': 'order_status', 'id': '77'}),
        'flavor://order/77',
      );
    });

    test('order_status without an id has no destination', () {
      expect(
        NotificationPayloadParser.resolveDeepLink({'type': 'order_status'}),
        isNull,
      );
    });

    test('reservation_status resolves to reservation history', () {
      expect(
        NotificationPayloadParser.resolveDeepLink(
            {'type': 'reservation_status', 'reservation_id': '42'}),
        'flavor://reservation-history',
      );
    });

    test('promo resolves to dish deep link', () {
      expect(
        NotificationPayloadParser.resolveDeepLink(
            {'type': 'promo', 'dish_id': '5150'}),
        'flavor://dish/5150',
      );
    });

    test('server click_action takes precedence over synthesized links', () {
      expect(
        NotificationPayloadParser.resolveDeepLink({
          'type': 'order_status',
          'order_id': '1001',
          'click_action': 'flavor://order/1001?guest_token=abc',
        }),
        'flavor://order/1001?guest_token=abc',
      );
    });

    test('non-flavor click_action schemes are rejected', () {
      expect(
        NotificationPayloadParser.resolveDeepLink(
            {'click_action': 'javascript:alert(1)'}),
        isNull,
      );
      expect(
        NotificationPayloadParser.resolveDeepLink(
            {'click_action': 'https://attacker.example/p'}),
        isNull,
      );
    });

    test('empty, unknown and hostile payloads have no destination', () {
      expect(NotificationPayloadParser.resolveDeepLink({}), isNull);
      expect(
        NotificationPayloadParser.resolveDeepLink({'type': 'unknown'}),
        isNull,
      );
      expect(
        NotificationPayloadParser.resolveDeepLink({
          'type': 'promo',
          'dish_id': "",
        }),
        isNull,
      );
    });

    test('hostile characters in click_action do not produce a link', () {
      expect(
        NotificationPayloadParser.resolveDeepLink({
          'click_action': 'flavor://order/1\nHost: evil',
        }),
        isNull, // Uri.parse rejects newlines — hostile value is neutralized.
      );
    });

    test('order payload resolves to a real order-tracking destination', () {
      final link = NotificationPayloadParser.resolveDeepLink(
          {'type': 'order_status', 'order_id': '555', 'status': 'ready'});
      final dest = DeepLinkService.instance.parseUri(link!);
      expect(dest, isNotNull);
      expect(dest!.route, '/order-tracking');
      expect((dest.args as Map)['order_id'], 555);
    });

    test('reservation payload resolves to reservation-history destination', () {
      final link = NotificationPayloadParser.resolveDeepLink(
          {'type': 'reservation_status', 'reservation_id': '8'});
      final dest = DeepLinkService.instance.parseUri(link!);
      expect(dest, isNotNull);
      expect(dest!.route, '/reservation-history');
      expect(dest.requiresAuth, isTrue);
    });
  });

  group('NotificationService — token lifecycle', () {
    late FakeMessagingGateway gateway;
    late List<Map<String, Object>> registerCalls;
    late List<String> unregisterCalls;
    late NotificationService service;

    setUp(() {
      gateway = FakeMessagingGateway();
      registerCalls = [];
      unregisterCalls = [];
      service = NotificationService(
        messaging: gateway,
        registerDeviceToken: (token, {required platform, required appVersion}) async {
          registerCalls.add({
            'token': token,
            'platform': platform,
            'appVersion': appVersion,
          });
        },
        unregisterDeviceToken: (token) async {
          unregisterCalls.add(token);
        },
      );
    });

    test('init requests permission exactly once', () async {
      await service.init();
      expect(gateway.permissionRequested, isTrue);
      await service.dispose();
    });

    test('registers the real device token (no mock tokens are fabricated)', () async {
      gateway.nextToken = 'real-fcm-token-from-firebase';
      await service.syncTokenRegistration(platform: 'android', appVersion: '2.4.0');
      expect(registerCalls, hasLength(1));
      expect(registerCalls.single['token'], 'real-fcm-token-from-firebase');
      expect(registerCalls.single['appVersion'], '2.4.0');
      await service.dispose();
    });

    test('does not register when Firebase yields no token', () async {
      gateway.nextToken = null;
      await service.syncTokenRegistration();
      expect(registerCalls, isEmpty);
      await service.dispose();
    });

    test('token rotation re-registers and unregisters the superseded token', () async {
      gateway.nextToken = 'token-rotation-old';
      await service.syncTokenRegistration(platform: 'android', appVersion: '1.0.0');
      expect(service.registeredToken, 'token-rotation-old');

      // Firebase rotates the token.
      gateway.nextToken = 'token-rotation-new';
      gateway.tokenRefresh.add('token-rotation-new');
      await service.init();
      await Future<void>.delayed(const Duration(milliseconds: 50));

      expect(service.registeredToken, 'token-rotation-new');
      expect(
        registerCalls.map((c) => c['token']),
        containsAll(['token-rotation-old', 'token-rotation-new']),
      );
      expect(unregisterCalls, contains('token-rotation-old'));
      await service.dispose();
    });

    test('logout cleanup unregisters the bound token exactly once', () async {
      gateway.nextToken = 'logout-bound-token';
      await service.syncTokenRegistration();
      await service.onLogout();
      expect(unregisterCalls, ['logout-bound-token']);
      // Second call must be a no-op (idempotent logout).
      await service.onLogout();
      expect(unregisterCalls, hasLength(1));
      expect(service.registeredToken, isNull);
      await service.dispose();
    });

    test('registration failures never crash the app', () async {
      final failing = NotificationService(
        messaging: gateway,
        registerDeviceToken: (token, {required platform, required appVersion}) async {
          throw StateError('network down');
        },
        unregisterDeviceToken: (token) async {},
      );
      gateway.nextToken = 'token-during-outage';
      await failing.syncTokenRegistration();
      expect(failing.registeredToken, isNull); // Safe to retry later.
      await failing.dispose();
      await service.dispose();
    });
  });
}
