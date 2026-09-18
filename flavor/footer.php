<?php
/**
 * Footer template.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

$about   = get_theme_mod( 'flavor_about', '' );
$phone   = get_theme_mod( 'flavor_phone', '02188001234' );
$address = get_theme_mod( 'flavor_address', 'تهران، خیابان ولیعصر، بالاتر از پارک‌وی' );
$copy    = get_theme_mod( 'flavor_footer_copy', '' );

$menu_page = get_page_by_path( 'menu' );
$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );
$res_page  = get_page_by_path( 'reservation' );
$res_url   = $res_page ? get_permalink( $res_page ) : home_url( '/reservation/' );
$b_page    = get_page_by_path( 'branches' );
$b_url     = $b_page ? get_permalink( $b_page ) : home_url( '/branches/' );
?>
</main><!-- #main -->

<footer class="flavor-footer">
	<div class="flavor-container flavor-footer__inner">
		<div class="flavor-footer__grid">
			<!-- Col 1: Brand & Bio -->
			<div class="flavor-footer__col flavor-footer__col--brand">
				<div class="flavor-footer__brand">
					<?php if ( has_custom_logo() ) : ?>
						<?php the_custom_logo(); ?>
					<?php else : ?>
						<h3 class="flavor-footer__brand-name"><?php flavor_site_name(); ?></h3>
					<?php endif; ?>
				</div>
				<p class="flavor-footer__bio">
					<?php echo esc_html( $about ? wp_trim_words( wp_strip_all_tags( $about ), 24 ) : __( 'تجربه طعم اصیل و فراموش‌نشدنی با تازه‌ترین مواد اولیه و میزبانی صمیمانه در محیطی آرام و دلپذیر.', 'flavor' ) ); ?>
				</p>
				<?php flavor_social_links(); ?>
			</div>

			<!-- Col 2: Quick Links -->
			<div class="flavor-footer__col">
				<h4 class="flavor-footer__heading"><?php esc_html_e( 'دسترسی سریع', 'flavor' ); ?></h4>
				<ul class="flavor-footer__links">
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'صفحه اصلی', 'flavor' ); ?></a></li>
					<li><a href="<?php echo esc_url( $menu_url ); ?>"><?php esc_html_e( 'منوی سفارش آنلاین', 'flavor' ); ?></a></li>
					<li><a href="<?php echo esc_url( $res_url ); ?>"><?php esc_html_e( 'رزرو اینترنتی میز', 'flavor' ); ?></a></li>
					<li><a href="<?php echo esc_url( $b_url ); ?>"><?php esc_html_e( 'شعبه‌ها و ساعات کاری', 'flavor' ); ?></a></li>
				</ul>
			</div>

			<!-- Col 3: Working Hours -->
			<div class="flavor-footer__col">
				<h4 class="flavor-footer__heading"><?php esc_html_e( 'ساعات پذیرایی و ارسال', 'flavor' ); ?></h4>
				<ul class="flavor-footer__schedule">
					<li>
						<span><?php esc_html_e( 'شنبه تا چهارشنبه:', 'flavor' ); ?></span>
						<strong><?php esc_html_e( '۱۱:۳۰ الی ۲۳:۳۰', 'flavor' ); ?></strong>
					</li>
					<li>
						<span><?php esc_html_e( 'پنج‌شنبه و جمعه:', 'flavor' ); ?></span>
						<strong><?php esc_html_e( '۱۱:۳۰ الی ۲۴:۰۰', 'flavor' ); ?></strong>
					</li>
					<li>
						<span><?php esc_html_e( 'سفارش آنلاین:', 'flavor' ); ?></span>
						<strong><?php esc_html_e( 'پذیرش پیوسته', 'flavor' ); ?></strong>
					</li>
				</ul>
			</div>

			<!-- Col 4: Contact & Hotline -->
			<div class="flavor-footer__col">
				<h4 class="flavor-footer__heading"><?php esc_html_e( 'اطلاعات تماس', 'flavor' ); ?></h4>
				<div class="flavor-footer__contact-item">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
					<span><?php echo esc_html( $address ); ?></span>
				</div>
				<div class="flavor-footer__contact-item">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
					<a href="tel:<?php echo esc_attr( preg_replace( '/\D+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
				</div>
			</div>
		</div>

		<!-- Footer Bottom Copyright -->
		<div class="flavor-footer__bottom">
			<p class="flavor-footer__copy">
				<?php if ( $copy ) : ?>
					<?php echo esc_html( $copy ); ?>
				<?php else : ?>
					&copy; <?php echo esc_html( (string) wp_date( 'Y' ) ); ?> <?php flavor_site_name(); ?>. <?php esc_html_e( 'تمامی حقوق محفوظ است.', 'flavor' ); ?>
				<?php endif; ?>
			</p>
			<span class="flavor-footer__powered">
				<?php esc_html_e( 'قدرت گرفته از پلتفرم رستوران مستقیم Flavor', 'flavor' ); ?>
			</span>
		</div>
	</div>
</footer>

<!-- Mobile Bottom Navigation Bar -->
<?php flavor_mobile_bottom_bar(); ?>

<?php wp_footer(); ?>
</body>
</html>
