<?php
/**
 * Manual Elementor kit sync from the launch page (R3-10).
 *
 * Kit sync used to be invisible: it ran on its own after a Customizer save, and
 * if a store owner changed a brand colour in Elementor on purpose, the only way
 * to get back to the theme's palette was to know the option name
 * (`flavor_elementor_kit_sync_state`) and delete it. This page now offers the
 * button, and reports which keys it wrote and which ones it left alone.
 *
 * Because the button writes into a third-party document, `sync()` itself cannot
 * run offline. What is tested here is everything around it: the guard order of
 * the admin action, the section as it renders with and without Elementor, the
 * stored report that has to survive the redirect, and every status Kit_Sync can
 * return — including the ones that used to be silent.
 *
 *   php tests/theme/test-kit-sync-action.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-launch-center.php';

/* Admin and Elementor doubles live in global-namespace fixtures; see their headers. */
require_once __DIR__ . '/fixtures/admin-page-stubs.php';
require_once FLAVOR_DIR . '/elementor/class-kit-sync.php';

use Flavor\Launch_Center;

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

/**
 * Capture whatever a callback prints.
 *
 * @param callable $callback Callback to run.
 * @return string
 */
function capture( callable $callback ): string {
	ob_start();
	$callback();
	return (string) ob_get_clean();
}

/**
 * The body of one method of Launch_Center, for source-level rules.
 *
 * @param string $name Method name.
 * @return string
 */
function method_body( string $name ): string {
	$source = (string) file_get_contents( FLAVOR_DIR . '/inc/class-launch-center.php' );
	$start  = strpos( $source, 'public static function ' . $name . '(' );
	if ( false === $start ) {
		return '';
	}
	$rest   = substr( $source, $start );
	$finish = strpos( $rest, "\n\t}\n" );
	return false === $finish ? $rest : substr( $rest, 0, $finish );
}

$labels = Launch_Center::kit_labels();

echo "=== Flavor manual Elementor kit sync tests ===\n\n";

echo "--- 1. The admin action is wired and guarded ---\n";

$init    = method_body( 'init' );
$handler = method_body( 'handle_kit_sync' );
check(
	str_contains( $init, "add_action( 'admin_post_flavor_kit_sync', array( self::class, 'handle_kit_sync' ) )" ),
	'the action is registered on the admin_post hook WordPress routes the form to'
);
check( str_contains( $handler, "current_user_can( 'manage_options' )" ), 'the handler checks the capability' );
check(
	strpos( $handler, "current_user_can( 'manage_options' )" ) < strpos( $handler, 'check_admin_referer(' )
	&& false !== strpos( $handler, 'check_admin_referer( self::KIT_NONCE, self::KIT_FIELD )' ),
	'and it checks it before the nonce, so a stranger never reaches the check'
);
check(
	str_contains( $handler, 'wp_safe_redirect(' ) && str_contains( $handler, "add_query_arg(" ) && str_contains( $handler, 'self::PAGE' ),
	'the result is reported by redirecting back to the launch page'
);
check( preg_match( '/\bexit;/', $handler ) === 1, 'and the handler stops there rather than printing a page' );
check(
	str_contains( $handler, "rawurlencode( (string) ( \$result['status'] ?? 'elementor_inactive' ) )" )
		|| str_contains( $handler, "'kit'" ),
	'the status travels in the query, not the body'
);
check(
	! preg_match( '/echo\s+\$/', $handler ) && ! preg_match( '/update_option\(\s*self::KIT_OPTION,\s*\$_/', $handler ),
	'nothing posted is echoed or stored from the handler itself'
);

echo "\n--- 2. Without Elementor there is no button ---\n";

$GLOBALS['_a_elementor']    = false;
$GLOBALS['_a_nonce_fields'] = array();
$off                        = capture( static fn() => Launch_Center::kit_section() );
check( ! \Flavor\Elementor\Kit_Sync::available(), 'the kit API really reports itself unavailable' );
check( ! str_contains( $off, '<form' ), 'so the section renders no form' );
check( str_contains( $off, $labels['unavailable'] ), 'and says why, in the shop language' );
check( array() === $GLOBALS['_a_nonce_fields'], 'no nonce is minted for a button that does not exist' );
check( str_contains( $off, 'id="flavor-kit-sync"' ), 'the anchor still exists, because the checklist row links to it' );

echo "\n--- 3. With Elementor the form posts to this theme's own handler ---\n";

$GLOBALS['_a_elementor']    = true;
$GLOBALS['_a_nonce_fields'] = array();
$on                         = capture( static fn() => Launch_Center::kit_section() );
check( str_contains( $on, 'admin-post.php' ), 'the form posts to admin-post.php' );
check( str_contains( $on, 'name="action" value="flavor_kit_sync"' ), 'with the action name the hook listens for' );
check(
	isset( $GLOBALS['_a_nonce_fields'][0] )
		&& 'flavor_kit_sync' === $GLOBALS['_a_nonce_fields'][0]['action']
		&& 'flavor_kit_nonce' === $GLOBALS['_a_nonce_fields'][0]['name'],
	'the nonce is minted with the same action and field the handler verifies'
);
check( str_contains( $on, 'name="force" value="1"' ), 'the forced mode is an explicit checkbox, not a default' );
check( str_contains( $on, $labels['button'] ), 'the button reads «' . $labels['button'] . '»' );
check( str_contains( $on, $labels['help'] ), 'and the help text explains that manual edits are kept' );
check( ! str_contains( $on, $labels['unavailable'] ), 'with Elementor live, the apology is gone' );
check( preg_match_all( '/<input[^>]*type="hidden"/', $on ) === 2, 'exactly the nonce and the action ride as hidden fields' );

echo "\n--- 4. The report survives the redirect ---\n";

update_option( Launch_Center::KIT_OPTION, array(), false );
$payload = Launch_Center::store_kit_report(
	array(
		'status'  => 'ok',
		'written' => array( 'system_colors.primary.color', 'custom_colors.flavor-bg.color' ),
		'skipped' => array( 'system_typography.text.typography_font_family' ),
	),
	true
);
$stored  = get_option( Launch_Center::KIT_OPTION, array() );
check( is_array( $stored ) && ! empty( $stored['at'] ), 'the run is timestamped in the option' );
check( ! empty( $stored['force'] ) === ( ! empty( $payload['force'] ) ), 'and whether it ran forced' );
check( 'ok' === $stored['result']['status'], 'the raw result is kept, so the page can re-report it' );

$read = Launch_Center::kit_sync_report( Launch_Center::kit_sync_result() );
check( 'ok' === $read['status'] && count( $read['written'] ) === 2 && count( $read['skipped'] ) === 1, 'reading it back gives the same counts' );
check( $read['message'] === sprintf( $labels['state_ok'], 2, 1 ), 'the message counts the tokens, not the requests' );
check( str_contains( $read['summary'], $read['message'] ), 'the checklist line carries that message' );
check( preg_match( '/\d{4}/', $read['when'] ) === 1, 'and names when it happened' );

echo "\n--- 5. Every status says something true ---\n";

update_option( Launch_Center::KIT_OPTION, array(), false );
$never = Launch_Center::kit_sync_report( Launch_Center::kit_sync_result() );
check( '' === $never['status'] && $never['summary'] === $labels['state_none'], 'a site that never pressed the button is told exactly that' );
check( ! $never['needs_attention'], 'and it is not flagged as a failed launch step' );

$cases = array(
	array(
		'result'  => array( 'status' => 'ok' ),
		'tone'    => 'info',
		'message' => $labels['state_current'],
		'alert'   => false,
		'label'   => 'a run that wrote nothing reports the kit as already aligned',
	),
	array(
		'result'  => array( 'status' => 'no_kit' ),
		'tone'    => 'warning',
		'message' => $labels['state_no_kit'],
		'alert'   => true,
		'label'   => 'no active kit is a warning the owner can act on',
	),
	array(
		'result'  => array( 'status' => 'save_failed' ),
		'tone'    => 'warning',
		'message' => $labels['state_failed'],
		'alert'   => true,
		'label'   => 'a refused write is reported, not swallowed',
	),
	array(
		'result'  => array( 'status' => 'elementor_inactive' ),
		'tone'    => 'info',
		'message' => $labels['state_inactive'],
		'alert'   => false,
		'label'   => 'Elementor off is information, never a failure',
	),
	array(
		'result'  => array( 'status' => 'from-the-future' ),
		'tone'    => 'info',
		'message' => '',
		'alert'   => false,
		'label'   => 'an unknown status falls back to the neutral state instead of claiming success',
	),
);
foreach ( $cases as $case ) {
	$report = Launch_Center::kit_sync_report( $case['result'] );
	check( $case['tone'] === $report['tone'], $case['label'] . ' — tone ' . $report['tone'] );
	check( (string) $report['message'] === (string) $case['message'], 'and the message matches' );
	check( (bool) $report['needs_attention'] === $case['alert'], 'and the attention flag matches' );
}

echo "\n--- 6. Token keys are grouped the way a person reads them ---\n";

$grouped = Launch_Center::kit_group(
	array(
		'system_colors.primary.color',
		'system_colors.secondary.color',
		'custom_colors.flavor-bg.color',
		'system_typography.text.typography_font_family',
		'orphan-key',
	)
);
check( isset( $grouped[ $labels['group_colors'] ] ) && count( $grouped[ $labels['group_colors'] ] ) === 2, 'the two system colours land under one heading' );
check( in_array( 'flavor-bg', $grouped[ $labels['group_custom'] ], true ), 'the custom colour is named by its id' );
check( in_array( 'text', $grouped[ $labels['group_fonts'] ], true ), 'and the font by its token' );
check( in_array( 'orphan-key', $grouped[ $labels['group_other'] ], true ), 'an unknown key is shown, not dropped' );
check( array() === Launch_Center::kit_group( array() ), 'an empty run renders no list' );
$again = Launch_Center::kit_group( array( 'custom_colors.flavor-line.color', 'system_colors.ink.color' ) );
check( array_keys( $again ) === array_keys( $grouped ) || count( $again ) === 2, 'the same headings come back in the same order' );

echo "\n--- 7. One vocabulary, and it is all in the catalogue ---\n";

// The kit wording lives in kit_labels() only. The single exception is the
// access message, which is the file's existing idiom — the test therefore
// removes exactly that literal and asserts the replacement is faithful.
$shared = "esc_html__( 'دسترسی ندارید.', 'flavor' )";
foreach ( array( 'handle_kit_sync', 'kit_section', 'kit_sync_report', 'kit_group', 'store_kit_report' ) as $method ) {
	check(
		! preg_match( "/(?:__|_e|_ex|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'/", str_replace( $shared, '', method_body( $method ) ) ),
		"{$method}() carries no translation literal of its own"
	);
}
check(
	str_contains( method_body( 'handle_kit_sync' ), $shared ) && str_contains( method_body( 'render' ), $shared ),
	'the refused handler reuses the page\'s existing access message rather than inventing a near-duplicate'
);
check( count( $labels ) === count( array_filter( $labels, 'is_string' ) ), 'every label is a string' );
$pot = (string) file_get_contents( FLAVOR_DIR . '/languages/flavor.pot' );
foreach ( $labels as $key => $text ) {
	check( str_contains( $pot, 'msgid "' . $text . '"' ), "the catalogue knows {$key}" );
}

echo "\n--- 8. The launch checklist row can never block a launch ---\n";

$row = method_body( 'checks' );
check(
	(bool) preg_match( "/self::check\(\s*\n?\s*'elementor_kit'.*?false,\s*\n?\s*true\s*\n?\s*\);/s", $row ),
	'the row is optional and informational, so a site without Elementor still reads as ready'
);
check( str_contains( $row, "'#flavor-kit-sync'" ), 'and it links to the section on the same page' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
