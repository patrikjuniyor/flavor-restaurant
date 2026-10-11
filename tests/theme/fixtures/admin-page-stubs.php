<?php
/**
 * WordPress admin doubles for the launch-center kit-sync section.
 *
 * The mock environment models the front end, so the handful of admin-only
 * functions the section touches are supplied here. Like the other fixture files
 * the global ones sit in the global namespace, because Flavor\Launch_Center
 * calls admin_url() and wp_nonce_field() unqualified from namespace Flavor.
 *
 * $GLOBALS['_a_elementor'] decides whether Elementor's kit API looks live: the
 * stub Plugin returns an object with no kits manager when it is off, which is
 * exactly how Kit_Sync::available() reads a site without Elementor.
 *
 * @package Flavor
 */

namespace {

	$GLOBALS['_a_elementor']    = ! empty( $GLOBALS['_a_elementor'] );
	$GLOBALS['_a_nonce_fields'] = array();

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( string $path = '' ): string {
			return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'wp_nonce_field' ) ) {
		function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $echo = true ): string {
			$GLOBALS['_a_nonce_fields'][] = array( 'action' => $action, 'name' => $name );
			$field                        = '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce-value" />';
			if ( $echo ) {
				echo $field; // phpcs:ignore WordPress.Security.EscapeOutput -- the value is escaped above.
			}

			return $field;
		}
	}

	if ( ! function_exists( 'check_admin_referer' ) ) {
		function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): bool {
			$GLOBALS['_a_nonce_checked'][] = array( 'action' => $action, 'name' => $query_arg );
			return true;
		}
	}

}

namespace Elementor {

	if ( ! class_exists( Kits_Manager_Stub::class ) ) {
		/**
		 * Enough of a kits manager for Kit_Sync::available() to be satisfied.
		 */
		class Kits_Manager_Stub {
			/**
			 * @return object
			 */
			public function get_active_kit() {
				return new \stdClass();
			}
		}
	}

	if ( ! class_exists( Plugin::class ) ) {
		/**
		 * Minimal stand-in for Elementor's plugin singleton.
		 */
		class Plugin {
			/** @var mixed */
			public $kits_manager;

			/**
			 * @return object
			 */
			public static function instance() {
				if ( ! empty( $GLOBALS['_a_elementor'] ) ) {
					$plugin               = new self();
					$plugin->kits_manager = new Kits_Manager_Stub();
					return $plugin;
				}

				return new \stdClass();
			}
		}
	}

}
