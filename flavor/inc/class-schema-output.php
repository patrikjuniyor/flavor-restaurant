<?php
/**
 * Schema.org JSON-LD: Restaurant, Menu, BreadcrumbList.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema_Output
 */
class Schema_Output {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'print_jsonld' ), 30 );
		add_action( 'wp_head', array( self::class, 'open_graph' ), 5 );
	}

	/**
	 * Print graph.
	 */
	public static function print_jsonld(): void {
		$graph = array( self::restaurant(), self::breadcrumbs() );
		if ( is_page_template( 'page-templates/template-menu.php' ) || is_front_page() ) {
			$menu = self::menu();
			if ( $menu ) {
				$graph[] = $menu;
			}
		}
		$graph = array_values( array_filter( $graph ) );

		/**
		 * @param array<int, array<string, mixed>> $graph Graph.
		 */
		$graph = apply_filters( 'flavor_schema_graph', $graph );

		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Open Graph / Twitter.
	 */
	public static function open_graph(): void {
		$title = wp_get_document_title();
		$desc  = get_bloginfo( 'description' );
		$url   = is_singular() ? get_permalink() : home_url( '/' );
		$img   = '';
		if ( is_singular() && has_post_thumbnail() ) {
			$img = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		}
		if ( ! $img ) {
			$img = FLAVOR_URI . '/demos/' . Design::current_skin() . '/hero.jpg';
		}
		printf( '<meta property="og:type" content="%s" />' . "\n", is_front_page() ? 'restaurant' : 'website' );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $img ) );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
	}

	/**
	 * Restaurant node.
	 *
	 * @return array<string, mixed>
	 */
	private static function restaurant(): array {
		$schema = array(
			'@type' => array( 'Restaurant', 'FoodEstablishment' ),
			'@id'   => home_url( '/#restaurant' ),
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		if ( class_exists( '\\FlavorCore\\PostTypes\\BranchPostType' ) ) {
			$branch_id = \FlavorCore\PostTypes\BranchPostType::default_id();
			$data      = $branch_id ? \FlavorCore\PostTypes\BranchPostType::to_array( $branch_id ) : null;
			if ( $data ) {
				$schema['name']      = $data['name'] ?: $schema['name'];
				$schema['telephone'] = $data['phone'];
				$schema['address']   = array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $data['address'],
					'addressLocality' => $data['city'],
					'addressRegion'   => $data['province'],
					'addressCountry'  => 'IR',
				);
				if ( $data['lat'] && $data['lng'] ) {
					$schema['geo'] = array(
						'@type'     => 'GeoCoordinates',
						'latitude'  => $data['lat'],
						'longitude' => $data['lng'],
					);
				}
				$schema['servesCuisine'] = 'Persian';
				$schema['currenciesAccepted'] = 'IRR';
				$schema['openingHoursSpecification'] = array(
					array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday' ),
						'opens'     => '12:00',
						'closes'    => '23:00',
					),
				);
			}
		}
		return apply_filters( 'flavor_schema_restaurant', $schema );
	}

	/**
	 * Menu with up to 20 items (menu page / front only).
	 *
	 * @return array<string, mixed>|null
	 */
	private static function menu(): ?array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return null;
		}
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 20,
				'type'   => 'simple',
			)
		);
		if ( empty( $products ) ) {
			return null;
		}
		$items = array();
		foreach ( $products as $p ) {
			$items[] = array(
				'@type'       => 'MenuItem',
				'name'        => $p->get_name(),
				'description' => wp_strip_all_tags( $p->get_short_description() ),
				'offers'      => array(
					'@type'         => 'Offer',
					'price'         => $p->get_price(),
					'priceCurrency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRR',
				),
			);
		}
		return array(
			'@type'           => 'Menu',
			'@id'             => home_url( '/#menu' ),
			'hasMenuSection'  => array(
				array(
					'@type'        => 'MenuSection',
					'name'         => __( 'منو', 'flavor' ),
					'hasMenuItem'  => $items,
				),
			),
		);
	}

	/**
	 * BreadcrumbList.
	 *
	 * @return array<string, mixed>
	 */
	private static function breadcrumbs(): array {
		$crumbs = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => __( 'خانه', 'flavor' ),
				'item'     => home_url( '/' ),
			),
		);
		if ( ! is_front_page() && is_singular() ) {
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => 2,
				'name'     => get_the_title(),
				'item'     => get_permalink(),
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $crumbs,
		);
	}
}
