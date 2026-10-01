<?php
/** A useful, branded not-found state without changing the HTTP 404. @package Flavor */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="flavor-container"><section class="flavor-not-found" aria-labelledby="flavor-404-title"><div class="flavor-not-found__art" aria-hidden="true"><span>۴۰۴</span><?php \Flavor\UI::icon( 'menu', 44 ); ?></div><span class="flavor-ui-overline"><?php esc_html_e( 'یک مسیر دیگر انتخاب کنیم', 'flavor' ); ?></span><h1 id="flavor-404-title"><?php esc_html_e( 'این صفحه پیدا نشد.', 'flavor' ); ?></h1><p><?php esc_html_e( 'ممکن است نشانی تغییر کرده باشد. از منو شروع کنید یا به صفحهٔ اصلی برگردید؛ انتخاب‌های شما بی‌دلیل پاک نمی‌شوند.', 'flavor' ); ?></p><div><a class="flavor-btn flavor-btn--primary" href="<?php echo esc_url( \Flavor\UI::url( 'menu' ) ); ?>"><?php esc_html_e( 'رفتن به منو', 'flavor' ); ?><?php \Flavor\UI::icon( 'arrow', 18 ); ?></a><a class="flavor-btn flavor-btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'صفحهٔ اصلی', 'flavor' ); ?></a></div></section></div>
<?php get_footer(); ?>
