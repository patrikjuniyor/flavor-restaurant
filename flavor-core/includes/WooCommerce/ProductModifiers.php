<?php
/**
 * Size / topping / cook / removal data on simple products.
 *
 * @package FlavorCore
 */

namespace FlavorCore\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProductModifiers
 */
class ProductModifiers {

	public const META_MODIFIERS = '_flavor_modifiers';
	public const META_PREP      = '_flavor_prep_time';
	public const META_CALORIES  = '_flavor_calories';
	public const META_DIETARY   = '_flavor_dietary';
	public const META_SCHEDULE  = '_flavor_schedule';

	public const TYPES = array( 'size', 'topping', 'cook', 'removal' );

	public const DIETARY = array(
		'vegetarian',
		'vegan',
		'spicy',
		'gluten_free',
		'dairy_free',
		'nut_free',
	);

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate' ), 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'to_order_item' ), 10, 4 );
	}

	/**
	 * Product data tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function tab( array $tabs ): array {
		$tabs['flavor_food'] = array(
			'label'    => __( 'غذا (رستوران مستقیم)', 'flavor-core' ),
			'target'   => 'flavor_food_data',
			'class'    => array( 'show_if_simple' ),
			'priority' => 21,
		);
		return $tabs;
	}

	/**
	 * Product data panel.
	 */
	public function panel(): void {
		global $post;

		$product_id = $post instanceof \WP_Post ? (int) $post->ID : 0;
		$modifiers  = self::get_modifiers( $product_id );
		$prep       = (int) get_post_meta( $product_id, self::META_PREP, true );
		$calories   = (int) get_post_meta( $product_id, self::META_CALORIES, true );
		$dietary    = get_post_meta( $product_id, self::META_DIETARY, true );
		$schedule   = get_post_meta( $product_id, self::META_SCHEDULE, true );
		if ( ! is_array( $dietary ) ) {
			$dietary = array();
		}
		if ( ! is_array( $schedule ) ) {
			$schedule = array();
		}

		$dietary_labels = array(
			'vegetarian'  => __( 'گیاهی', 'flavor-core' ),
			'vegan'       => __( 'وگان', 'flavor-core' ),
			'spicy'       => __( 'تند', 'flavor-core' ),
			'gluten_free' => __( 'بدون گلوتن', 'flavor-core' ),
			'dairy_free'  => __( 'بدون لبنیات', 'flavor-core' ),
			'nut_free'    => __( 'بدون مغز', 'flavor-core' ),
		);
		$schedule_labels = array(
			'breakfast'  => __( 'صبحانه', 'flavor-core' ),
			'lunch'      => __( 'ناهار', 'flavor-core' ),
			'dinner'     => __( 'شام', 'flavor-core' ),
			'late_night' => __( 'دیرهنگام', 'flavor-core' ),
		);
		$type_labels = array(
			'size'    => __( 'اندازه', 'flavor-core' ),
			'topping' => __( 'تاپینگ', 'flavor-core' ),
			'cook'    => __( 'درجه پخت', 'flavor-core' ),
			'removal' => __( 'حذف ماده', 'flavor-core' ),
		);

		wp_nonce_field( 'flavor_save_modifiers', 'flavor_modifiers_nonce' );
		?>
		<div id="flavor_food_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field">
					<label><?php esc_html_e( 'زمان آماده‌سازی (دقیقه)', 'flavor-core' ); ?></label>
					<input type="number" name="flavor_prep_time" min="0" step="1" value="<?php echo esc_attr( (string) $prep ); ?>" />
				</p>
				<p class="form-field">
					<label><?php esc_html_e( 'کالری (اختیاری)', 'flavor-core' ); ?></label>
					<input type="number" name="flavor_calories" min="0" step="1" value="<?php echo esc_attr( (string) $calories ); ?>" />
				</p>
				<p class="form-field">
					<label><?php esc_html_e( 'برچسب رژیمی', 'flavor-core' ); ?></label>
					<span>
						<?php foreach ( $dietary_labels as $key => $label ) : ?>
							<label style="display:inline-block;margin-inline-end:10px;">
								<input type="checkbox" name="flavor_dietary[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $dietary, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</span>
				</p>
				<p class="form-field">
					<label><?php esc_html_e( 'وعده زمانی', 'flavor-core' ); ?></label>
					<span>
						<?php foreach ( $schedule_labels as $key => $label ) : ?>
							<label style="display:inline-block;margin-inline-end:10px;">
								<input type="checkbox" name="flavor_schedule[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $schedule, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</span>
				</p>
			</div>
			<div class="options_group">
				<p><strong><?php esc_html_e( 'مدیفایرها — قیمت‌ها به واحد ذخیره (پیش‌فرض: ریال)', 'flavor-core' ); ?></strong></p>
				<table class="widefat" id="flavor-modifiers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نوع', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'نام', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'تغییر قیمت', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'پیش‌فرض', 'flavor-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rows = $modifiers;
						if ( empty( $rows ) ) {
							$rows = array(
								array(
									'type'          => 'size',
									'name'          => '',
									'price'         => 0,
									'is_default'    => 0,
								),
							);
						}
						foreach ( $rows as $i => $row ) :
							?>
							<tr>
								<td>
									<select name="flavor_mod[<?php echo esc_attr( (string) $i ); ?>][type]">
										<?php foreach ( $type_labels as $type => $label ) : ?>
											<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $row['type'] ?? '', $type ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td><input type="text" name="flavor_mod[<?php echo esc_attr( (string) $i ); ?>][name]" value="<?php echo esc_attr( (string) ( $row['name'] ?? '' ) ); ?>" /></td>
								<td><input type="number" name="flavor_mod[<?php echo esc_attr( (string) $i ); ?>][price]" value="<?php echo esc_attr( (string) ( $row['price'] ?? 0 ) ); ?>" step="1" /></td>
								<td><input type="checkbox" name="flavor_mod[<?php echo esc_attr( (string) $i ); ?>][is_default]" value="1" <?php checked( ! empty( $row['is_default'] ) ); ?> /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'ردیف خالی نادیده گرفته می‌شود. برای افزودن ردیف، پس از ذخیره دوباره باز کنید — ویرایشگر پویا در فاز ۲ می‌آید.', 'flavor-core' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product meta.
	 *
	 * @param int $product_id Product id.
	 */
	public function save( int $product_id ): void {
		if ( ! isset( $_POST['flavor_modifiers_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flavor_modifiers_nonce'] ) ), 'flavor_save_modifiers' ) ) {
			return;
		}

		$prep     = isset( $_POST['flavor_prep_time'] ) ? absint( wp_unslash( $_POST['flavor_prep_time'] ) ) : 0;
		$calories = isset( $_POST['flavor_calories'] ) ? absint( wp_unslash( $_POST['flavor_calories'] ) ) : 0;
		$dietary  = isset( $_POST['flavor_dietary'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['flavor_dietary'] ) ) : array();
		$schedule = isset( $_POST['flavor_schedule'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['flavor_schedule'] ) ) : array();
		$raw      = isset( $_POST['flavor_mod'] ) ? (array) wp_unslash( $_POST['flavor_mod'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$dietary  = array_values( array_intersect( $dietary, self::DIETARY ) );
		$schedule = array_values( array_intersect( $schedule, array( 'breakfast', 'lunch', 'dinner', 'late_night' ) ) );

		$clean = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'topping';
			if ( ! in_array( $type, self::TYPES, true ) ) {
				$type = 'topping';
			}
			$clean[] = array(
				'id'         => sanitize_title( $type . '-' . $name ),
				'type'       => $type,
				'name'       => $name,
				'price'      => isset( $row['price'] ) ? (int) $row['price'] : 0,
				'is_default' => ! empty( $row['is_default'] ) ? 1 : 0,
			);
		}

		update_post_meta( $product_id, self::META_PREP, $prep );
		update_post_meta( $product_id, self::META_CALORIES, $calories );
		update_post_meta( $product_id, self::META_DIETARY, $dietary );
		update_post_meta( $product_id, self::META_SCHEDULE, $schedule );
		update_post_meta( $product_id, self::META_MODIFIERS, $clean );
	}

	/**
	 * Read modifiers.
	 *
	 * @param int $product_id Product id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_modifiers( int $product_id ): array {
		$rows = get_post_meta( $product_id, self::META_MODIFIERS, true );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Attach posted modifiers to the cart item.
	 *
	 * @param array<string, mixed> $cart_item_data Cart item.
	 * @param int                  $product_id     Product.
	 * @param int                  $variation_id   Variation.
	 * @return array<string, mixed>
	 */
	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		unset( $variation_id );

		$posted = array();
		if ( isset( $_REQUEST['flavor_modifiers'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = wp_unslash( $_REQUEST['flavor_modifiers'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Recommended
			if ( is_string( $raw ) ) {
				$decoded = json_decode( $raw, true );
				$posted  = is_array( $decoded ) ? $decoded : array();
			} elseif ( is_array( $raw ) ) {
				$posted = $raw;
			}
		}

		$instructions = isset( $_REQUEST['flavor_instructions'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['flavor_instructions'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( strlen( $instructions ) > 200 ) {
			$instructions = substr( $instructions, 0, 200 );
		}

		$selected = self::sanitize_selection( $product_id, $posted );
		$extra    = self::selection_extra( $selected );

		$cart_item_data['flavor_modifiers']    = $selected;
		$cart_item_data['flavor_instructions'] = $instructions;
		$cart_item_data['flavor_extra']        = $extra;
		$cart_item_data['unique_key']          = md5( wp_json_encode( array( $product_id, $selected, $instructions ) ) );

		return $cart_item_data;
	}

	/**
	 * Show modifiers in the cart.
	 *
	 * @param array<int, array<string, string>> $item_data Item data.
	 * @param array<string, mixed>              $cart_item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public function display_cart_item_data( array $item_data, array $cart_item ): array {
		if ( empty( $cart_item['flavor_modifiers'] ) || ! is_array( $cart_item['flavor_modifiers'] ) ) {
			return $item_data;
		}
		foreach ( $cart_item['flavor_modifiers'] as $mod ) {
			$item_data[] = array(
				'key'   => (string) ( $mod['type_label'] ?? $mod['type'] ),
				'value' => (string) $mod['name'],
			);
		}
		if ( ! empty( $cart_item['flavor_instructions'] ) ) {
			$item_data[] = array(
				'key'   => __( 'توضیحات', 'flavor-core' ),
				'value' => (string) $cart_item['flavor_instructions'],
			);
		}
		return $item_data;
	}

	/**
	 * Recalculate line price from base + extras. Never trust the client.
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public function recalculate( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart || $cart->is_empty() ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) || ! is_object( $item['data'] ) ) {
				continue;
			}
			$extra = isset( $item['flavor_extra'] ) ? (int) $item['flavor_extra'] : 0;
			if ( $extra <= 0 ) {
				continue;
			}
			$base = (float) $item['data']->get_price();
			$item['data']->set_price( $base + self::storage_to_wc( $extra ) );
		}
	}

	/**
	 * Persist modifiers onto the order line item.
	 *
	 * @param \WC_Order_Item_Product $item          Item.
	 * @param string                 $cart_item_key Key.
	 * @param array<string, mixed>   $values        Cart values.
	 * @param \WC_Order              $order         Order.
	 */
	public function to_order_item( $item, string $cart_item_key, array $values, $order ): void {
		unset( $cart_item_key, $order );
		if ( ! empty( $values['flavor_modifiers'] ) ) {
			$item->add_meta_data( '_flavor_modifiers', $values['flavor_modifiers'], true );
		}
		if ( ! empty( $values['flavor_instructions'] ) ) {
			$item->add_meta_data( '_flavor_instructions', $values['flavor_instructions'], true );
		}
	}

	/**
	 * Validate posted modifier ids against the product catalog.
	 *
	 * @param int                  $product_id Product.
	 * @param array<string, mixed> $posted     Posted selection (ids or names).
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_selection( int $product_id, array $posted ): array {
		$catalog  = self::get_modifiers( $product_id );
		$by_id    = array();
		foreach ( $catalog as $row ) {
			$by_id[ (string) $row['id'] ] = $row;
		}

		$selected = array();
		$ids      = array();
		if ( isset( $posted['ids'] ) && is_array( $posted['ids'] ) ) {
			$ids = $posted['ids'];
		} elseif ( $posted && array_keys( $posted ) === range( 0, count( $posted ) - 1 ) ) {
			$ids = $posted;
		}

		foreach ( $ids as $id ) {
			$id = sanitize_title( (string) $id );
			if ( ! isset( $by_id[ $id ] ) ) {
				continue;
			}
			$row = $by_id[ $id ];
			$selected[] = array(
				'id'         => $row['id'],
				'type'       => $row['type'],
				'type_label' => self::type_label( (string) $row['type'] ),
				'name'       => $row['name'],
				'price'      => (int) $row['price'],
			);
		}

		return $selected;
	}

	/**
	 * Extra in storage units.
	 *
	 * @param array<int, array<string, mixed>> $selected Selection.
	 */
	public static function selection_extra( array $selected ): int {
		$sum = 0;
		foreach ( $selected as $row ) {
			$sum += (int) ( $row['price'] ?? 0 );
		}
		return $sum;
	}

	/**
	 * Convert a storage-unit integer into the WooCommerce catalog currency unit.
	 *
	 * WooCommerce stores whatever the store currency is. If the shop currency is IRT
	 * and we store extras in IRR, divide by 10. This keeps gateway amounts consistent.
	 */
	public static function storage_to_wc( int $stored ): float {
		$wc_code = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRT';
		$storage = Currency::storage_unit();

		if ( 'IRT' === $wc_code && Currency::RIAL === $storage ) {
			return (float) Currency::convert( $stored, Currency::RIAL, Currency::TOMAN );
		}
		if ( 'IRR' === $wc_code && Currency::TOMAN === $storage ) {
			return (float) Currency::convert( $stored, Currency::TOMAN, Currency::RIAL );
		}
		return (float) $stored;
	}

	/**
	 * Human type label.
	 */
	public static function type_label( string $type ): string {
		$map = array(
			'size'    => __( 'اندازه', 'flavor-core' ),
			'topping' => __( 'تاپینگ', 'flavor-core' ),
			'cook'    => __( 'درجه پخت', 'flavor-core' ),
			'removal' => __( 'بدون', 'flavor-core' ),
		);
		return $map[ $type ] ?? $type;
	}
}
