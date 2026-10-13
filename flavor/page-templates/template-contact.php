<?php
/** Template Name: تماس و اطلاعات مجموعه
 * @package Flavor
 */
defined( 'ABSPATH' ) || exit;
$context = \Flavor\UI::context();
$branch = \Flavor\UI_Pages::branch( $context['id'] );
$phone = (string) get_theme_mod( 'flavor_phone', $branch['phone'] ?? '' );
$address = (string) get_theme_mod( 'flavor_address', $branch['address'] ?? '' );
$hours = (string) get_theme_mod( 'flavor_hours', '' );
$email = (string) get_theme_mod( 'flavor_ui_contact_email', '' );
get_header();
?>
<div class="flavor-container flavor-contact-page">
<?php \Flavor\UI_Pages::breadcrumb( __( 'تماس', 'flavor' ) ); ?>
<div class="flavor-ui-page-head"><div><span class="flavor-ui-overline"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span><h1><?php esc_html_e( 'در ارتباط باشیم.', 'flavor' ); ?></h1><p><?php esc_html_e( 'برای هماهنگی سفارش، نیاز غذایی یا جزئیات مراجعه، از راه‌های ارتباطی ثبت‌شده استفاده کنید.', 'flavor' ); ?></p></div></div>
<div class="flavor-contact-layout"><section class="flavor-ui-panel"><h2><?php esc_html_e( 'راه‌های ارتباطی', 'flavor' ); ?></h2>
	<?php if ( $phone ) :
?><div class="flavor-contact-detail"><?php \Flavor\UI::icon( 'phone', 25 ); ?><div><h3><?php esc_html_e( 'گفت‌وگو با مجموعه', 'flavor' ); ?></h3><a href="<?php echo esc_url( \Flavor\UI_Pages::tel( $phone ) ); ?>"><bdi><?php echo esc_html( \Flavor\Bespoke_Demos::digits( $phone ) ); ?></bdi></a><p><?php esc_html_e( 'نیاز غذایی و حساسیت را پیش از ثبت سفارش هماهنگ کنید.', 'flavor' ); ?></p></div></div><?php endif; ?>
	<?php if ( $address ) :
?><div class="flavor-contact-detail"><?php \Flavor\UI::icon( 'pin', 25 ); ?><div><h3><?php esc_html_e( 'نشانی ثبت‌شده', 'flavor' ); ?></h3><p><?php echo esc_html( $address ); ?></p><?php if ( ! empty( $branch['map'] ) ) :
?><a class="flavor-ui-text-link" href="<?php echo esc_url( $branch['map'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'دیدن موقعیت ثبت‌شده در نقشه', 'flavor' ); ?></a><?php endif; ?></div></div><?php endif; ?>
	<?php if ( is_email( $email ) ) :
?><div class="flavor-contact-detail"><?php \Flavor\UI::icon( 'info', 25 ); ?><div><h3><?php esc_html_e( 'ایمیل مجموعه', 'flavor' ); ?></h3><a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><bdi><?php echo esc_html( $email ); ?></bdi></a></div></div><?php endif; ?>
	<div class="flavor-contact-social"><?php flavor_social_links(); ?></div>
</section><aside class="flavor-contact-hours flavor-ui-panel"><span class="flavor-ui-overline"><?php esc_html_e( 'پیش از مراجعه', 'flavor' ); ?></span><h2><?php echo 'catering' === \Flavor\Design::current_skin() ? esc_html__( 'ساعات هماهنگی', 'flavor' ) : esc_html__( 'ساعات ثبت‌شده', 'flavor' ); ?></h2><?php if ( $hours ) :
?><p><?php echo esc_html( $hours ); ?></p><?php else :
?><?php \Flavor\UI_Pages::render_hours( $context['id'] ); ?><?php endif; ?><p class="flavor-ui-note"><?php esc_html_e( 'تعطیلی‌های موردی، ظرفیت و زمان اجرای سفارش نیاز به تأیید مجموعه دارند؛ وضعیت بازبودن لحظه‌ای در این بخش ادعا نمی‌شود.', 'flavor' ); ?></p><a class="flavor-btn flavor-btn--primary" href="<?php echo esc_url( \Flavor\UI::url( 'menu' ) ); ?>"><?php esc_html_e( 'دیدن منو', 'flavor' ); ?><?php \Flavor\UI::icon( 'arrow', 18 ); ?></a></aside></div>
<?php if ( is_email( $email ) ) : ?>
<section class="flavor-contact-form flavor-ui-panel" aria-labelledby="flavor-contact-title">
	<h2 id="flavor-contact-title"><?php esc_html_e( 'ارسال پیام', 'flavor' ); ?></h2>
	<p class="flavor-ui-note"><?php esc_html_e( 'برای هماهنگی سفارش، نیاز غذایی یا جزئیات مراجعه. پیام به ایمیل مجموعه ارسال می‌شود؛ برای موارد فوری تماس بگیرید.', 'flavor' ); ?></p>
	<form id="flavor-contact-form" class="flavor-checkout" hidden>
		<div class="flavor-ui-grid-2">
			<div class="flavor-ui-field"><label for="flavor-contact-name"><?php esc_html_e( 'نام', 'flavor' ); ?></label><input type="text" id="flavor-contact-name" autocomplete="name" maxlength="100" required /><p class="flavor-ui-field__err" id="flavor-contact-name-err" hidden></p></div>
			<div class="flavor-ui-field"><label for="flavor-contact-mobile"><?php esc_html_e( 'موبایل', 'flavor' ); ?><span class="flavor-ui-required"><?php esc_html_e( ' (اختیاری)', 'flavor' ); ?></span></label><input type="tel" id="flavor-contact-mobile" autocomplete="tel" inputmode="tel" dir="ltr" maxlength="16" placeholder="09xxxxxxxxx" aria-describedby="flavor-contact-reply-hint" /><p class="flavor-ui-field__err" id="flavor-contact-mobile-err" hidden></p></div>
		</div>
		<div class="flavor-ui-field"><label for="flavor-contact-email"><?php esc_html_e( 'ایمیل', 'flavor' ); ?><span class="flavor-ui-required"><?php esc_html_e( ' (اختیاری)', 'flavor' ); ?></span></label><input type="email" id="flavor-contact-email" autocomplete="email" dir="ltr" maxlength="100" placeholder="name@example.com" aria-describedby="flavor-contact-reply-hint" /><p class="flavor-ui-field__err" id="flavor-contact-email-err" hidden></p></div>
		<div class="flavor-ui-field"><label for="flavor-contact-message"><?php esc_html_e( 'پیام', 'flavor' ); ?></label><textarea id="flavor-contact-message" rows="4" maxlength="1000" required></textarea><p class="flavor-ui-field__err" id="flavor-contact-message-err" hidden></p></div>
		<p class="flavor-ui-hint" id="flavor-contact-reply-hint"><?php esc_html_e( 'برای پاسخ، دست‌کم یکی از موبایل یا ایمیل را وارد کنید.', 'flavor' ); ?></p>
		<div class="flavor-ui-field flavor-contact-hp" aria-hidden="true"><label for="flavor-contact-website"><?php esc_html_e( 'وب‌سایت', 'flavor' ); ?></label><input type="text" id="flavor-contact-website" tabindex="-1" autocomplete="off" /></div>
		<p class="flavor-checkout__err" id="flavor-contact-err" role="alert" tabindex="-1" hidden></p>
		<p class="flavor-ui-success" id="flavor-contact-ok" role="status" tabindex="-1" hidden></p>
		<button type="submit" class="flavor-btn flavor-btn--primary flavor-btn--full"><?php esc_html_e( 'ارسال پیام', 'flavor' ); ?><?php \Flavor\UI::icon( 'arrow', 18 ); ?></button>
	</form>
	<noscript><p class="flavor-ui-note"><?php esc_html_e( 'ارسال پیام به جاوااسکریپت نیاز دارد؛ می‌توانید مستقیم از رایانامهٔ خود به ایمیل مجموعه در بالا پیام بدهید.', 'flavor' ); ?></p></noscript>
</section>
<?php endif; ?>
<?php if ( ! empty( $context['id'] ) ) {
\Flavor\Branch_Map::render( (int) $context['id'], __( 'ما را روی نقشه ببینید', 'flavor' ) ); } ?>
<?php while ( have_posts() ) :
the_post();
\Flavor\UI_Pages::content( true );
endwhile; ?>
</div>
<?php get_footer(); ?>
