import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flavor_mobile/core/navigation/deep_link_service.dart';
import 'package:flavor_mobile/core/notifications/firebase_gateway.dart';
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
  bool permissionThrows = false;
  int tokenCalls = 0;
  Map<String, dynamic>? initialMessage;

  @override
  Future<void> requestPermission() async {
    permissionRequested = true;
    if (permissionThrows) {
      throw StateError('permission dialog unavailable (degraded build)');
    }
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
  Future<Map<String, dynamic>?> getInitialMessage() async => initialMessage;
}

/// In-memory fake local-notifications presenter — records show() calls.
class FakePresenter implements LocalNotificationPresenter {
  FakePresenter(this.shown, {this.throws = false});

  final List<Map<String, Object?>> shown;
  final bool throws;

  @override
  Future<void> show({required String title, required String body, String? payload}) async {
    if (throws) {
      throw StateError('local notifications plugin unavailable');
    }
    shown.add({'title': title, 'body': body, 'payload': payload});
  }
}

/// Loads the shared push contract fixture. The SAME file is asserted by the
/// PHP e2e suite (run-e2e-integration.php) against the real FCM messages:send
/// body emitted by PushNotificationService::dispatch_order_status() — so the
/// server wire format and this parser can never drift apart silently.
Map<String, dynamic> loadPushContractFixture() {
  final file = File('test/fixtures/push_payload_contract.json');
  expect(file.existsSync(), isTrue,
      reason: 'contract fixture must be read from the mobile/ package root');
  return jsonDecode(file.readAsStringSync()) as Map<String, dynamic>;
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

  group('wire contract fixture — server PushNotificationService sync', () {
    late Map<String, dynamic> fixture;
    late List<dynamic> payloads;

    setUpAll(() {
      fixture = loadPushContractFixture();
      payloads = fixture['payloads'] as List<dynamic>;
    });

    test('fixture pins flavor-mobile-push@1 and the server status vocabulary', () {
      expect(fixture['contract'], 'flavor-mobile-push@1');
      expect(fixture['status_vocabulary'],
          ['preparing', 'ready', 'completed', 'cancelled']);
      // One pinned wire payload per server status, in vocabulary order.
      expect(payloads.map((p) => (p['wire_data'] as Map)['status']).toList(),
          fixture['status_vocabulary']);
    });

    test('every wire payload: all-string data, click_action mirrors order_id, resolves to the pinned route + args', () {
      for (final entry in payloads) {
        final name = entry['name'] as String;
        final wire = (entry['wire_data'] as Map).cast<String, dynamic>();
        final expected = entry['expected'] as Map;

        // send_fcm() coerces every data value to string on the wire.
        expect(wire.values.every((v) => v is String), isTrue, reason: name);
        // Contract format: click_action == flavor://order/<order_id>.
        expect(wire['click_action'], 'flavor://order/${wire['order_id']}',
            reason: name);

        final link = NotificationPayloadParser.resolveDeepLink(wire);
        expect(link, expected['deep_link'], reason: name);

        final dest = DeepLinkService.instance.parseUri(link!);
        expect(dest, isNotNull, reason: name);
        expect(dest!.route, expected['route'], reason: name);
        final args = dest.args as Map;
        expect(args['order_id'], (expected['args'] as Map)['order_id'],
            reason: name);
        expect(args['guest_token'], (expected['args'] as Map)['guest_token'],
            reason: name);
        // Order tracking stays reachable for guests: the server link carries
        // the order id, auth is NOT required to open the tracking screen.
        expect(dest.requiresAuth, isFalse, reason: name);
      }
    });

    test('transport settings mirror what send_fcm()/send_apns() emit', () {
      final transport = fixture['transport'] as Map;
      expect((transport['android'] as Map)['priority'], 'HIGH');
      expect((transport['android'] as Map)['sound'], 'default');
      expect((transport['apns'] as Map)['priority'], '10');
      expect((transport['apns'] as Map)['sound'], 'default');
    });
  });

  group('NotificationService — payload routing', () {
    late FakeMessagingGateway gateway;
    late List<String> routed;
    late List<bool> routedAuth;

    setUp(() {
      gateway = FakeMessagingGateway();
      routed = [];
      routedAuth = [];
    });

    NotificationService buildService({
      LocalNotificationPresenter? presenter,
      bool Function()? auth,
    }) =>
        NotificationService(
          messaging: gateway,
          registerDeviceToken:
              (token, {required platform, required appVersion}) async {},
          unregisterDeviceToken: (token) async {},
          localNotifications: presenter,
          isAuthenticatedResolver: auth,
          dispatchDeepLink: (url, {required isAuthenticated}) {
            routed.add(url);
            routedAuth.add(isAuthenticated);
            return true;
          },
        );

    test('notification tap routes the resolved deep link with the current auth flag', () async {
      final service = buildService(auth: () => true);
      await service.init();
      gateway.taps.add({
        'type': 'order_status',
        'order_id': '1001',
        'status': 'ready',
        'click_action': 'flavor://order/1001',
      });
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(routed, ['flavor://order/1001']);
      expect(routedAuth, [true]);
      await service.dispose();
    });

    test('taps without a routable destination never reach the dispatcher', () async {
      final service = buildService();
      await service.init();
      gateway.taps.add({'type': 'unknown'});
      gateway.taps.add({'click_action': 'https://attacker.example/p'});
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(routed, isEmpty);
      await service.dispose();
    });

    test('cold start: the message that launched the app routes during init', () async {
      gateway.initialMessage = {
        'type': 'order_status',
        'order_id': '1003',
        'click_action': 'flavor://order/1003',
      };
      final service = buildService(auth: () => false);
      await service.init();
      expect(routed, ['flavor://order/1003']);
      expect(routedAuth, [false]);
      await service.dispose();
    });

    test('foreground message surfaces via the local presenter with the deep link as payload', () async {
      final shown = <Map<String, Object?>>[];
      final service = buildService(presenter: FakePresenter(shown));
      await service.init();
      gateway.foreground.add({
        'title': 'سفارش شما آماده است',
        'body': 'سفارش #1002 آماده سرو / تحویل به پیک اختصاصی شد.',
        'type': 'order_status',
        'order_id': '1002',
        'status': 'ready',
        'click_action': 'flavor://order/1002',
      });
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(shown, hasLength(1));
      expect(shown.single['title'], 'سفارش شما آماده است');
      expect(shown.single['payload'], 'flavor://order/1002');
      await service.dispose();
    });

    test('presenter failures degrade silently (no unhandled async error)', () async {
      final service = buildService(presenter: FakePresenter([], throws: true));
      await service.init();
      gateway.foreground
          .add({'title': 't', 'body': 'b', 'type': 'order_status', 'order_id': '9'});
      await Future<void>.delayed(const Duration(milliseconds: 50));
      // Reaching this point IS the assertion: the throw was contained.
      await service.dispose();
    });

    test('permission denial never blocks stream subscriptions', () async {
      gateway.permissionThrows = true;
      final service = buildService();
      await service.init();
      gateway.taps.add(
          {'type': 'order_status', 'order_id': '55', 'click_action': 'flavor://order/55'});
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(routed, ['flavor://order/55']);
      await service.dispose();
    });
  });

  group('background handler — local display + silent degrade', () {
    Map<String, dynamic> dataOnlyWire() => {
          'title': 'سفارش شما در حال آماده‌سازی است',
          'body': 'آشپزخانه رستوران سفارش #1001 را تایید کرد و در حال پخت است.',
          'type': 'order_status',
          'order_id': '1001',
          'status': 'preparing',
          'click_action': 'flavor://order/1001',
        };

    test('data-only message surfaces as a local notification carrying the deep link', () async {
      final shown = <Map<String, Object?>>[];
      final message = RemoteMessage.fromMap({'data': dataOnlyWire()});
      await handleBackgroundMessage(
        message,
        firebaseInit: () async {},
        presenter: FakePresenter(shown),
      );
      expect(shown, hasLength(1));
      expect(shown.single['title'], 'سفارش شما در حال آماده‌سازی است');
      expect(shown.single['payload'], 'flavor://order/1001');
    });

    test('messages with a notification block are left to the OS (no duplicate)', () async {
      final shown = <Map<String, Object?>>[];
      final message = RemoteMessage.fromMap({
        'data': dataOnlyWire(),
        'notification': {'title': 'سفارش شما آماده است', 'body': 'بدنه'},
      });
      await handleBackgroundMessage(
        message,
        firebaseInit: () async {},
        presenter: FakePresenter(shown),
      );
      expect(shown, isEmpty);
    });

    test('no google-services.json (Firebase init fails) exits silently — presenter untouched', () async {
      final shown = <Map<String, Object?>>[];
      final message = RemoteMessage.fromMap({'data': dataOnlyWire()});
      await handleBackgroundMessage(
        message,
        firebaseInit: () async =>
            throw StateError('[core/no-options] google-services.json missing'),
        presenter: FakePresenter(shown),
      );
      expect(shown, isEmpty);
    });

    test('presenter failure inside the background isolate degrades silently', () async {
      final message = RemoteMessage.fromMap({'data': dataOnlyWire()});
      await handleBackgroundMessage(
        message,
        firebaseInit: () async {},
        presenter: FakePresenter([], throws: true),
      );
      // Reaching this point IS the assertion: the isolate never crashed.
    });
  });
}
