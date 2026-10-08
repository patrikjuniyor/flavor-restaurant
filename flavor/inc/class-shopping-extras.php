<?php
/**
 * The commercial shopping layer: sticky buy bar, quick view, free-delivery
 * progress and trust badges.
 *
 * All four are things paid restaurant themes advertise on their sales page.
 * None of them re-implements pricing, stock or shipping: the bar submits the
 * real WooCommerce form, quick view reads store data, the progress bar can use
 * the shop's own free-shipping rule, and badges are presentation only.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shopping_Extras
 */
class Shopping_Extras {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_footer', array( self::class, 'sticky_bar' ), 7 );
		add_action( 'wp_footer', array( self::class, 'quick_view_modal' ), 8 );

		add_action( 'wp_footer', array( self::class, 'free_delivery_standalone' ), 6 );

		add_action( 'woocommerce_after_shop_loop_item', array( self::class, 'quick_view_button' ), 8 );
		add_action( 'woocommerce_before_cart', array( self::class, 'free_delivery_notice' ) );
		add_action( 'woocommerce_before_checkout_form', array( self::class, 'free_delivery_notice' ) );

		add_action( 'woocommerce_review_order_before_payment', array( self::class, 'trust_badges' ) );
		add_action( 'woocommerce_after_cart_table', array( self::class, 'trust_badges' ) );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'trust_badges_single' ), 35 );
	}

	/**
	 * Free-delivery threshold in storage units (0 disables the bar).
	 */
	public static function threshold(): int {
		$explicit = (int) get_theme_mod( 'flavor_free_delivery_threshold', 0 );

		if ( $explicit > 0 ) {
			return self::to_storage( $explicit );
		}

		if ( 'yes' !== get_theme_mod( 'flavor_free_delivery_detect', 'yes' ) || ! function_exists( 'WC' ) ) {
			return 0;
		}

		$cached = get_transient( 'flavor_free_shipping_min' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$minimum  = 0;
		$zones    = array();

		if ( class_exists( '\WC_Shipping_Zones' ) ) {
			$zones = \WC_Shipping_Zones::get_zones();
			// The "rest of the world" zone is not part of get_zones().
			$zones[] = array( 'shipping_methods' => \WC_Shipping_Zones::get_zone( 0 )->get_shipping_methods() );
		}

		foreach ( $zones as $zone ) {
			foreach ( (array) ( $zone['shipping_methods'] ?? array() ) as $method ) {
				if ( ! is_object( $method ) || 'free_shipping' !== ( $method->id ?? '' ) ) {
					continue;
				}

				$amount = (float) ( $method->min_amount ?? 0 );

				if ( $amount > 0 && ( 0 === $minimum || $amount < $minimum ) ) {
					$minimum = (int) round( $amount );
				}
			}
		}

		$stored = $minimum > 0 ? self::to_storage( $minimum ) : 0;

		set_transient( 'flavor_free_shipping_min', $stored, HOUR_IN_SECONDS );

		return $stored;
	}

	/**
	 * Convert an amount in the shop's display unit into storage units.
	 *
	 * @param int $amount Amount.
	 * @return int
	 */
	public static function to_storage( int $amount ): int {
		if ( class_exists( '\FlavorCore\WooCommerce\Currency' ) ) {
			return \FlavorCore\WooCommerce\Currency::to_storage( $amount, \FlavorCore\WooCommerce\Currency::display_unit() );
		}

		return $amount;
	}

	/**
	 * Format a stored amount for humans.
	 *
	 * @param int $stored Amount in storage units.
	 * @return string
	 */
	public static function money( int $stored ): string {
		if ( class_exists( '\FlavorCore\WooCommerce\Currency' ) ) {
			return wp_strip_all_tags( \FlavorCore\WooCommerce\Currency::format( $stored ) );
		}

		return number_format_i18n( $stored ) . ' ' . __( 'تومان', 'flavor' );
	}

	/**
	 * Public state for the script.
	 *
	 * @return array<string, mixed>
	 */
	public static function free_delivery_state(): array {
		$threshold = self::threshold();

		return array(
			'enabled'   => $threshold > 0,
			'threshold' => $threshold,
			'label'     => self::money( $threshold ),
			'i18n'      => array(
				/* translators: %s: formatted amount. */
				'remaining' => __( '%s دیگر تا ارسال رایگان', 'flavor' ),
				'done'      => __( 'ارسال این سفارش رایگان است.', 'flavor' ),
			),
		);
	}

	/**
	 * Progress markup. Rendered on the cart/checkout pages by WordPress and
	 * moved into the order drawer by the script on the menu page.
	 */
	public static function free_delivery_notice(): void {
		if ( self::threshold() <= 0 ) {
			return;
		}

		// The server already knows the subtotal on cart/checkout, so the bar is
		// correct before any script runs.
		$subtotal = 0;

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$subtotal = (int) round( (float) WC()->cart->get_subtotal() );
		}

		self::free_delivery_markup( $subtotal );
	}

	/**
	 * The menu page renders its own cart drawer; the bar travels with it.
	 */
	public static function free_delivery_standalone(): void {
		if ( is_admin() || ! is_page_template( 'page-templates/template-menu.php' ) ) {
			return;
		}

		self::free_delivery_markup( 0, true, true );
	}

	/**
	 * The bar itself.
	 *
	 * @param int  $subtotal   Subtotal in storage units.
	 * @param bool $live       Whether the script should keep updating it.
	 * @param bool $standalone Render outside the cart, for the drawer to adopt.
	 */
	public static function free_delivery_markup( int $subtotal, bool $live = true, bool $standalone = false ): void {
		$threshold = self::threshold();

		if ( $threshold <= 0 ) {
			return;
		}

		$percent   = min( 100, (int) round( ( $subtotal / $threshold ) * 100 ) );
		$remaining = max( 0, $threshold - $subtotal );
		$text      = $remaining > 0
			? sprintf( /* translators: %s: formatted amount. */ __( '%s دیگر تا ارسال رایگان', 'flavor' ), self::money( $remaining ) )
			: __( 'ارسال این سفارش رایگان است.', 'flavor' );

		echo '<div class="flavor-ship" data-flavor-ship' . ( $live ? ' data-flavor-ship-live="1"' : '' ) . ( $standalone ? ' data-flavor-ship-standalone="1"' : '' )
			. ' data-flavor-ship-threshold="' . esc_attr( (string) $threshold ) . '" hidden>';
		echo '<p class="flavor-ship__text" role="status" aria-live="polite" data-flavor-ship-text>' . esc_html( $text ) . '</p>';
		echo '<div class="flavor-ship__track" role="progressbar" aria-label="' . esc_attr__( 'پیشرفت تا ارسال رایگان', 'flavor' ) . '"'
			. ' aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $percent ) . '">';
		echo '<span class="flavor-ship__fill" style="width:' . esc_attr( (string) $percent ) . '%"></span>';
		echo '</div>';
		echo '<span class="flavor-ship__icon" aria-hidden="true">';
		UI::icon( 'truck', 18 );
		echo '</span></div>';
	}

	/**
	 * Badge list from the Customizer, one `icon|label` per line.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function badges(): array {
		if ( 'no' === get_theme_mod( 'flavor_trust_badges_enable', 'yes' ) ) {
			return array();
		}

		$raw = (string) get_theme_mod( 'flavor_trust_badges', '' );

		if ( '' === trim( $raw ) ) {
			$raw = implode(
				"\n",
				array(
					'shield|پرداخت امن از طریق درگاه بانکی',
					'truck|ارسال با بستهٔ حرارتی و کنترل کیفیت',
					'leaf|مواد اولیهٔ تازه، تهیهٔ روز',
					'clock|پشتیبانی و پیگیری سفارش',
				)
			);
		}

		$allowed = array( 'shield', 'truck', 'leaf', 'clock', 'star', 'tag', 'check', 'lock', 'info', 'heart' );
		$badges  = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$icon  = in_array( $parts[0], $allowed, true ) ? $parts[0] : 'check';
			$label = $parts[1] ?? $parts[0];

			if ( '' === $label ) {
				continue;
			}

			$badges[] = array( 'icon' => $icon, 'label' => $label );
		}

		return array_slice( $badges, 0, 6 );
	}

	/**
	 * Print the badge row.
	 */
	public static function trust_badges(): void {
		$badges = self::badges();

		if ( ! $badges ) {
			return;
		}

		echo '<ul class="flavor-trust" aria-label="' . esc_attr__( 'تضمین‌های خرید', 'flavor' ) . '">';

		foreach ( $badges as $badge ) {
			echo '<li class="flavor-trust__item">' . '<span class="flavor-trust__icon">';
			UI::icon( $badge['icon'], 18 );
			echo '</span><span>' . esc_html( $badge['label'] ) . '</span></li>';
		}

		echo '</ul>';
	}

	/**
	 * Badges under the add-to-cart button on a product page.
	 */
	public static function trust_badges_single(): void {
		if ( 'no' === get_theme_mod( 'flavor_trust_badges_single', 'yes' ) ) {
			return;
		}

		self::trust_badges();
	}

	/**
	 * Quick view trigger on archive cards.
	 */
	public static function quick_view_button(): void {
		if ( 'no' === get_theme_mod( 'flavor_quick_view_enable', 'yes' ) ) {
			return;
		}

		global $product;

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}

		$name = (string) ( method_exists( $product, 'get_name' ) ? $product->get_name() : '' );

		echo '<button type="button" class="flavor-qv-btn" data-flavor-quickview="' . esc_attr( (string) $product->get_id() ) . '" aria-haspopup="dialog" aria-controls="flavor-quickview" aria-expanded="false">';
		UI::icon( 'grid', 16 );
		echo '<span>' . esc_html__( 'نمای سریع', 'flavor' ) . '</span></button>';

		unset( $name );
	}

	/**
	 * Quick-view dialog: content is fetched from the store on demand, so the
	 * markup stays tiny and nothing is duplicated in the page.
	 */
	public static function quick_view_modal(): void {
		if ( 'no' === get_theme_mod( 'flavor_quick_view_enable', 'yes' ) || is_admin() ) {
			return;
		}
		?>
		<div class="flavor-qv" id="flavor-quickview" hidden>
			<div class="flavor-qv__backdrop" data-quickview-close aria-hidden="true"></div>
			<div class="flavor-qv__panel" role="dialog" aria-modal="true" aria-labelledby="flavor-qv-title" tabindex="-1">
				<button type="button" class="flavor-ui-icon-button flavor-qv__close" data-quickview-close aria-label="<?php esc_attr_e( 'بستن نمای سریع', 'flavor' ); ?>"><?php UI::icon( 'close' ); ?></button>
				<div class="flavor-qv__body" id="flavor-qv-body">
					<p class="flavor-qv__loading" role="status"><?php esc_html_e( 'در حال دریافت اطلاعات غذا…', 'flavor' ); ?></p>
				</div>
				<h2 class="screen-reader-text" id="flavor-qv-title"><?php esc_html_e( 'نمای سریع غذا', 'flavor' ); ?></h2>
			</div>
		</div>
		<?php
	}

	/**
	 * Sticky buy bar for single product pages. It submits the page's own form,
	 * so variations, quantity rules and stock validation stay authoritative.
	 */
	public static function sticky_bar(): void {
		if ( 'no' === get_theme_mod( 'flavor_sticky_buy_enable', 'yes' ) || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		global $product;

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}

		$image = $product->get_image_id() ? wp_get_attachment_image_url( (int) $product->get_image_id(), 'thumbnail' ) : '';
		$price = (string) $product->get_price_html();
		?>
		<div class="flavor-buybar" id="flavor-buybar" hidden data-flavor-buybar>
			<div class="flavor-buybar__inner">
				<?php if ( $image ) : ?>
					<img class="flavor-buybar__thumb" src="<?php echo esc_url( $image ); ?>" alt="" width="48" height="48" loading="lazy" />
				<?php endif; ?>
				<div class="flavor-buybar__meta">
					<strong class="flavor-buybar__name"><?php echo esc_html( $product->get_name() ); ?></strong>
					<span class="flavor-buybar__price"><?php echo wp_kses_post( $price ); ?></span>
				</div>
				<button type="button" class="flavor-btn flavor-btn--primary flavor-btn--sm" data-flavor-buybar-submit>
					<?php UI::icon( 'bag', 18 ); ?>
					<span class="flavor-buybar__cta"><?php esc_html_e( 'افزودن به سبد', 'flavor' ); ?></span>
				</button>
			</div>
		</div>
		<?php
	}
}
