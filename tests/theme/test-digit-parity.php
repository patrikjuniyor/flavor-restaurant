<?php
/**
 * Pure parity guard for the theme's number formatting (prompt 22).
 *
 * Before this, eight scripts each shaped their own digits: menu.js and ui.js
 * asked the browser (toLocaleString('fa-IR'), i.e. whatever the local ICU build
 * knows about Persian), the rest hand-mapped a '۰۱۲۳۴۵۶۷۸۹' table with no
 * thousands separator at all, and PHP's Currency::format() grouped with
 * number_format_i18n(). Three answers for the same price, and nothing compared
 * them. digits.js + UI::digits_config() is now the single spec, so this file
 * checks the parts that can be checked without a browser:
 *
 *   1. the spec PHP publishes is well-formed and derived, not hard-coded;
 *   2. the ten characters the JS algorithm will build from `zero` are exactly the
 *      ones core's strtr() table produces — the parameterisation is the risk;
 *   3. digits.js' own offline fallback still equals what PHP sends, or a page
 *      that loses the payload quietly changes the shop's number style;
 *   4. no script outside digits.js owns a digit table or calls toLocaleString;
 *   5. the module is actually on the wire (payload key + script dependency), and
 *      the node side that proves behaviour parity is wired into the runners.
 *
 * Behaviour parity between the two languages is tests/ui/digits.mjs: it runs this
 * PHP and that file over the same amounts. Run: php tests/theme/test-digit-parity.php
 */

declare( strict_types=1 );

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once dirname( __DIR__, 2 ) . '/flavor-core/includes/WooCommerce/Currency.php';

$passed = 0;
$failed = 0;

function check( bool $condition, string $label ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$label}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$label}\n";
	}
}

$js_dir       = FLAVOR_DIR . '/assets/js';
$enqueue_php  = (string) file_get_contents( FLAVOR_DIR . '/inc/class-enqueue.php' );
$digits_js    = (string) file_get_contents( $js_dir . '/digits.js' );
$config       = \Flavor\UI::digits_config();

echo "\n--- 1. The spec PHP publishes ---\n";

check(
	array( 'zero', 'arabicZero', 'thousands', 'mode' ) === array_keys( $config ),
	'the spec carries exactly the four keys digits.js reads, nothing a script has to guess'
);
check(
	1 === mb_strlen( $config['zero'] ) && 0x6f0 === mb_ord( $config['zero'], 'UTF-8' ),
	"zero is U+06F0 (the Persian ۰ the JS offset maths starts from)"
);
check(
	1 === mb_strlen( $config['arabicZero'] ) && 0x660 === mb_ord( $config['arabicZero'], 'UTF-8' ),
	'arabicZero is U+0660, so Arabic-Indic input is read back as Latin too'
);
check(
	in_array( $config['mode'], array( 'persian', 'latin' ), true ),
	'the digit mode is one of the two the setting can hold (' . $config['mode'] . ')'
);
check(
	'' === $config['thousands'] || 1 === mb_strlen( $config['thousands'] ),
	'the thousands separator is one character or empty, never a stray group of bytes'
);

// The separator must be read out of the same call that formats a price, not
// assumed: fa_IR in WordPress groups with U+066C while the default locale uses a
// comma, and a hard-coded comma is exactly the bug this prompt exists to kill.
$sample = (string) number_format_i18n( 1234567 );
$derived = preg_replace( '/\p{N}+/u', '', $sample );
$expect  = '';
if ( is_string( $derived ) && preg_match( '/^\P{N}/u', $derived, $match ) ) {
	$expect = $match[0];
}
check(
	$expect === (string) $config['thousands'],
	'the separator really comes from number_format_i18n ("' . $config['thousands'] . '"), not from a guess'
);

echo "\n--- 2. The JS algorithm reproduces the core table ---\n";

/*
 * digits.js has no table: it renders `zero + digit`. That is only equivalent to
 * \FlavorCore\WooCommerce\Currency::to_persian_digits() if the block is
 * contiguous, so compare the two outputs digit by digit instead of trusting the
 * arithmetic — and compare a grouped price as well, which is what users read.
 */
$zero   = mb_ord( (string) $config['zero'], 'UTF-8' );
$diff   = array();
$prices = array( 0, 7, 42, 999, 1000, 12345, 100000, 1234567, 25000000 );
foreach ( $prices as $amount ) {
	$php      = \FlavorCore\WooCommerce\Currency::to_persian_digits( number_format_i18n( $amount ) );
	$separ    = (string) $config['thousands'];
	$grouped  = preg_replace( '/\B(?=(\d{3})+(?!\d))/u', $separ, (string) $amount );
	$mapped   = preg_replace_callback(
		'/[0-9]/',
		static function ( array $m ) use ( $zero ): string {
			return mb_chr( $zero + (int) $m[0], 'UTF-8' );
		},
		$grouped
	);
	$expected = ( 'persian' === $config['mode'] ) ? $mapped : $grouped;
	if ( $expected !== $php ) {
		$diff[] = $amount . ': php="' . $php . '" js="' . $expected . '"';
	}
}
check(
	! $diff,
	'over nine real amounts, the offset mapping digits.js performs equals Currency::to_persian_digits( number_format_i18n() )'
	. ( $diff ? ' — ' . implode( '; ', $diff ) : '' )
);

foreach ( range( 0, 9 ) as $digit ) {
	if ( \FlavorCore\WooCommerce\Currency::to_persian_digits( (string) $digit ) !== mb_chr( $zero + $digit, 'UTF-8' ) ) {
		$diff[] = (string) $digit;
	}
}
check( ! $diff, 'the digit block is contiguous from zero, so no digit can drift from core (bad: ' . ( $diff ? implode( ',', $diff ) : 'none' ) . ')' );

echo "\n--- 3. The offline fallback in digits.js matches the server ---\n";

$fallback = array();
if ( preg_match( '/var FALLBACK = \{([^}]*)\}/', $digits_js, $m ) ) {
	// 'zero: 0x06f0' — the fallback is spelled as an escape on purpose (no digit
	// table in a JS file), so compare it against the characters PHP sends.
	if ( preg_match( '/zero:\s*0x([0-9a-f]{4})/i', $m[1], $z ) ) {
		$fallback['zero'] = (int) hexdec( $z[1] );
	}
	if ( preg_match( '/arabicZero:\s*0x([0-9a-f]{4})/i', $m[1], $z ) ) {
		$fallback['arabicZero'] = (int) hexdec( $z[1] );
	}
	preg_match( "/thousands:\s*'([^']*)'/", $m[1], $t );
	$fallback['thousands'] = $t[1] ?? '';
}
check( isset( $fallback['zero'] ), 'digits.js declares its fallback spec in one place' );
check(
	( $fallback['zero'] ?? -1 ) === mb_ord( (string) $config['zero'], 'UTF-8' ),
	'a page that lost the payload still starts Persian digits at the codepoint PHP uses'
);
check(
	( $fallback['arabicZero'] ?? -1 ) === mb_ord( (string) $config['arabicZero'], 'UTF-8' ),
	'and the same for the Arabic-Indic block it has to read back'
);
check(
	( $fallback['thousands'] ?? null ) === (string) $config['thousands'] || '' === (string) $config['thousands'],
	'the fallback separator is the shipped default (a comma) — a server that groups differently is told, not assumed'
);
$digits_code = preg_replace( '/\/\*.*?\*\/|\/\/[^\n]*/s', '', $digits_js ); // comments document the old table
check(
	0 === preg_match( "/['\"][۰-۹٠-٩]{10}['\"]/u", $digits_code ),
	'digits.js maps by codepoint rather than keeping a table of its own'
);

echo "\n--- 4. No other script shapes a number on its own ---\n";

/*
 * The ledger is derived from the directory, so adding a tenth formatter fails
 * here instead of shipping. Input parsing is deliberately allowed: motion.js
 * reads a number out of text the visitor (or PHP) wrote, which is why latin() is
 * exported, and pages.js folds Arabic letters for search — neither is a display.
 */
$offenders  = array();
$scanned    = 0;
foreach ( glob( $js_dir . '/*.js' ) as $path ) {
	$basename = basename( $path );
	if ( 'digits.js' === $basename || 'admin' === substr( $basename, -8, -3 ) ) {
		continue;
	}
	++$scanned;
	$src      = (string) file_get_contents( $path );
	$code     = preg_replace( '/\/\*.*?\*\/|\/\/[^\n]*/s', '', $src ); // strip comments
	$findings = array();
	if ( preg_match( "/['\"][۰-۹٠-٩]{10}['\"]/u", $code ) ) {
		$findings[] = 'carries a digit table';
	}
	if ( preg_match( "/[۰-۹]\s*',\s*'[۰-۹]/u", $code ) ) {
		$findings[] = 'carries a digit list';
	}
	if ( false !== strpos( $code, 'toLocaleString' ) ) {
		$findings[] = 'formats a number through the browser locale';
	}
	if ( $findings ) {
		$offenders[] = $basename . ' (' . implode( ', ', $findings ) . ')';
	}
}
check( $scanned > 20, 'the scan covers the whole storefront script set (' . $scanned . ' files)' );
check(
	! $offenders,
	'no script outside digits.js decides how a number looks' . ( $offenders ? ': ' . implode( '; ', $offenders ) : '' )
);

echo "\n--- 5. The spec is on the wire ---\n";

check(
	(bool) preg_match( "/\\\$payload\['digits'\]\s*=\s*UI::digits_config\(\)/", $enqueue_php ),
	'flavorData.digits is filled from UI::digits_config() in Enqueue'
);
check(
	(bool) preg_match( "/'flavor-digits',\s*\n\s*FLAVOR_URI \. '\/assets\/js\/digits\.js'/", $enqueue_php ),
	'digits.js is registered as its own handle, so the dependency order is explicit'
);
check(
	(bool) preg_match( "/'flavor-main-js',\s*\n\s*FLAVOR_URI \. '\/assets\/js\/main\.js',\s*\n\s*array\( 'flavor-digits' \)/", $enqueue_php ),
	'flavor-main-js depends on flavor-digits — every script that loads main gets the formatter first'
);
check(
	(bool) preg_match( '/root\.FlavorDigits = \{/', $digits_js ),
	'digits.js exports the module the other scripts call by name'
);
foreach ( array( 'config', 'digits', 'latin', 'group', 'money' ) as $export ) {
	check(
		(bool) preg_match( '/\b' . preg_quote( $export, '/' ) . ':/', $digits_js ),
		'and exports ' . $export . '()'
	);
}

echo "\n--- 6. The behavioural test is wired into the runners ---\n";

$mjs = dirname( __DIR__ ) . '/ui/digits.mjs';
check( is_file( $mjs ), 'tests/ui/digits.mjs exists — it runs both languages over the same amounts' );
check(
	false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/ui/package.json' ), '"test:digits"' ),
	'node tests/ui/digits.mjs is reachable through npm run test:digits'
);
$ci = (string) file_get_contents( dirname( __DIR__, 2 ) . '/.github/workflows/ci.yml' );
check(
	false !== strpos( $ci, 'tests/ui/digits.mjs' ),
	'CI runs it, so a formatting change cannot land without the parity check'
);

echo "\n=======================================================\n";
printf( "Results: %d Passed, %d Failed\n", $passed, $failed );
echo "=======================================================\n";
exit( $failed > 0 ? 1 : 0 );
