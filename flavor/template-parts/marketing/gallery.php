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

// Items come from the repeater («سفارشی‌سازی → گالری تصاویر رستوران»).
// An untouched site still sees the demo art: Repeater falls back to the
// migrated fixed slots and then to the skin's own images.
$images = $args['images'] ?? \Flavor\Repeater::items( 'gallery' );

// A caller passing plain URLs still works, so nothing outside this file
// needs to know the shape changed.
$images = array_map(
	static fn( $item ) => is_array( $item ) ? $item : array( 'image' => (string) $item, 'alt' => '' ),
	(array) $images
);

// The demo pack is a separate download. With no photographs at all, an
// empty grid of headings and no pictures looks broken, so the whole
// section steps aside rather than advertising a gallery that is not there.
if ( empty( $images ) ) {
	return;
}
?>
<section class="flavor-section flavor-gallery" aria-label="<?php esc_attr_e( 'گالری تصاویر', 'flavor' ); ?>">
	<div class="flavor-container">
		<div class="flavor-section-header">
			<span class="flavor-section-header__tag"><?php esc_html_e( 'گوشه‌هایی از فضا و طعم‌ها', 'flavor' ); ?></span>
			<h2 class="flavor-section-header__title"><?php esc_html_e( 'گالری تصاویر رستوران', 'flavor' ); ?></h2>
			<p class="flavor-section-header__desc"><?php esc_html_e( 'نگاهی به محیط دلنشین، چیدمان میزها و هنر سرآشپزان ما در آماده‌سازی سفارش‌ها', 'flavor' ); ?></p>
		</div>

		<div class="flavor-gallery__grid">
			<?php foreach ( array_slice( $images, 0, 6 ) as $index => $item ) : ?>
				<?php
				$src = \Flavor\Repeater::image_url( $item );
				if ( '' === $src ) {
					continue;
				}
				?>
				<figure class="flavor-gallery__item flavor-gallery__item--<?php echo esc_attr( (string) ( $index + 1 ) ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in flavor_responsive_image().
					echo flavor_responsive_image(
						$src,
						'flavor-card',
						array(
								'alt'      => \Flavor\Repeater::image_alt( $item ),
							'sizes'    => '(min-width: 992px) 30vw, 92vw',
							'width'    => 600,
							'height'   => 400,
							'loading'  => 'lazy',
							'decoding' => 'async',
						)
					);
					?>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>
