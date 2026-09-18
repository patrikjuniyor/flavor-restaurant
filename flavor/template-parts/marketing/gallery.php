<?php
/**
 * Gallery section.
 *
 * @package Flavor
 *
 * @var array<string, mixed> $args images[] urls.
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_gallery_enable', 'yes' ) ) {
	return;
}

$skin = \Flavor\Design::current_skin();
$default_img = FLAVOR_URI . '/demos/' . $skin . '/hero.jpg';
$images = $args['images'] ?? array(
	$default_img,
	FLAVOR_URI . '/demos/fast-food/hero.jpg',
	FLAVOR_URI . '/demos/traditional/hero.jpg',
	FLAVOR_URI . '/demos/fine-dining/hero.jpg',
	FLAVOR_URI . '/demos/pastry/hero.jpg',
	FLAVOR_URI . '/demos/modern-cafe/hero.jpg',
);
?>
<section class="flavor-section flavor-gallery" aria-label="<?php esc_attr_e( 'گالری تصاویر', 'flavor' ); ?>">
	<div class="flavor-container">
		<div class="flavor-section-header">
			<span class="flavor-section-header__tag"><?php esc_html_e( 'گوشه‌هایی از فضا و طعم‌ها', 'flavor' ); ?></span>
			<h2 class="flavor-section-header__title"><?php esc_html_e( 'گالری تصاویر رستوران', 'flavor' ); ?></h2>
			<p class="flavor-section-header__desc"><?php esc_html_e( 'نگاهی به محیط دلنشین، چیدمان میزها و هنر سرآشپزان ما در آماده‌سازی سفارش‌ها', 'flavor' ); ?></p>
		</div>

		<div class="flavor-gallery__grid">
			<?php foreach ( array_slice( $images, 0, 6 ) as $index => $src ) : ?>
				<figure class="flavor-gallery__item flavor-gallery__item--<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
					<img src="<?php echo esc_url( $src ); ?>" alt="<?php esc_attr_e( 'تصویر رستوران', 'flavor' ); ?>" loading="lazy" width="600" height="400" />
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
