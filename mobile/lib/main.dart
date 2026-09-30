import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'config/app_config.dart';
import 'config/constants.dart';
import 'config/routes.dart';
import 'config/theme.dart';
import 'core/api/api_client.dart';
import 'core/constants/brand_tokens.g.dart';
import 'core/navigation/deep_link_service.dart';
import 'core/notifications/firebase_gateway.dart';
import 'core/notifications/notification_service.dart';
import 'state/auth_state.dart';
import 'state/cart_state.dart';
import 'state/config_state.dart';
import 'state/favorites_state.dart';
import 'state/menu_state.dart';
import 'state/order_state.dart';
import 'state/reservation_state.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize App Configuration from provisioned Brand Tokens
  AppConfig.initialize(
    environment: FlavorEnvironment.prod,
    appName: BrandTokens.appName,
    apiBaseUrl: BrandTokens.apiBaseUrl,
    tenantId: BrandTokens.tenantId,
    defaultBranchId: BrandTokens.defaultBranchId,
    enableLogging: false,
  );

  await _initializePushNotifications();

  runApp(const FlavorMobileApp());
}

/// Bootstraps Firebase + the production push pipeline.
///
/// Brand-level Firebase configuration ships natively per brand
/// (google-services.json on Android, GoogleService-Info.plist on iOS — see
/// docs/PUSH-NOTIFICATIONS.md); both are added by CI / brand provisioning and
/// are NEVER committed to the repo. When they are absent the app must still
/// start: push simply degrades to a no-op.
Future<void> _initializePushNotifications() async {
  try {
    await Firebase.initializeApp();
  } catch (_) {
    return; // Firebase not provisioned for this brand/build flavor.
  }

  FirebaseMessaging.onBackgroundMessage(flavorFirebaseMessagingBackgroundHandler);

  final apiClient = ApiClient();
  final presenter = FlutterLocalNotificationsPresenter(
    onTap: (deepLink) => DeepLinkService.instance.dispatch(
      deepLink,
      isAuthenticated: AuthBridge.isAuthenticated.value,
    ),
  );
  await presenter.initialize();

  final service = NotificationService(
    messaging: FirebaseMessagingGateway.instanceOf(),
    localNotifications: presenter,
    isAuthenticatedResolver: () => AuthBridge.isAuthenticated.value,
    registerDeviceToken: (token, {required platform, required appVersion}) async {
      await apiClient.post(
        AppConstants.epDevice,
        body: {
          'device_token': token,
          'platform': platform,
          'app_version': appVersion,
        },
      );
    },
    unregisterDeviceToken: (token) async {
      await apiClient.delete(
        AppConstants.epDevice,
        body: {'device_token': token},
      );
    },
  );
  NotificationService.instance = service;

  await service.init();

  // Provisioning-provided application version (set via --dart-define in CI).
  const appVersion = String.fromEnvironment('FLAVOR_APP_VERSION', defaultValue: '1.0.0');
  await service.syncTokenRegistration(
    platform: Platform.isIOS ? 'ios' : 'android',
    appVersion: appVersion,
  );
}

/// Root Application Widget with RTL Persian Localization, Dynamic Server Theme, and Deep Link Navigation.
class FlavorMobileApp extends StatelessWidget {
  const FlavorMobileApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ConfigProvider()),
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => MenuProvider()),
        ChangeNotifierProvider(create: (_) => CartProvider()),
        ChangeNotifierProvider(create: (_) => OrderProvider()),
        ChangeNotifierProvider(create: (_) => ReservationProvider()),
        ChangeNotifierProvider(create: (_) => FavoritesProvider()),
      ],
      child: Consumer<ConfigProvider>(
        builder: (context, config, _) {
          final design = config.design;

          final lightTheme = AppTheme.createTheme(
            brightness: Brightness.light,
            primaryColor: design.primaryColor,
            accentColor: design.accentColor,
            borderRadius: design.borderRadius,
            fontFamily: design.fontFamily,
          );

          final darkTheme = AppTheme.createTheme(
            brightness: Brightness.dark,
            primaryColor: design.primaryColor,
            accentColor: design.accentColor,
            borderRadius: design.borderRadius,
            fontFamily: design.fontFamily,
          );

          return MaterialApp(
            navigatorKey: DeepLinkService.instance.navigatorKey,
            title: config.brand.name,
            debugShowCheckedModeBanner: false,
            theme: lightTheme,
            darkTheme: darkTheme,
            themeMode: config.themeMode,

            // RTL & Persian Localization
            locale: const Locale('fa', 'IR'),
            supportedLocales: const [
              Locale('fa', 'IR'),
              Locale('en', 'US'),
            ],
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],

            // Routing & Deep Linking
            initialRoute: AppRoutes.splash,
            onGenerateRoute: AppRoutes.generateRoute,
            builder: (context, child) {
              return Directionality(
                textDirection: TextDirection.rtl,
                child: child ?? const SizedBox(),
              );
            },
          );
        },
      ),
    );
  }
}
