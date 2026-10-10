<?php
/**
 * Shared Elementor widget helpers.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Class Widget_Base
 */
abstract class Widget_Base extends \Elementor\Widget_Base {

	/**
	 * Slug of the branded widget category.
	 */
	public const CATEGORY = 'flavor';

	/**
	 * Widgets live in a "Flavor" category rather than Elementor's generic
	 * "General" bucket, so a merchant can find them without scrolling past
	 * every other plugin's widgets.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( self::CATEGORY );
	}

	/**
	 * Register the branded category once.
	 *
	 * @param \Elementor\Elements_Manager $elements Manager.
	 */
	public static function register_category( $elements ): void {
		$existing = $elements->get_categories();

		// Elementor throws if a category is added twice, and other code paths
		// (or a stale object cache) may already have registered it.
		if ( isset( $existing[ self::CATEGORY ] ) ) {
			return;
		}

		$elements->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'رستوران مستقیم — Flavor', 'flavor' ),
				'icon'  => 'eicon-info-circle',
			)
		);
	}

	/**
	 * Resolve a link setting into a safe URL.
	 *
	 * Elementor's URL control stores an array with `url`, `is_external` and
	 * `nofollow`. Treating it as a string produces "Array" in the href.
	 *
	 * @param mixed  $setting  Raw control value.
	 * @param string $fallback Used when nothing usable was entered.
	 * @return string
	 */
	protected static function url_from( $setting, string $fallback = '' ): string {
		$url = '';
		if ( is_array( $setting ) ) {
			$url = (string) ( $setting['url'] ?? '' );
		} elseif ( is_string( $setting ) ) {
			$url = $setting;
		}

		$url = trim( $url );
		if ( '' === $url ) {
			return $fallback;
		}

		return esc_url( $url );
	}

	/**
	 * Whether a link setting asked to open in a new tab.
	 *
	 * @param mixed $setting Raw control value.
	 * @return bool
	 */
	protected static function opens_new_tab( $setting ): bool {
		return is_array( $setting ) && ! empty( $setting['is_external'] );
	}

	/**
	 * Render an anchor's attributes, escaped and complete.
	 *
	 * @param mixed  $setting Raw control value.
	 * @param string $fallback Fallback URL.
	 * @param string $class    Optional class attribute value.
	 * @return string
	 */
	protected static function anchor_attrs( $setting, string $fallback = '', string $class = '' ): string {
		$href = self::url_from( $setting, $fallback );
		$out  = 'href="' . $href . '"';

		if ( '' !== $class ) {
			$out .= ' class="' . esc_attr( $class ) . '"';
		}
		if ( self::opens_new_tab( $setting ) ) {
			$out .= ' target="_blank" rel="noopener noreferrer"';
		}

		return $out;
	}

	/**
	 * Wrap rendered content in the theme's section shell.
	 *
	 * Reusing .flavor-section/.flavor-container means a widget inherits the
	 * theme's gutters, spacing tokens and responsive behaviour instead of
	 * shipping a second, slightly different layout.
	 *
	 * @param string $label    Accessible label for the region.
	 * @param string $inner    Rendered content.
	 * @param string $extra    Extra classes for the section.
	 * @return string
	 */
	protected static function section( string $label, string $inner, string $extra = '' ): string {
		$class = trim( 'flavor-section ' . $extra );

		return sprintf(
			'<section class="%1$s" aria-label="%2$s"><div class="flavor-container">%3$s</div></section>',
			esc_attr( $class ),
			esc_attr( $label ),
			$inner
		);
	}

	/**
	 * An image URL from a MEDIA control, or an empty string.
	 *
	 * @param mixed $setting Raw control value.
	 * @return string
	 */
	protected static function image_url( $setting ): string {
		if ( ! is_array( $setting ) || empty( $setting['url'] ) ) {
			return '';
		}
		return esc_url( (string) $setting['url'] );
	}

	/**
	 * Text from a control, with a fallback for an untouched widget.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @param string               $key      Control name.
	 * @param string               $fallback Fallback text.
	 * @return string
	 */
	protected static function text( array $settings, string $key, string $fallback = '' ): string {
		$value = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		$value = trim( $value );

		return '' === $value ? $fallback : $value;
	}

	/**
	 * Rows of a REPEATER control, as a clean list of arrays.
	 *
	 * Elementor hands back whatever was saved; anything that is not an
	 * array row is dropped rather than rendered.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @param string               $key      Repeater control name.
	 * @return array<int, array<string, mixed>>
	 */
	protected static function rows( array $settings, string $key ): array {
		$rows = $settings[ $key ] ?? array();

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values( array_filter( $rows, 'is_array' ) );
	}
}
