<?php
/**
 * Simple image gallery.
 *
 * @package Flavor
 *
 * @var array<string, mixed> $args images[] urls.
 */

defined( 'ABSPATH' ) || exit;

$images = $args['images'] ?? array();
if ( empty( $images ) ) {
	$skin   = \Flavor\Design::current_skin();
	$images = array( FLAVOR_URI . '/demos/' . $skin . '/hero.jpg' );
}
?>
<section class="flavor-gallery flavor-container">
	<div class="flavor-gallery__grid">
		<?php foreach ( $images as $src ) : ?>
			<figure><img src="<?php echo esc_url( $src ); ?>" alt="" loading="lazy" /></figure>
		<?php endforeach; ?>
	</div>
</section>
