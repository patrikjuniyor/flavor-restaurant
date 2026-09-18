<?php
/**
 * Introduction and Highlights section.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_intro_enable', 'yes' ) ) {
	return;
}

$title = get_theme_mod( 'flavor_intro_title', __( 'چرا مهمان‌ها ما را انتخاب می‌کنند؟', 'flavor' ) );
?>
<section class="flavor-section flavor-intro" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="flavor-container">
		<div class="flavor-section-header">
			<span class="flavor-section-header__tag"><?php esc_html_e( 'تمایز ما', 'flavor' ); ?></span>
			<h2 class="flavor-section-header__title"><?php echo esc_html( $title ); ?></h2>
			<p class="flavor-section-header__desc"><?php esc_html_e( 'تعهد به بالاترین استانداردهای بهداشت، مواد تازه و طبخ اصیل با عشق و احترام به ذائقه شما', 'flavor' ); ?></p>
		</div>

		<div class="flavor-intro__grid">
			<div class="flavor-intro__card">
				<div class="flavor-intro__icon" style="background: rgba(22, 163, 74, 0.1); color: #16a34a;">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
				</div>
				<h3><?php esc_html_e( 'مواد اولیه تازه و ارگانیک', 'flavor' ); ?></h3>
				<p><?php esc_html_e( 'تهیه روزانه گوشت گرم، سبزیجات تازه و برنج خالص ایرانی بدون افزودنی‌های غیرمجاز.', 'flavor' ); ?></p>
			</div>

			<div class="flavor-intro__card">
				<div class="flavor-intro__icon" style="background: rgba(234, 88, 12, 0.1); color: #ea580c;">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
				</div>
				<h3><?php esc_html_e( 'پخت آنی پس از ثبت سفارش', 'flavor' ); ?></h3>
				<p><?php esc_html_e( 'غذاها به صورت زنده و اختصاصی پس از سفارش شما آماده و مستقیماً از روی حرارت سرو می‌شوند.', 'flavor' ); ?></p>
			</div>

			<div class="flavor-intro__card">
				<div class="flavor-intro__icon" style="background: rgba(2, 132, 199, 0.1); color: #0284c7;">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
				</div>
				<h3><?php esc_html_e( 'ناوگان ارسال اختصاصی', 'flavor' ); ?></h3>
				<p><?php esc_html_e( 'پیک‌های مجهز به باکس حرارتی جهت تحویل غذا در سریع‌ترین زمان و با دمای ایده‌آل.', 'flavor' ); ?></p>
			</div>

			<div class="flavor-intro__card">
				<div class="flavor-intro__icon" style="background: rgba(138, 90, 43, 0.1); color: #8a5a2b;">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
				</div>
				<h3><?php esc_html_e( 'رزرو میز و فضای آرام', 'flavor' ); ?></h3>
				<p><?php esc_html_e( 'امکان رزرو آسان سالن برای جشن‌ها، دورهمی‌ها و قرارهای کاری با پذیرایی اختصاصی.', 'flavor' ); ?></p>
			</div>
		</div>
	</div>
</section>
