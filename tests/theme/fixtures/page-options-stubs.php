<?php
/**
 * Stand-ins needed to exercise the per-page options metabox offline.
 *
 * @package Flavor
 */

namespace {
	if ( ! function_exists( 'get_the_ID' ) ) {
		/**
		 * Current post ID.
		 */
		function get_the_ID() {
			return (int) ( $GLOBALS['_mock_current_post_id'] ?? 0 );
		}
	}

	if ( ! function_exists( 'add_meta_box' ) ) {
		/**
		 * Record metabox registrations instead of printing them.
		 *
		 * @param string           $id     Box ID.
		 * @param string           $title  Title.
		 * @param callable         $cb     Callback.
		 * @param string|array     $screen Screens.
		 * @param string           $ctx    Context.
		 * @param string           $prio   Priority.
		 */
		function add_meta_box( string $id, string $title, callable $cb, $screen = null, string $ctx = 'advanced', string $prio = 'default' ): void {
			$GLOBALS['_mock_meta_boxes'][ $id ] = array(
				'id'     => $id,
				'title'  => $title,
				'screen' => $screen,
			);
		}
	}

	if ( ! function_exists( 'wp_nonce_field' ) ) {
		/**
		 * Emit a hidden nonce input, like core.
		 *
		 * @param string $action Action.
		 * @param string $name   Field name.
		 */
		function wp_nonce_field( $action = -1, string $name = '_wpnonce' ): void {
			printf(
				'<input type="hidden" id="%1$s" name="%1$s" value="%2$s" />',
				esc_attr( $name ),
				esc_attr( md5( $action ) )
			);
		}
	}

	if ( ! function_exists( 'selected' ) ) {
		/**
		 * Mark the selected option, like core.
		 *
		 * @param mixed $selected Value to compare.
		 * @param mixed $current  Current value.
		 * @param bool  $echo     Echo or return.
		 * @return string
		 */
		function selected( $selected, $current = true, bool $echo = true ) {
			$result = (string) $selected === (string) $current ? " selected='selected'" : '';
			if ( $echo ) {
				echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute.
			}
			return $result;
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		/**
		 * Identity stand-in: fixtures carry no slashed data.
		 *
		 * @param mixed $value Value.
		 * @return mixed
		 */
		function wp_unslash( $value ) {
			return $value;
		}
	}
}
