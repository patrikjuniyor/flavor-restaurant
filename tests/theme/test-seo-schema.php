<?php
/**
 * Test SEO, Meta Tags, Canonical & Schema.org Rich Results (پرامپت ۷۵ / Prompt 75).
 *
 * Requirements:
 * 1. Unique Title and Meta Description for pages, posts, products, archives, search.
 * 2. Complete Open Graph and Twitter Card markup for products, articles, and home.
 * 3. XML Sitemap support and Core sitemaps filter.
 * 4. Clean Canonical URL handling that strips filter/query noise parameters.
 * 5. Valid Schema.org JSON-LD output matching Restaurant, FoodEstablishment, Menu, MenuItem, BreadcrumbList.
 *
 * php tests/theme/test-seo-schema.php
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

echo "=== 1. Meta Description & Document Titles ===\n";

// Front page description
$desc_home = Schema_Output::get_meta_description();
check( '' !== $desc_home, 'front page provides meta description' );
check( mb_strlen( $desc_home, 'UTF-8' ) <= 160, 'meta description is capped at <= 160 characters' );

// Output check
ob_start();
Schema_Output::meta_description();
$meta_desc_tag = ob_get_clean();
check( false !== strpos( $meta_desc_tag, '<meta name="description"' ), 'meta_description outputs valid <meta name="description"> tag' );

echo "\n=== 2. Clean Canonical URLs (Stripping storefront filter noise) ===\n";

$clean_url = Schema_Output::get_canonical_url();
check( false !== strpos( $clean_url, 'http' ), 'canonical URL is well-formed URL' );

$test_dirty_url = 'https://example.com/menu/?cat=2&view=grid&sort=price&dietary=vegan&order_mode=delivery';
$cleaned = Schema_Output::clean_canonical_url( $test_dirty_url );
check( false === strpos( $cleaned, 'cat=' ), 'clean_canonical_url strips cat filter parameter' );
check( false === strpos( $cleaned, 'view=' ), 'clean_canonical_url strips view parameter' );
check( false === strpos( $cleaned, 'sort=' ), 'clean_canonical_url strips sort parameter' );
check( false === strpos( $cleaned, 'dietary=' ), 'clean_canonical_url strips dietary parameter' );
check( false === strpos( $cleaned, 'order_mode=' ), 'clean_canonical_url strips order_mode parameter' );

ob_start();
Schema_Output::canonical_url();
$canonical_tag = ob_get_clean();
check( false !== strpos( $canonical_tag, '<link rel="canonical"' ), 'canonical_url outputs valid <link rel="canonical"> tag' );

echo "\n=== 3. Open Graph & Twitter Cards ===\n";

ob_start();
Schema_Output::open_graph();
$og_output = ob_get_clean();

check( false !== strpos( $og_output, 'property="og:type"' ), 'open graph outputs og:type' );
check( false !== strpos( $og_output, 'property="og:title"' ), 'open graph outputs og:title' );
check( false !== strpos( $og_output, 'property="og:description"' ), 'open graph outputs og:description' );
check( false !== strpos( $og_output, 'property="og:url"' ), 'open graph outputs og:url' );
check( false !== strpos( $og_output, 'property="og:image"' ), 'open graph outputs og:image' );
check( false !== strpos( $og_output, 'name="twitter:card"' ), 'twitter card outputs twitter:card' );
check( false !== strpos( $og_output, 'summary_large_image' ), 'twitter card uses summary_large_image' );

echo "\n=== 4. XML Sitemap Post Types Filter ===\n";

$filtered_types = Schema_Output::sitemaps_post_types( array( 'post' => true, 'page' => true ) );
check( isset( $filtered_types['post'] ) && isset( $filtered_types['page'] ), 'sitemaps retain standard post and page types' );
check( isset( $filtered_types['branch'] ), 'sitemaps include branch post type' );

echo "\n=== 5. Schema.org JSON-LD Rich Results (Restaurant, Menu, Breadcrumbs) ===\n";

ob_start();
Schema_Output::print_jsonld();
$jsonld_output = ob_get_clean();

check( false !== strpos( $jsonld_output, '<script type="application/ld+json">' ), 'JSON-LD script tag is rendered' );

preg_match( '/<script type="application\/ld\+json">(.*?)<\/script>/s', $jsonld_output, $matches );
check( ! empty( $matches[1] ), 'extracted JSON-LD payload' );

$data = json_decode( $matches[1] ?? '', true );
check( is_array( $data ), 'JSON-LD payload is valid JSON' );
check( 'https://schema.org' === ( $data['@context'] ?? '' ), '@context is https://schema.org' );

$graph = $data['@graph'] ?? array();
check( ! empty( $graph ), '@graph contains schema objects' );

// Find Restaurant / FoodEstablishment node
$has_restaurant = false;
$has_breadcrumbs = false;

foreach ( $graph as $node ) {
	$type = $node['@type'] ?? '';
	if ( is_array( $type ) && in_array( 'Restaurant', $type, true ) ) {
		$has_restaurant = true;
		check( isset( $node['name'] ), 'Restaurant schema defines name' );
		check( isset( $node['url'] ), 'Restaurant schema defines url' );
	} elseif ( 'BreadcrumbList' === $type ) {
		$has_breadcrumbs = true;
		check( ! empty( $node['itemListElement'] ), 'BreadcrumbList schema defines itemListElement' );
	}
}

check( $has_restaurant, 'JSON-LD graph includes Restaurant / FoodEstablishment node' );
check( $has_breadcrumbs, 'JSON-LD graph includes BreadcrumbList node' );

echo "\n──────────────────────────────────────────────────\n";
echo "Result: {$passed} checks passed, {$failed} failed.\n";

if ( $failed > 0 ) {
	exit( 1 );
}
