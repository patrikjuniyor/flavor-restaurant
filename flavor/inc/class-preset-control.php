<?php
/**
 * Visual preset picker for the Customizer.
 *
 * The old control was a radio list reading "رستوران مدرن — استایل شیک و
 * مینیمال با رنگ‌های گرم قهوه‌ای و بژ". Twelve of those in a row is a wall of
 * prose: you cannot compare palettes by reading descriptions of them. Paid
 * themes show a thumbnail of each skin, and that is the whole reason the
 * choice is easy to make.
 *
 * The second half of the job is honesty about what a preset does. A preset
 * writes the same nine colour tokens the merchant may have set by hand, so
 * picking one after customising looks like nothing happened. This control
 * says so, names the overrides, and offers a button that clears them.
 *
 * Clearing is non-destructive: the button writes '' into the changeset via
 * the JS API, which is a draft until the merchant publishes. Nothing touches
 * the database, and discarding the changeset restores everything.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Preset_Control
 */
class Preset_Control extends \WP_Customize_Control {

	/**
	 * Control type, matched by the JS that wires the clear-overrides button.
	 *
	 * @var string
	 */
	public $type = 'flavor_preset';

	/**
	 * A neutral placeholder, used if a preset ever ships a malformed colour.
	 *
	 * @var string
	 */
	const FALLBACK = '#cccccc';

	/**
	 * Render one preset card.
	 *
	 * Static and free of Customizer state so it can be tested on its own;
	 * the markup is identical to what the control prints.
	 *
	 * @param string $slug  Preset slug.
	 * @param array  $skin  Skin data (title, desc).
	 * @param bool   $is_on Whether this preset is the current value.
	 * @return string
	 */
	public static function card( string $slug, array $skin, bool $is_on ): string {
		$swatch = Design::skin_swatch( $slug );
		$chips  = '';

		foreach ( $swatch as $token => $color ) {
			// skin_swatch() already guarantees a hex string or an empty one;
			// the fallback keeps a malformed preset from printing "".
			$color = '' !== $color ? $color : self::FALLBACK;
			$chips .= sprintf(
				'<span class="flavor-preset__chip" style="background:%1$s" title="%2$s"></span>',
				esc_attr( $color ),
				esc_attr( $token )
			);
		}

		$title = (string) ( $skin['title'] ?? $slug );
		$desc  = (string) ( $skin['desc'] ?? '' );

		return sprintf(
			'<label class="flavor-preset__card%5$s" for="flavor-preset-%1$s">' .
				'<input type="radio" id="flavor-preset-%1$s" name="flavor-preset-choice" value="%2$s"%6$s />' .
				'<span class="flavor-preset__swatch" aria-hidden="true">%3$s</span>' .
				'<span class="flavor-preset__title">%4$s</span>' .
			'</label>',
			esc_attr( $slug ),
			esc_attr( $slug ),
			$chips, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each chip is escaped above.
			esc_html( $title ),
			$is_on ? ' is-active' : '',
			checked( $is_on, true, false ) . sprintf( ' data-desc="%s"', esc_attr( $desc ) )
		);
	}

	/**
	 * The notice shown when manual colour overrides are masking the preset.
	 *
	 * @param array<string, string> $overrides Active overrides, id => value.
	 * @return string
	 */
	public static function override_notice( array $overrides ): string {
		if ( empty( $overrides ) ) {
			return '';
		}

		$labels = Design::colour_token_labels();
		$names  = array();

		foreach ( array_keys( $overrides ) as $id ) {
			$key     = (string) str_replace( 'flavor_', '', $id );
			$names[] = (string) ( $labels[ $key ] ?? $key );
		}

		return sprintf(
			'<div class="flavor-preset__notice">' .
				'<p><strong>%1$s</strong> %2$s</p>' .
				'<p class="flavor-preset__list">%3$s</p>' .
				'<button type="button" class="button-link flavor-preset__clear">%4$s</button>' .
				'<p class="flavor-preset__hint">%5$s</p>' .
			'</div>',
			esc_html__( 'این پوسته را رنگ‌های دستی شما پوشانده است.', 'flavor' ),
			esc_html(
				sprintf(
					/* translators: %d is the number of colour settings. */
					_n(
						'تنظیم زیر از پوسته پیروی نمی‌کند:',
						'تنظیمات زیر از پوسته پیروی نمی‌کنند:',
						count( $names ),
						'flavor'
					),
					count( $names )
				)
			),
			esc_html( implode( '، ', $names ) ),
			esc_html__( 'پاک‌کردنِ رنگ‌های دستی و پیروی از پوسته', 'flavor' ),
			esc_html__( 'تا وقتی تغییرات را منتشر نکنید، چیزی در سایت تغییر نمی‌کند و با لغوِ تغییرات همه‌چیز برمی‌گردد.', 'flavor' )
		);
	}

	/**
	 * Render the control.
	 *
	 * @return void
	 */
	public function render_content(): void {
		$value     = (string) $this->value();
		$skins     = Design::skins();
		$overrides = Design::active_colour_overrides();

		printf(
			'<div class="flavor-preset" data-setting="%1$s">',
			esc_attr( $this->id )
		);

		if ( ! empty( $this->label ) ) {
			printf( '<span class="customize-control-title">%s</span>', esc_html( $this->label ) );
		}
		if ( ! empty( $this->description ) ) {
			printf( '<span class="description customize-control-description">%s</span>', esc_html( $this->description ) );
		}

		echo '<div class="flavor-preset__grid">';
		foreach ( $skins as $slug => $skin ) {
			echo self::card( (string) $slug, (array) $skin, (string) $slug === $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card() escapes every value.
		}
		echo '</div>';

		echo '<p class="flavor-preset__desc" aria-live="polite"></p>';

		echo self::override_notice( $overrides ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice() escapes every value.

		echo '</div>';
	}
}
