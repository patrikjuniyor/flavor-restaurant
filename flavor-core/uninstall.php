<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Data is removed only when the merchant explicitly opted in
 * via the "Remove all data on uninstall" setting.
 *
 * @package FlavorCore
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$remove = get_option( 'flavor_core_remove_data', 'no' );

if ( 'yes' !== $remove ) {
	return;
}

global $wpdb;

require_once __DIR__ . '/includes/Autoloader.php';
\FlavorCore\Autoloader::register();

if ( ! defined( 'FLAVOR_CORE_PATH' ) ) {
	define( 'FLAVOR_CORE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'FLAVOR_CORE_FILE' ) ) {
	define( 'FLAVOR_CORE_FILE', __DIR__ . '/flavor-core.php' );
}

\FlavorCore\Database\Schema::drop_tables();

$branch_ids = get_posts(
	array(
		'post_type'      => 'flavor_branch',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $branch_ids as $branch_id ) {
	wp_delete_post( (int) $branch_id, true );
}

$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_flavor\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_flavor\\_%' OR meta_key LIKE 'flavor\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'flavor_core\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$roles = array( 'flavor_branch_manager', 'flavor_kitchen', 'flavor_cashier' );
foreach ( $roles as $role ) {
	remove_role( $role );
}

wp_cache_flush();
