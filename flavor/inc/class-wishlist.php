<?php
/**
 * Wishlist without a companion plugin.
 *
 * Paid themes ship wishlist as a paid add-on (YITH / WPC) that adds a second
 * cart implementation to the site. Flavor keeps a single source of truth: the
 * list lives in the browser, and every write goes through the same add-to-cart
 * flow the menu already uses, so modifiers, availability and pricing are still
 * decided by the server.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Wishlist
 */
class Wishlist {

	/**
	 * localStorage key.
	 */
	const STORAGE_KEY = 'flavorWishlist';

	/**
	 * Guard rail so a runaway list cannot fill the storage quota.
	 */
	const MAX_ITEMS = 60;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_footer', array( self::class, 'panel' ), 6 );
		add_action( 'woocommerce_after_shop_loop_item', array( self::class, 'loop_button' ), 6 );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'single_button' ), 31 );
	}

	/**
	 * Is the feature on?
	 */
	public static function enabled(): bool {
		return 'no' !== get_theme_mod( 'flavor_wishlist_enable', 'yes' );
	}

	/**
	 * Build the data a card hands to the list.
	 *
	 * @param int    $id    Product id.
	 * @param string $name  Product name.
	 * @param string $price Price markup (tags are stripped).
	 * @param string $image Image URL.
	 * @param string $url   Permalink.
	 * @return array<string, string>
	 */
	public static function item( int $id, string $name = '', string $price = '', string $image = '', string $url = '' ): array {
		return array(
			'id'    => (string) $id,
			'name'  => $name,
			'price' => wp_strip_all_tags( $price ),
			'image' => $image,
			'url'   => $url,
		);
	}

	/**
	 * Snapshot of a WooCommerce product, using core APIs so the card can be
	 * rendered anywhere (archive, single, widget).
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, string>
	 */
	public static function product_item( $product ): array {
		$image = '';

		if ( is_object( $product ) && method_exists( $product, 'get_image_id' ) && $product->get_image_id() ) {
			$image = (string) wp_get_attachment_image_url( $product->get_image_id(), 'medium' );
		}

		return self::item(
			(int) ( is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : 0 ),
			is_object( $product ) && method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '',
			is_object( $product ) && method_exists( $product, 'get_price_html' ) ? (string) $product->get_price_html() : '',
			$image,
			is_object( $product ) && method_exists( $product, 'get_permalink' ) ? (string) $product->get_permalink() : ''
		);
	}

	/**
	 * Heart button markup.
	 *
	 * @param array<string, string> $item    Item data.
	 * @param string                $context loop | single | menu.
	 * @param bool                  $labelled Print the visible label.
	 */
	public static function button( array $item, string $context = 'loop', bool $labelled = false ): void {
		if ( ! self::enabled() || '' === ( $item['id'] ?? '' ) ) {
			return;
		}

		$name = '' !== ( $item['name'] ?? '' ) ? (string) $item['name'] : __( 'این غذا', 'flavor' );

		echo '<button type="button" class="flavor-wish-btn flavor-wish-btn--' . esc_attr( sanitize_html_class( $context ) ) . '"'
			. ' data-wishlist-toggle'
			. ' data-wish-id="' . esc_attr( $item['id'] ) . '"'
			. ' data-wish-name="' . esc_attr( $item['name'] ) . '"'
			. ' data-wish-price="' . esc_attr( $item['price'] ) . '"'
			. ' data-wish-image="' . esc_url( $item['image'] ) . '"'
			. ' data-wish-url="' . esc_url( $item['url'] ) . '"'
			. ' aria-pressed="false"'
			. ' aria-label="' . esc_attr( sprintf( /* translators: %s: dish name. */ __( 'افزودن %s به علاقه‌مندی‌ها', 'flavor' ), $name ) ) . '">';

		UI::icon( 'heart', 20 );

		if ( $labelled ) {
			echo '<span class="flavor-wish-btn__label">' . esc_html__( 'ذخیره در علاقه‌مندی‌ها', 'flavor' ) . '</span>';
		}

		echo '</button>';
	}

	/**
	 * Button on shop / archive cards.
	 */
	public static function loop_button(): void {
		global $product;

		if ( ! self::enabled() || ! is_object( $product ) ) {
			return;
		}

		self::button( self::product_item( $product ), 'loop' );
	}

	/**
	 * Button next to the single-product add-to-cart.
	 */
	public static function single_button(): void {
		global $product;

		if ( ! self::enabled() || ! is_object( $product ) ) {
			return;
		}

		self::button( self::product_item( $product ), 'single', true );
	}

	/**
	 * Header entry point with the live counter.
	 */
	public static function header_button(): void {
		if ( ! self::enabled() ) {
			return;
		}

		echo '<button type="button" class="flavor-header__wish" data-wishlist-open aria-haspopup="dialog" aria-controls="flavor-wishlist" aria-expanded="false" aria-label="' . esc_attr__( 'علاقه‌مندی‌ها', 'flavor' ) . '">';
		UI::icon( 'heart', 20 );
		echo '<span class="flavor-header__wish-label">' . esc_html__( 'علاقه‌مندی‌ها', 'flavor' ) . '</span>';
		echo '<span class="flavor-wish-count" data-wishlist-count hidden>۰</span></button>';
	}

	/**
	 * Slide-in list. Rendered in the footer on every page, including pages
	 * where WooCommerce is absent (the list keeps working from the snapshot).
	 */
	public static function panel(): void {
		if ( ! self::enabled() || is_admin() ) {
			return;
		}

		$config = array(
			'key'      => self::STORAGE_KEY,
			'max'      => self::MAX_ITEMS,
			'canAdd'   => function_exists( 'wc_get_product' ),
			'i18n'     => array(
				'added'    => __( 'به علاقه‌مندی‌ها اضافه شد.', 'flavor' ),
				'removed'  => __( 'از علاقه‌مندی‌ها حذف شد.', 'flavor' ),
				'empty'    => __( 'هنوز غذایی را ذخیره نکرده‌اید.', 'flavor' ),
				'full'     => __( 'فهرست علاقه‌مندی‌ها پر است؛ ابتدا چند مورد را حذف کنید.', 'flavor' ),
				'limit'    => sprintf( /* translators: %d: number. */ __( 'حداکثر %d مورد قابل ذخیره است.', 'flavor' ), self::MAX_ITEMS ),
				'adding'   => __( 'در حال افزودن به سبد…', 'flavor' ),
				'choose'   => __( 'برای این غذا باید ترکیبات را انتخاب کنید.', 'flavor' ),
				'addOne'   => __( 'افزودن به سبد', 'flavor' ),
				'addAll'   => __( 'افزودن همه به سبد', 'flavor' ),
				'partial'  => __( 'برای بعضی موارد انتخاب ترکیبات لازم است؛ آن‌ها را از فهرست باز کنید.', 'flavor' ),
				'offline'  => __( 'افزودن به سبد در این صفحه ممکن نیست؛ صفحهٔ غذا را باز کنید.', 'flavor' ),
				'openPage' => __( 'صفحهٔ غذا', 'flavor' ),
			),
		);
		?>
		<div class="flavor-wish" id="flavor-wishlist" hidden data-flavor-wishlist="<?php echo esc_attr( (string) wp_json_encode( $config ) ); ?>">
			<div class="flavor-wish__backdrop" data-wishlist-close aria-hidden="true"></div>
			<aside class="flavor-wish__panel" role="dialog" aria-modal="true" aria-labelledby="flavor-wish-title" tabindex="-1">
				<header class="flavor-wish__header">
					<div>
						<span class="flavor-ui-overline"><?php esc_html_e( 'انتخاب‌های ذخیره‌شده', 'flavor' ); ?></span>
						<h2 id="flavor-wish-title"><?php esc_html_e( 'علاقه‌مندی‌های من', 'flavor' ); ?></h2>
					</div>
					<button type="button" class="flavor-ui-icon-button" data-wishlist-close aria-label="<?php esc_attr_e( 'بستن علاقه‌مندی‌ها', 'flavor' ); ?>"><?php UI::icon( 'close' ); ?></button>
				</header>
				<p class="flavor-wish__empty" id="flavor-wish-empty"><?php UI::icon( 'heart', 34 ); ?><span><?php esc_html_e( 'هنوز غذایی را ذخیره نکرده‌اید. با زدن قلب روی هر غذا، اینجا نگه‌داری می‌شود.', 'flavor' ); ?></span></p>
				<p class="flavor-wish__status screen-reader-text" id="flavor-wish-status" role="status" aria-live="polite"></p>
				<ul class="flavor-wish__list" id="flavor-wish-items"></ul>
				<footer class="flavor-wish__footer" id="flavor-wish-footer" hidden>
					<button type="button" class="flavor-btn flavor-btn--primary flavor-btn--full" id="flavor-wish-add-all"><?php esc_html_e( 'افزودن همه به سبد', 'flavor' ); ?></button>
					<button type="button" class="flavor-ui-text-link" id="flavor-wish-clear"><?php esc_html_e( 'پاک کردن فهرست', 'flavor' ); ?></button>
				</footer>
			</aside>
		</div>
		<?php
	}
}
