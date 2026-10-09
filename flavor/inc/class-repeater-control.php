<?php
/**
 * Repeating section control for the Customizer.
 *
 * Renders the container and one row per item; customizer-repeater.js owns
 * add, duplicate, reorder and delete. The whole list lives in a single
 * theme_mod as JSON, and Repeater::sanitize() validates it server-side, so
 * whatever the JS sends is checked rather than trusted.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Repeater_Control
 */
class Repeater_Control extends \WP_Customize_Control {

	/**
	 * Control type, matched by customizer-repeater.js.
	 *
	 * @var string
	 */
	public $type = 'flavor_repeater';

	/**
	 * Which schema this control edits.
	 *
	 * @var string
	 */
	public $schema = 'gallery';

	/**
	 * A hidden input holding the JSON, updated by the JS.
	 *
	 * Static so the markup is testable without a Customizer instance.
	 *
	 * @param string $id     Field id.
	 * @param string $value  JSON value.
	 * @param string $schema Schema key.
	 * @return string
	 */
	public static function field( string $id, string $value, string $schema ): string {
		return sprintf(
			'<input type="hidden" id="%1$s" class="flavor-repeater__value" data-schema="%3$s" value="%2$s" />',
			esc_attr( $id ),
			esc_attr( $value ),
			esc_attr( $schema )
		);
	}

	/**
	 * One item row.
	 *
	 * @param array<string, string> $item     Item values.
	 * @param array<string, mixed>  $fields   Field definitions.
	 * @param int                   $position Position, 1-based, for the label.
	 * @return string
	 */
	public static function row( array $item, array $fields, int $position ): string {
		$controls = '';

		foreach ( $fields as $field => $definition ) {
			$value = (string) ( $item[ $field ] ?? '' );
			$label = (string) ( $definition['label'] ?? $field );
			$type  = (string) ( $definition['type'] ?? 'text' );

			$controls .= '<p class="flavor-repeater__field flavor-repeater__field--' . esc_attr( $type ) . '">';
			$controls .= sprintf( '<label><span>%s</span>', esc_html( $label ) );

			switch ( $type ) {
				case 'image':
					$controls .= sprintf(
						'<input type="url" class="flavor-repeater__input" data-field="%1$s" value="%2$s" placeholder="https://" />' .
						'<button type="button" class="button flavor-repeater__pick" data-field="%1$s">%3$s</button>',
						esc_attr( $field ),
						esc_attr( $value ),
						esc_html__( 'انتخاب', 'flavor' )
					);
					break;

				case 'textarea':
					$controls .= sprintf(
						'<textarea class="flavor-repeater__input" data-field="%1$s" rows="3">%2$s</textarea>',
						esc_attr( $field ),
						esc_textarea( $value )
					);
					break;

				case 'rating':
					$controls .= sprintf(
						'<select class="flavor-repeater__input" data-field="%1$s">',
						esc_attr( $field )
					);
					for ( $star = 1; $star <= 5; $star++ ) {
						$controls .= sprintf(
							'<option value="%1$d"%2$s>%1$d ★</option>',
							$star,
							selected( (string) $star, '' === $value ? '5' : $value, false )
						);
					}
					$controls .= '</select>';
					break;

				case 'text':
				default:
					$controls .= sprintf(
						'<input type="text" class="flavor-repeater__input" data-field="%1$s" value="%2$s" />',
						esc_attr( $field ),
						esc_attr( $value )
					);
					break;
			}

			$controls .= '</label></p>';
		}

		$buttons = sprintf(
			'<button type="button" class="button-link flavor-repeater__up" title="%1$s" aria-label="%1$s">↑</button>' .
			'<button type="button" class="button-link flavor-repeater__down" title="%2$s" aria-label="%2$s">↓</button>' .
			'<button type="button" class="button-link flavor-repeater__duplicate" title="%3$s">%4$s</button>' .
			'<button type="button" class="button-link button-link-delete flavor-repeater__remove" title="%5$s">%6$s</button>',
			esc_attr__( 'انتقال به بالا', 'flavor' ),
			esc_attr__( 'انتقال به پایین', 'flavor' ),
			esc_attr__( 'تکرار این مورد', 'flavor' ),
			esc_html__( 'تکرار', 'flavor' ),
			esc_attr__( 'حذف این مورد', 'flavor' ),
			esc_html__( 'حذف', 'flavor' )
		);

		return sprintf(
			'<li class="flavor-repeater__item" data-position="%2$d">' .
				'<div class="flavor-repeater__head">' .
					'<span class="flavor-repeater__index">%3$s</span>' .
					'<span class="flavor-repeater__actions">%4$s</span>' .
				'</div>' .
				'<div class="flavor-repeater__body">%1$s</div>' .
			'</li>',
			$controls, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every input escaped above.
			$position,
			esc_html( (string) $position ),
			$buttons // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every label escaped above.
		);
	}

	/**
	 * Render the control.
	 *
	 * @return void
	 */
	public function render_content(): void {
		$schema   = $this->schema;
		$definition = Repeater::schema( $schema );
		$fields   = (array) ( $definition['fields'] ?? array() );
		$items    = Repeater::sanitize( $this->value(), $schema );

		// Editing an empty list is confusing, so the control opens with the
		// content currently on the page: the migrated or demo items. Nothing
		// is written until the merchant actually changes something.
		if ( empty( $items ) ) {
			$items = Repeater::items( $schema );
		}

		printf( '<div class="flavor-repeater" data-schema="%s" data-max="%d">', esc_attr( $schema ), (int) ( $definition['max'] ?? Repeater::MAX_ITEMS ) );

		if ( ! empty( $this->label ) ) {
			printf( '<span class="customize-control-title">%s</span>', esc_html( $this->label ) );
		}
		if ( ! empty( $this->description ) ) {
			printf( '<span class="description customize-control-description">%s</span>', esc_html( $this->description ) );
		}

		echo '<ul class="flavor-repeater__list">';
		foreach ( $items as $index => $item ) {
			echo self::row( (array) $item, $fields, $index + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- row() escapes every value.
		}
		echo '</ul>';

		printf(
			'<button type="button" class="button flavor-repeater__add">%s</button>',
			/* translators: %s is the item name, e.g. "تصویر گالری". */
			esc_html( sprintf( __( 'افزودن %s', 'flavor' ), (string) ( $definition['label'] ?? '' ) ) )
		);

		echo '<p class="flavor-repeater__empty" hidden>' . esc_html__( 'هنوز موردی نیست.', 'flavor' ) . '</p>';

		// A blank row for the JS to clone.
		printf(
			'<script type="text/template" class="flavor-repeater__template">%s</script>',
			self::row( array(), $fields, 0 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- row() escapes every value.
		);

		echo self::field( $this->id, (string) wp_json_encode( $items ), $schema ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- field() escapes both attributes.

		echo '</div>';
	}
}
