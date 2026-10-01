<?php
/** Template Name: دربارهٔ مجموعه
 * @package Flavor
 */
defined( 'ABSPATH' ) || exit;
$image = get_theme_mod( 'flavor_landing_story_image', '' );
if ( ! $image && \Flavor\Bespoke_Demos::active() ) { $image = \Flavor\Bespoke_Demos::asset( 'story.jpg' ); }
get_header();
?>
<div class="flavor-container flavor-about-page">
 <?php \Flavor\UI_Pages::breadcrumb( __( 'دربارهٔ ما', 'flavor' ) ); ?>
 <div class="flavor-about-layout"><div><span class="flavor-ui-overline"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span><h1><?php esc_html_e( 'پشت هر انتخاب، یک داستان.', 'flavor' ); ?></h1><p class="flavor-about-tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p><?php while ( have_posts() ) : the_post(); \Flavor\UI_Pages::content(); endwhile; ?><a class="flavor-btn flavor-btn--primary" href="<?php echo esc_url( \Flavor\UI::url( 'menu' ) ); ?>"><?php esc_html_e( 'آشنایی با منو', 'flavor' ); ?><?php \Flavor\UI::icon( 'arrow', 18 ); ?></a></div>
 <?php if ( $image ) : ?><figure class="flavor-about-photo"><img src="<?php echo esc_url( $image ); ?>" alt="<?php esc_attr_e( 'تصویر معرفی مجموعه', 'flavor' ); ?>" width="1000" height="800" loading="lazy" decoding="async" /><?php if ( \Flavor\Bespoke_Demos::active() ) : ?><figcaption><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'story_caption' ) ); ?></figcaption><?php endif; ?></figure><?php else : ?><div class="flavor-about-wordmark" aria-hidden="true"><?php \Flavor\UI::icon( 'leaf', 54 ); ?><span><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span></div><?php endif; ?>
 </div>
 <section class="flavor-about-choices" aria-labelledby="flavor-about-choices-title"><div><span class="flavor-ui-overline"><?php esc_html_e( 'یک مسیر روشن برای انتخاب', 'flavor' ); ?></span><h2 id="flavor-about-choices-title"><?php esc_html_e( 'از آشنایی تا سفارش.', 'flavor' ); ?></h2></div><div class="flavor-ui-grid-2"><a href="<?php echo esc_url( \Flavor\UI::url( 'menu' ) ); ?>"><?php \Flavor\UI::icon( 'menu', 24 ); ?><span><strong><?php esc_html_e( 'منو و ترکیبات', 'flavor' ); ?></strong><small><?php esc_html_e( 'جزئیات هر انتخاب را پیش از سفارش بخوانید.', 'flavor' ); ?></small></span><?php \Flavor\UI::icon( 'arrow', 20 ); ?></a><a href="<?php echo esc_url( \Flavor\UI::url( 'contact' ) ); ?>"><?php \Flavor\UI::icon( 'phone', 24 ); ?><span><strong><?php esc_html_e( 'هماهنگی و تماس', 'flavor' ); ?></strong><small><?php esc_html_e( 'سؤال و نیاز خاصتان را با مجموعه مطرح کنید.', 'flavor' ); ?></small></span><?php \Flavor\UI::icon( 'arrow', 20 ); ?></a></div></section>
</div>
<?php get_footer(); ?>
