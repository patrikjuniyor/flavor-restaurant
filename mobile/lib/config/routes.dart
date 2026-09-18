import 'package:flutter/material.dart';
import '../core/navigation/deep_link_service.dart';
import '../ui/screens/splash_screen.dart';
import '../ui/screens/auth/otp_request_screen.dart';
import '../ui/screens/auth/otp_verify_screen.dart';
import '../ui/screens/home/home_screen.dart';
import '../ui/screens/menu/menu_screen.dart';
import '../ui/screens/menu/dish_detail_screen.dart';
import '../ui/screens/search/search_screen.dart';
import '../ui/screens/cart/cart_screen.dart';
import '../ui/screens/checkout/checkout_screen.dart';
import '../ui/screens/orders/orders_screen.dart';
import '../ui/screens/orders/order_tracking_screen.dart';
import '../ui/screens/reservation/reservation_screen.dart';
import '../ui/screens/reservation/reservation_history_screen.dart';
import '../ui/screens/profile/profile_screen.dart';
import '../ui/screens/profile/edit_profile_screen.dart';
import '../ui/screens/profile/addresses_screen.dart';
import '../ui/screens/profile/favorites_screen.dart';
import '../ui/screens/restaurant/restaurant_info_screen.dart';
import '../ui/screens/notifications/notifications_screen.dart';

/// App Route names, Route Generator, and Deep Link Parser.
class AppRoutes {
  static const String splash = '/';
  static const String home = '/home';
  static const String login = '/login';
  static const String otpVerify = '/otp-verify';
  static const String menu = '/menu';
  static const String dishDetail = '/dish-detail';
  static const String search = '/search';
  static const String cart = '/cart';
  static const String checkout = '/checkout';
  static const String orders = '/orders';
  static const String orderTracking = '/order-tracking';
  static const String reservation = '/reservation';
  static const String reservationHistory = '/reservation-history';
  static const String profile = '/profile';
  static const String editProfile = '/profile/edit';
  static const String addresses = '/profile/addresses';
  static const String favorites = '/profile/favorites';
  static const String restaurantInfo = '/restaurant-info';
  static const String notifications = '/notifications';

  /// Generates typed MaterialPageRoute for all application views.
  static Route<dynamic> generateRoute(RouteSettings settings) {
    final rawName = settings.name ?? '/';
    final destination = DeepLinkService.instance.parseUri(rawName);

    final routeName = destination?.route ?? rawName;
    final routeArgs = destination?.args ?? settings.arguments;

    switch (routeName) {
      case splash:
        return MaterialPageRoute(builder: (_) => const SplashScreen(), settings: settings);

      case home:
        return MaterialPageRoute(builder: (_) => const HomeScreen(), settings: settings);

      case login:
        return MaterialPageRoute(builder: (_) => const OtpRequestScreen(), settings: settings);

      case otpVerify:
        final mobile = routeArgs as String? ?? '';
        return MaterialPageRoute(builder: (_) => OtpVerifyScreen(mobile: mobile), settings: settings);

      case menu:
        int catId = 0;
        if (routeArgs is int) {
          catId = routeArgs;
        } else if (routeArgs is Map && routeArgs['category_id'] != null) {
          catId = int.tryParse(routeArgs['category_id'].toString()) ?? 0;
        }
        return MaterialPageRoute(builder: (_) => MenuScreen(initialCategoryId: catId), settings: settings);

      case dishDetail:
        final dishId = (routeArgs is int) ? routeArgs : (int.tryParse(routeArgs?.toString() ?? '') ?? 0);
        return MaterialPageRoute(builder: (_) => DishDetailScreen(dishId: dishId), settings: settings);

      case search:
        return MaterialPageRoute(builder: (_) => const SearchScreen(), settings: settings);

      case cart:
        return MaterialPageRoute(builder: (_) => const CartScreen(), settings: settings);

      case checkout:
        return MaterialPageRoute(builder: (_) => const CheckoutScreen(), settings: settings);

      case orders:
        return MaterialPageRoute(builder: (_) => const OrdersScreen(), settings: settings);

      case orderTracking:
        int orderId = 0;
        String? guestToken;
        if (routeArgs is int) {
          orderId = routeArgs;
        } else if (routeArgs is Map) {
          orderId = int.tryParse(routeArgs['order_id']?.toString() ?? '') ?? 0;
          guestToken = routeArgs['guest_token']?.toString();
        }
        return MaterialPageRoute(
          builder: (_) => OrderTrackingScreen(orderId: orderId, guestToken: guestToken),
          settings: settings,
        );

      case reservation:
        return MaterialPageRoute(builder: (_) => const ReservationScreen(), settings: settings);

      case reservationHistory:
        return MaterialPageRoute(builder: (_) => const ReservationHistoryScreen(), settings: settings);

      case profile:
        return MaterialPageRoute(builder: (_) => const ProfileScreen(), settings: settings);

      case editProfile:
        return MaterialPageRoute(builder: (_) => const EditProfileScreen(), settings: settings);

      case addresses:
        return MaterialPageRoute(builder: (_) => const AddressesScreen(), settings: settings);

      case favorites:
        return MaterialPageRoute(builder: (_) => const FavoritesScreen(), settings: settings);

      case restaurantInfo:
        return MaterialPageRoute(builder: (_) => const RestaurantInfoScreen(), settings: settings);

      case notifications:
        return MaterialPageRoute(builder: (_) => const NotificationsScreen(), settings: settings);

      default:
        return MaterialPageRoute(builder: (_) => const HomeScreen(), settings: settings);
    }
  }

  /// Parses deep link (backward compatible helper).
  static Map<String, dynamic>? parseDeepLink(String url) {
    final dest = DeepLinkService.instance.parseUri(url);
    if (dest == null) return null;
    return {'route': dest.route, 'args': dest.args, 'requiresAuth': dest.requiresAuth};
  }
}
