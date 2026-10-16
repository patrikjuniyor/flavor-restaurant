<?php
/**
 * Schema.org JSON-LD, SEO Meta Descriptions, Canonical URLs, and Open Graph.
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
		add_action( 'wp_head', array( self::class, 'meta_description' ), 1 );
		add_action( 'wp_head', array( self::class, 'canonical_url' ), 2 );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'sitemaps_post_types' ) );
	}

	/**
	 * Determine if a known 3rd-party SEO plugin is active.
	 *
	 * @return bool
	 */
	public static function has_third_party_seo_plugin(): bool {
		return defined( 'WPSEO_VERSION' ) // Yoast SEO
			|| defined( 'RANK_MATH_VERSION' ) // Rank Math
			|| defined( 'AIOSEO_VERSION' ) // All in One SEO
			|| defined( 'SEOPRESS_VERSION' ); // SEOPress
	}

	/**
	 * Get unique meta description for current view (capped at 160 characters).
	 *
	 * @return string
	 */
	public static function get_meta_description(): string {
		$desc = '';

		if ( is_front_page() || is_home() ) {
			$desc = get_bloginfo( 'description' );
			if ( empty( $desc ) ) {
				$desc = get_bloginfo( 'name' );
			}
		} elseif ( is_singular() ) {
			$post_id = get_queried_object_id();
			$post = get_post( $post_id );
			if ( $post ) {
				if ( ! empty( $post->post_excerpt ) ) {
					$desc = $post->post_excerpt;
				} else {
					$desc = $post->post_content;
				}
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term_desc = term_description();
			if ( ! empty( $term_desc ) ) {
				$desc = $term_desc;
			} else {
				$single_term = single_term_title( '', false );
				$desc = sprintf(
					/* translators: %s: Term title */
					__( 'مشاهده و سفارش محصولات دسته %s', 'flavor' ),
					$single_term
				);
			}
		} elseif ( is_search() ) {
			$query = get_search_query();
			$desc  = sprintf(
				/* translators: %s: Search query */
				__( 'نتایج جست‌وجو برای %s در منوی رستوران', 'flavor' ),
				$query
			);
		} elseif ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$desc      = sprintf(
				/* translators: %s: Post type archive name */
				__( 'آرشیو %s رستوران', 'flavor' ),
				post_type_archive_title( '', false )
			);
		}

		$desc = wp_strip_all_tags( (string) $desc );
		$desc = preg_replace( '/\s+/', ' ', $desc );
		$desc = trim( $desc );

		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $desc, 'UTF-8' ) > 160 : strlen( $desc ) > 160 ) {
			$desc = function_exists( 'flavor_substr' ) ? flavor_substr( $desc, 0, 157 ) . '...' : substr( $desc, 0, 157 ) . '...';
		}

		return apply_filters( 'flavor_meta_description', $desc );
	}

	/**
	 * Print meta description tag if no third-party SEO plugin is handling it.
	 */
	public static function meta_description(): void {
		if ( self::has_third_party_seo_plugin() ) {
			return;
		}

		$description = self::get_meta_description();
		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}
	}

	/**
	 * Strip noisy storefront query arguments from a URL.
	 *
	 * @param string $url URL to clean.
	 * @return string
	 */
	public static function clean_canonical_url( string $url ): string {
		$noisy_params = array(
			'cat',
			'view',
			'sort',
			'dietary',
			'order_mode',
			'flavor_nonce',
			'preview',
			'add-to-cart',
			'removed_item',
		);
		return remove_query_arg( $noisy_params, $url );
	}

	/**
	 * Get canonical URL for current request, stripping noisy storefront filter query params.
	 *
	 * @return string
	 */
	public static function get_canonical_url(): string {
		if ( is_front_page() ) {
			$canonical = home_url( '/' );
		} elseif ( is_singular() ) {
			$canonical = get_permalink();
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$canonical = $term ? get_term_link( $term ) : home_url( '/' );
			if ( is_wp_error( $canonical ) ) {
				$canonical = home_url( '/' );
			}
		} elseif ( is_post_type_archive() ) {
			$canonical = get_post_type_archive_link( get_query_var( 'post_type' ) ) ?: home_url( '/' );
		} else {
			$canonical = home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) );
		}

		$canonical = self::clean_canonical_url( $canonical );

		return apply_filters( 'flavor_canonical_url', $canonical );
	}

	/**
	 * Print clean canonical URL tag.
	 */
	public static function canonical_url(): void {
		if ( self::has_third_party_seo_plugin() ) {
			return;
		}

		$canonical = self::get_canonical_url();
		if ( '' !== $canonical ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical ) );
		}
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

		// If viewing a single product, also output MenuItem rich result
		if ( function_exists( 'is_product' ) && is_product() ) {
			$single_menu_item = self::single_product_menu_item();
			if ( $single_menu_item ) {
				$graph[] = $single_menu_item;
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
	 * Open Graph / Twitter Card markup.
	 */
	public static function open_graph(): void {
		if ( self::has_third_party_seo_plugin() ) {
			return;
		}

		$title = wp_get_document_title();
		$desc  = self::get_meta_description();
		$url   = self::get_canonical_url();
		$img   = '';

		if ( is_singular() && has_post_thumbnail() ) {
			$img = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		}
		if ( ! $img ) {
			$img = \Flavor\Bespoke_Demos::asset( 'hero.jpg' );
		}

		$og_type = 'website';
		if ( is_front_page() ) {
			$og_type = 'restaurant';
		} elseif ( function_exists( 'is_product' ) && is_product() ) {
			$og_type = 'product';
		} elseif ( is_singular( 'post' ) ) {
			$og_type = 'article';
		}

		printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $og_type ) );
		printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
		printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		if ( ! empty( $img ) ) {
			printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $img ) );
		}

		// Product-specific Open Graph tags
		if ( function_exists( 'is_product' ) && is_product() && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				printf( '<meta property="product:price:amount" content="%s" />' . "\n", esc_attr( (string) $product->get_price() ) );
				printf( '<meta property="product:price:currency" content="%s" />' . "\n", esc_attr( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRR' ) );
			}
		}

		// Article published time
		if ( is_singular( 'post' ) ) {
			$post = get_post( get_queried_object_id() );
			if ( $post ) {
				printf( '<meta property="article:published_time" content="%s" />' . "\n", esc_attr( get_the_date( 'c', $post ) ) );
			}
		}

		// Twitter Cards
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
		printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $desc ) );
		if ( ! empty( $img ) ) {
			printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $img ) );
		}
	}

	/**
	 * Core XML Sitemaps filter - ensure custom post types are included.
	 *
	 * @param array<string, mixed> $post_types Post types for sitemap.
	 * @return array<string, mixed>
	 */
	public static function sitemaps_post_types( array $post_types ): array {
		if ( post_type_exists( 'branch' ) ) {
			$post_types['branch'] = true;
		}
		return $post_types;
	}

	/**
	 * Single product MenuItem schema.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function single_product_menu_item(): ?array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product ) {
			return null;
		}

		$schema = array(
			'@type'       => 'MenuItem',
			'@id'         => get_permalink( $product->get_id() ) . '#menuitem',
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'offers'      => array(
				'@type'         => 'Offer',
				'price'         => $product->get_price(),
				'priceCurrency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRR',
				'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'           => get_permalink( $product->get_id() ),
			),
		);

		if ( has_post_thumbnail( $product->get_id() ) ) {
			$image_url = get_the_post_thumbnail_url( $product->get_id(), 'large' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// Prompt 76: AggregateRating ONLY when real ratings exist (no fake rating claims)
		$rating_count = (int) $product->get_rating_count();
		$rating_avg   = (float) $product->get_average_rating();
		if ( $rating_count > 0 && $rating_avg > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating_avg,
				'reviewCount' => $rating_count,
			);
		}

		return apply_filters( 'flavor_schema_menu_item', $schema, $product );
	}

	/**
	 * Restaurant node.
	 *
	 * @return array<string, mixed>
	 */
	public static function restaurant(): array {
		$name    = (string) get_theme_mod( 'flavor_hero_title', get_bloginfo( 'name' ) );
		$phone   = (string) get_theme_mod( 'flavor_phone', '' );
		$address = (string) get_theme_mod( 'flavor_address', '' );
		$city    = (string) get_theme_mod( 'flavor_city', '' );

		$lat     = '';
		$lng     = '';
		$hours_specs = array();

		if ( class_exists( '\FlavorCore\PostTypes\BranchPostType' ) ) {
			$branch_id = \FlavorCore\PostTypes\BranchPostType::default_id();
			$data      = $branch_id ? \FlavorCore\PostTypes\BranchPostType::to_array( $branch_id ) : null;
			if ( $data ) {
				$name    = ! empty( $data['name'] ) ? $data['name'] : $name;
				$phone   = ! empty( $data['phone'] ) ? $data['phone'] : $phone;
				$address = ! empty( $data['address'] ) ? $data['address'] : $address;
				$city    = ! empty( $data['city'] ) ? $data['city'] : $city;
				$lat     = ! empty( $data['lat'] ) ? $data['lat'] : $lat;
				$lng     = ! empty( $data['lng'] ) ? $data['lng'] : $lng;

				if ( class_exists( '\FlavorCore\Reservation\SlotCalculator' ) && class_exists( '\FlavorCore\Database\Schema' ) ) {
					global $wpdb;
					$table = \FlavorCore\Database\Schema::table( 'flavor_branch_hours' );
					if ( $wpdb && $table ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$rows = $wpdb->get_results(
							$wpdb->prepare(
								// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- {$table} comes from Schema::table(), a trusted prefix-derived identifier (never user input); the branch id is the only bind value.
								"SELECT day_of_week, open_time, close_time, is_closed FROM {$table} WHERE branch_id = %d AND is_closed = 0",
								$branch_id
							),
							ARRAY_A
						);
						if ( ! empty( $rows ) ) {
							$dow_map = array(
								0 => 'Saturday',
								1 => 'Sunday',
								2 => 'Monday',
								3 => 'Tuesday',
								4 => 'Wednesday',
								5 => 'Thursday',
								6 => 'Friday',
							);
							foreach ( $rows as $row ) {
								$dow_num  = (int) ( $row['day_of_week'] ?? 0 );
								$day_name = $dow_map[ $dow_num ] ?? null;
								if ( $day_name && ! empty( $row['open_time'] ) && ! empty( $row['close_time'] ) ) {
									$hours_specs[] = array(
										'@type'     => 'OpeningHoursSpecification',
										'dayOfWeek' => $day_name,
										'opens'     => substr( (string) $row['open_time'], 0, 5 ),
										'closes'    => substr( (string) $row['close_time'], 0, 5 ),
									);
								}
							}
						}
					}
				}
			}
		}

		$schema = array(
			'@type' => array( 'Restaurant', 'FoodEstablishment' ),
			'@id'   => home_url( '/#restaurant' ),
			'name'  => $name,
			'url'   => home_url( '/' ),
		);

		if ( '' !== $phone ) {
			$schema['telephone'] = $phone;
		}

		if ( '' !== $address || '' !== $city ) {
			$schema['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $address,
				'addressLocality' => $city,
				'addressCountry'  => 'IR',
			);
		}

		if ( '' !== $lat && '' !== $lng ) {
			$schema['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $lat,
				'longitude' => $lng,
			);
		}

		$schema['servesCuisine']      = 'Persian';
		$schema['currenciesAccepted'] = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'IRR';

		if ( ! empty( $hours_specs ) ) {
			$schema['openingHoursSpecification'] = $hours_specs;
		}

		// Prompt 76: AggregateRating ONLY when real approved comments exist (no fake rating claims)
		$rating_data = self::get_site_aggregate_rating();
		if ( null !== $rating_data ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating_data['ratingValue'],
				'reviewCount' => $rating_data['reviewCount'],
			);
		}

		return apply_filters( 'flavor_schema_restaurant', $schema );
	}

	/**
	 * Compute aggregate rating for restaurant ONLY if real comments/reviews exist.
	 *
	 * @return array{ratingValue: float, reviewCount: int}|null
	 */
	public static function get_site_aggregate_rating(): ?array {
		if ( ! function_exists( 'get_comments' ) ) {
			return null;
		}

		$comments = get_comments(
			array(
				'status' => 'approve',
				'type'   => 'review',
				'number' => 50,
			)
		);

		if ( empty( $comments ) ) {
			return null;
		}

		$total_score = 0;
		$counted     = 0;

		foreach ( $comments as $comment ) {
			$rating = get_comment_meta( $comment->comment_ID, 'rating', true );
			if ( is_numeric( $rating ) && (float) $rating > 0 ) {
				$total_score += (float) $rating;
				++$counted;
			}
		}

		if ( 0 === $counted ) {
			return null;
		}

		return array(
			'ratingValue' => round( $total_score / $counted, 1 ),
			'reviewCount' => $counted,
		);
	}


	/**
	 * Menu with up to 20 items (menu page / front only).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function menu(): ?array {
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
