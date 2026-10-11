<?php
/**
 * The single-product page in Flavor's own visual language.
 *
 * R3-01 was the open question of what plays the role Elementor calls
 * «Theme Builder». The decision is (ب): Flavor keeps its own header and footer
 * (Chrome Builder + Customizer) and builds the single-product screen itself, so
 * Elementor Pro is not a prerequisite for any part of a working storefront.
 * This class is the product half of that decision.
 *
 * What it does is deliberately narrow. WooCommerce renders the product title,
 * gallery, price, variations, tabs and the add-to-cart button; this class adds
 * the one thing the theme was missing — the restaurant modifiers. Flavor Core
 * stores modifier groups on the product (`ProductModifiers::get_modifiers()`)
 * and reads a posted selection back in `add_cart_item_data()`, but until now the
 * only UI for that data was the sheet in menu.js. A customer who came to a
 * product page straight from a search engine or a category listing could see
 * «اضافه‌ها» on the receipt, and had no way to choose them.
 *
 * The picker is printed inside WooCommerce's own form, on its documented
 * `woocommerce_before_add_to_cart_button` hook, and posts the exact field names
 * the plugin already consumes (`flavor_modifiers[ids][]`,
 * `flavor_instructions`). No WooCommerce template is copied, so nothing here can
 * silently go stale against a WooCommerce release; the price stays
 * WooCommerce's, the stock stays WooCommerce's, and the group rules are the ones
 * `MenuController` uses for the sheet, so both surfaces ask for the same thing.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Product-page pieces: modifier picker and the stylesheet that carries the card.
 */
class Product_Page {

	/** Character limit of the kitchen note; mirrors ProductModifiers::clean_instructions(). */
	const NOTE_LIMIT = 200;

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ), 40 );
		add_action( 'woocommerce_before_add_to_cart_button', array( self::class, 'modifiers' ), 5 );
	}

	/**
	 * Load the product-page stylesheet, on product pages only.
	 */
	public static function assets(): void {
		if ( ! self::active() ) {
			return;
		}

		$file = FLAVOR_DIR . '/assets/css/ui-product.css';
		if ( ! is_readable( $file ) ) {
			return;
		}

		wp_enqueue_style(
			'flavor-product',
			FLAVOR_URI . '/assets/css/ui-product.css',
			array( 'flavor-ui' ),
			(string) filemtime( $file )
		);
	}

	/**
	 * Are we on a screen this class renders into?
	 *
	 * Every guard is load-bearing: `is_product()` keeps WooCommerce out of
	 * reach on other screens, and the plugin check keeps the picker off a site
	 * that has the theme without Flavor Core, where there is no modifier model
	 * to read.
	 */
	public static function active(): bool {
		return function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Is the modifier picker switched on for this product page?
	 *
	 * @return bool
	 */
	public static function modifiers_enabled(): bool {
		if ( ! self::active() ) {
			return false;
		}
		if ( ! class_exists( '\FlavorCore\WooCommerce\ProductModifiers' ) ) {
			return false;
		}
		if ( 'no' === (string) get_theme_mod( 'flavor_product_modifiers', 'yes' ) ) {
			return false;
		}

		/**
		 * Filters whether Flavor renders its modifier picker on the product page.
		 *
		 * @param bool $enabled Default true.
		 */
		return (bool) apply_filters( 'flavor_product_modifiers_enabled', true );
	}

	/**
	 * The product being viewed, resolved the way WooCommerce leaves it.
	 *
	 * @return int Product post ID, or 0 when nothing is queryable.
	 */
	public static function product_id(): int {
		global $product;

		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return (int) $product->get_id();
		}

		return function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
	}

	/**
	 * Modifier rows grouped the way the menu sheet groups them.
	 *
	 * `MenuController::dish_detail()` is the shape the JavaScript sheet already
	 * consumes: one group per type, `size` required, `topping` multi-select,
	 * everything else single-select. Repeating that rule here is what keeps the
	 * product page and the menu page asking for the same selection; the test
	 * pins both to the same constants so the two cannot drift apart quietly.
	 *
	 * @param int $product_id Product post ID.
	 * @return array<string,array<string,mixed>> Groups keyed by type.
	 */
	public static function groups( int $product_id ): array {
		if ( ! $product_id || ! class_exists( '\FlavorCore\WooCommerce\ProductModifiers' ) ) {
			return array();
		}

		$rows      = \FlavorCore\WooCommerce\ProductModifiers::get_modifiers( $product_id );
		$labels    = UI::modifier_labels();
		$grouped   = array();

		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id   = (string) ( $row['id'] ?? '' );
			$name = (string) ( $row['name'] ?? '' );
			// sanitize_selection() matches on the id alone, so an unnamed option
			// would render as an empty checkbox that adds nothing.
			if ( '' === $id || '' === $name ) {
				continue;
			}

			$type = (string) ( $row['type'] ?? 'topping' );

			if ( ! isset( $grouped[ $type ] ) ) {
				$grouped[ $type ] = array(
					'type'     => $type,
					'title'    => (string) ( $labels[ 'group_' . $type ] ?? $labels['group_choices'] ),
					'required' => 'size' === $type,
					'multi'    => 'topping' === $type,
					'options'  => array(),
				);
			}

			$price = (int) ( $row['price'] ?? 0 );

			$grouped[ $type ]['options'][] = array(
				'id'       => $id,
				'name'     => $name,
				'price'    => $price,
				'label'    => $price > 0 ? '+' . Shopping_Extras::money( $price ) : (string) $labels['no_fee'],
				'default'  => ! empty( $row['is_default'] ),
			);
		}

		return $grouped;
	}

	/**
	 * Render the picker inside the add-to-cart form.
	 */
	public static function modifiers(): void {
		if ( ! self::modifiers_enabled() ) {
			return;
		}

		$labels = UI::modifier_labels();
		$groups = self::groups( self::product_id() );
		?>
		<div class="flavor-product-mods" data-flavor-product-mods>
			<?php if ( $groups ) : ?>
				<h3 class="flavor-product-mods__title"><?php echo esc_html( (string) $labels['group_choices'] ); ?></h3>
				<?php foreach ( $groups as $type => $group ) : ?>
					<?php echo self::group_markup( (string) $type, $group, $labels ); // phpcs:ignore WordPress.Security.EscapeOutput -- every branch below escapes. ?>
				<?php endforeach; ?>
			<?php endif; ?>
			<p class="flavor-product-mods__field">
				<label for="flavor-product-instructions">
					<?php echo esc_html( (string) $labels['kitchen_note'] ); ?>
					<span class="flavor-product-mods__hint"><?php echo esc_html( (string) $labels['optional'] ); ?></span>
				</label>
				<textarea
					id="flavor-product-instructions"
					name="flavor_instructions"
					rows="2"
					maxlength="<?php echo (int) self::NOTE_LIMIT; ?>"
					placeholder="<?php echo esc_attr( (string) $labels['note_placeholder'] ); ?>"
					aria-describedby="flavor-product-instructions-help"></textarea>
				<small id="flavor-product-instructions-help"><?php echo esc_html( (string) $labels['note_help'] ); ?></small>
			</p>
			<p class="flavor-product-mods__note"><?php echo esc_html( (string) $labels['price_note'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * One fieldset of options.
	 *
	 * @param string                         $type   Modifier type key.
	 * @param array<string,mixed>            $group  Group from groups().
	 * @param array<string,string>           $labels Labels from UI::modifier_labels().
	 * @return string
	 */
	public static function group_markup( string $type, array $group, array $labels ): string {
		$options = isset( $group['options'] ) && is_array( $group['options'] ) ? array_values( $group['options'] ) : array();
		if ( ! $options ) {
			return '';
		}

		$multi    = ! empty( $group['multi'] );
		$required = ! empty( $group['required'] );
		$name     = 'flavor-mod-' . $type;

		// Same rule the sheet applies: an explicit default wins, otherwise a
		// required single-select group pre-picks its first option so the form can
		// never be submitted without one.
		$first = -1;
		foreach ( $options as $index => $option ) {
			if ( ! empty( $option['default'] ) ) {
				$first = $index;
				break;
			}
		}
		if ( $first < 0 && $required && ! $multi ) {
			$first = 0;
		}

		$out = '<fieldset class="flavor-product-mods__group" data-flavor-mod-group="' . esc_attr( $type ) . '" data-required="' . ( $required ? '1' : '0' ) . '">';
		$out .= '<legend>' . esc_html( (string) $group['title'] );
		if ( $required ) {
			$out .= ' <span class="flavor-ui-required">' . esc_html( (string) $labels['required_suffix'] ) . '</span>';
		}
		$out .= '</legend><div class="flavor-product-mods__options">';

		// A single-select optional group offers the explicit «no change», so
		// un-checking a radio is not the only way out of it — that is how the
		// menu sheet does it, and the empty value is ignored by the server.
		if ( ! $multi && ! $required ) {
			$out .= self::option_markup( $name, '', (string) $labels['no_change'], (string) $labels['no_fee'], $first < 0, 'radio' );
		}

		foreach ( $options as $index => $option ) {
			$checked = $multi ? ! empty( $option['default'] ) : $index === $first;
			$out    .= self::option_markup(
				$name,
				(string) ( $option['id'] ?? '' ),
				(string) ( $option['name'] ?? '' ),
				(string) ( $option['label'] ?? '' ),
				$checked,
				$multi ? 'checkbox' : 'radio'
			);
		}

		$out .= '</div></fieldset>';

		return $out;
	}

	/**
	 * One option row.
	 *
	 * @param string $name    Group field name prefix, used to build the input id.
	 * @param string $id      Option id, or '' for the «no change» row.
	 * @param string $label   Option label.
	 * @param string $price   Price suffix as the theme formats it.
	 * @param bool   $checked Whether it starts selected.
	 * @param string $kind    'radio' or 'checkbox'.
	 * @return string
	 */
	public static function option_markup( string $name, string $id, string $label, string $price, bool $checked, string $kind ): string {
		$input  = '<input type="' . ( 'checkbox' === $kind ? 'checkbox' : 'radio' ) . '"';
		$input .= '' === $id ? '' : ' name="flavor_modifiers[ids][]"';
		$input .= ' value="' . esc_attr( $id ) . '"';
		$input .= ' id="flavor-mod-' . esc_attr( $name . '-' . ( '' !== $id ? $id : 'none' ) ) . '"';
		$input .= $checked ? ' checked="checked"' : '';
		$input .= ' />';

		return '<label class="flavor-product-mods__option" for="flavor-mod-' . esc_attr( $name . '-' . ( '' !== $id ? $id : 'none' ) ) . '">'
			. $input
			. '<span class="flavor-product-mods__name">' . esc_html( $label ) . '</span>'
			. ( '' !== $price ? '<span class="flavor-product-mods__price">' . esc_html( $price ) . '</span>' : '' )
			. '</label>';
	}
}
