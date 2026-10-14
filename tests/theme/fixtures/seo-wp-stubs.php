<?php
/**
 * Global WP query/template conditionals for offline SEO tests.
 */

if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool {
		return true;
	}
}
if ( ! function_exists( 'is_home' ) ) {
	function is_home(): bool {
		return false;
	}
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ): bool {
		return false;
	}
}
if ( ! function_exists( 'is_category' ) ) {
	function is_category(): bool {
		return false;
	}
}
if ( ! function_exists( 'is_tag' ) ) {
	function is_tag(): bool {
		return false;
	}
}
if ( ! function_exists( 'is_tax' ) ) {
	function is_tax(): bool {
		return false;
	}
}
if ( ! function_exists( 'is_search' ) ) {
	function is_search(): bool {
		return false;
	}
}
if ( ! function_exists( 'get_search_query' ) ) {
	function get_search_query(): string {
		return '';
	}
}
if ( ! function_exists( 'get_search_link' ) ) {
	function get_search_link(): string {
		return home_url( '/?s=' );
	}
}
if ( ! function_exists( 'wp_get_document_title' ) ) {
	function wp_get_document_title(): string {
		return 'Flavor Restaurant';
	}
}
if ( ! function_exists( 'is_page_template' ) ) {
	function is_page_template( $template = '' ): bool {
		return false;
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $url = '' ): string {
		if ( empty( $url ) ) {
			$url = 'https://example.com/';
		}
		$keys = (array) $keys;
		$parts = parse_url( $url );
		if ( empty( $parts['query'] ) ) {
			return $url;
		}
		parse_str( $parts['query'], $query_args );
		foreach ( $keys as $k ) {
			unset( $query_args[ $k ] );
		}
		$new_query = http_build_query( $query_args );
		$new_url = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.com' ) . ( $parts['path'] ?? '/' );
		if ( ! empty( $new_query ) ) {
			$new_url .= '?' . $new_query;
		}
		return $new_url;
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $filename ): string {
		return preg_replace( '/[^a-zA-Z0-9_\.-]/', '', $filename );
	}
}

if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists( string $post_type ): bool {
		return in_array( $post_type, array( 'post', 'page', 'product', 'branch' ), true );
	}
}

if ( ! function_exists( 'set_theme_mod' ) ) {
	function set_theme_mod( string $key, $value ): void {
		$GLOBALS['_mock_theme_mods'][ $key ] = $value;
	}
}

if ( ! function_exists( 'get_theme_mod' ) ) {
	function get_theme_mod( string $key, $default = false ) {
		return $GLOBALS['_mock_theme_mods'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id(): int {
		return $GLOBALS['_mock_queried_object_id'] ?? 1;
	}
}

if ( ! function_exists( 'get_comments' ) ) {
	function get_comments( $args = array() ) {
		return $GLOBALS['_mock_approved_reviews'] ?? array();
	}
}

if ( ! function_exists( 'get_comment_meta' ) ) {
	function get_comment_meta( $comment_id, $key = '', $single = false ) {
		return $GLOBALS['_mock_comment_meta'][ $comment_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'has_post_thumbnail' ) ) {
	function has_post_thumbnail( $post = null ): bool {
		return false;
	}
}

if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
	function get_the_post_thumbnail_url( $post = null, $size = 'post-thumbnail' ) {
		return false;
	}
}
