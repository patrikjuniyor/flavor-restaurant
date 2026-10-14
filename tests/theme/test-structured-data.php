<?php
/**
 * Test Structured Data & JSON-LD Output (پرامپت ۷۶ / Prompt 76).
 *
 * Requirements:
 * 1. Restaurant details (name/address/hours/phone) are drawn from settings (Branch & Theme mods), not hardcoded.
 * 2. Menu node is only output when real products exist.
 * 3. AggregateRating is ONLY output when real reviews exist (no fraudulent rating claims).
 * 4. Single product MenuItem includes valid fields, price, and conditional AggregateRating.
 *
 * php tests/theme/test-structured-data.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/seo-wp-stubs.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/class-schema-output.php';

use Flavor\Schema_Output;

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

echo "=== 1. Dynamic Restaurant Details from Settings ===\n";

set_theme_mod( 'flavor_hero_title', 'رستوران سنتی زعفران' );
set_theme_mod( 'flavor_phone', '02188776655' );
set_theme_mod( 'flavor_address', 'تهران، خیابان ولیعصر، پلاک ۱۰۰' );
set_theme_mod( 'flavor_city', 'تهران' );

$restaurant = Schema_Output::restaurant();

check( 'رستوران سنتی زعفران' === $restaurant['name'], 'restaurant name matches setting' );
check( '02188776655' === ( $restaurant['telephone'] ?? '' ), 'restaurant phone matches setting' );
check( 'تهران، خیابان ولیعصر، پلاک ۱۰۰' === ( $restaurant['address']['streetAddress'] ?? '' ), 'restaurant address matches setting' );
check( 'تهران' === ( $restaurant['address']['addressLocality'] ?? '' ), 'restaurant city matches setting' );

echo "\n=== 2. Truthful AggregateRating: Only when real reviews exist ===\n";

// Default: no approved reviews
check( ! isset( $restaurant['aggregateRating'] ), 'restaurant omits aggregateRating when no reviews exist' );

// Mock review comments
$GLOBALS['_mock_approved_reviews'] = array(
	(object) array( 'comment_ID' => 101 ),
	(object) array( 'comment_ID' => 102 ),
);
$GLOBALS['_mock_comment_meta'] = array(
	101 => array( 'rating' => 5 ),
	102 => array( 'rating' => 4 ),
);

$restaurant_with_reviews = Schema_Output::restaurant();
check( isset( $restaurant_with_reviews['aggregateRating'] ), 'restaurant includes aggregateRating when reviews exist' );
check( 4.5 === ( $restaurant_with_reviews['aggregateRating']['ratingValue'] ?? 0.0 ), 'aggregateRating calculates truthful average rating (4.5)' );
check( 2 === ( $restaurant_with_reviews['aggregateRating']['reviewCount'] ?? 0 ), 'aggregateRating calculates truthful review count (2)' );

// Reset reviews
unset( $GLOBALS['_mock_approved_reviews'], $GLOBALS['_mock_comment_meta'] );

echo "\n=== 3. Menu Node: Output ONLY when products exist ===\n";

// When products exist in mock
$menu = Schema_Output::menu();
check( null !== $menu, 'menu node is generated when products exist' );
check( 'Menu' === ( $menu['@type'] ?? '' ), 'menu node type is Menu' );
check( ! empty( $menu['hasMenuSection'][0]['hasMenuItem'] ), 'menu section contains menu items' );

// When NO products exist (temporarily clear mock products)
$backup_products = $GLOBALS['_mock_wc_products'] ?? array();
$GLOBALS['_mock_wc_products'] = array();

$empty_menu = Schema_Output::menu();
check( null === $empty_menu, 'menu node is null when no products exist' );

// Restore mock products
$GLOBALS['_mock_wc_products'] = $backup_products;

echo "\n=== 4. Single Product MenuItem Schema & Truthful Rating ===\n";

// Case A: 0 reviews -> no aggregateRating
$GLOBALS['_mock_queried_object_id'] = 1;
$p_obj = wc_get_product( 1 );

// Alter rating to 0
$GLOBALS['_mock_wc_products'][1] = new class extends \WC_Product {
	public function __construct() {
		$this->id    = 1;
		$this->name  = 'چلو کباب بدون نظر';
		$this->price = 2800000;
	}
	public function get_rating_count(): int { return 0; }
	public function get_average_rating(): float { return 0.0; }
};

$single_item = Schema_Output::single_product_menu_item();
check( null !== $single_item, 'single product generates MenuItem schema' );
check( 'MenuItem' === ( $single_item['@type'] ?? '' ), 'item type is MenuItem' );
check( ! isset( $single_item['aggregateRating'] ), 'MenuItem omits aggregateRating when product has 0 reviews' );

// Case B: Has real reviews -> aggregateRating present
$GLOBALS['_mock_wc_products'][1] = new class extends \WC_Product {
	public function __construct() {
		$this->id    = 1;
		$this->name  = 'چلو کباب پرطرفدار';
		$this->price = 2800000;
	}
	public function get_rating_count(): int { return 25; }
	public function get_average_rating(): float { return 4.9; }
};

$single_item_rated = Schema_Output::single_product_menu_item();
check( isset( $single_item_rated['aggregateRating'] ), 'MenuItem includes aggregateRating when real reviews exist' );
check( 4.9 === ( $single_item_rated['aggregateRating']['ratingValue'] ?? 0.0 ), 'MenuItem ratingValue is accurate (4.9)' );
check( 25 === ( $single_item_rated['aggregateRating']['reviewCount'] ?? 0 ), 'MenuItem reviewCount is accurate (25)' );

// Restore original product
$GLOBALS['_mock_wc_products'] = $backup_products;

echo "\n──────────────────────────────────────────────────\n";
echo "Result: {$passed} checks passed, {$failed} failed.\n";

if ( $failed > 0 ) {
	exit( 1 );
}
