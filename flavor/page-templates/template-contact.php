<?php
/** Template Name: تماس و اطلاعات مجموعه
 * @package Flavor
 */
defined( 'ABSPATH' ) || exit;
$context = \Flavor\UI::context(); $branch = \Flavor\UI_Pages::branch( $context['id'] );
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
  <?php if ( $phone ) : ?><div class="flavor-contact-detail"><?php \Flavor\UI::icon( 'phone', 25 ); ?><div><h3><?php esc_html_e( 'گفت‌وگو با مجموعه', 'flavor' ); ?></h3><a href="<?php echo esc_url( \Flavor\UI_Pages::tel( $phone ) ); ?>"><bdi><?php echo esc_html( \Flavor\Bespoke_Demos::digits( $phone ) ); ?></bdi></a><p><?php esc_html_e( 'نیاز غذایی و حساسیت را پیش از ثبت سفارش هماهنگ کنید.', 'flavor' ); ?></p></div></div><?php endif; ?>
  <?php if ( $address ) : ?><div class="flavor-contact-detail"><?php \Flavor\UI::icon( 'pin', 25 ); ?><div><h3><?php esc_html_e( 'نشانی ثبت‌شده', 'flavor' ); ?></h3><p><?php echo esc_html( $address ); ?></p><?php if ( ! empty( $branch['map'] ) ) : ?><a class="flavor-ui-text-link" href="<?php echo esc_url( $branch['map'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'دیدن موقعیت ثبت‌شده در نقشه', 'flavor' ); ?></a><?php endif; ?></div></div><?php endif; ?>
  <?php if ( is_email( $email ) ) : ?><div class="flavor-contact-detail"><?php \Flavor\UI::icon( 'info', 25 ); ?><div><h3><?php esc_html_e( 'ایمیل مجموعه', 'flavor' ); ?></h3><a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><bdi><?php echo esc_html( $email ); ?></bdi></a></div></div><?php endif; ?>
  <div class="flavor-contact-social"><?php flavor_social_links(); ?></div>
 </section><aside class="flavor-contact-hours flavor-ui-panel"><span class="flavor-ui-overline"><?php esc_html_e( 'پیش از مراجعه', 'flavor' ); ?></span><h2><?php echo 'catering' === \Flavor\Design::current_skin() ? esc_html__( 'ساعات هماهنگی', 'flavor' ) : esc_html__( 'ساعات ثبت‌شده', 'flavor' ); ?></h2><?php if ( $hours ) : ?><p><?php echo esc_html( $hours ); ?></p><?php else : ?><?php \Flavor\UI_Pages::render_hours( $context['id'] ); ?><?php endif; ?><p class="flavor-ui-note"><?php esc_html_e( 'تعطیلی‌های موردی، ظرفیت و زمان اجرای سفارش نیاز به تأیید مجموعه دارند؛ وضعیت بازبودن لحظه‌ای در این بخش ادعا نمی‌شود.', 'flavor' ); ?></p><a class="flavor-btn flavor-btn--primary" href="<?php echo esc_url( \Flavor\UI::url( 'menu' ) ); ?>"><?php esc_html_e( 'دیدن منو', 'flavor' ); ?><?php \Flavor\UI::icon( 'arrow', 18 ); ?></a></aside></div>
 <?php while ( have_posts() ) : the_post(); \Flavor\UI_Pages::content( true ); endwhile; ?>
</div>
<?php get_footer(); ?>
