<?php
/**
 * Hero section.
 *
 * @package Flavor
 *
 * @var array<string, string> $args title, text, cta, image, url.
 */

defined( 'ABSPATH' ) || exit;

$title = $args['title'] ?? get_theme_mod( 'flavor_hero_title', get_bloginfo( 'name' ) );
$text  = $args['text'] ?? get_theme_mod( 'flavor_hero_text', get_bloginfo( 'description' ) );
$cta   = $args['cta'] ?? get_theme_mod( 'flavor_hero_cta', __( 'مشاهده منو', 'flavor' ) );
$image = $args['image'] ?? '';
$url   = $args['url'] ?? '';
if ( ! $url ) {
	$menu = get_page_by_path( 'menu' );
	$url  = $menu ? get_permalink( $menu ) : home_url( '/' );
}
if ( ! $image && has_post_thumbnail() ) {
	$image = get_the_post_thumbnail_url( get_the_ID(), 'flavor-hero' );
}
$skin = \Flavor\Design::current_skin();
if ( ! $image ) {
	$image = FLAVOR_URI . '/demos/' . $skin . '/hero.jpg';
}
?>
<section class="flavor-hero">
	<?php if ( $image ) : ?>
		<img class="flavor-hero__bg" src="<?php echo esc_url( $image ); ?>" alt="" fetchpriority="high" decoding="async" />
	<?php endif; ?>
	<div class="flavor-hero__inner">
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $text ); ?></p>
		<a class="flavor-btn flavor-btn--primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $cta ); ?></a>
	</div>
</section>
