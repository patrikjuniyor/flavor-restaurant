<?php
/**
 * Test contract for docs/fa/ accuracy, valid file references, menu paths and terminology.
 * Prompt 87 contract gate.
 *
 * @package Flavor
 */

declare( strict_types=1 );

$root = dirname( __DIR__, 2 );
$docs_dir = $root . '/docs/fa';

$target_docs = array(
	'01-nasb.md',
	'02-peykar-bandi.md',
	'03-bahrebardari.md',
	'04-moshtari.md',
	'05-toshe-dahande.md',
	'06-jostojoo-hooshmand.md',
	'07-demo-css.md',
	'08-shakhsi-sazi.md',
	'11-elementor.md',
);

$checks_passed = 0;

// Check 1: All 9 target docs must exist and be non-empty.
foreach ( $target_docs as $doc ) {
	$p = $docs_dir . '/' . $doc;
	if ( ! file_exists( $p ) || filesize( $p ) < 500 ) {
		fwrite( STDERR, "Check 1 FAIL: Document missing or suspiciously small: {$doc}\n" );
		exit( 1 );
	}
}
$checks_passed++;
echo "OK 1: All 9 targeted docs/fa files exist and are substantial.\n";

// Check 2: 01-nasb.md compatibility requirements (PHP 8.1+, WP 6.4+, WC 8.5+).
$nasb = file_get_contents( $docs_dir . '/01-nasb.md' );
if ( false !== strpos( $nasb, 'PHP ۸.۰' ) || false === strpos( $nasb, 'PHP ۸.۱' ) ) {
	fwrite( STDERR, "Check 2 FAIL: 01-nasb.md references obsolete PHP 8.0 instead of 8.1+.\n" );
	exit( 1 );
}
if ( false === strpos( $nasb, 'وردپرس ۶.۴' ) || false === strpos( $nasb, 'ووکامرس ۸.۵' ) ) {
	fwrite( STDERR, "Check 2 FAIL: 01-nasb.md missing WP 6.4 or WC 8.5 baseline.\n" );
	exit( 1 );
}
$checks_passed++;
echo "OK 2: 01-nasb.md baseline platform specs are aligned.\n";

// Check 3: 02-peykar-bandi.md references valid scripts and branch CPT.
$peykar = file_get_contents( $docs_dir . '/02-peykar-bandi.md' );
if ( false !== strpos( $peykar, '`flavor-digits.js`' ) ) {
	fwrite( STDERR, "Check 3 FAIL: 02-peykar-bandi.md references obsolete non-existent flavor-digits.js script.\n" );
	exit( 1 );
}
if ( false === strpos( $peykar, 'flavor/assets/js/digits.js' ) ) {
	fwrite( STDERR, "Check 3 FAIL: 02-peykar-bandi.md must reference flavor/assets/js/digits.js.\n" );
	exit( 1 );
}
if ( false === strpos( $peykar, '`flavor_branch`' ) ) {
	fwrite( STDERR, "Check 3 FAIL: 02-peykar-bandi.md must accurately document flavor_branch post type.\n" );
	exit( 1 );
}
$checks_passed++;
echo "OK 3: 02-peykar-bandi.md script paths and branch definitions verified.\n";

// Check 4: 03-bahrebardari.md documents reporting & CSV export features.
$bahre = file_get_contents( $docs_dir . '/03-bahrebardari.md' );
if ( false !== strpos( $bahre, 'افزونه Pro است' ) ) {
	fwrite( STDERR, "Check 4 FAIL: 03-bahrebardari.md references phantom Pro plugin for reporting.\n" );
	exit( 1 );
}
if ( false === strpos( $bahre, 'خروجی گزارش (CSV)' ) ) {
	fwrite( STDERR, "Check 4 FAIL: 03-bahrebardari.md must document built-in CSV export.\n" );
	exit( 1 );
}
$checks_passed++;
echo "OK 4: 03-bahrebardari.md reports and exports documented.\n";

// Check 5: 05-toshe-dahande.md runnable test commands and valid child theme paths.
$toshe = file_get_contents( $docs_dir . '/05-toshe-dahande.md' );
if ( false !== strpos( $toshe, 'vendor/bin/phpunit' ) ) {
	fwrite( STDERR, "Check 5 FAIL: 05-toshe-dahande.md contains outdated phpunit instructions.\n" );
	exit( 1 );
}
if ( false === strpos( $toshe, 'flavor-core/tests/run-pure-tests.php' ) || false === strpos( $toshe, 'run-e2e-integration.php' ) ) {
	fwrite( STDERR, "Check 5 FAIL: 05-toshe-dahande.md must document runnable test suites.\n" );
	exit( 1 );
}
if ( false === strpos( $toshe, 'flavor/template-parts/menu/*' ) ) {
	fwrite( STDERR, "Check 5 FAIL: 05-toshe-dahande.md must provide exact repo paths for template overrides.\n" );
	exit( 1 );
}
$checks_passed++;
echo "OK 5: 05-toshe-dahande.md test suites and template override paths verified.\n";

// Check 6: 07-demo-css.md assets paths.
$democss = file_get_contents( $docs_dir . '/07-demo-css.md' );
if ( false !== strpos( $democss, '`assets/css/motion.css`' ) || false !== strpos( $democss, '`front-page.php`' ) ) {
	fwrite( STDERR, "Check 6 FAIL: 07-demo-css.md has incomplete paths without flavor/ prefix.\n" );
	exit( 1 );
}
if ( false === strpos( $democss, '`flavor/assets/css/motion.css`' ) || false === strpos( $democss, '`flavor/front-page.php`' ) ) {
	fwrite( STDERR, "Check 6 FAIL: 07-demo-css.md must use canonical flavor/ prefixed paths.\n" );
	exit( 1 );
}
$checks_passed++;
echo "OK 6: 07-demo-css.md canonical assets paths verified.\n";

// Check 7: Terminology consistency ("سازندهٔ پاورقی" and "پاورقی" instead of "پانوشت" or "فوتر").
foreach ( $target_docs as $doc ) {
	$content = file_get_contents( $docs_dir . '/' . $doc );
	if ( false !== strpos( $content, 'پانوشت' ) ) {
		fwrite( STDERR, "Check 7 FAIL: Document {$doc} contains inconsistent term 'پانوشت' instead of 'پاورقی'.\n" );
		exit( 1 );
	}
	if ( false !== strpos( $content, 'هدر و فوتر' ) ) {
		fwrite( STDERR, "Check 7 FAIL: Document {$doc} contains inconsistent term 'هدر و فوتر' instead of 'هدر و پاورقی'.\n" );
		exit( 1 );
	}
}
$checks_passed++;
echo "OK 7: Terminology across all target docs is unified to 'سازندهٔ هدر' and 'سازندهٔ پاورقی' / 'پاورقی'.\n";

// Check 8: 11-elementor.md template json paths and distribution zip clarification.
$elem = file_get_contents( $docs_dir . '/11-elementor.md' );
if ( false === strpos( $elem, 'flavor/elementor/templates/flavor-home.json' ) ) {
	fwrite( STDERR, "Check 8 FAIL: 11-elementor.md must reference exact template json path.\n" );
	exit( 1 );
}
if ( false === strpos( $elem, 'build-distribution.sh' ) ) {
	fwrite( STDERR, "Check 8 FAIL: 11-elementor.md must clarify dist/flavor.zip builder script.\n" );
	exit( 1 );
}
$checks_passed++;
echo "OK 8: 11-elementor.md template paths and distribution instructions verified.\n";

echo "ALL {$checks_passed}/8 DOCS/FA ACCURACY CHECKS PASSED!\n";
exit( 0 );
