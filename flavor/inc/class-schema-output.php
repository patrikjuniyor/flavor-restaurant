<?php
/**
 * Schema.org JSON-LD. Filled from Flavor Core branch data when present.
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
	}

	/**
	 * Print Restaurant / FoodEstablishment JSON-LD.
	 */
	public static function print_jsonld(): void {
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Restaurant',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
		);

		if ( class_exists( '\\FlavorCore\\PostTypes\\BranchPostType' ) ) {
			$branch_id = \FlavorCore\PostTypes\BranchPostType::default_id();
			$data      = $branch_id ? \FlavorCore\PostTypes\BranchPostType::to_array( $branch_id ) : null;
			if ( $data ) {
				$schema['name']          = $data['name'] ?: $schema['name'];
				$schema['telephone']     = $data['phone'];
				$schema['address']       = array(
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
			}
		}

		/**
		 * Filter the Restaurant JSON-LD graph.
		 *
		 * @param array<string, mixed> $schema Schema.
		 */
		$schema = apply_filters( 'flavor_schema_restaurant', $schema );

		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
