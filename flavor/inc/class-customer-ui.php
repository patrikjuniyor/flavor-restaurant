<?php
/**
 * Customer-facing presentation on top of native WooCommerce ownership checks.
 * No alternative order creation, gateway or authentication service is defined.
 * @package Flavor
 */
namespace Flavor;
defined( 'ABSPATH' ) || exit;
class Customer_UI {
 public static function init(): void {
  add_filter( 'body_class', array( self::class, 'body_class' ) );
  add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ), 35 );
  add_filter( 'gettext', array( self::class, 'persian_fallback' ), 20, 3 );
  add_action( 'woocommerce_before_customer_login_form', array( self::class, 'login' ) );
  add_action( 'woocommerce_account_dashboard', array( self::class, 'dashboard' ), 5 );
  add_filter( 'woocommerce_account_menu_items', array( self::class, 'account_menu' ) );
  add_action( 'woocommerce_view_order', array( self::class, 'receipt' ), 5 );
  add_action( 'woocommerce_before_thankyou', array( self::class, 'receipt' ), 5 );
 }
 public static function body_class( array $classes ): array {
  if ( function_exists( 'is_account_page' ) && is_account_page() ) { $classes[] = 'flavor-ui-account'; }
  return $classes;
 }
 public static function assets(): void {
  $account = function_exists( 'is_account_page' ) && is_account_page();
  $tracking = is_page_template( 'page-templates/template-order-tracking.php' );
  if ( $account || $tracking || is_page_template( 'page-templates/template-menu.php' ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
   wp_enqueue_style( 'flavor-ui-account', FLAVOR_URI . '/assets/css/ui-account.css', array( 'flavor-ui', 'flavor-ui-checkout' ), (string) filemtime( FLAVOR_DIR . '/assets/css/ui-account.css' ) );
  }
  if ( $account || $tracking || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
   wp_enqueue_script( 'flavor-ui-customer', FLAVOR_URI . '/assets/js/customer.js', array( 'flavor-ui-js' ), (string) filemtime( FLAVOR_DIR . '/assets/js/customer.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
  }
  if ( Theme_Setup::has_core() && ( $account || is_page_template( 'page-templates/template-menu.php' ) ) ) {
   wp_enqueue_script( 'flavor-ui-auth', FLAVOR_URI . '/assets/js/auth.js', array( 'flavor-ui-js' ), (string) filemtime( FLAVOR_DIR . '/assets/js/auth.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
  }
  if ( $tracking && Theme_Setup::has_core() ) {
   wp_enqueue_script( 'flavor-ui-tracking', FLAVOR_URI . '/assets/js/tracking.js', array( 'flavor-ui-js' ), (string) filemtime( FLAVOR_DIR . '/assets/js/tracking.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
  }
 }
 public static function persian_fallback( string $translated, string $text, string $domain ): string {
  if ( 'woocommerce' !== $domain || $translated !== $text || is_admin() ) { return $translated; }
  // Only untranslated native labels; an installed language pack takes priority.
  $labels = array(
   'Login' => 'ورود', 'Log in' => 'ورود', 'Register' => 'ساخت حساب', 'Username or email address' => 'نام کاربری یا ایمیل', 'Password' => 'رمز عبور', 'Remember me' => 'مرا به خاطر بسپار', 'Lost your password?' => 'رمز عبور را فراموش کرده‌اید؟', 'Email address' => 'نشانی ایمیل', 'Username' => 'نام کاربری',
   'Order' => 'سفارش', 'Orders' => 'سفارش‌ها', 'Date' => 'تاریخ', 'Status' => 'وضعیت', 'Total' => 'مبلغ', 'Actions' => 'اقدام', 'View' => 'مشاهده', 'Pay' => 'پرداخت', 'Cancel' => 'لغو', 'Product' => 'محصول', 'Quantity' => 'تعداد', 'Subtotal' => 'جمع جزء', 'Order details' => 'جزئیات سفارش', 'Billing address' => 'نشانی صورتحساب', 'Shipping address' => 'نشانی دریافت', 'Billing details' => 'اطلاعات صورتحساب',
   'First name' => 'نام', 'Last name' => 'نام خانوادگی', 'Display name' => 'نام نمایشی', 'Save changes' => 'ذخیرهٔ تغییرات', 'Password change' => 'تغییر رمز عبور', 'Current password (leave blank to leave unchanged)' => 'رمز فعلی؛ اگر تغییری نمی‌دهید خالی بگذارید', 'New password (leave blank to leave unchanged)' => 'رمز جدید؛ اگر تغییری نمی‌دهید خالی بگذارید', 'Confirm new password' => 'تکرار رمز جدید',
   'Order number:' => 'شمارهٔ سفارش:', 'Date:' => 'تاریخ:', 'Total:' => 'مبلغ:', 'Payment method:' => 'روش پرداخت:', 'Payment method' => 'روش پرداخت', 'Order ID' => 'شناسهٔ سفارش', 'Billing email' => 'ایمیل صورتحساب', 'Track' => 'پیگیری', 'Invalid order.' => 'سفارش نامعتبر است.', 'No order has been made yet.' => 'هنوز سفارشی به این حساب متصل نیست.',
   'Hello %1$s (not %1$s? <a href="%2$s">Log out</a>)' => 'سلام %1$s؛ شما نیستید؟ <a href="%2$s">خروج از حساب</a>',
   'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">billing address</a>, and <a href="%3$s">edit your password and account details</a>.' => 'از این بخش <a href="%1$s">سفارش‌های حساب</a> را ببینید، <a href="%2$s">نشانی صورتحساب</a> را مدیریت کنید و <a href="%3$s">اطلاعات و رمز عبور</a> را تغییر دهید.',
   'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.' => 'از این بخش <a href="%1$s">سفارش‌های حساب</a> را ببینید، <a href="%2$s">نشانی‌ها</a> را مدیریت کنید و <a href="%3$s">اطلاعات و رمز عبور</a> را تغییر دهید.',
  );
  return $labels[ $text ] ?? $translated;
 }
 public static function login(): void {
  if ( ! Theme_Setup::has_core() || is_user_logged_in() || 'no' === get_theme_mod( 'flavor_ui_phone_login', 'yes' ) ) { return; }
  get_template_part( 'template-parts/ui/login' );
 }
 public static function account_menu( array $items ): array {
  $labels = array( 'dashboard' => 'پیشخوان من', 'orders' => 'سفارش‌ها', 'downloads' => 'دریافت فایل', 'edit-address' => 'نشانی‌ها', 'payment-methods' => 'روش‌های پرداخت', 'edit-account' => 'اطلاعات حساب', 'customer-logout' => 'خروج از حساب' );
  foreach ( $items as $key => $label ) { if ( isset( $labels[ $key ] ) ) { $items[ $key ] = $labels[ $key ]; } }
  if ( function_exists( 'wc_get_customer_available_downloads' ) && ! wc_get_customer_available_downloads( get_current_user_id() ) ) { unset( $items['downloads'] ); }
  return $items;
 }
 public static function price( float $amount, string $currency ): string {
  if ( class_exists( \FlavorCore\WooCommerce\Currency::class ) && in_array( strtoupper( $currency ), array( 'IRR', 'IRT' ), true ) ) {
   return esc_html( \FlavorCore\WooCommerce\Currency::format( \FlavorCore\WooCommerce\Currency::to_storage( (int) round( $amount ), strtolower( $currency ) ) ) );
  }
  return function_exists( 'wc_price' ) ? wc_price( $amount, array( 'currency' => $currency ) ) : esc_html( (string) $amount );
 }
 public static function status_label( string $status ): string {
  $labels = array( 'pending' => 'در انتظار پرداخت', 'processing' => 'در حال انجام', 'on-hold' => 'در انتظار تأیید', 'completed' => 'تکمیل‌شده', 'cancelled' => 'لغوشده', 'refunded' => 'مستردشده', 'failed' => 'پرداخت ناموفق' );
  return $labels[ $status ] ?? ( function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : $status );
 }
 public static function dashboard(): void {
  if ( ! is_user_logged_in() || ! function_exists( 'wc_get_orders' ) ) { return; }
  $id = get_current_user_id(); $user = wp_get_current_user();
  $orders = wc_get_orders( array( 'customer_id' => $id, 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC' ) );
  $summary = wc_get_orders( array( 'customer_id' => $id, 'limit' => 1, 'paginate' => true ) );
  $count = is_object( $summary ) ? (int) $summary->total : count( $orders );
  $points = class_exists( \FlavorCore\Loyalty\PointsManager::class ) ? \FlavorCore\Loyalty\PointsManager::summary( $id ) : null;
  ?>
  <section class="flavor-account-dashboard" aria-labelledby="flavor-account-welcome"><div class="flavor-account-dashboard__head"><span class="flavor-account-avatar" aria-hidden="true"><?php echo esc_html( flavor_substr( $user->display_name ?: $user->user_login, 0, 1 ) ); ?></span><div><span class="flavor-ui-overline"><?php esc_html_e( 'حساب مشتری', 'flavor' ); ?></span><h2 id="flavor-account-welcome"><?php echo esc_html( sprintf( __( 'سلام، %s', 'flavor' ), $user->display_name ?: $user->user_login ) ); ?></h2><p><?php esc_html_e( 'سفارش‌ها و اطلاعاتی که به همین حساب متصل‌اند، اینجا در دسترس شما هستند.', 'flavor' ); ?></p></div></div>
   <div class="flavor-account-stats"><div><span><?php esc_html_e( 'سفارش‌های حساب', 'flavor' ); ?></span><strong><?php echo esc_html( Bespoke_Demos::digits( (string) $count ) ); ?></strong></div><?php if ( $points ) : ?><div><span><?php esc_html_e( 'امتیاز ثبت‌شده', 'flavor' ); ?></span><strong><?php echo esc_html( Bespoke_Demos::digits( (string) $points['points'] ) ); ?></strong></div><div><span><?php esc_html_e( 'مهرهای ثبت‌شده', 'flavor' ); ?></span><strong><?php echo esc_html( Bespoke_Demos::digits( (string) $points['stamps'] ) ); ?></strong></div><?php endif; ?></div>
   <div class="flavor-account-recent"><div class="flavor-account-section-head"><h3><?php esc_html_e( 'آخرین سفارش‌های شما', 'flavor' ); ?></h3><a class="flavor-ui-text-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'همهٔ سفارش‌ها', 'flavor' ); ?><?php UI::icon( 'arrow', 16 ); ?></a></div>
    <?php if ( ! $orders ) : ?><div class="flavor-ui-empty"><?php UI::icon( 'bag', 32 ); ?><h3><?php esc_html_e( 'هنوز سفارشی به این حساب متصل نیست.', 'flavor' ); ?></h3><p><?php esc_html_e( 'سفارش مهمان لزوماً در تاریخچهٔ حساب نمایش داده نمی‌شود؛ از رسید یا مسیر پیگیری آن استفاده کنید.', 'flavor' ); ?></p><a class="flavor-btn flavor-btn--primary" href="<?php echo esc_url( UI::url( 'menu' ) ); ?>"><?php esc_html_e( 'دیدن منو', 'flavor' ); ?></a></div><?php else : ?>
    <?php foreach ( $orders as $order ) : ?><a class="flavor-account-order" href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><div><strong><?php echo esc_html( sprintf( __( 'سفارش %s', 'flavor' ), Bespoke_Demos::digits( $order->get_order_number() ) ) ); ?></strong><small><?php echo esc_html( self::status_label( $order->get_status() ) ); ?></small></div><span><?php echo wp_kses_post( self::price( (float) $order->get_total(), $order->get_currency() ) ); ?></span><?php UI::icon( 'arrow', 18 ); ?></a><?php endforeach; ?>
    <?php endif; ?>
   </div>
  </section>
  <?php
 }
 /** Defense in depth on display hooks: owner, manager, or a verified Woo receipt key. */
 public static function can_view( $order ): bool {
  if ( ! $order ) { return false; }
  if ( current_user_can( 'manage_woocommerce' ) ) { return true; }
  if ( get_current_user_id() > 0 && (int) $order->get_customer_id() === get_current_user_id() ) { return true; }
  $key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- secret receipt key, not a mutation.
  return $key && hash_equals( (string) $order->get_order_key(), $key );
 }
 public static function receipt( int $id ): void {
  $order = function_exists( 'wc_get_order' ) ? wc_get_order( $id ) : null;
  if ( ! self::can_view( $order ) ) { return; }
  $ticket = class_exists( \FlavorCore\Order\KitchenTicketRepository::class ) ? \FlavorCore\Order\KitchenTicketRepository::find_by_order( $id ) : null;
  ?>
  <section class="flavor-order-receipt" aria-labelledby="flavor-order-receipt-title"><div class="flavor-order-receipt__head"><span class="flavor-order-receipt__icon" aria-hidden="true"><?php UI::icon( 'bag', 28 ); ?></span><div><span class="flavor-ui-overline"><?php esc_html_e( 'اطلاعات واقعی سفارش', 'flavor' ); ?></span><h2 id="flavor-order-receipt-title"><?php echo esc_html( sprintf( __( 'سفارش %s', 'flavor' ), Bespoke_Demos::digits( $order->get_order_number() ) ) ); ?></h2></div><span class="flavor-ui-tag"><?php echo esc_html( self::status_label( $order->get_status() ) ); ?></span></div><p><?php esc_html_e( 'این وضعیت از سفارش ثبت‌شده خوانده می‌شود؛ رسیدن به این صفحه به‌تنهایی به معنی پرداخت موفق یا تحویل غذا نیست.', 'flavor' ); ?></p><div class="flavor-order-receipt__meta"><div><span><?php esc_html_e( 'مبلغ سفارش', 'flavor' ); ?></span><strong><?php echo wp_kses_post( self::price( (float) $order->get_total(), $order->get_currency() ) ); ?></strong></div><div><span><?php esc_html_e( 'روش پرداخت ثبت‌شده', 'flavor' ); ?></span><strong><?php echo esc_html( $order->get_payment_method_title() ?: __( 'در رسید اصلی بررسی کنید', 'flavor' ) ); ?></strong></div></div>
   <?php if ( $ticket && ! $order->has_status( array( 'pending', 'failed', 'cancelled', 'refunded' ) ) ) : ?><?php self::timeline( $ticket ); ?><?php endif; ?>
   <?php $guest_secret = (string) $order->get_meta( '_flavor_guest_token' ); if ( ! $order->get_customer_id() && $guest_secret ) : ?><details class="flavor-receipt-secret" data-ui-receipt-secret><summary><?php esc_html_e( 'کد خصوصی پیگیری سفارش مهمان', 'flavor' ); ?></summary><label class="screen-reader-text" for="flavor-receipt-code"><?php esc_html_e( 'کد خصوصی پیگیری', 'flavor' ); ?></label><input id="flavor-receipt-code" type="password" value="<?php echo esc_attr( $guest_secret ); ?>" readonly autocomplete="off" /><div><button type="button" class="flavor-btn flavor-btn--outline" data-ui-copy-secret><?php esc_html_e( 'کپی کد', 'flavor' ); ?></button><button type="button" class="flavor-ui-text-link" data-ui-show-secret><?php esc_html_e( 'نمایش کد', 'flavor' ); ?></button></div><p role="status" class="flavor-ui-note"><?php esc_html_e( 'این کد خصوصی است و فقط پس از احراز دسترسی به رسید نمایش داده می‌شود. آن را در اختیار دیگران نگذارید.', 'flavor' ); ?></p><a class="flavor-ui-text-link" href="<?php echo esc_url( UI::url( 'tracking' ) ); ?>"><?php esc_html_e( 'رفتن به صفحهٔ پیگیری', 'flavor' ); ?></a></details><?php endif; ?>
  </section>
  <?php
 }
 public static function timeline( array $ticket ): void {
  $states = array( 'new', 'preparing', 'ready', 'completed' );
  $index = array_search( $ticket['kitchen_status'] ?? '', $states, true );
  $labels = array( 'در صف بررسی آشپزخانه', 'در حال آماده‌سازی', 'آمادهٔ تحویل یا سرو', 'تحویل ثبت شده' );
  if ( false === $index ) { return; }
  echo '<ol class="flavor-order-timeline" aria-label="' . esc_attr__( 'وضعیت ثبت‌شده در آشپزخانه', 'flavor' ) . '">';
  foreach ( $labels as $i => $label ) {
   echo '<li class="' . ( $i <= $index ? 'is-reached' : '' ) . '"' . ( $i === $index ? ' aria-current="step"' : '' ) . '><span aria-hidden="true">' . esc_html( Bespoke_Demos::digits( (string) ( $i + 1 ) ) ) . '</span><strong>' . esc_html( $label ) . '</strong></li>';
  }
  echo '</ol><p class="flavor-ui-note">' . esc_html__( 'آمادهٔ تحویل بودن با تحویل به پیک یا رسیدن به مقصد یکی نیست؛ رهگیری زندهٔ پیک در این رابط ارائه نمی‌شود.', 'flavor' ) . '</p>';
 }
}
