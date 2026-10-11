<?php
/** Standalone, non-destructive pack validation: php tests/demos/catalog.php */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
if ( ! defined( 'FLAVOR_DIR' ) ) { define( 'FLAVOR_DIR', dirname( __DIR__, 2 ) . '/flavor' ); }
if ( ! function_exists( '__' ) ) { function __( $text, $domain = '' ) { return $text; } }
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/demo-catalog.php';
function demo_check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$catalog = flavor_demo_catalog();
$skins   = \Flavor\Design::skins();
demo_check( 12 === count( $skins ), 'The design system must keep all 12 skins.' );
demo_check( count( $catalog ) === 7 + count( \Flavor\Bespoke_Demos::SLUGS ), 'Catalog and completed-demo registry must agree.' );
foreach ( $catalog as $slug => $pack ) {
	demo_check( isset( $skins[ $slug ] ), 'Unregistered skin: ' . $slug );
	demo_check( $slug === $pack['slug'], 'Pack slug mismatch: ' . $slug );
	demo_check( count( $pack['items'] ) >= 8, 'Insufficient menu content: ' . $slug );
	$categories = array_values( $pack['categories'] );
	foreach ( $pack['items'] as $item ) {
		demo_check( in_array( $item['category'], $categories, true ), 'Unknown product category: ' . $item['name'] );
		demo_check( $item['price'] > 0 && $item['prep'] > 0, 'Invalid price or prep time: ' . $item['name'] );
	}
	// Every category needs art of its own. Without this the importer falls back
	// to the hero, so a pack can be "complete" while showing one repeated photo
	// four times — which is how persian-traditional shipped.
	foreach ( $categories as $category ) {
		demo_check( isset( $pack['category_images'][ $category ] ), 'Category without its own image: ' . $slug . '/' . $category );
	}
	$files = array_merge( array( 'hero.jpg' ), array_values( $pack['category_images'] ?? array() ), array_column( $pack['items'], 'image' ) );
	if ( isset( $pack['landing'] ) ) {
		$files[] = 'story.jpg';
		$files = array_merge( $files, array_column( $pack['landing']['services'] ?? array(), 'image' ) );
		if ( 'catering' === $slug ) {
			demo_check( 0 === $pack['tables'] && array( 'takeaway' ) === $pack['order_modes'], 'Catering must not promise restaurant tables or instant delivery.' );
			demo_check( '#proposal' === $pack['landing']['primary_action'], 'Catering must lead to event planning, not table reservations.' );
			demo_check( 3 === count( $pack['landing']['services'] ), 'Catering needs its three independent service paths.' );
			demo_check( is_file( FLAVOR_DIR . '/assets/js/catering.js' ), 'Missing progressive catering planner.' );
		}
		demo_check( in_array( $slug, \Flavor\Bespoke_Demos::SLUGS, true ), 'Pack has no bespoke template: ' . $slug );
		demo_check( is_file( FLAVOR_DIR . '/template-parts/demos/' . $slug . '.php' ), 'Missing landing template: ' . $slug );
		demo_check( is_file( FLAVOR_DIR . '/assets/css/skins/' . $slug . '.css' ), 'Missing skin stylesheet: ' . $slug );
		$anchors = array( '#menu', '#story', '#faq', '#visit', '#experience', '#services', '#proposal', '#process', '#coverage' );
		foreach ( $pack['navigation'] as $link ) { demo_check( in_array( $link['anchor'], $anchors, true ), 'Invalid section anchor: ' . $link['anchor'] ); }
		demo_check( count( $pack['landing']['faq'] ) >= 3, 'Missing FAQs: ' . $slug );
	}
	foreach ( array_unique( $files ) as $file ) {
		demo_check( $file === basename( $file ), 'Unsafe asset basename: ' . $file );
		$path = FLAVOR_DIR . '/demos/' . $slug . '/' . $file;
		$size = is_file( $path ) ? getimagesize( $path ) : false;
		demo_check( false !== $size, 'Invalid image: ' . $path );
		if ( false !== $size && 0 === strpos( $file, 'category-' ) ) {
			// Grid-cell art below this is a thumbnail blown up. 600px is under
			// the smallest we ship (dark-luxe's 900×675 bar shot) so the floor
			// catches a placeholder, not a deliberate crop.
			demo_check( min( $size[0], $size[1] ) >= 600, 'Category image too small: ' . $path . ' (' . $size[0] . '×' . $size[1] . ')' );
		}
		demo_check( filesize( $path ) < 750000, 'Unoptimized image: ' . $path );
	}
	echo 'PASS ' . $slug . ' (' . count( $pack['items'] ) . ' products)' . PHP_EOL;
}
// Art that no pack references is dead weight in the demo zip and a trap in
// the code: the gallery fallback used to name the alias folders, so those four
// hero.jpg files looked load-bearing while being unreachable from any pack.
$known = array_keys( $catalog );
$skins  = array_keys( $skins );
foreach ( glob( FLAVOR_DIR . '/demos/*', GLOB_ONLYDIR ) as $dir ) {
	$dir_slug = basename( $dir );
	demo_check( in_array( $dir_slug, $known, true ) || in_array( $dir_slug, $skins, true ), 'Demo art no pack uses: demos/' . $dir_slug );
}

// The marketing copy has to agree with the catalog. Listing docs used to sell
// "هشت دمو" while the product shipped twelve — a buyer notices on first install.
$repo_root = dirname( __DIR__, 2 );
foreach ( array( 'docs/raastichin/listing.md', 'README.md' ) as $doc ) {
	$path = $repo_root . '/' . $doc;
	if ( ! is_file( $path ) ) {
		continue;
	}
	$text = (string) file_get_contents( $path );
	// Persian digits or Latin ones, any "N دمو" claim is checked.
	if ( preg_match_all( '/([0-9۰-۹٠-٩]+)\s*دمو/u', $text, $found ) ) {
		foreach ( $found[1] as $claimed ) {
			$latin = strtr( $claimed, array( '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9' ) );
			demo_check( (int) $latin === count( $catalog ), $doc . ' advertises ' . $claimed . ' دمو but the catalog has ' . count( $catalog ) );
		}
	}
}
foreach ( glob( $repo_root . '/docs/raastichin/*.md' ) as $md ) {
	$body = (string) file_get_contents( $md );
	demo_check( ! preg_match( '/هشت\s+دمو/u', $body ), basename( $md ) . ' still says "هشت دمو"' );
}

echo count( $catalog ) . ' complete importable packs; 12 defined skins.' . PHP_EOL;
