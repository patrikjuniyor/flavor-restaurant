<?php
/** Template Name: پیگیری سفارش
 * @package Flavor
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="flavor-container flavor-tracking-page">
 <nav class="flavor-ui-breadcrumb" aria-label="<?php esc_attr_e( 'مسیر صفحه', 'flavor' ); ?>"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'flavor' ); ?></a><span aria-hidden="true">/</span><span aria-current="page"><?php esc_html_e( 'پیگیری سفارش', 'flavor' ); ?></span></nav>
 <div class="flavor-ui-page-head"><div><span class="flavor-ui-overline"><?php esc_html_e( 'بعد از انتخاب، در جریان بمانید', 'flavor' ); ?></span><h1><?php esc_html_e( 'سفارش شما در چه وضعیتی است؟', 'flavor' ); ?></h1><p><?php esc_html_e( 'شناسهٔ سفارش و کد پیگیری مهمان را از رسید وارد کنید. سفارش‌های متصل به حساب، پس از ورود بدون کد مهمان قابل بررسی‌اند.', 'flavor' ); ?></p></div></div>
 <div class="flavor-tracking-layout"><section class="flavor-ui-panel"><h2><?php esc_html_e( 'بررسی سفارش', 'flavor' ); ?></h2>
  <?php if ( \Flavor\Theme_Setup::has_core() ) : ?>
  <form data-ui-tracking hidden><div class="flavor-ui-field"><label for="flavor-track-id"><?php esc_html_e( 'شناسهٔ سفارش', 'flavor' ); ?></label><input type="text" id="flavor-track-id" inputmode="numeric" maxlength="12" required placeholder="<?php esc_attr_e( 'شناسه درج‌شده در رسید', 'flavor' ); ?>" /></div><div class="flavor-ui-field"><label for="flavor-track-token"><?php esc_html_e( 'کد پیگیری مهمان', 'flavor' ); ?></label><input type="password" id="flavor-track-token" autocomplete="off" maxlength="128" aria-describedby="flavor-track-privacy" /><small id="flavor-track-privacy"><?php esc_html_e( 'برای سفارش متعلق به حساب واردشده اختیاری است. کد در نشانی صفحه یا حافظهٔ مرورگر ذخیره نمی‌شود؛ آن را در اختیار دیگران نگذارید.', 'flavor' ); ?></small></div><button type="submit" class="flavor-btn flavor-btn--primary flavor-btn--full"><?php esc_html_e( 'بررسی وضعیت واقعی', 'flavor' ); ?><?php \Flavor\UI::icon( 'arrow', 18 ); ?></button><p data-ui-track-error class="flavor-ui-error" role="alert" hidden></p></form>
  <noscript><p class="flavor-ui-note"><?php esc_html_e( 'بررسی با کد مهمان به JavaScript نیاز دارد. برای پیگیری با شناسه و ایمیل صورتحساب، فرم اصلی ووکامرس در ادامه در دسترس است.', 'flavor' ); ?></p></noscript>
  <?php else : ?><p class="flavor-ui-note"><?php esc_html_e( 'پیگیری سریع فعلاً فعال نیست. از رسید سفارش یا حساب مشتری استفاده کنید.', 'flavor' ); ?></p><?php endif; ?>
  <details class="flavor-tracking-native"><summary><?php esc_html_e( 'پیگیری اصلی ووکامرس با ایمیل صورتحساب', 'flavor' ); ?></summary><?php if ( shortcode_exists( 'woocommerce_order_tracking' ) ) { echo do_shortcode( '[woocommerce_order_tracking]' ); } ?></details>
 </section><aside class="flavor-tracking-guide"><span class="flavor-account-login__mark" aria-hidden="true"><?php \Flavor\UI::icon( 'bag', 32 ); ?></span><h2><?php esc_html_e( 'وضعیت واقعی، نه تخمین نمایشی.', 'flavor' ); ?></h2><p><?php esc_html_e( 'وضعیت پرداخت از سفارش و مراحل آماده‌سازی فقط از رکورد موجود آشپزخانه خوانده می‌شوند. زمان رسیدن تضمین‌شده و نقشهٔ زندهٔ پیک ارائه نمی‌شود.', 'flavor' ); ?></p><a class="flavor-ui-text-link" href="<?php echo esc_url( \Flavor\UI::url( 'account' ) ); ?>"><?php esc_html_e( 'رفتن به حساب مشتری', 'flavor' ); ?><?php \Flavor\UI::icon( 'arrow', 18 ); ?></a></aside></div>
 <section class="flavor-tracking-result" data-ui-track-result role="region" aria-labelledby="flavor-tracking-result-title" tabindex="-1" hidden></section>
</div>
<?php get_footer(); ?>
