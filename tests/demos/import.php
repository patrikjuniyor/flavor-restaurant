<?php
/**
 * DESTRUCTIVE test on a disposable WP install only.
 * FLAVOR_DEMO_QA=1 DEMO_SLUG=juice-bar wp eval-file tests/demos/import.php
 */
if ( '1' !== getenv( 'FLAVOR_DEMO_QA' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Use an explicitly opted-in, disposable local/development WordPress install, never a live site.' );
}
if ( ! function_exists( 'wc_get_product' ) || ! defined( 'FLAVOR_CORE_VERSION' ) ) { throw new RuntimeException( 'Activate WooCommerce and Flavor Core.' ); }
require_once FLAVOR_DIR . '/inc/demo-catalog.php';
$catalog = flavor_demo_catalog();
$slug    = getenv( 'DEMO_SLUG' ) ?: 'juice-bar';
if ( ! isset( $catalog[ $slug ] ) ) { throw new RuntimeException( 'Unknown demo.' ); }
$pack = $catalog[ $slug ];
function import_check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$sentinel = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Non-demo content sentinel', 'post_status' => 'draft' ) );
try {
	\Flavor\Demo_Importer::import( $pack );
	$first_media = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_flavor_demo' ) );
	$first_branch_id = \FlavorCore\PostTypes\BranchPostType::default_id();
	set_theme_mod( 'flavor_landing_story_title', 'Previous-demo override sentinel' );
	\Flavor\Demo_Importer::import( $pack );
	$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_flavor_demo' ) );
	$products = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_flavor_demo' ) );
	$media = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids', 'posts_per_page' => -1, 'meta_key' => '_flavor_demo' ) );
	import_check( 6 === count( $pages ), 'Repeated import duplicated pages.' );
	import_check( count( $pack['items'] ) === count( $products ), 'Repeated import duplicated or lost products.' );
	import_check( count( $first_media ) === count( $media ), 'Repeated import leaked media.' );
	import_check( ! array_intersect( $first_media, $media ), 'Old demo attachments were not deleted.' );
	import_check( null !== get_post( $sentinel ), 'Non-demo content was removed.' );
	import_check( $slug === \Flavor\Design::current_skin(), 'Active skin is wrong.' );
	if ( isset( $pack['landing'] ) ) {
		import_check( '' === get_post_field( 'post_content', (int) get_option( 'page_on_front' ) ), 'Duplicate marketing blocks were inserted.' );
		import_check( $pack['landing']['story_title'] === \Flavor\Bespoke_Demos::value( 'story_title' ), 'Previous landing override leaked into new pack.' );
		$nav_id = get_theme_mod( 'nav_menu_locations' )['primary'];
		import_check( count( $pack['navigation'] ) === count( wp_get_nav_menu_items( $nav_id ) ), 'Duplicate anchor navigation.' );
	}
	foreach ( $products as $id ) {
		$product = wc_get_product( $id );
		import_check( $product && $product->is_purchasable() && $product->get_image_id() > 0, 'Imported product is not purchasable or has no image.' );
	}
	$branch_id = \FlavorCore\PostTypes\BranchPostType::default_id();
	$tables = \FlavorCore\Table\TableRepository::for_branch( $branch_id );
	import_check( count( $tables ) === (int) ( $pack['tables'] ?? 8 ), 'Actual demo table count differs from the pack.' );
	import_check( ! \FlavorCore\Table\TableRepository::for_branch( $first_branch_id ), 'Repeated import leaked old demo tables.' );
	import_check( \Flavor\Enqueue::current_branch_id() === $branch_id, 'Frontend default branch differs from the imported branch.' );
	if ( ! empty( $pack['opening_hours'] ) ) {
		for ( $day = 0; $day < 7; $day++ ) {
			$hours = \FlavorCore\Reservation\SlotCalculator::hours_for( $branch_id, $day );
			import_check( $hours['open_time'] === $pack['opening_hours']['open'] && $hours['close_time'] === $pack['opening_hours']['close'], 'Reservation hours differ from the advertised pack hours.' );
		}
	}
	echo 'PASS ' . $slug . ': repeated imports, product CRUD, images, navigation, empty editor, override reset, real branch/tables/hours and preservation of non-demo content.' . PHP_EOL;
} finally {
	wp_delete_post( (int) $sentinel, true );
}
