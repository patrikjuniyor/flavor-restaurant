<?php
/**
 * Plugin settings stored in a single option array.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

use FlavorCore\Activator;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	public const OPTION = 'flavor_core_settings';

	/**
	 * Hook registration.
	 */
	public function hooks(): void {
		add_filter( 'flavor_core_get_setting', array( $this, 'filter_get' ), 10, 2 );
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, Activator::default_settings() );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		if ( ! array_key_exists( $key, $all ) ) {
			return $default;
		}
		return apply_filters( 'flavor_core_setting', $all[ $key ], $key );
	}

	/**
	 * Persist a subset of settings.
	 *
	 * @param array<string, mixed> $values Values to merge.
	 */
	public static function update( array $values ): void {
		$merged = array_merge( self::all(), $values );
		update_option( self::OPTION, $merged, false );
	}

	/**
	 * Filter callback used by apply_filters( 'flavor_core_get_setting' ).
	 *
	 * @param mixed  $value Current.
	 * @param string $key   Key.
	 * @return mixed
	 */
	public function filter_get( $value, string $key ) {
		return self::get( $key, $value );
	}

	/**
	 * Cache-busting menu version for a branch. Bump on availability / price override.
	 */
	public static function menu_version( int $branch_id ): int {
		return (int) get_option( 'flavor_core_menu_version_' . $branch_id, 1 );
	}

	/**
	 * Increment menu version.
	 */
	public static function bump_menu_version( int $branch_id ): int {
		$key     = 'flavor_core_menu_version_' . $branch_id;
		$current = (int) get_option( $key, 1 );
		$next    = $current + 1;
		update_option( $key, $next, false );
		clean_option_cache( $key );
		return $next;
	}
}
