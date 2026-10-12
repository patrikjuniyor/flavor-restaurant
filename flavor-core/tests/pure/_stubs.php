<?php
/**
 * Micro-shims for the aggregated pure suites (prompt 47).
 *
 * The suites test pure logic — money/points/discount math, crypto, retry
 * arithmetic, allowlists — without WordPress. Only the handful of WP symbols
 * those code paths touch at runtime are shimmed here, each behind an
 * exists-guard so this file stays inert inside a real WordPress boot or the
 * e2e mock environment. Nothing here emulates behaviour: get_option returns
 * the caller's default (so Settings falls back to Activator's pure
 * default_settings()), apply_filters is pass-through, __() is identity.
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput -- CLI harness shims, not an HTTP response.

if ( ! function_exists( '__' ) ) {
	/**
	 * Identity translation shim.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Pass-through filter shim.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		unset( $hook, $args );
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Option shim: the caller's default, always — settings fall back to
	 * Activator::default_settings() exactly as on a fresh install.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $name, $default = false ) {
		unset( $name );
		return $default;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Args merge shim (defaults overridden by args).
	 *
	 * @param mixed $args     Args.
	 * @param mixed $defaults Defaults.
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON shim.
	 *
	 * @param mixed $data    Data.
	 * @param int   $options Options.
	 * @param int   $depth   Depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Current-user shim: the pure suites run as nobody (0), exactly like a
	 * guest checkout — which is the interesting idempotency case anyway.
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return 0;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error carrying code/message/data — the three accessors the
	 * pure surfaces use.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		protected $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		protected $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		protected $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = $data;
		}

		/**
		 * Code accessor.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Message accessor.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}

		/**
		 * Data accessor.
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * WP_Error check shim.
	 *
	 * @param mixed $thing Thing.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
