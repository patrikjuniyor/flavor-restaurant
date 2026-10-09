<?php
/**
 * Testimonials & Reviews section.
 *
 * @package Flavor
 *
 * @var array<string, mixed> $args items[].
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_testimonials_enable', 'yes' ) ) {
	return;
}

// The three sample reviews used to be hard-coded here. They are now editable
// and unlimited, and live in Repeater::fallback() as translated defaults so
// a site that has never touched the control looks exactly as it did.
$items = $args['items'] ?? \Flavor\Repeater::items( 'testimonials' );
?>
<section class="flavor-section flavor-testimonials" aria-label="<?php esc_attr_e( 'نظرات مهمان‌ها', 'flavor' ); ?>">
	<div class="flavor-container">
		<div class="flavor-section-header">
			<span class="flavor-section-header__tag"><?php esc_html_e( 'تجربه مهمانان', 'flavor' ); ?></span>
			<h2 class="flavor-section-header__title"><?php esc_html_e( 'نظرات و رضایت همراهان ما', 'flavor' ); ?></h2>
			<p class="flavor-section-header__desc"><?php esc_html_e( 'افتخار ما لبخند رضایت و ثبت خاطرات خوشمزه برای شماست', 'flavor' ); ?></p>
		</div>

		<div class="flavor-testimonials__grid">
			<?php foreach ( $items as $item ) : ?>
				<blockquote class="flavor-review-card">
					<div class="flavor-review-card__stars" aria-label="۵ ستاره">
						<?php for ( $s = 0; $s < (int) ( $item['rating'] ?? 5 ); $s++ ) : ?>
							<svg width="18" height="18" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
						<?php endfor; ?>
					</div>
					<p class="flavor-review-card__text"><?php echo esc_html( (string) ( $item['text'] ?? '' ) ); ?></p>
					<footer class="flavor-review-card__author">
						<div class="flavor-review-card__avatar">
							<?php echo esc_html( flavor_substr( (string) ( $item['name'] ?? 'م' ), 0, 1 ) ); ?>
						</div>
						<div>
							<strong class="flavor-review-card__name"><?php echo esc_html( (string) ( $item['name'] ?? '' ) ); ?></strong>
							<span class="flavor-review-card__role"><?php echo esc_html( (string) ( $item['role'] ?? __( 'مهمان رستوران', 'flavor' ) ) ); ?></span>
						</div>
					</footer>
				</blockquote>
			<?php endforeach; ?>
		</div>
	</div>
</section>
