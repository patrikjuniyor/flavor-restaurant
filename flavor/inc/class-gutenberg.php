<?php
/**
 * Lightweight dynamic Gutenberg blocks (no webpack).
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Gutenberg
 */
class Gutenberg {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Register four marketing blocks.
	 */
	public static function register(): void {
		$asset = array(
			'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			'version'      => FLAVOR_VERSION,
		);

		wp_register_script(
			'flavor-blocks',
			FLAVOR_URI . '/gutenberg/blocks.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		foreach ( array( 'hero', 'about', 'gallery', 'testimonial' ) as $slug ) {
			register_block_type(
				'flavor/' . $slug,
				array(
					'api_version'     => 2,
					'editor_script'   => 'flavor-blocks',
					'render_callback' => array( self::class, 'render_' . $slug ),
					'attributes'      => self::attrs( $slug ),
				)
			);
		}
	}

	/**
	 * Attribute schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function attrs( string $slug ): array {
		if ( 'hero' === $slug ) {
			return array(
				'title' => array( 'type' => 'string', 'default' => '' ),
				'cta'   => array( 'type' => 'string', 'default' => '' ),
				'image' => array( 'type' => 'string', 'default' => '' ),
			);
		}
		if ( 'about' === $slug ) {
			return array(
				'title' => array( 'type' => 'string', 'default' => '' ),
			);
		}
		if ( 'gallery' === $slug ) {
			return array(
				'images' => array( 'type' => 'string', 'default' => '' ),
			);
		}
		return array(
			'name' => array( 'type' => 'string', 'default' => '' ),
		);
	}

	/**
	 * Hero.
	 *
	 * @param array<string, mixed> $attrs Attrs.
	 * @param string               $content Inner.
	 */
	public static function render_hero( array $attrs, string $content = '' ): string {
		ob_start();
		get_template_part(
			'template-parts/marketing/hero',
			null,
			array(
				'title' => $attrs['title'] ?? '',
				'text'  => wp_strip_all_tags( $content ),
				'cta'   => $attrs['cta'] ?? '',
				'image' => $attrs['image'] ?? '',
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * About.
	 *
	 * @param array<string, mixed> $attrs Attrs.
	 * @param string               $content Inner.
	 */
	public static function render_about( array $attrs, string $content = '' ): string {
		ob_start();
		get_template_part(
			'template-parts/marketing/about',
			null,
			array(
				'title' => $attrs['title'] ?? '',
				'text'  => $content ? wp_strip_all_tags( $content ) : '',
			)
		);
		return (string) ob_get_clean();
	}

	/**
	 * Gallery.
	 *
	 * @param array<string, mixed> $attrs Attrs.
	 */
	public static function render_gallery( array $attrs ): string {
		$raw = array_filter( array_map( 'trim', explode( ',', (string) ( $attrs['images'] ?? '' ) ) ) );
		ob_start();
		get_template_part( 'template-parts/marketing/gallery', null, array( 'images' => $raw ) );
		return (string) ob_get_clean();
	}

	/**
	 * Single quote.
	 *
	 * @param array<string, mixed> $attrs Attrs.
	 * @param string               $content Inner.
	 */
	public static function render_testimonial( array $attrs, string $content = '' ): string {
		ob_start();
		get_template_part(
			'template-parts/marketing/testimonials',
			null,
			array(
				'items' => array(
					array(
						'name' => $attrs['name'] ?? '',
						'text' => wp_strip_all_tags( $content ),
					),
				),
			)
		);
		return (string) ob_get_clean();
	}
}
