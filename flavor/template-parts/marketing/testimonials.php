<?php
/**
 * Testimonials list.
 *
 * @package Flavor
 *
 * @var array<string, mixed> $args items[].
 */

defined( 'ABSPATH' ) || exit;

$items = $args['items'] ?? array();
if ( empty( $items ) ) {
	return;
}
?>
<section class="flavor-quotes flavor-container">
	<h2><?php esc_html_e( 'نظر مهمان‌ها', 'flavor' ); ?></h2>
	<div class="flavor-quotes__grid">
		<?php foreach ( $items as $item ) : ?>
			<blockquote>
				<p><?php echo esc_html( (string) ( $item['text'] ?? '' ) ); ?></p>
				<footer><?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?></footer>
			</blockquote>
		<?php endforeach; ?>
	</div>
</section>
