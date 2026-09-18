import 'package:flutter/material.dart';
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

/// App Route names and Deep Link resolver.
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

  static Route<dynamic> generateRoute(RouteSettings settings) {
    final uri = Uri.parse(settings.name ?? '/');

    // Handle deep links like /product/123 or /order/123
    if (uri.pathSegments.isNotEmpty) {
      if (uri.pathSegments.first == 'product' || uri.pathSegments.first == 'dish') {
        final dishId = int.tryParse(uri.pathSegments.length > 1 ? uri.pathSegments[1] : '') ?? 0;
        return MaterialPageRoute(
          builder: (_) => DishDetailScreen(dishId: dishId),
          settings: settings,
        );
      }
      if (uri.pathSegments.first == 'order') {
        final orderId = int.tryParse(uri.pathSegments.length > 1 ? uri.pathSegments[1] : '') ?? 0;
        return MaterialPageRoute(
          builder: (_) => OrderTrackingScreen(orderId: orderId),
          settings: settings,
        );
      }
    }

    switch (settings.name) {
      case splash:
        return MaterialPageRoute(builder: (_) => const SplashScreen());
      case home:
        return MaterialPageRoute(builder: (_) => const HomeScreen());
      case login:
        return MaterialPageRoute(builder: (_) => const OtpRequestScreen());
      case otpVerify:
        final mobile = settings.arguments as String? ?? '';
        return MaterialPageRoute(builder: (_) => OtpVerifyScreen(mobile: mobile));
      case menu:
        final catId = settings.arguments as int? ?? 0;
        return MaterialPageRoute(builder: (_) => MenuScreen(initialCategoryId: catId));
      case dishDetail:
        final dishId = settings.arguments as int? ?? 0;
        return MaterialPageRoute(builder: (_) => DishDetailScreen(dishId: dishId));
      case search:
        return MaterialPageRoute(builder: (_) => const SearchScreen());
      case cart:
        return MaterialPageRoute(builder: (_) => const CartScreen());
      case checkout:
        return MaterialPageRoute(builder: (_) => const CheckoutScreen());
      case orders:
        return MaterialPageRoute(builder: (_) => const OrdersScreen());
      case orderTracking:
        final orderId = settings.arguments as int? ?? 0;
        return MaterialPageRoute(builder: (_) => OrderTrackingScreen(orderId: orderId));
      case reservation:
        return MaterialPageRoute(builder: (_) => const ReservationScreen());
      case reservationHistory:
        return MaterialPageRoute(builder: (_) => const ReservationHistoryScreen());
      case profile:
        return MaterialPageRoute(builder: (_) => const ProfileScreen());
      case editProfile:
        return MaterialPageRoute(builder: (_) => const EditProfileScreen());
      case addresses:
        return MaterialPageRoute(builder: (_) => const AddressesScreen());
      case favorites:
        return MaterialPageRoute(builder: (_) => const FavoritesScreen());
      case restaurantInfo:
        return MaterialPageRoute(builder: (_) => const RestaurantInfoScreen());
      case notifications:
        return MaterialPageRoute(builder: (_) => const NotificationsScreen());
      default:
        return MaterialPageRoute(builder: (_) => const HomeScreen());
    }
  }

  /// Parses external deep link into route name and arguments.
  static Map<String, dynamic>? parseDeepLink(String url) {
    try {
      final uri = Uri.parse(url);
      if (uri.path.contains('/menu')) {
        return {'route': menu, 'args': null};
      }
      if (uri.path.contains('/product/') || uri.path.contains('/dish/')) {
        final id = int.tryParse(uri.pathSegments.last) ?? 0;
        return {'route': dishDetail, 'args': id};
      }
      if (uri.path.contains('/order/')) {
        final id = int.tryParse(uri.pathSegments.last) ?? 0;
        return {'route': orderTracking, 'args': id};
      }
      if (uri.path.contains('/reservation')) {
        return {'route': reservation, 'args': null};
      }
    } catch (_) {}
    return null;
  }
}
