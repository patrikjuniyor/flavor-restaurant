<?php
/**
 * Per-page presentation options.
 *
 * The theme offered no way to change how a single page is presented: every
 * page got the same title, the same header treatment and the same background.
 * A landing page and a menu page have different jobs, and a merchant who
 * wants a transparent header on the homepage currently has to write CSS.
 *
 * These options affect presentation only. They never touch content, and they
 * never touch price, stock or checkout — those stay server-side in Flavor
 * Core, as the architecture documents require.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Page_Options
 */
class Page_Options {

	/**
	 * Meta key prefix.
	 */
	const PREFIX = '_flavor_';

	/**
	 * Nonce action for the metabox.
	 */
	const NONCE = 'flavor_page_options';

	/**
	 * Post types that get the metabox.
	 */
	const TYPES = array( 'page', 'post', 'product' );

	/**
	 * Option definitions.
	 *
	 * Deliberately a small, closed set. Every value is validated against an
	 * allowlist, because these land in markup and CSS.
	 *
	 * @return array<string, array{label: string, type: string, choices?: array<string,string>, description?: string}>
	 */
	public static function options(): array {
		return array(
			'hide_title'          => array(
				'label'       => __( 'پنهان‌کردن عنوان صفحه', 'flavor' ),
				'type'        => 'checkbox',
				'description' => __( 'برای صفحاتی که خودشان تیتر بزرگ دارند (مثل صفحهٔ اصلی).', 'flavor' ),
			),
			'transparent_header'  => array(
				'label'       => __( 'هدر شفاف روی این صفحه', 'flavor' ),
				'type'        => 'checkbox',
				'description' => __( 'هدر روی تصویرِ بالای صفحه می‌نشیند. صفحه باید تصویر شاخص داشته باشد.', 'flavor' ),
			),
			'page_layout'         => array(
				'label'   => __( 'عرض محتوا', 'flavor' ),
				'type'    => 'select',
				'choices' => array(
					''       => __( 'پیش‌فرض قالب', 'flavor' ),
					'narrow' => __( 'باریک (متن‌محور)', 'flavor' ),
					'full'   => __( 'تمام‌عرض', 'flavor' ),
				),
			),
			'page_bg'             => array(
				'label'       => __( 'رنگ پس‌زمینهٔ صفحه', 'flavor' ),
				'type'        => 'color',
				'description' => __( 'اگر خالی بماند، رنگ پوستهٔ فعال استفاده می‌شود.', 'flavor' ),
			),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'add_metabox' ) );
		add_action( 'save_post', array( self::class, 'save' ), 10, 2 );
		add_filter( 'body_class', array( self::class, 'filter_body_class' ) );
		add_action( 'wp_head', array( self::class, 'print_css' ), 30 );
	}

	/**
	 * Sanitize one submitted value.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public static function sanitize( string $key, $value ): string {
		$options = self::options();
		if ( ! isset( $options[ $key ] ) ) {
			return '';
		}

		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( 'checkbox' === $options[ $key ]['type'] ) {
			return '1' === $value ? '1' : '';
		}

		if ( 'color' === $options[ $key ]['type'] ) {
			// A hex colour goes straight into a CSS declaration, so it is
			// matched strictly rather than merely escaped.
			return (bool) preg_match( '/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $value ) ? $value : '';
		}

		if ( 'select' === $options[ $key ]['type'] ) {
			return array_key_exists( $value, $options[ $key ]['choices'] ) ? $value : '';
		}

		return '';
	}

	/**
	 * Read a saved, sanitized value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Option key.
	 * @return string
	 */
	public static function get( int $post_id, string $key ): string {
		if ( $post_id <= 0 ) {
			return '';
		}

		$raw = get_post_meta( $post_id, self::PREFIX . $key, true );

		return self::sanitize( $key, $raw );
	}

	/**
	 * Body classes derived from the page's own options.
	 *
	 * Kept separate from the hook so it can be tested without a real post.
	 *
	 * @param string[] $classes Existing classes.
	 * @param int      $post_id Post ID.
	 * @return string[]
	 */
	public static function body_classes( array $classes, int $post_id ): array {
		if ( '' !== self::get( $post_id, 'hide_title' ) ) {
			$classes[] = 'flavor-hide-title';
		}

		if ( '' !== self::get( $post_id, 'transparent_header' ) ) {
			$classes[] = 'flavor-header-overlay';
		}

		$layout = self::get( $post_id, 'page_layout' );
		if ( in_array( $layout, array( 'full', 'narrow' ), true ) ) {
			$classes[] = 'flavor-layout-' . $layout;
		}

		return $classes;
	}

	/**
	 * The CSS this page needs, or an empty string when it needs none.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function inline_css( int $post_id ): string {
		$declarations = array();

		$bg = self::get( $post_id, 'page_bg' );
		if ( '' !== $bg ) {
			$declarations[] = '--flavor-page-bg:' . $bg;
		}

		if ( '' !== self::get( $post_id, 'hide_title' ) ) {
			$declarations[] = '--flavor-page-title-display:none';
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		// The stylesheet owns the selectors; this only sets variables. That
		// keeps the PHP from depending on which class the templates happen to
		// put on a title, so renaming .flavor-article__title cannot silently
		// break the option.
		return 'body.flavor-page-scoped{' . implode( ';', $declarations ) . '}';
	}

	/**
	 * Register the metabox.
	 */
	public static function add_metabox(): void {
		add_meta_box(
			'flavor_page_options',
			__( 'تنظیمات نمایش این صفحه', 'flavor' ),
			array( self::class, 'render_metabox' ),
			self::TYPES,
			'side',
			'default'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function render_metabox( $post ): void {
		$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;

		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		echo '<div class="flavor-page-options">';

		foreach ( self::options() as $key => $option ) {
			$value = self::get( $post_id, $key );
			$id    = 'flavor_page_option_' . $key;

			echo '<p style="margin:0 0 12px">';

			if ( 'checkbox' === $option['type'] ) {
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( self::PREFIX . $key ),
					checked( '1', $value, false ),
					esc_html( $option['label'] )
				);
			} elseif ( 'select' === $option['type'] ) {
				printf(
					'<label for="%1$s" style="display:block;font-weight:600;margin-bottom:4px">%2$s</label>',
					esc_attr( $id ),
					esc_html( $option['label'] )
				);
				printf( '<select id="%1$s" name="%2$s" style="width:100%%">', esc_attr( $id ), esc_attr( self::PREFIX . $key ) );
				foreach ( $option['choices'] as $choice => $label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( (string) $choice ),
						selected( $value, (string) $choice, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
			} else {
				printf(
					'<label for="%1$s" style="display:block;font-weight:600;margin-bottom:4px">%2$s</label>',
					esc_attr( $id ),
					esc_html( $option['label'] )
				);
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="code" placeholder="#f4efe7" style="width:100%%" />',
					esc_attr( $id ),
					esc_attr( self::PREFIX . $key ),
					esc_attr( $value )
				);
			}

			if ( ! empty( $option['description'] ) ) {
				printf(
					'<span class="description" style="display:block;margin-top:4px;color:#6b625b;font-size:12px">%s</span>',
					esc_html( $option['description'] )
				);
			}

			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Save the metabox.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function save( int $post_id, $post ): void {
		unset( $post );

		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array_keys( self::options() ) as $key ) {
			$field = self::PREFIX . $key;
			$raw   = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
			$value = self::sanitize( $key, $raw );

			if ( '' === $value ) {
				delete_post_meta( $post_id, $field );
				continue;
			}

			update_post_meta( $post_id, $field, $value );
		}
	}

	/**
	 * Body class filter.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function filter_body_class( array $classes ): array {
		if ( ! is_singular() ) {
			return $classes;
		}

		$post_id = (int) get_the_ID();

		// Always add the scope class so per-page CSS has something to hang on.
		$classes[] = 'flavor-page-scoped';

		return self::body_classes( $classes, $post_id );
	}

	/**
	 * Print the per-page stylesheet, if there is one.
	 */
	public static function print_css(): void {
		if ( ! is_singular() ) {
			return;
		}

		$css = self::inline_css( (int) get_the_ID() );

		if ( '' === $css ) {
			return;
		}

		// A handful of declarations: an inline <style> beats a whole request.
		echo '<style id="flavor-page-options">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is validated by sanitize().
	}
}
