<?php
/**
 * Table Reservation CTA section.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_res_enable', 'yes' ) ) {
	return;
}

$title    = get_theme_mod( 'flavor_res_title', __( 'میز خود را در محیطی دنج و آرام رزرو کنید', 'flavor' ) );
$res_page = get_page_by_path( 'reservation' );
$url      = $res_page ? get_permalink( $res_page ) : home_url( '/reservation/' );
$skin     = \Flavor\Design::current_skin();
$hero_img = FLAVOR_URI . '/demos/' . $skin . '/hero.jpg';
?>
<section class="flavor-section flavor-res-cta" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="flavor-container">
		<div class="flavor-res-banner">
			<div class="flavor-res-banner__content">
				<span class="flavor-res-banner__tag"><?php esc_html_e( 'پذیرایی اختصاصی', 'flavor' ); ?></span>
				<h2 class="flavor-res-banner__title"><?php echo esc_html( $title ); ?></h2>
				<p class="flavor-res-banner__desc">
					<?php esc_html_e( 'برای دورهمی‌های خانوادگی، جلسات کاری و جشن‌های خاطره‌انگیز، میز و بخش دلخواه خود را به سادگی و بدون هزینه آنلاین رزرو نمایید.', 'flavor' ); ?>
				</p>

				<div class="flavor-res-banner__perks">
					<div class="flavor-res-banner__perk">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
						<span><?php esc_html_e( 'تایید آنی با پیامک', 'flavor' ); ?></span>
					</div>
					<div class="flavor-res-banner__perk">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
						<span><?php esc_html_e( 'انتخاب بخش سالن یا فضای باز', 'flavor' ); ?></span>
					</div>
					<div class="flavor-res-banner__perk">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
						<span><?php esc_html_e( 'پشتیبانی و امکان هماهنگی پیش‌غذا', 'flavor' ); ?></span>
					</div>
				</div>

				<div class="flavor-res-banner__action">
					<a href="<?php echo esc_url( $url ); ?>" class="flavor-btn flavor-btn--primary flavor-btn--lg">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
						<?php esc_html_e( 'مشاهده تقویم و رزرو میز', 'flavor' ); ?>
					</a>
				</div>
			</div>

			<div class="flavor-res-banner__media">
				<img src="<?php echo esc_url( $hero_img ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
			</div>
		</div>
	</div>
</section>
