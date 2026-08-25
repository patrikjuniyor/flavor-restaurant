<?php
/**
 * PHPUnit bootstrap. Expects WP_TESTS_DIR when running in CI.
 *
 * @package FlavorCore
 */

$wp_tests = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests ) {
	$wp_tests = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test lib not found. Set WP_TESTS_DIR.\n" );
	return;
}

require_once $wp_tests . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/flavor-core.php';
	}
);

require $wp_tests . '/includes/bootstrap.php';
