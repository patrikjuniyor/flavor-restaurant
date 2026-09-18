import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'config/app_config.dart';
import 'config/routes.dart';
import 'config/theme.dart';
import 'core/constants/brand_tokens.g.dart';
import 'core/navigation/deep_link_service.dart';
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

  runApp(const FlavorMobileApp());
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
