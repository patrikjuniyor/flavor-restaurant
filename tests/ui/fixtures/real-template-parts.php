<?php
/**
 * Fixture-only template-part loader for the offline UI pages.
 *
 * The widget stubs (tests/theme/fixtures/elementor-stubs.php) replace
 * get_template_part with a placeholder because the contract tests only care
 * that widgets call it without fataling. The visual guard needs the opposite:
 * the real hero/about/gallery markup on real pixels. Because the stub guards
 * its definition with function_exists(), pre-defining this loader before the
 * stub loads wins for the fixture, and the contract tests (which never
 * include this file) keep their placeholder.
 *
 * Global namespace on purpose: widget files call `get_template_part()` as a
 * global function.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

/*
 * The parts assume singular context; the fixture has no queried post, so the
 * loop-ish core calls answer with their empty-state values. (Widgets render
 * their own content through $args regardless.)
 */
if ( ! function_exists( 'has_post_thumbnail' ) ) {
	/**
	 * No featured image offline.
	 *
	 * @param mixed $post Ignored.
	 * @return false
	 */
	function has_post_thumbnail( $post = null ) { // phpcs:ignore
		return false;
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	/**
	 * No loop id offline.
	 *
	 * @return int
	 */
	function get_the_ID() {
		return 0;
	}
}
if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
	/**
	 * No thumbnail url offline.
	 *
	 * @param mixed  $post   Ignored.
	 * @param string $size   Ignored.
	 * @return string
	 */
	function get_the_post_thumbnail_url( $post = null, $size = 'post-thumbnail' ) { // phpcs:ignore
		return '';
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Empty post result set (branch/gallery parts degrade to their empty states).
	 *
	 * @param array $args Ignored.
	 * @return array
	 */
	function get_posts( $args = array() ) { // phpcs:ignore
		return array();
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * Slug-safe filename.
	 *
	 * @param string $filename Raw.
	 * @return string
	 */
	function sanitize_file_name( $filename ) { // phpcs:ignore
		return strtolower( preg_replace( '/[^a-zA-Z0-9_.-]+/', '-', (string) $filename ) );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Slug.
	 *
	 * @param string $title Raw.
	 * @return string
	 */
	function sanitize_title( $title ) { // phpcs:ignore
		return strtolower( preg_replace( '/[^a-zA-Z0-9-]+/', '-', (string) $title ) );
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Append a query arg.
	 *
	 * @param mixed  $key   Key or array.
	 * @param mixed  $value Value.
	 * @param mixed  $url   Url.
	 * @return string
	 */
	function add_query_arg( $key, $value = false, $url = '' ) { // phpcs:ignore
		$url = (string) $url;
		$pair = is_array( $key ) ? http_build_query( $key ) : rawurlencode( (string) $key ) . ( false === $value ? '' : '=' . rawurlencode( (string) $value ) );
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $pair;
	}
}
if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Merge args.
	 *
	 * @param mixed $args     New.
	 * @param mixed $defaults Base.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) { // phpcs:ignore
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'attachment_url_to_postid' ) ) {
	/**
	 * No media database offline.
	 *
	 * @param string $url Ignored.
	 * @return int
	 */
	function attachment_url_to_postid( $url ) { // phpcs:ignore
		return 0;
	}
}
if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	/**
	 * No attachment markup offline.
	 *
	 * @param mixed  $id   Ignored.
	 * @param string $size Ignored.
	 * @return string
	 */
	function wp_get_attachment_image( $id, $size = 'thumbnail', $icon = false, $attr = array() ) { // phpcs:ignore
		return '';
	}
}

if ( ! function_exists( 'wpautop' ) ) {
	/**
	 * Paragraph-wrap the fixture text.
	 *
	 * @param string $text Raw.
	 * @return string
	 */
	function wpautop( $text, $br = true ) { // phpcs:ignore
		$out = '';
		foreach ( preg_split( '/\n\s*\n/', trim( (string) $text ) ) as $para ) {
			$para = trim( $para );
			if ( '' === $para ) { continue; }
			$out .= '<p>' . ( $br ? nl2br( esc_html( $para ) ) : esc_html( $para ) ) . "</p>\n";
		}
		return $out;
	}
}

if ( ! function_exists( 'get_template_part' ) ) {
	/**
	 * Include the real template part, with $args in scope like WP ≥ 5.5.
	 *
	 * @param string      $slug Slug (relative to the theme root).
	 * @param string|null $name Name.
	 * @param array       $args Args.
	 */
	function get_template_part( string $slug, ?string $name = null, array $args = array() ): void {
		$file = FLAVOR_DIR . '/' . $slug . ( null !== $name && '' !== $name ? '-' . $name : '' ) . '.php';
		if ( is_readable( $file ) ) {
			include $file;
			return;
		}
		echo '<div class="flavor-template-part" data-slug="' . htmlspecialchars( $slug, ENT_QUOTES ) . '"></div>';
	}
}
