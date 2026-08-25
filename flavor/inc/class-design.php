<?php
/**
 * Design tokens for the 8 Flavor skins.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Design
 */
class Design {

	/**
	 * Skin slugs and labels.
	 *
	 * @return array<string, string>
	 */
	public static function skins(): array {
		return array(
			'modern-cafe'   => __( 'کافه مدرن', 'flavor' ),
			'fast-food'     => __( 'فست‌فود', 'flavor' ),
			'traditional'   => __( 'رستوران سنتی', 'flavor' ),
			'fine-dining'   => __( 'رستوران لوکس', 'flavor' ),
			'pastry'        => __( 'شیرینی‌فروشی', 'flavor' ),
			'juice-bar'     => __( 'آبمیوه و اسموتی', 'flavor' ),
			'catering'      => __( 'کترینگ', 'flavor' ),
			'cloud-kitchen' => __( 'آشپزخانه ابری', 'flavor' ),
		);
	}

	/**
	 * Default CSS variables per skin.
	 *
	 * @return array<string, string>
	 */
	public static function tokens( string $skin ): array {
		$map = array(
			'modern-cafe'   => array(
				'bg'      => '#f6f1ea',
				'surface' => '#fffdf8',
				'ink'     => '#2b2118',
				'muted'   => '#6b5b4f',
				'accent'  => '#8a5a2b',
				'olive'   => '#5c6b3a',
				'line'    => '#e6d8c8',
			),
			'fast-food'     => array(
				'bg'      => '#fff8e8',
				'surface' => '#ffffff',
				'ink'     => '#1a1a1a',
				'muted'   => '#5c4a3a',
				'accent'  => '#d62828',
				'olive'   => '#fcbf49',
				'line'    => '#f0d9a0',
			),
			'traditional'   => array(
				'bg'      => '#f7f1e6',
				'surface' => '#fffaf0',
				'ink'     => '#1b2a4a',
				'muted'   => '#5a4a3a',
				'accent'  => '#6b1d2a',
				'olive'   => '#c9a227',
				'line'    => '#e4d3b0',
			),
			'fine-dining'   => array(
				'bg'      => '#111111',
				'surface' => '#1c1c1c',
				'ink'     => '#f7f4ef',
				'muted'   => '#b8aa94',
				'accent'  => '#c9a84c',
				'olive'   => '#8a7340',
				'line'    => '#2e2e2e',
			),
			'pastry'        => array(
				'bg'      => '#fff6f4',
				'surface' => '#ffffff',
				'ink'     => '#4a3040',
				'muted'   => '#8a6a78',
				'accent'  => '#d9899c',
				'olive'   => '#7ec8b3',
				'line'    => '#f0d8de',
			),
			'juice-bar'     => array(
				'bg'      => '#f3fff6',
				'surface' => '#ffffff',
				'ink'     => '#14332b',
				'muted'   => '#4a6b5e',
				'accent'  => '#e76f51',
				'olive'   => '#2a9d8f',
				'line'    => '#cde8d8',
			),
			'catering'      => array(
				'bg'      => '#f4f7fb',
				'surface' => '#ffffff',
				'ink'     => '#1d3557',
				'muted'   => '#5a6d82',
				'accent'  => '#1d3557',
				'olive'   => '#457b9d',
				'line'    => '#d5e0ea',
			),
			'cloud-kitchen' => array(
				'bg'      => '#f4f4f5',
				'surface' => '#ffffff',
				'ink'     => '#212529',
				'muted'   => '#6c757d',
				'accent'  => '#ff6b35',
				'olive'   => '#343a40',
				'line'    => '#dee2e6',
			),
		);
		return $map[ $skin ] ?? $map['modern-cafe'];
	}

	/**
	 * Active skin slug.
	 */
	public static function current_skin(): string {
		$skin = (string) get_theme_mod( 'flavor_skin', 'modern-cafe' );
		return array_key_exists( $skin, self::skins() ) ? $skin : 'modern-cafe';
	}

	/**
	 * Resolved tokens (customizer overrides skin defaults).
	 *
	 * @return array<string, string>
	 */
	public static function resolved(): array {
		$base = self::tokens( self::current_skin() );
		foreach ( $base as $key => $fallback ) {
			$mod = get_theme_mod( 'flavor_' . $key, '' );
			if ( is_string( $mod ) && '' !== $mod ) {
				$base[ $key ] = $mod;
			}
		}
		return $base;
	}

	/**
	 * Inline CSS variables.
	 */
	public static function css_variables(): string {
		$t = self::resolved();
		return ':root{--flavor-bg:' . $t['bg'] . ';--flavor-surface:' . $t['surface'] . ';--flavor-ink:' . $t['ink'] . ';--flavor-muted:' . $t['muted'] . ';--flavor-accent:' . $t['accent'] . ';--flavor-olive:' . $t['olive'] . ';--flavor-line:' . $t['line'] . ';}';
	}
}
