<?php
/**
 * Contract test for WordPress readme.txt files (theme & core plugin).
 *
 * Checks:
 * 1. Both flavor/readme.txt and flavor-core/readme.txt exist and are readable.
 * 2. Standard WordPress sections exist in both files:
 *    - == Description ==
 *    - == Installation ==
 *    - == Frequently Asked Questions ==
 *    - == Screenshots ==
 *    - == Changelog ==
 * 3. Frequently Asked Questions in both files contains at least 5 substantial buyer Q&As.
 *    Specifically verifying coverage of: theme vs plugin split, WooCommerce requirement,
 *    multilingual/translation readiness, mobile app integration, and update channels.
 * 4. Screenshots section exists in both files with descriptive entries for each image.
 * 5. Stable tags match code versions (1.6.2 for theme, 1.5.1 for core plugin).
 *
 * @package Flavor
 */

declare( strict_types=1 );

$root = dirname( __DIR__, 2 );
$passed = 0;
$failed = 0;

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

echo "=== Flavor WordPress readme.txt Contract Test ===\n\n";

$theme_readme_path = $root . '/flavor/readme.txt';
$core_readme_path  = $root . '/flavor-core/readme.txt';

check( is_readable( $theme_readme_path ), 'flavor/readme.txt exists and is readable' );
check( is_readable( $core_readme_path ), 'flavor-core/readme.txt exists and is readable' );

$theme_readme = (string) file_get_contents( $theme_readme_path );
$core_readme  = (string) file_get_contents( $core_readme_path );

// Check standard section headers
$required_sections = array(
	'== Description ==',
	'== Installation ==',
	'== Frequently Asked Questions ==',
	'== Screenshots ==',
	'== Changelog ==',
);

foreach ( $required_sections as $sec ) {
	check( false !== strpos( $theme_readme, $sec ), "flavor/readme.txt contains {$sec}" );
	check( false !== strpos( $core_readme, $sec ), "flavor-core/readme.txt contains {$sec}" );
}

// Check FAQ count (at least 5 in each)
$count_faqs = static function ( string $content ): int {
	if ( ! preg_match( '/== Frequently Asked Questions ==(.*?)(?:==|\Z)/s', $content, $m ) ) {
		return 0;
	}
	preg_match_all( '/^=\s*[^=\n\r]+\s*=\s*$/m', $m[1], $questions );
	return count( $questions[0] );
};

$theme_faq_count = $count_faqs( $theme_readme );
$core_faq_count  = $count_faqs( $core_readme );

check( $theme_faq_count >= 5, "flavor/readme.txt has at least 5 FAQs (found {$theme_faq_count})" );
check( $core_faq_count >= 5, "flavor-core/readme.txt has at least 5 FAQs (found {$core_faq_count})" );

// Check key buyer topics covered in FAQs
$buyer_topics = array(
	'Theme vs Plugin split'     => false !== stripos( $theme_readme, 'Flavor Core' ) && false !== stripos( $core_readme, 'Flavor theme' ),
	'WooCommerce requirement'   => false !== stripos( $theme_readme, 'WooCommerce' ) && false !== stripos( $core_readme, 'WooCommerce' ),
	'Multilingual / Translation'=> false !== stripos( $theme_readme, 'translat' ) && false !== stripos( $theme_readme, 'RTL' ),
	'Mobile app'                => false !== stripos( $theme_readme, 'mobile' ) && false !== stripos( $core_readme, 'Flutter' ),
	'Updates mechanism'         => false !== stripos( $theme_readme, 'update' ) && false !== stripos( $theme_readme, 'Ed25519' ),
);

foreach ( $buyer_topics as $topic => $condition ) {
	check( $condition, "readme files cover buyer topic: {$topic}" );
}

// Check screenshots section
$count_screenshots = static function ( string $content ): int {
	if ( ! preg_match( '/== Screenshots ==(.*?)(?:==|\Z)/s', $content, $m ) ) {
		return 0;
	}
	preg_match_all( '/^\d+\.\s+[^\n\r]+/m', $m[1], $shots );
	return count( $shots[0] );
};

$theme_shots = $count_screenshots( $theme_readme );
$core_shots  = $count_screenshots( $core_readme );

check( $theme_shots >= 4, "flavor/readme.txt has descriptive screenshots (found {$theme_shots})" );
check( $core_shots >= 4, "flavor-core/readme.txt has descriptive screenshots (found {$core_shots})" );

// Check versions
check( 1 === preg_match( '/Stable tag:\s*1\.6\.2/m', $theme_readme ), 'flavor/readme.txt stable tag is 1.6.2' );
check( 1 === preg_match( '/Stable tag:\s*1\.5\.1/m', $core_readme ), 'flavor-core/readme.txt stable tag is 1.5.1' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
