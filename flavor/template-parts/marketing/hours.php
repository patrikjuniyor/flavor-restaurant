<?php
/**
 * Working Hours and Live Open/Closed Status section.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_hours_enable', 'yes' ) ) {
	return;
}

$phone   = get_theme_mod( 'flavor_phone', '02188001234' );
$address = get_theme_mod( 'flavor_address', 'تهران، خیابان ولیعصر، بالاتر از پارک‌وی' );
$hours   = get_theme_mod( 'flavor_hours', 'همه روزه: ۱۱:۳۰ تا ۲۳:۴۵' );
?>
<section class="flavor-section flavor-hours-loc" aria-label="<?php esc_attr_e( 'ساعات کاری و اطلاعات تماس', 'flavor' ); ?>">
	<div class="flavor-container">
		<div class="flavor-hours-card">
			<div class="flavor-hours-card__col">
				<div class="flavor-live-status flavor-live-status--open">
					<span class="flavor-live-status__dot"></span>
					<strong><?php esc_html_e( 'هم‌اکنون باز است', 'flavor' ); ?></strong>
					<span><?php esc_html_e( '— سفارش آنلاین و پذیرش سالن فعال است', 'flavor' ); ?></span>
				</div>

				<h3 class="flavor-hours-card__title"><?php esc_html_e( 'برنامه کاری رستوران', 'flavor' ); ?></h3>

				<ul class="flavor-schedule-list">
					<li>
						<span><?php esc_html_e( 'شنبه تا چهارشنبه:', 'flavor' ); ?></span>
						<strong><?php esc_html_e( '۱۱:۳۰ الی ۲۳:۳۰', 'flavor' ); ?></strong>
					</li>
					<li>
						<span><?php esc_html_e( 'پنج‌شنبه و جمعه:', 'flavor' ); ?></span>
						<strong><?php esc_html_e( '۱۱:۳۰ الی ۲۴:۰۰', 'flavor' ); ?></strong>
					</li>
					<li>
						<span><?php esc_html_e( 'سرویس بیرون‌بر و پیک:', 'flavor' ); ?></span>
						<strong><?php esc_html_e( 'بدون وقفه تا پایان ساعت کاری', 'flavor' ); ?></strong>
					</li>
				</ul>
			</div>

			<div class="flavor-hours-card__col flavor-hours-card__col--contact">
				<h3 class="flavor-hours-card__title"><?php esc_html_e( 'نشانی و راه‌های ارتباطی', 'flavor' ); ?></h3>
				
				<div class="flavor-contact-row">
					<div class="flavor-contact-row__icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
					</div>
					<div>
						<span class="flavor-contact-row__label"><?php esc_html_e( 'نشانی شعبه اصلی:', 'flavor' ); ?></span>
						<p class="flavor-contact-row__value"><?php echo esc_html( $address ); ?></p>
					</div>
				</div>

				<div class="flavor-contact-row">
					<div class="flavor-contact-row__icon">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
					</div>
					<div>
						<span class="flavor-contact-row__label"><?php esc_html_e( 'تلفن پشتیبانی و سفارش تلفنی:', 'flavor' ); ?></span>
						<a href="tel:<?php echo esc_attr( preg_replace( '/\D+/', '', $phone ) ); ?>" class="flavor-contact-row__link">
							<?php echo esc_html( $phone ); ?>
						</a>
					</div>
				</div>

				<div class="flavor-hours-card__actions">
					<a href="tel:<?php echo esc_attr( preg_replace( '/\D+/', '', $phone ) ); ?>" class="flavor-btn flavor-btn--primary">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
						<?php esc_html_e( 'تماس مستقیم', 'flavor' ); ?>
					</a>
					<?php $branches_page = get_page_by_path( 'branches' ); ?>
					<?php if ( $branches_page ) : ?>
						<a href="<?php echo esc_url( get_permalink( $branches_page ) ); ?>" class="flavor-btn flavor-btn--outline">
							<?php esc_html_e( 'مشاهده تمام شعبه‌ها', 'flavor' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</section>
