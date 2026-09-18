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

$items = $args['items'] ?? array(
	array(
		'name'   => 'سارا محمدی',
		'role'   => 'مشتری وفادار',
		'rating' => 5,
		'text'   => 'کیفیت غذاها بی‌نظیر بود، سفارش با بسته‌بندی کاملاً گرم و به موقع تحویل داده شد. به شدت کوبیده مخصوص را پیشنهاد می‌کنم.',
	),
	array(
		'name'   => 'کیان رضایی',
		'role'   => 'مهمان سالن',
		'rating' => 5,
		'text'   => 'فضای سالن فوق‌العاده آرام و دلنشین است. برخورد پرسنل عالی و سرعت آماده‌سازی سفارش با QR کد سر میز بسیار راحت و مدرن بود.',
	),
	array(
		'name'   => 'مریم شفیعی',
		'role'   => 'سفارش آنلاین',
		'rating' => 5,
		'text'   => 'برای مهمانی خانوادگی سفارش دادیم؛ همه مهمان‌ها از طعم اصیل و تازگی سالادها و پیش‌غذاها تعریف کردند. ممنون از تیم حرفه‌ای‌تان.',
	),
);
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
							<?php echo esc_html( mb_substr( (string) ( $item['name'] ?? 'م' ), 0, 1, 'UTF-8' ) ); ?>
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
