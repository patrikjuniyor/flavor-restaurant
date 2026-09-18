<?php
/**
 * Special Offers and Promo Banner section.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_offers_enable', 'yes' ) ) {
	return;
}

$title = get_theme_mod( 'flavor_offers_title', __( '۱۵٪ تخفیف برای اولین سفارش آنلاین', 'flavor' ) );
$code  = get_theme_mod( 'flavor_offers_code', 'FIRST15' );

$menu_page = get_page_by_path( 'menu' );
$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );
?>
<section class="flavor-section flavor-offers" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="flavor-container">
		<div class="flavor-offer-banner">
			<div class="flavor-offer-banner__bg-pattern"></div>
			<div class="flavor-offer-banner__content">
				<span class="flavor-offer-banner__badge">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
					<?php esc_html_e( 'پیشنهاد طلایی این ماه', 'flavor' ); ?>
				</span>
				<h2 class="flavor-offer-banner__title"><?php echo esc_html( $title ); ?></h2>
				<p class="flavor-offer-banner__desc">
					<?php esc_html_e( 'با وارد کردن کد تخفیف زیر در سبد خرید، از تخفیف ویژه اولین سفارش خود بهره‌مند شوید.', 'flavor' ); ?>
				</p>

				<div class="flavor-offer-banner__actions">
					<div class="flavor-offer-coupon">
						<span class="flavor-offer-coupon__label"><?php esc_html_e( 'کد تخفیف:', 'flavor' ); ?></span>
						<strong class="flavor-offer-coupon__code" id="flavor-promo-code"><?php echo esc_html( $code ); ?></strong>
						<button type="button" class="flavor-offer-coupon__btn" onclick="navigator.clipboard.writeText('<?php echo esc_js( $code ); ?>'); this.textContent='<?php esc_attr_e( 'کپی شد!', 'flavor' ); ?>';">
							<?php esc_html_e( 'کپی کد', 'flavor' ); ?>
						</button>
					</div>

					<a href="<?php echo esc_url( $menu_url ); ?>" class="flavor-btn flavor-btn--primary flavor-btn--lg">
						<?php esc_html_e( 'سفارش آنلاین با تخفیف', 'flavor' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>
