import 'dart:async';

import '../navigation/deep_link_service.dart';

/// ---------------------------------------------------------------------------
/// Push payload contract (flavor-mobile-push@1)
///
/// The server (flavor-core PushNotificationService) sends data payloads with:
///   - `click_action`: a flavor:// deep link (takes precedence when present).
///   - `type`: order_status | reservation_status | promo.
///   - order_status:      `order_id`, `status`
///   - reservation_status:`reservation_id`, `status`
///   - promo:             `dish_id`
/// Legacy keys (`id`) are still accepted for backward compatibility.
/// ---------------------------------------------------------------------------
class NotificationPayloadParser {
  const NotificationPayloadParser._();

  /// Resolves a remote-notification data map to an internal flavor:// deep link.
  /// Returns null when the payload carries no routable destination.
  static String? resolveDeepLink(Map<String, dynamic> data) {
    if (data.isEmpty) return null;

    // 1. Server-provided deep link has priority.
    final clickAction = (data['click_action'] ?? data['clickAction'] ?? data['deep_link'])
        ?.toString()
        .trim();
    if (clickAction != null && clickAction.isNotEmpty) {
      final uri = Uri.tryParse(clickAction);
      if (uri != null && uri.scheme == 'flavor') {
        return clickAction;
      }
    }

    // 2. Synthesize from the typed contract.
    final type = data['type']?.toString().trim();
    switch (type) {
      case 'order_status':
        final orderId = (data['order_id'] ?? data['id'])?.toString().trim() ?? '';
        if (orderId.isEmpty) return null;
        return 'flavor://order/$orderId';
      case 'reservation_status':
        return 'flavor://reservation-history';
      case 'promo':
        final dishId = (data['dish_id'] ?? data['id'])?.toString().trim() ?? '';
        if (dishId.isEmpty) return null;
        return 'flavor://dish/$dishId';
      default:
        return null;
    }
  }
}

/// Minimal abstraction over `firebase_messaging.FirebaseMessaging` so the
/// service is unit-testable without platform channels.
abstract class PushMessagingGateway {
  Future<void> requestPermission();
  Future<String?> getToken();
  Future<String?> getAPNSToken();
  Stream<String> get onTokenRefresh;
  Stream<Map<String, dynamic>> get onForegroundMessage;

  /// Notification taps while the app is in background.
  Stream<Map<String, dynamic>> get onMessageTap;

  /// Notification data that launched the app from a terminated state.
  Future<Map<String, dynamic>?> getInitialMessage();
}

/// Minimal abstraction over `flutter_local_notifications` for foreground display.
abstract class LocalNotificationPresenter {
  Future<void> show({required String title, required String body, String? payload});
}

/// Production push notification manager.
///
/// - Registers REAL FCM/APNs tokens against the authenticated backend account.
/// - Handles token rotation via [PushMessagingGateway.onTokenRefresh].
/// - Foreground / background / terminated handling drives
///   [DeepLinkService] navigation through [NotificationPayloadParser].
/// - Never fabricates tokens: when Firebase is unavailable the service
///   degrades gracefully to a no-op.
class NotificationService {
  /// Process-wide handle for the initialized service. Set once during app
  /// bootstrap so authenticated flows (login/logout) can reach it without
  /// coupling state providers to the notification plumbing.
  static NotificationService? instance;

  final PushMessagingGateway _messaging;
  final LocalNotificationPresenter? _localNotifications;
  final Future<void> Function(String token, {required String platform, required String appVersion}) _register;
  final Future<void> Function(String token) _unregister;
  final bool Function()? _isAuthenticatedResolver;

  final List<StreamSubscription<dynamic>> _subscriptions = [];

  String? _registeredToken;

  NotificationService({
    required PushMessagingGateway messaging,
    required Future<void> Function(String token, {required String platform, required String appVersion}) registerDeviceToken,
    required Future<void> Function(String token) unregisterDeviceToken,
    LocalNotificationPresenter? localNotifications,
    bool Function()? isAuthenticatedResolver,
  })  : _messaging = messaging,
        _register = registerDeviceToken,
        _unregister = unregisterDeviceToken,
        _localNotifications = localNotifications,
        _isAuthenticatedResolver = isAuthenticatedResolver;

  String? get registeredToken => _registeredToken;

  /// Initializes permissions, token registration, rotation, and message streams.
  Future<void> init() async {
    try {
      await _messaging.requestPermission();
    } catch (_) {
      // Permission denial (or unsupported platform) must never crash startup.
    }

    _subscriptions.add(
      _messaging.onTokenRefresh.listen((newToken) {
        unawaited(_onTokenRotated(newToken));
      }),
    );

    _subscriptions.add(
      _messaging.onForegroundMessage.listen((data) {
        unawaited(_localNotifications?.show(
          title: data['title']?.toString() ?? '',
          body: data['body']?.toString() ?? '',
          payload: NotificationPayloadParser.resolveDeepLink(data),
        ));
      }),
    );

    _subscriptions.add(
      _messaging.onMessageTap.listen(_routeToPayload),
    );

    final initial = await _messaging.getInitialMessage();
    if (initial != null) {
      _routeToPayload(initial);
    }
  }

  /// Registers (or re-registers) the current device token. Safe to call after
  /// login: the device becomes bound to the authenticated account.
  Future<void> syncTokenRegistration({
    String platform = 'android',
    String appVersion = '1.0.0',
  }) async {
    try {
      // On iOS the APNs token must exist before FCM can issue a token.
      await _messaging.getAPNSToken();
      final token = await _messaging.getToken();
      if (token == null || token.isEmpty) return;
      if (token == _registeredToken) return;

      await _register(token, platform: platform, appVersion: appVersion);

      // Rotation hygiene: revoke the superseded token AFTER the new one is persisted.
      final previous = _registeredToken;
      if (previous != null && previous != token) {
        try {
          await _unregister(previous);
        } catch (_) {}
      }

      _registeredToken = token;
    } catch (_) {
      // Registration failures are recoverable; push is best-effort until next sync.
    }
  }

  /// Deactivates the device token on logout (server requires authentication).
  Future<void> onLogout() async {
    final token = _registeredToken;
    if (token == null) return;
    _registeredToken = null;
    try {
      await _unregister(token);
    } catch (_) {}
  }

  void _routeToPayload(Map<String, dynamic> data) {
    final deepLink = NotificationPayloadParser.resolveDeepLink(data);
    if (deepLink == null) return;
    DeepLinkService.instance.dispatch(
      deepLink,
      isAuthenticated: _isAuthenticatedResolver?.call() ?? false,
    );
  }

  Future<void> dispose() async {
    for (final sub in _subscriptions) {
      await sub.cancel();
    }
  }
}
