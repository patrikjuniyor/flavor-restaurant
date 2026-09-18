<?php
/**
 * Template tags and UI helpers for Flavor Theme.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Escape and print the site name.
 */
function flavor_site_name(): void {
	echo esc_html( get_bloginfo( 'name' ) );
}

/**
 * Primary navigation menu.
 */
function flavor_primary_nav(): void {
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'flavor-nav',
				'fallback_cb'    => false,
			)
		);
		return;
	}

	echo '<ul class="flavor-nav">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'خانه', 'flavor' ) . '</a></li>';
	$menu = get_page_by_path( 'menu' );
	if ( $menu ) {
		echo '<li><a href="' . esc_url( get_permalink( $menu ) ) . '">' . esc_html__( 'منوی غذا', 'flavor' ) . '</a></li>';
	}
	$res = get_page_by_path( 'reservation' );
	if ( $res ) {
		echo '<li><a href="' . esc_url( get_permalink( $res ) ) . '">' . esc_html__( 'رزرو میز', 'flavor' ) . '</a></li>';
	}
	$branches = get_page_by_path( 'branches' );
	if ( $branches ) {
		echo '<li><a href="' . esc_url( get_permalink( $branches ) ) . '">' . esc_html__( 'شعبه‌ها', 'flavor' ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Social media links with clean inline SVGs.
 */
function flavor_social_links(): void {
	$insta = get_theme_mod( 'flavor_social_instagram', '' );
	$tele  = get_theme_mod( 'flavor_social_telegram', '' );
	$wa    = get_theme_mod( 'flavor_social_whatsapp', '' );

	if ( ! $insta && ! $tele && ! $wa ) {
		return;
	}

	echo '<div class="flavor-social-links">';
	if ( $insta ) {
		echo '<a href="' . esc_url( $insta ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'اینستاگرام', 'flavor' ) . '">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>';
		echo '</a>';
	}
	if ( $tele ) {
		echo '<a href="' . esc_url( $tele ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'تلگرام', 'flavor' ) . '">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
		echo '</a>';
	}
	if ( $wa ) {
		echo '<a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr__( 'واتس‌اپ', 'flavor' ) . '">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
		echo '</a>';
	}
	echo '</div>';
}

/**
 * Sticky bottom action bar for mobile viewport.
 */
function flavor_mobile_bottom_bar(): void {
	$menu_page = get_page_by_path( 'menu' );
	$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );
	$res_page  = get_page_by_path( 'reservation' );
	$res_url   = $res_page ? get_permalink( $res_page ) : home_url( '/reservation/' );
	?>
	<nav class="flavor-mobile-bar" aria-label="<?php esc_attr_e( 'ناوبری سریع موبایل', 'flavor' ); ?>">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flavor-mobile-bar__item <?php echo is_front_page() ? 'is-active' : ''; ?>">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
			<span><?php esc_html_e( 'خانه', 'flavor' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $menu_url ); ?>" class="flavor-mobile-bar__item <?php echo is_page( 'menu' ) ? 'is-active' : ''; ?>">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
			<span><?php esc_html_e( 'منوی غذا', 'flavor' ); ?></span>
		</a>
		<a href="<?php echo esc_url( $res_url ); ?>" class="flavor-mobile-bar__item <?php echo is_page( 'reservation' ) ? 'is-active' : ''; ?>">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
			<span><?php esc_html_e( 'رزرو میز', 'flavor' ); ?></span>
		</a>
		<button type="button" class="flavor-mobile-bar__item" id="flavor-mobile-cart-btn" aria-label="<?php esc_attr_e( 'مشاهده سبد خرید', 'flavor' ); ?>">
			<div class="flavor-mobile-bar__cart-icon">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
				<span class="flavor-cart-badge" id="flavor-mobile-cart-count">۰</span>
			</div>
			<span><?php esc_html_e( 'سبد خرید', 'flavor' ); ?></span>
		</button>
	</nav>
	<?php
}
