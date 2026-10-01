<?php
/**
 * DESTRUCTIVE, explicitly opted-in local WP test only. No orders or messages.
 * FLAVOR_DEMO_QA=1 wp eval-file tests/demos/currency.php
 * Verifies real imports and REST dish details in all four storage/display pairs.
 */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Use a disposable, opted-in local/development WordPress install, never a live site.' );
}
if ( ! function_exists( 'wc_get_product' ) || ! defined( 'FLAVOR_CORE_VERSION' ) ) {
	throw new RuntimeException( 'Activate WooCommerce and Flavor Core.' );
}
require_once FLAVOR_DIR . '/inc/demo-catalog.php';
$catalog  = flavor_demo_catalog();
$original = get_option( \FlavorCore\Support\Settings::OPTION );
$woo_unit = get_option( 'woocommerce_currency' );
$previous = get_option( 'flavor_active_demo', 'catering' );
$fixture  = $catalog['catering'];
// A nonzero fixture proves modifiers do not blindly multiply by ten in IRT.
$fixture['items'][0]['modifiers'] = array( array( 'type' => 'topping', 'name' => 'Currency QA fixture', 'price' => 12000 ) );
function money_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
try {
	foreach ( array( 'irr', 'irt' ) as $storage ) {
		foreach ( array( 'irt', 'irr' ) as $display ) {
			\FlavorCore\Support\Settings::update( array( 'currency_storage' => $storage, 'currency_display' => $display ) );
			update_option( 'woocommerce_currency', strtoupper( $storage ) );
			\Flavor\Demo_Importer::import( $fixture );
			$products = wc_get_products( array( 'status' => 'publish', 'limit' => 8, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
			money_check( 8 === count( $products ), 'Currency fixture lost products.' );
			foreach ( $products as $index => $product ) {
				$row = $fixture['items'][ $index ];
				$expected = 'irr' === $storage ? $row['price'] * 10 : $row['price'];
				money_check( (int) $product->get_price() === $expected, 'Imported price uses the wrong storage unit.' );
				$request = new WP_REST_Request( 'GET', '/flavor/v1/dishes/' . $product->get_id() );
				$request->set_param( 'branch_id', \FlavorCore\PostTypes\BranchPostType::default_id() );
				$response = rest_do_request( $request );
				$body = $response->get_data();
				money_check( 200 === $response->get_status() && ! empty( $body['success'] ), 'Real dish API request failed.' );
				$dish = $body['data'];
				money_check( $dish['price'] === $expected, 'API raw price differs from imported product.' );
				money_check( \Flavor\Bespoke_Demos::price( $product->get_price() ) === esc_html( $dish['price_html'] ), 'Homepage price label/amount differs from the real API.' );
				$expected_display = 'irr' === $display ? $row['price'] * 10 : $row['price'];
				money_check( \FlavorCore\WooCommerce\Currency::to_display( $expected ) === $expected_display, 'Display conversion is numerically wrong.' );
				if ( 0 === $index ) {
					$extra = $dish['modifier_groups'][0]['options'][0];
					money_check( $extra['price'] === ( 'irr' === $storage ? 120000 : 12000 ), 'Modifier storage amount is wrong.' );
					money_check( $extra['price_html'] === '+' . \FlavorCore\WooCommerce\Currency::format( $extra['price'] ), 'Modifier display amount is wrong.' );
				}
			}
			echo 'PASS currency ' . $storage . ' → ' . $display . ': 8 imported prices, real REST details, landing labels and nonzero modifier.' . PHP_EOL;
		}
	}
} finally {
	if ( false === $original ) { delete_option( \FlavorCore\Support\Settings::OPTION ); }
	else { update_option( \FlavorCore\Support\Settings::OPTION, $original ); }
	update_option( 'woocommerce_currency', $woo_unit );
	// Remove the test-only modifier and return to a real, unmodified demo pack.
	\Flavor\Demo_Importer::import( $catalog[ $previous ] ?? $catalog['catering'] );
}
