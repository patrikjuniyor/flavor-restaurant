/// Application-wide constants, storage keys, and API path mappings.
class AppConstants {
  // Storage keys
  static const String keyAccessToken = 'flavor_access_token';
  static const String keyRefreshToken = 'flavor_refresh_token';
  static const String keyCartToken = 'flavor_cart_token';
  static const String keyUserProfile = 'flavor_user_profile';
  static const String keyActiveBranch = 'flavor_active_branch';
  static const String keyThemeMode = 'flavor_theme_mode';
  static const String keySavedAddresses = 'flavor_saved_addresses';
  static const String keyFavorites = 'flavor_favorites';
  static const String keyBootstrapCache = 'flavor_bootstrap_cache';

  // API Endpoints
  static const String epBootstrap = '/settings/app-bootstrap';
  static const String epOtpRequest = '/auth/otp/request';
  static const String epOtpVerify = '/auth/otp/verify';
  static const String epTokenRefresh = '/auth/token/refresh';
  static const String epTokenRevoke = '/auth/token/revoke';
  static const String epMe = '/auth/me';
  static const String epDevice = '/auth/device';

  static const String epBranches = '/branches';
  static const String epCategories = '/categories';
  static const String epMenu = '/menu';
  static const String epDishes = '/dishes';
  static const String epFeatured = '/dishes/featured';
  static const String epSpecialOffers = '/dishes/special-offers';

  static const String epCart = '/cart';
  static const String epCartItems = '/cart/items';
  static const String epCartCoupon = '/cart/coupon';
  static const String epCartLoyalty = '/cart/loyalty';

  static const String epOrders = '/orders';
  static const String epReservations = '/reservations';
  static const String epReservationSlots = '/reservations/slots';
  static const String epReservationCalendar = '/reservations/calendar';
  static const String epReviewsRecent = '/reviews/recent';
  static const String epSearch = '/search';
  static const String epSearchSuggest = '/search/suggest';
  static const String epSearchPopular = '/search/popular';

  // Order Modes
  static const String modeDineIn = 'dine_in';
  static const String modeTakeaway = 'takeaway';
  static const String modeDelivery = 'delivery';

  // App defaults
  static const String defaultFontFamily = 'Vazirmatn';
  static const String defaultCurrency = 'تومان';
  static const int defaultOtpLength = 5;
  static const int defaultOtpTimerSec = 120;
}
