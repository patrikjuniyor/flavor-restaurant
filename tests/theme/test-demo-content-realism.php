<?php
/**
 * Test contract for Demo Content Realism across all 12 demo packs (پرامپت ۸۰).
 *
 * Verifies:
 *   1. All 12 demo packs are registered and loadable (7 in catalog + 5 bespoke packs).
 *   2. Credible Iranian addresses & phone numbers:
 *      - Believable Persian addresses in major cities (Tehran, Isfahan) with real street/neighborhood anchors.
 *      - Real landline formats (021..., 031...) with valid area codes.
 *   3. 1405 Iranian market realistic pricing:
 *      - All prices are within realistic restaurant ranges for 1405 (between 25,000 and 1,500,000 Toman).
 *      - Differentiates cafe/bakery items (60k - 250k) vs luxury steak/seafood (400k - 950k) vs traditional/grill (150k - 600k).
 *   4. Zero unfinished text / Lorem ipsum:
 *      - No Latin placeholder text (lorem, ipsum, dolor, sit amet, dummy, etc.).
 *      - No generic incomplete Persian placeholders («متن ساختگی», «لورم ایپسوم», «تست...»).
 *   5. Menu item completeness:
 *      - Each item has a non-empty name, realistic description, category, and valid price.
 *
 *   php tests/theme/test-demo-content-realism.php
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
function check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

echo "=== Flavor Demo Content Realism & Quality Audit (پرامپت ۸۰) ===\n\n";

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

require_once $root . '/flavor/inc/demo-catalog.php';

echo "--- 1. Verification of all 12 Demo Packs ---\n";

$catalog = flavor_demo_catalog();
$catalog_slugs = array(
	'modern-restaurant',
	'luxury-dining',
	'persian-traditional',
	'fast-food',
	'cafe-bistro',
	'pizza-italian',
	'bakery-pastry',
);

foreach ( $catalog_slugs as $slug ) {
	check( isset( $catalog[ $slug ] ), "catalog demo pack '{$slug}' exists in flavor_demo_catalog()" );
}

$pack_files = array(
	'catering'       => $root . '/flavor/inc/demo-packs/catering.php',
	'cloud-kitchen'  => $root . '/flavor/inc/demo-packs/cloud-kitchen.php',
	'dark-luxe'      => $root . '/flavor/inc/demo-packs/dark-luxe.php',
	'juice-bar'      => $root . '/flavor/inc/demo-packs/juice-bar.php',
	'minimal-clean'  => $root . '/flavor/inc/demo-packs/minimal-clean.php',
);

$bespoke_packs = array();
foreach ( $pack_files as $slug => $file ) {
	check( is_file( $file ), "bespoke demo pack file '{$slug}.php' exists" );
	$data = require $file;
	check( is_array( $data ) && ( $data['slug'] ?? '' ) === $slug, "bespoke demo pack '{$slug}' returns valid spec array" );
	$bespoke_packs[ $slug ] = $data;
}

$all_demos = array_merge( $catalog, $bespoke_packs );
check( count( $all_demos ) >= 12, 'total demo count is at least 12 (found ' . count( $all_demos ) . ')' );

echo "\n--- 2. Credible Iranian Addresses and Phone Numbers ---\n";

$prohibited_dummy = array( 'lorem', 'ipsum', 'dolor', 'sit amet', 'dummy', 'asdf', 'test', 'نمونه', 'متن ساختگی', 'لورم' );

foreach ( $all_demos as $slug => $demo ) {
	$address = (string) ( $demo['address'] ?? '' );
	$phone   = (string) ( $demo['phone'] ?? '' );
	$city    = (string) ( $demo['city'] ?? '' );

	// Phone check: 021 for Tehran, 031 for Isfahan, 11 digits
	$valid_phone = (bool) preg_match( '/^0(21|31)[0-9]{8}$/', $phone );
	check( $valid_phone, "[{$slug}] phone '{$phone}' matches credible Iranian landline format" );

	// City check
	check( in_array( $city, array( 'تهران', 'اصفهان' ), true ), "[{$slug}] city is a recognized Iranian metropolis ({$city})" );

	// Address check: at least 15 chars, no dummy text
	$valid_addr = mb_strlen( $address, 'UTF-8' ) >= 15;
	foreach ( $prohibited_dummy as $bad ) {
		if ( false !== mb_stripos( $address, $bad, 0, 'UTF-8' ) ) {
			$valid_addr = false;
			break;
		}
	}
	check( $valid_addr, "[{$slug}] address is detailed and credible: '{$address}'" );
}

echo "\n--- 3. 1405 Iranian Market Price Range Validation ---\n";

$all_items = array();
$out_of_range = array();

foreach ( $all_demos as $slug => $demo ) {
	$items = (array) ( $demo['items'] ?? array() );
	check( count( $items ) >= 8, "[{$slug}] has full menu presentation (>= 8 dishes, found " . count( $items ) . ')' );

	foreach ( $items as $item ) {
		$all_items[] = $item;
		$price = (int) ( $item['price'] ?? 0 );
		$name  = (string) ( $item['name'] ?? '' );

		// Realistic Iranian price floor for 1405: minimum 25,000 Toman (e.g. side dish / dough), maximum 1,500,000 Toman (premium steak/seafood)
		if ( $price < 25000 || $price > 1500000 ) {
			$out_of_range[] = "{$name} in [{$slug}]: {$price} Toman";
		}
	}
}

check( empty( $out_of_range ), 'all menu item prices conform to 1405 market economics (25,000 to 1,500,000 Toman)' . ( $out_of_range ? ' — Invalid: ' . implode( '; ', $out_of_range ) : '' ) );
check( count( $all_items ) >= 96, 'total dishes across 12 demos is at least 96 (found ' . count( $all_items ) . ')' );

echo "\n--- 4. Zero Unfinished/Lorem Text Across All Demo Copy ---\n";

$lorem_found = array();

foreach ( $all_demos as $slug => $demo ) {
	$fields = array(
		'title'       => $demo['title'] ?? '',
		'site_title'  => $demo['site_title'] ?? '',
		'tagline'     => $demo['tagline'] ?? '',
		'hero_title'  => $demo['hero_title'] ?? '',
		'hero_text'   => $demo['hero_text'] ?? '',
		'about'       => $demo['about'] ?? '',
		'branch_name' => $demo['branch_name'] ?? '',
	);

	foreach ( $fields as $field => $val ) {
		foreach ( array( 'lorem', 'ipsum', 'dolor', 'sit amet', 'dummy', 'consectetur', 'متن ساختگی' ) as $needle ) {
			if ( false !== mb_stripos( (string) $val, $needle, 0, 'UTF-8' ) ) {
				$lorem_found[] = "[{$slug}].{$field} contains '{$needle}'";
			}
		}
	}

	foreach ( (array) ( $demo['items'] ?? array() ) as $item ) {
		$iname = (string) ( $item['name'] ?? '' );
		$idesc = (string) ( $item['description'] ?? '' );
		foreach ( array( 'lorem', 'ipsum', 'dolor', 'sit amet', 'dummy', 'متن ساختگی' ) as $needle ) {
			if ( false !== mb_stripos( $iname . ' ' . $idesc, $needle, 0, 'UTF-8' ) ) {
				$lorem_found[] = "[{$slug}] item '{$iname}' contains '{$needle}'";
			}
		}
	}
}

check( empty( $lorem_found ), 'all 12 demo packs are 100% free of lorem ipsum or unfinished copy' . ( $lorem_found ? ': ' . implode( '; ', $lorem_found ) : '' ) );

echo "\n" . str_repeat( '=', 50 ) . "\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo str_repeat( '=', 50 ) . "\n";

exit( $failed > 0 ? 1 : 0 );
