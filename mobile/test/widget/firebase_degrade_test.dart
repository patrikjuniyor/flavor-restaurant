// Widget tests for the prompt 69 silent-degradation contract: a brand build
// WITHOUT google-services.json / GoogleService-Info.plist must still boot.
//
// The real Firebase platform channels DO NOT ANSWER under flutter_test (they
// hang instead of throwing), so these tests drive the production bootstrap
// with the provisioning check injected as "config absent" — the exact branch
// an unprovisioned brand build takes when google-services.json is missing —
// and then boot the REAL app widget (FlavorMobileApp) to prove splash → home
// and deep-link navigation keep working with push degraded to a no-op.
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flavor_mobile/config/app_config.dart';
import 'package:flavor_mobile/core/constants/brand_tokens.g.dart';
import 'package:flavor_mobile/core/navigation/deep_link_service.dart';
import 'package:flavor_mobile/core/notifications/firebase_gateway.dart';
import 'package:flavor_mobile/core/notifications/notification_service.dart';
import 'package:flavor_mobile/main.dart';
import 'package:flavor_mobile/ui/screens/home/home_screen.dart';
import 'package:flavor_mobile/ui/screens/notifications/notifications_screen.dart';
import 'package:flavor_mobile/ui/screens/splash_screen.dart';

/// Hard cap per test: a hang (regression) must surface in 90s, not 10min.
const Timeout _cap = Timeout(Duration(seconds: 90));

Future<void> settle(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final service = DeepLinkService.instance;

  /// The production provisioning check, injected as FAILED — mirrors a brand
  /// build whose google-services.json / GoogleService-Info.plist is absent.
  Future<bool> noFirebaseConfig() async => false;

  setUpAll(() {
    // Same provisioning main() performs before runApp.
    AppConfig.initialize(
      environment: FlavorEnvironment.prod,
      appName: BrandTokens.appName,
      apiBaseUrl: BrandTokens.apiBaseUrl,
      tenantId: BrandTokens.tenantId,
      defaultBranchId: BrandTokens.defaultBranchId,
      enableLogging: false,
    );
  });

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    service.clearPendingDestination();
    NotificationService.instance = null;
  });

  testWidgets(
      'the Firebase provisioning seam degrades silently when the config is absent',
      (tester) async {
    // Missing configuration (the google-services.json case) → false, never throws.
    expect(
      await initializeFirebaseIfProvisioned(
          () async => throw StateError('[core/no-app] google-services.json missing')),
      isFalse,
    );
    // Provisioned brand → true (the initializer itself succeeds).
    expect(await initializeFirebaseIfProvisioned(() async {}), isTrue);
  }, timeout: _cap);

  testWidgets(
      'production bootstrap exits quietly without Firebase: push stays unwired',
      (tester) async {
    // The FULL production bootstrap function from main.dart, degrade branch.
    await initializePushNotifications(firebaseProvisioned: noFirebaseConfig);
    expect(NotificationService.instance, isNull); // pipeline never wired
    expect(service.pendingDestination, isNull);
  }, timeout: _cap);

  testWidgets('the app boots without Firebase: splash renders, then forwards to home',
      (tester) async {
    await initializePushNotifications(firebaseProvisioned: noFirebaseConfig);

    await tester.pumpWidget(const FlavorMobileApp());
    await settle(tester);
    expect(find.byType(SplashScreen), findsOneWidget);

    // Splash auto-forwards after its 1500ms delay — no Firebase involved.
    await tester.pump(const Duration(milliseconds: 1600));
    await settle(tester);
    expect(find.byType(HomeScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  }, timeout: _cap);

  testWidgets('deep links keep working in the degraded (no-Firebase) app',
      (tester) async {
    await initializePushNotifications(firebaseProvisioned: noFirebaseConfig);

    await tester.pumpWidget(const FlavorMobileApp());
    await settle(tester);
    await tester.pump(const Duration(milliseconds: 1600));
    await settle(tester);
    expect(find.byType(HomeScreen), findsOneWidget);

    // Push is a no-op, but the navigation half of the notification contract
    // (deep links → screens, the same pipe notification taps ride) must be
    // fully alive without Firebase.
    expect(service.dispatch('flavor://notifications', isAuthenticated: false), isTrue);
    await settle(tester);
    expect(find.byType(NotificationsScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  }, timeout: _cap);
}
