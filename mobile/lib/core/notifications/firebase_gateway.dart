import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import 'notification_service.dart';

/// Android notification channel used for all Flavor notifications. The Android
/// manifest references this id via
/// `com.google.firebase.messaging.default_notification_channel_id` meta-data.
const String flavorNotificationChannelId = 'flavor_channel';
const String flavorNotificationChannelName = 'Flavor Notifications';

/// Initializes Firebase only when the current brand/build actually ships the
/// Firebase configuration (google-services.json on Android,
/// GoogleService-Info.plist on iOS — provisioned by CI, never committed).
///
/// Returns `false` — silently, never throwing — when that configuration is
/// absent, so the app can boot with push degraded to a no-op (Prompt 69).
/// [init] is injectable for tests; it defaults to the real
/// `Firebase.initializeApp`.
Future<bool> initializeFirebaseIfProvisioned([Future<void> Function()? init]) async {
  try {
    final initializer = init ?? Firebase.initializeApp;
    await initializer();
    return true;
  } catch (_) {
    // No google-services.json / GoogleService-Info.plist for this brand:
    // push degrades silently; app startup must never be blocked.
    return false;
  }
}

/// Background message entry point. Required to be a top-level function.
/// Delegates to [handleBackgroundMessage], which is injectable and unit-tested.
@pragma('vm:entry-point')
Future<void> flavorFirebaseMessagingBackgroundHandler(RemoteMessage message) =>
    handleBackgroundMessage(message);

/// Testable core of the background handler.
///
/// Data-only background messages are surfaced as local notifications so the
/// payload (order / reservation deep link) remains tappable; messages carrying
/// a notification block are displayed by the OS itself and are NOT duplicated.
/// Every failure mode (missing Firebase configuration, missing local
/// notifications plugin) degrades silently: a background isolate must never
/// take the app down.
Future<void> handleBackgroundMessage(
  RemoteMessage message, {
  Future<void> Function()? firebaseInit,
  LocalNotificationPresenter? presenter,
}) async {
  final provisioned = await initializeFirebaseIfProvisioned(firebaseInit);
  if (!provisioned) return;

  // Notification payloads are displayed by the OS automatically in background.
  if (message.notification != null) return;

  final data = message.data;
  try {
    await (presenter ?? FlutterLocalNotificationsPresenter()).show(
      title: data['title']?.toString() ?? '',
      body: data['body']?.toString() ?? '',
      payload: NotificationPayloadParser.resolveDeepLink(data),
    );
  } catch (_) {
    // Local-notifications plugin unavailable: degrade to a no-op, not a crash.
  }
}

/// Concrete [PushMessagingGateway] bridging to Firebase Cloud Messaging.
class FirebaseMessagingGateway implements PushMessagingGateway {
  final FirebaseMessaging _messaging;

  FirebaseMessagingGateway(this._messaging);

  factory FirebaseMessagingGateway.instanceOf() =>
      FirebaseMessagingGateway(FirebaseMessaging.instance);

  @override
  Future<void> requestPermission() async {
    await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );
  }

  @override
  Future<String?> getToken() => _messaging.getToken();

  @override
  Future<String?> getAPNSToken() async {
    try {
      return await _messaging.getAPNSToken();
    } catch (_) {
      // Non-Apple platforms throw here; that is expected.
      return null;
    }
  }

  @override
  Stream<String> get onTokenRefresh => _messaging.onTokenRefresh;

  @override
  Stream<Map<String, dynamic>> get onForegroundMessage =>
      FirebaseMessaging.onMessage.map((msg) => {
            'title': msg.notification?.title ?? msg.data['title'],
            'body': msg.notification?.body ?? msg.data['body'],
            ...msg.data,
          });

  @override
  Stream<Map<String, dynamic>> get onMessageTap =>
      FirebaseMessaging.onMessageOpenedApp.map((msg) => msg.data);

  @override
  Future<Map<String, dynamic>?> getInitialMessage() async {
    final msg = await _messaging.getInitialMessage();
    return msg?.data;
  }
}

/// Concrete [LocalNotificationPresenter] using flutter_local_notifications.
class FlutterLocalNotificationsPresenter implements LocalNotificationPresenter {
  static final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();
  static bool _initialized = false;

  FlutterLocalNotificationsPresenter({Function(String deepLink)? onTap}) {
    _onTap = onTap;
  }

  static Function(String deepLink)? _onTap;

  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    flavorNotificationChannelId,
    flavorNotificationChannelName,
    importance: Importance.high,
  );

  Future<void> initialize() async {
    if (_initialized) return;
    _initialized = true;

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings();
    await _plugin.initialize(
      const InitializationSettings(android: android, iOS: ios),
      onDidReceiveNotificationResponse: (response) {
        final payload = response.payload;
        if (payload != null && payload.isNotEmpty) {
          _onTap?.call(payload);
        }
      },
    );

    await _plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);
  }

  @override
  Future<void> show({required String title, required String body, String? payload}) async {
    await initialize();
    const details = NotificationDetails(
      android: AndroidNotificationDetails(
        flavorNotificationChannelId,
        flavorNotificationChannelName,
        importance: Importance.high,
        priority: Priority.high,
      ),
      iOS: DarwinNotificationDetails(),
    );
    await _plugin.show(
      (title.hashCode ^ body.hashCode) & 0x7fffffff,
      title,
      body,
      details,
      payload: payload,
    );
  }
}
