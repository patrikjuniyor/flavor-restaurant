<?php
/**
 * Template helpers.
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
 * Primary navigation.
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
		echo '<li><a href="' . esc_url( get_permalink( $menu ) ) . '">' . esc_html__( 'منو', 'flavor' ) . '</a></li>';
	}
	echo '</ul>';
}
