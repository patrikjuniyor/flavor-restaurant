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
 * Cut a string to a number of characters, without depending on mbstring.
 *
 * WordPress loads a polyfill for mb_substr() in wp-includes/compat.php, so
 * calling it directly works today. But that is an invisible dependency: the
 * theme would break on any load path that skips the polyfill, and the
 * failure mode is a fatal error rather than a wrong character count.
 *
 * Persian and Arabic are multi-byte, so a byte-based substr() would slice a
 * character in half and emit a replacement character. This falls back to
 * splitting on UTF-8 code points, which is slower but correct.
 *
 * @param string $text   Input text.
 * @param int    $start  Start offset, in characters.
 * @param int    $length Maximum characters to return; 0 means the rest.
 * @return string
 */
function flavor_substr( string $text, int $start = 0, int $length = 0 ): string {
	if ( function_exists( 'mb_substr' ) ) {
		return 0 === $length
			? (string) mb_substr( $text, $start, null, 'UTF-8' )
			: (string) mb_substr( $text, $start, $length, 'UTF-8' );
	}

	return flavor_substr_fallback( $text, $start, $length );
}

/**
 * The no-mbstring path, split out so it can be tested.
 *
 * A fallback that only runs on machines missing mbstring is a fallback
 * nobody ever exercises. Making it callable on its own lets the tests run
 * both paths over the same fixtures and assert they agree.
 *
 * @param string $text   Input text.
 * @param int    $start  Start offset, in characters.
 * @param int    $length Maximum characters; 0 means the rest.
 * @return string
 */
function flavor_substr_fallback( string $text, int $start = 0, int $length = 0 ): string {
	// preg_split with the /u modifier yields whole UTF-8 code points.
	$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );

	if ( false === $chars ) {
		// Not valid UTF-8: fall back to bytes rather than returning nothing.
		return 0 === $length ? (string) substr( $text, $start ) : (string) substr( $text, $start, $length );
	}

	$slice = 0 === $length ? array_slice( $chars, $start ) : array_slice( $chars, $start, $length );

	return implode( '', $slice );
}

/**
 * Resolve a media-library attachment ID from a stored image URL.
 *
 * Theme mods such as `flavor_hero_image` store a plain URL, which means the
 * markup loses srcset/width/height and every phone downloads the full-size
 * original. Looking the attachment back up restores responsive delivery.
 * The lookup hits the DB, so the result is cached per URL.
 *
 * @param string $url Image URL.
 * @return int Attachment ID, or 0 when the URL is not in the media library.
 */
function flavor_attachment_id_from_url( string $url ): int {
	$url = trim( $url );

	if ( '' === $url ) {
		return 0;
	}

	$key    = 'flavor_img_id_' . md5( $url );
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	$id = attachment_url_to_postid( $url );

	if ( ! $id ) {
		// A resized URL (…-1600x900.jpg) never matches; try the original.
		$original = preg_replace( '/-\d+x\d+(\.[a-zA-Z0-9]+)$/', '$1', $url );
		if ( $original && $original !== $url ) {
			$id = attachment_url_to_postid( $original );
		}
	}

	set_transient( $key, (int) $id, DAY_IN_SECONDS );

	return (int) $id;
}

/**
 * Render an image that stays responsive whatever the source is.
 *
 * Falls back to a plain tag for files shipped with the theme (demo art),
 * which are not attachments and therefore have no srcset.
 *
 * @param string               $url  Image URL.
 * @param string               $size Registered image size for attachments.
 * @param array<string, mixed> $attr Tag attributes. `width`/`height` are used
 *                                   by the fallback to reserve layout space.
 * @return string Escaped <img> markup, or an empty string.
 */
function flavor_responsive_image( string $url, string $size = 'full', array $attr = array() ): string {
	if ( '' === trim( $url ) ) {
		return '';
	}

	$id = flavor_attachment_id_from_url( $url );

	if ( $id ) {
		// Core derives the real intrinsic width/height from the attachment,
		// so the placeholder pair must not be passed through as well.
		$attachment_attr = $attr;
		unset( $attachment_attr['width'], $attachment_attr['height'] );

		$image = wp_get_attachment_image( $id, $size, false, $attachment_attr );
		if ( $image ) {
			return $image;
		}
	}

	$attr['src'] = $url;
	unset( $attr['sizes'] );

	if ( ! isset( $attr['alt'] ) ) {
		$attr['alt'] = '';
	}

	$html = '<img';
	foreach ( $attr as $name => $value ) {
		if ( false === $value || null === $value ) {
			continue;
		}
		$value = 'src' === $name ? esc_url( (string) $value ) : esc_attr( (string) $value );
		$html .= ' ' . esc_attr( $name ) . '="' . $value . '"';
	}

	return $html . ' />';
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
	get_template_part( 'template-parts/ui/mobile-nav' );
}
