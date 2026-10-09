<?php
/**
 * Repeating sections the merchant can actually edit.
 *
 * The Customizer has no native repeater, and the theme worked around that
 * with fixed slots: six hard-coded gallery images with no alt text, and a
 * testimonials section whose three reviews were hard-coded into the
 * template. A restaurant with four reviews or nine photos had nowhere to
 * put them.
 *
 * This stores a JSON array in one theme_mod and validates it on the way in.
 * Two things are non-negotiable: every image carries its own alt text,
 * because a gallery of decorative images is a gallery of images a screen
 * reader cannot describe; and an empty repeater falls back to the demo
 * content rather than rendering an empty section, so an existing site that
 * has never opened these controls looks exactly as it did before.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Repeater
 */
class Repeater {

	/**
	 * Hard ceiling on items, so a runaway import cannot bloat the row.
	 */
	const MAX_ITEMS = 24;

	/**
	 * Field definitions for every repeating section.
	 *
	 * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
	 */
	public static function schemas(): array {
		return array(
			'gallery'      => array(
				'label'  => __( 'تصویر گالری', 'flavor' ),
				'max'    => 12,
				'fields' => array(
					'image' => array(
						'type'  => 'image',
						'label' => __( 'تصویر', 'flavor' ),
					),
					'alt'   => array(
						'type'    => 'text',
						'label'   => __( 'متن جایگزین', 'flavor' ),
						/* translators: %s is the gallery item position. */
						'default' => __( 'تصویر رستوران', 'flavor' ),
					),
				),
			),
			'testimonials' => array(
				'label'  => __( 'نظر مهمان', 'flavor' ),
				'max'    => 12,
				'fields' => array(
					'name'   => array(
						'type'  => 'text',
						'label' => __( 'نام', 'flavor' ),
					),
					'role'   => array(
						'type'    => 'text',
						'label'   => __( 'نقش', 'flavor' ),
						'default' => __( 'مهمان رستوران', 'flavor' ),
					),
					'rating' => array(
						'type'  => 'rating',
						'label' => __( 'امتیاز', 'flavor' ),
					),
					'text'   => array(
						'type'  => 'textarea',
						'label' => __( 'متن نظر', 'flavor' ),
					),
				),
			),
		);
	}

	/**
	 * One schema.
	 *
	 * @param string $schema Schema key.
	 * @return array<string, mixed>
	 */
	public static function schema( string $schema ): array {
		return self::schemas()[ $schema ] ?? array( 'label' => $schema, 'max' => self::MAX_ITEMS, 'fields' => array() );
	}

	/**
	 * Validate and normalise a submitted repeater value.
	 *
	 * Unknown fields are dropped rather than kept: this array is rendered
	 * straight into the page, and an unrecognised key is a key nobody
	 * reviewed.
	 *
	 * @param mixed  $value  Raw JSON string or array.
	 * @param string $schema Schema key.
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize( $value, string $schema = 'gallery' ): array {
		$schema_def = self::schema( $schema );
		$fields     = $schema_def['fields'];
		$max        = min( (int) ( $schema_def['max'] ?? self::MAX_ITEMS ), self::MAX_ITEMS );

		$items = is_array( $value ) ? $value : json_decode( (string) $value, true );
		if ( ! is_array( $items ) ) {
			return array();
		}

		$out = array();

		foreach ( $items as $item ) {
			if ( count( $out ) >= $max ) {
				break;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}

			$row = array();

			foreach ( $fields as $field => $definition ) {
				$raw = $item[ $field ] ?? '';
				$row[ $field ] = self::sanitize_field( $raw, (string) $definition['type'] );
			}

			// An item with nothing in it is noise, not content.
			if ( '' === implode( '', array_map( 'strval', $row ) ) ) {
				continue;
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Sanitize one field by type.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $type  Field type.
	 * @return string
	 */
	public static function sanitize_field( $value, string $type ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $type ) {
			case 'image':
				$value = esc_url_raw( trim( $value ) );

				if ( '' === $value ) {
					return '';
				}

				// Protocol-relative URLs resolve to whatever scheme the page
				// is served over, so on an http page the image would come
				// back over http. Pinning them to https is both predictable
				// and the safer of the two outcomes.
				if ( 0 === strpos( $value, '//' ) ) {
					$value = 'https:' . $value;
				}

				// Reject the schemes that execute rather than display:
				// javascript: and data: are XSS vectors in an image src.
				// Any https URL or site-relative path is fine.
				return ( preg_match( '#^https?://#i', $value ) || 0 === strpos( $value, '/' ) ) ? $value : '';

			case 'rating':
				$rating = (int) $value;
				return (string) min( 5, max( 0, $rating ) );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * The items to render: saved ones, else the legacy slots, else demo.
	 *
	 * @param string $schema Schema key.
	 * @return array<int, array<string, string>>
	 */
	public static function items( string $schema ): array {
		$saved = self::sanitize( get_theme_mod( 'flavor_' . $schema . '_items', array() ), $schema );

		if ( ! empty( $saved ) ) {
			return $saved;
		}

		// A site that used the old fixed slots must keep its photos.
		$migrated = self::migrate( $schema );
		if ( ! empty( $migrated ) ) {
			return $migrated;
		}

		return self::fallback( $schema );
	}

	/**
	 * Build items from the pre-repeater settings, if any were ever set.
	 *
	 * @param string $schema Schema key.
	 * @return array<int, array<string, string>>
	 */
	public static function migrate( string $schema ): array {
		if ( 'gallery' !== $schema ) {
			return array();
		}

		$out = array();

		for ( $slot = 1; $slot <= Customizer::GALLERY_SLOTS; $slot++ ) {
			$url = (string) get_theme_mod( 'flavor_gallery_image_' . $slot, '' );

			if ( '' === trim( $url ) ) {
				continue;
			}

			$out[] = array(
				'image' => self::sanitize_field( $url, 'image' ),
				// The old slots had nowhere to store alt text, so the
				// generic description is the honest value here.
				'alt'   => __( 'تصویر رستوران', 'flavor' ),
			);
		}

		return array_values( array_filter( $out, static fn( array $row ): bool => '' !== $row['image'] ) );
	}

	/**
	 * Demo content, translated. Used when nothing has been set at all.
	 *
	 * The gallery falls back to the active skin's demo art so the section is
	 * never empty on a fresh install, which is what the old slot system did.
	 *
	 * @param string $schema Schema key.
	 * @return array<int, array<string, string>>
	 */
	public static function fallback( string $schema ): array {
		if ( 'gallery' === $schema ) {
			$slugs = array( Design::current_skin(), 'fast-food', 'traditional', 'fine-dining', 'pastry', 'modern-cafe' );
			$out   = array();

			foreach ( $slugs as $slug ) {
				// The demo pack is a separate download, so most of these are
				// absent on a default install. Listing a URL that 404s would
				// fill the gallery with broken-image icons, so only art that
				// is actually on disk is offered.
				if ( ! is_readable( FLAVOR_DIR . '/demos/' . $slug . '/hero.jpg' ) ) {
					continue;
				}
				$out[] = array(
					'image' => FLAVOR_URI . '/demos/' . $slug . '/hero.jpg',
					'alt'   => __( 'تصویر رستوران', 'flavor' ),
				);
			}

			// With the demo pack absent there is nothing to show, and an
			// empty grid is better than six identical placeholders.
			return $out;
		}

		if ( 'testimonials' === $schema ) {
			return array(
				array(
					'name'   => __( 'سارا محمدی', 'flavor' ),
					'role'   => __( 'مشتری وفادار', 'flavor' ),
					'rating' => '5',
					'text'   => __( 'کیفیت غذاها بی‌نظیر بود، سفارش با بسته‌بندی کاملاً گرم و به موقع تحویل داده شد. به شدت کوبیده مخصوص را پیشنهاد می‌کنم.', 'flavor' ),
				),
				array(
					'name'   => __( 'کیان رضایی', 'flavor' ),
					'role'   => __( 'مهمان سالن', 'flavor' ),
					'rating' => '5',
					'text'   => __( 'فضای سالن فوق‌العاده آرام و دلنشین است. برخورد پرسنل عالی و سرعت آماده‌سازی سفارش با QR کد سر میز بسیار راحت و مدرن بود.', 'flavor' ),
				),
				array(
					'name'   => __( 'مریم شفیعی', 'flavor' ),
					'role'   => __( 'سفارش آنلاین', 'flavor' ),
					'rating' => '5',
					'text'   => __( 'برای مهمانی خانوادگی سفارش دادیم؛ همه مهمان‌ها از طعم اصیل و تازگی سالادها و پیش‌غذاها تعریف کردند. ممنون از تیم حرفه‌ای‌تان.', 'flavor' ),
				),
			);
		}

		return array();
	}

	/**
	 * A URL for an item image, or '' when the item has none.
	 *
	 * Templates use this so a deleted image cannot print an empty src.
	 *
	 * @param array<string, string> $item Gallery item.
	 * @return string
	 */
	public static function image_url( array $item ): string {
		return (string) ( $item['image'] ?? '' );
	}

	/**
	 * Alt text for an item, never empty.
	 *
	 * A gallery image without alt text is invisible to a screen reader, so
	 * an empty value falls back to a generic description rather than being
	 * omitted — which is what the old fixed slots did.
	 *
	 * @param array<string, string> $item Gallery item.
	 * @return string
	 */
	public static function image_alt( array $item ): string {
		$alt = trim( (string) ( $item['alt'] ?? '' ) );

		return '' !== $alt ? $alt : __( 'تصویر رستوران', 'flavor' );
	}
}
