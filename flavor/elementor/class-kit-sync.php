<?php
/**
 * Pushes Flavor design tokens into the active Elementor Kit (Global Colors
 * and Global Fonts), so widgets built in Elementor match the theme.
 *
 * Safety rule: the last value this class wrote for each key is remembered.
 * If someone later edits that global in Elementor's Site Settings, the
 * edit is kept and the key is reported as skipped instead of overwritten.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Kit_Sync
 */
class Kit_Sync {

	const OPTION = 'flavor_elementor_kit_sync_state';

	/**
	 * Hooks. Only acts when Elementor is loaded.
	 */
	public static function init(): void {
		add_action( 'customize_save_after', array( self::class, 'on_customizer_save' ) );
	}

	/**
	 * @return void
	 */
	public static function on_customizer_save(): void {
		self::sync();
	}

	/**
	 * Mapping from Elementor kit item to Flavor token.
	 *
	 * @return array{colors: array<string,string>, custom: array<string,string>, fonts: array<string,string>}
	 */
	public static function map(): array {
		return array(
			// system_colors: _id => token.
			'colors' => array(
				'primary'   => 'primary',
				'secondary' => 'secondary',
				'text'      => 'ink',
				'accent'    => 'accent',
			),
			// custom_colors: _id => token.
			'custom' => array(
				'flavor-bg'           => 'bg',
				'flavor-surface'      => 'surface',
				'flavor-surface-alt'  => 'surface_alt',
				'flavor-muted'        => 'muted',
				'flavor-line'         => 'line',
			),
			// system_typography: _id => token.
			'fonts'  => array(
				'primary' => 'font_heading',
				'text'    => 'font_body',
			),
		);
	}

	/**
	 * Whether Elementor's kit API is available.
	 */
	public static function available(): bool {
		return class_exists( '\Elementor\Plugin' )
			&& isset( \Elementor\Plugin::instance()->kits_manager )
			&& method_exists( \Elementor\Plugin::instance()->kits_manager, 'get_active_kit' );
	}

	/**
	 * Sync tokens into the active kit.
	 *
	 * @param bool $force Overwrite keys even when edited in Elementor.
	 * @return array{status: string, written?: string[], skipped?: string[]}
	 */
	public static function sync( bool $force = false ): array {
		if ( ! self::available() ) {
			return array( 'status' => 'elementor_inactive' );
		}

		$kit = \Elementor\Plugin::instance()->kits_manager->get_active_kit();
		if ( ! $kit || ! $kit->get_id() ) {
			return array( 'status' => 'no_kit' );
		}

		$tokens   = \Flavor\Design::resolved();
		$state    = get_option( self::OPTION, array() );
		$state    = is_array( $state ) ? $state : array();
		$map      = self::map();
		// Compare against what is stored, not the per-request settings cache:
		// the cache can hold values from before a user edit in Elementor.
		$stored   = get_post_meta( $kit->get_id(), '_elementor_page_settings', true );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = $kit->get_settings();
		$written  = array();
		$skipped  = array();
		$changed  = false;

		// Each entry: [ setting key, item id, value ].
		$targets = array();
		foreach ( $map['colors'] as $id => $token ) {
			$targets[] = array( 'system_colors', $id, 'color', $tokens[ $token ] ?? '' );
		}
		foreach ( $map['custom'] as $id => $token ) {
			$targets[] = array( 'custom_colors', $id, 'color', $tokens[ $token ] ?? '' );
		}
		foreach ( $map['fonts'] as $id => $token ) {
			$targets[] = array( 'system_typography', $id, 'typography_font_family', $tokens[ $token ] ?? '' );
		}

		foreach ( $targets as [ $group, $id, $field, $value ] ) {
			if ( '' === $value ) {
				continue;
			}
			$key = $group . '.' . $id . '.' . $field;
			$idx     = self::find_index( $stored[ $group ] ?? array(), $id );
			$current = null === $idx ? null : ( $stored[ $group ][ $idx ][ $field ] ?? null );
			$write_idx = self::find_index( $settings[ $group ] ?? array(), $id );
			if ( null === $write_idx ) {
				// Only custom colours are ours to add: they carry the
				// flavor-* ids and exist nowhere else in the kit.
				if ( 'custom_colors' !== $group ) {
					continue;
				}
				$settings[ $group ][] = array(
					'_id'   => $id,
					'title' => self::title_for( $id ),
					'color' => '',
				);
				$write_idx            = count( $settings[ $group ] ) - 1;
			}

			// A system item the kit no longer has is not re-created.
			if ( null === $idx && 'custom_colors' !== $group ) {
				continue;
			}

			if ( $current === $value ) {
				$state[ $key ] = $value;
				continue;
			}

			$user_edited = array_key_exists( $key, $state ) && $state[ $key ] !== $current;
			if ( $user_edited && ! $force ) {
				$skipped[] = $key;
				continue;
			}

			$settings[ $group ][ $write_idx ][ $field ] = $value;
			$state[ $key ]                        = $value;
			$written[]                            = $key;
			$changed                              = true;
		}

		if ( $changed ) {
			// Document::save() returns false when the current user may not
			// edit the kit. Nothing was written then, so do not record state.
			if ( ! $kit->save( array( 'settings' => $settings ) ) ) {
				return array( 'status' => 'save_failed' );
			}
			if ( isset( \Elementor\Plugin::instance()->files_manager ) ) {
				\Elementor\Plugin::instance()->files_manager->clear_cache();
			}
		}

		update_option( self::OPTION, $state, false );

		return array(
			'status'  => 'ok',
			'written' => $written,
			'skipped' => $skipped,
		);
	}

	/**
	 * Human label for a custom colour id.
	 *
	 * @param string $id Item id.
	 */
	private static function title_for( string $id ): string {
		$titles = array(
			'flavor-bg'          => 'Flavor Background',
			'flavor-surface'     => 'Flavor Surface',
			'flavor-surface-alt' => 'Flavor Surface Alt',
			'flavor-muted'       => 'Flavor Muted',
			'flavor-line'        => 'Flavor Line',
		);
		return $titles[ $id ] ?? $id;
	}

	/**
	 * @param array<int,array<string,mixed>> $items Repeater rows.
	 * @param string                         $id    Item _id.
	 * @return int|null
	 */
	private static function find_index( array $items, string $id ): ?int {
		foreach ( $items as $i => $item ) {
			if ( isset( $item['_id'] ) && $item['_id'] === $id ) {
				return (int) $i;
			}
		}
		return null;
	}
}
