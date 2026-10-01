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
	$files = array_merge( array( 'hero.jpg' ), array_values( $pack['category_images'] ?? array() ), array_column( $pack['items'], 'image' ) );
	if ( isset( $pack['landing'] ) ) {
		$files[] = 'story.jpg';
		demo_check( in_array( $slug, \Flavor\Bespoke_Demos::SLUGS, true ), 'Pack has no bespoke template: ' . $slug );
		demo_check( is_file( FLAVOR_DIR . '/template-parts/demos/' . $slug . '.php' ), 'Missing landing template: ' . $slug );
		demo_check( is_file( FLAVOR_DIR . '/assets/css/skins/' . $slug . '.css' ), 'Missing skin stylesheet: ' . $slug );
		$anchors = array( '#menu', '#story', '#faq', '#visit', '#experience', '#services', '#proposal', '#process' );
		foreach ( $pack['navigation'] as $link ) { demo_check( in_array( $link['anchor'], $anchors, true ), 'Invalid section anchor: ' . $link['anchor'] ); }
		demo_check( count( $pack['landing']['faq'] ) >= 3, 'Missing FAQs: ' . $slug );
	}
	foreach ( array_unique( $files ) as $file ) {
		demo_check( $file === basename( $file ), 'Unsafe asset basename: ' . $file );
		$path = FLAVOR_DIR . '/demos/' . $slug . '/' . $file;
		demo_check( is_file( $path ) && false !== getimagesize( $path ), 'Invalid image: ' . $path );
		demo_check( filesize( $path ) < 750000, 'Unoptimized image: ' . $path );
	}
	echo 'PASS ' . $slug . ' (' . count( $pack['items'] ) . ' products)' . PHP_EOL;
}
echo count( $catalog ) . ' complete importable packs; 12 defined skins.' . PHP_EOL;
