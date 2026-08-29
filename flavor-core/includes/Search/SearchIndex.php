<?php
/**
 * Menu search index.
 *
 * Builds one normalised, cached document per product so AJAX search does not
 * hammer WooCommerce on every keystroke. Cache is invalidated when a product,
 * a category or a menu version changes.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Search;

use FlavorCore\Menu\AvailabilityManager;
use FlavorCore\Menu\MenuScheduler;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\PersianText;
use FlavorCore\WooCommerce\Currency;
use FlavorCore\WooCommerce\ProductModifiers;

defined( 'ABSPATH' ) || exit;

/**
 * Class SearchIndex
 */
class SearchIndex {

	/**
	 * Transient prefix.
	 */
	private const CACHE_PREFIX = 'flavor_search_idx_';

	/**
	 * Cache lifetime in seconds.
	 */
	private const CACHE_TTL = 900;

	/**
	 * Runtime memo.
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private static array $memo = array();

	/**
	 * Hooks that invalidate the index.
	 */
	public function hooks(): void {
		add_action( 'save_post_product', array( self::class, 'flush' ) );
		add_action( 'deleted_post', array( self::class, 'flush' ) );
		add_action( 'woocommerce_update_product', array( self::class, 'flush' ) );
		add_action( 'edited_product_cat', array( self::class, 'flush' ) );
		add_action( 'flavor_core_menu_version_bumped', array( self::class, 'flush' ) );
		add_action( 'flavor_core_availability_changed', array( self::class, 'flush' ) );
	}

	/**
	 * Drop every cached index.
	 */
	public static function flush(): void {
		self::$memo = array();
		global $wpdb;

		if ( isset( $wpdb ) && $wpdb instanceof \wpdb ) {
			$like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
			foreach ( (array) $names as $name ) {
				delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
			}
		}
	}

	/**
	 * Cached documents for a branch.
	 *
	 * @param int $branch_id Branch.
	 * @return array<int, array<string, mixed>>
	 */
	public static function documents( int $branch_id ): array {
		if ( isset( self::$memo[ $branch_id ] ) ) {
			return self::$memo[ $branch_id ];
		}

		$key    = self::CACHE_PREFIX . $branch_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			self::$memo[ $branch_id ] = $cached;
			return $cached;
		}

		$docs = self::build( $branch_id );
		set_transient( $key, $docs, self::CACHE_TTL );
		self::$memo[ $branch_id ] = $docs;

		return $docs;
	}

	/**
	 * Build documents from WooCommerce.
	 *
	 * @param int $branch_id Branch.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build( int $branch_id ): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$limit = (int) apply_filters( 'flavor_core_search_index_limit', 500 );

		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => $limit,
				'type'   => array( 'simple', 'variable' ),
				'return' => 'objects',
			)
		);

		$docs = array();

		foreach ( (array) $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}
			$doc = self::document( $product, $branch_id );
			if ( $doc ) {
				$docs[] = $doc;
			}
		}

		return apply_filters( 'flavor_core_search_documents', $docs, $branch_id );
	}

	/**
	 * One product → one searchable document.
	 *
	 * @param \WC_Product $product   Product.
	 * @param int         $branch_id Branch.
	 * @return array<string, mixed>|null
	 */
	private static function document( $product, int $branch_id ): ?array {
		$id    = (int) $product->get_id();
		$state = MenuScheduler::product_state( $branch_id, $id );

		if ( empty( $state['visible'] ) ) {
			return null;
		}

		$categories = array();
		$terms      = get_the_terms( $id, 'product_cat' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = array(
					'id'   => (int) $term->term_id,
					'name' => (string) $term->name,
					'slug' => (string) $term->slug,
				);
			}
		}

		$tags      = array();
		$tag_terms = get_the_terms( $id, 'product_tag' );
		if ( is_array( $tag_terms ) ) {
			foreach ( $tag_terms as $term ) {
				$tags[] = (string) $term->name;
			}
		}

		$dietary = get_post_meta( $id, ProductModifiers::META_DIETARY, true );
		$dietary = is_array( $dietary ) ? $dietary : array();

		$modifiers = ProductModifiers::get_modifiers( $id );
		$mod_names = array();
		foreach ( (array) $modifiers as $modifier ) {
			if ( isset( $modifier['name'] ) ) {
				$mod_names[] = (string) $modifier['name'];
			}
		}

		$name  = (string) $product->get_name();
		$short = wp_strip_all_tags( (string) $product->get_short_description() );
		$long  = wp_strip_all_tags( (string) $product->get_description() );

		$price     = (int) round( (float) $product->get_price() );
		$wc_code   = strtoupper( (string) get_woocommerce_currency() );
		$from_unit = 'IRR' === $wc_code ? Currency::RIAL : Currency::TOMAN;
		$stored    = Currency::to_storage( $price, $from_unit );

		$haystack = implode(
			' ',
			array_merge(
				array( $name, $short, PersianText::substr( $long, 0, 400 ) ),
				wp_list_pluck( $categories, 'name' ),
				$tags,
				$dietary,
				$mod_names
			)
		);

		return array(
			'id'            => $id,
			'name'          => $name,
			'name_norm'     => PersianText::normalize( $name ),
			'name_tokens'   => PersianText::tokens( $name ),
			'text_tokens'   => PersianText::tokens( $haystack ),
			'cat_tokens'    => PersianText::tokens( implode( ' ', wp_list_pluck( $categories, 'name' ) ) ),
			'tag_tokens'    => PersianText::tokens( implode( ' ', $tags ) ),
			'short'         => $short,
			'image'         => (string) ( wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: '' ),
			'permalink'     => (string) get_permalink( $id ),
			'price'         => $stored,
			'price_html'    => Currency::format( $stored ),
			'prep_time'     => (int) get_post_meta( $id, ProductModifiers::META_PREP, true ),
			'calories'      => (int) get_post_meta( $id, ProductModifiers::META_CALORIES, true ),
			'dietary'       => array_values( $dietary ),
			'categories'    => $categories,
			'available'     => AvailabilityManager::is_available( $branch_id, $id ),
			'in_schedule'   => ! empty( $state['now'] ),
			'available_at'  => (string) ( $state['next'] ?? '' ),
			'popularity'    => (int) get_post_meta( $id, 'total_sales', true ),
			'rating'        => (float) $product->get_average_rating(),
			'branch_id'     => $branch_id,
		);
	}

	/**
	 * Resolve a usable branch id.
	 *
	 * @param int $branch_id Requested branch.
	 */
	public static function resolve_branch( int $branch_id ): int {
		return $branch_id > 0 ? $branch_id : BranchPostType::default_id();
	}
}
