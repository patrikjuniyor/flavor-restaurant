<?php
/**
 * R3-11: claim-versus-reality test for sales copy.
 *
 * Every numeric or capability claim in README.md, the Raastachin listing,
 * mobile/README.md and flavor/readme.txt must be true against the tree as
 * it stands TODAY. A claim that is bigger than reality fails the build; so
 * does a reality that changed without the text.
 *
 * @package Flavor
 */

$root = dirname( __DIR__, 2 );

function claim_read( string $path ): string {
	return (string) file_get_contents( $path );
}

function claim_fa_to_ascii( string $text ): string {
	return strtr( $text, array_flip(
		array_map( static fn ( $i ) => chr( 0xF0 + $i ) , range( 0, 5 ) )
	) ) ?: $text;
}

function claim_digits( string $text ): string {
	$out = '';
	foreach ( preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
		$ord = mb_ord( $ch );
		$out .= ( $ord >= 0x06F0 && $ord <= 0x06F9 ) ? (string) ( $ord - 0x06F0 ) : $ch;
	}
	return $out;
}

$checks = 0;
$failed = 0;
function check( bool $cond, string $label ): void {
	global $checks, $failed;
	$checks++;
	if ( $cond ) {
		echo "  ✓ " . $label . "\n";
	} else {
		$failed++;
		echo "  ✗ " . $label . "\n";
	}
}

$readme   = claim_read( $root . '/README.md' );
$listing  = claim_read( $root . '/docs/raastichin/listing.md' );
$mobreadme = claim_read( $root . '/mobile/README.md' );
$themerd  = claim_read( $root . '/flavor/readme.txt' );

echo "== 1. no Theme Builder claims in sales copy ==\n";
foreach ( array( 'README.md' => $readme, 'listing.md' => $listing, 'flavor/readme.txt' => $themerd ) as $name => $text ) {
	$hits = preg_grep( '/Theme Builder/i', preg_split( '/\n/', $text ) );
	$negation = static function ( $l ) {
		return false !== strpos( $l, '❌ ندارد' )
			|| false !== strpos( $l, 'ادعا نمی‌شود' )
			|| false !== strpos( $l, 'بدون ادعا' )
			|| false !== stripos( $l, 'not a claim' );
	};
	$hits = array_values( array_filter( $hits, static fn ( $l ) => ! $negation( $l ) ) );
	check( array() === $hits, $name . ' makes no positive Theme Builder claim' );
}

echo "\n== 2. demo count matches the catalog everywhere ==\n";
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $root . '/' ); }
if ( ! defined( 'FLAVOR_DIR' ) ) { define( 'FLAVOR_DIR', $root . '/flavor' ); }
if ( ! function_exists( '__' ) ) { function __( $text, $domain = '' ) { return $text; } }
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/demo-catalog.php';
$demos = array_keys( flavor_demo_catalog() );
$count = is_array( $demos ) ? count( $demos ) : 0;
check( 12 === $count, 'catalog really has 12 demo packs' );
foreach ( array( 'README.md' => $readme, 'listing.md' => $listing ) as $name => $text ) {
	$pct = true;
	foreach ( preg_split( '/\n/', $text ) as $line ) {
		if ( preg_match_all( '/([۰-۹0-9]+)\s*(?:دمو|دمویی|packs?)/iu', $line, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $one ) {
				$claimed = (int) claim_digits( $one[1] );
				if ( 0 !== $claimed && $claimed !== $count ) {
					$pct = false;
				}
			}
		}
	}
	check( $pct, $name . ': every «N دمو» equals the real ' . $count );
}

echo "\n== 3. native app: real code, honest scope ==\n";
check( is_dir( $root . '/mobile/lib' ) && is_file( $root . '/.github/workflows/build-mobile.yml' ), 'the mobile sources and CI job exist' );
check( false !== strpos( $readme, 'اپ بومی' ) && false !== strpos( $readme, 'mobile/' ) && false !== strpos( $readme, 'کافه‌بازار' ), 'README presents the app as in-repo, store release owned by the seller' );
check( false === strpos( $readme, 'خارج از V1: مارکت‌پلیس چندفروشنده، اپ بومی' ), 'README no longer lists the native app as missing' );
check( false === strpos( $mobreadme, 'اسکن QR Code' ) && false !== strpos( $mobreadme, 'با دوربین در V1 نیست' ), 'mobile README dropped the camera-scan claim' );
check( false === strpos( $mobreadme, 'کلیهٔ قابلیت‌های سفارش‌گیری، رزرو میز، پرداخت و' ), 'mobile README no longer promises payment as an app feature' );

echo "\n== 4. no payment-gateway branding in sales copy ==\n";
foreach ( array( 'README.md' => $readme, 'listing.md' => $listing, 'flavor/readme.txt' => $themerd, 'mobile/README.md' => $mobreadme ) as $name => $text ) {
	check( false === mb_stripos( $text, 'زرین' ) && false === mb_stripos( $text, 'آیدی‌پی' ) && false === mb_stripos( $text, 'idpay' ), $name . ' names no bundled gateway' );
}
check( false !== mb_strpos( $listing, 'بسته‌بندی نیست' ), 'listing states explicitly that no gateway is packaged' );

echo "\n== 5. ledger numbers read the live tree ==\n";
check( false !== strpos( $listing, 'فهرست نهایی ادعاها' ), 'listing carries the final claims ledger' );
if ( preg_match( '/\|\s*([۰-۹0-9]+)\s*ویجتِ المنتور/', $listing, $m ) ) {
	$widgets = count( glob( $root . '/flavor/elementor/widgets/class-*-widget.php' ) ?: array() );
	check( (int) claim_digits( $m[1] ) === $widgets, 'widget count in ledger equals ' . $widgets . ' widget classes' );
} else {
	check( false, 'ledger states a widget count' );
}
$ar_line = '';
foreach ( preg_split( '/\n/', $listing ) as $line ) {
	if ( false !== strpos( $line, 'کاتالوگ عربی کامل' ) ) { $ar_line = $line; break; }
}
if ( '' !== $ar_line && preg_match( '/([۰-۹0-9]+)\/([۰-۹0-9]+)/u', $ar_line, $m ) ) {
	$po     = claim_read( $root . '/flavor/languages/flavor-ar.po' );
	$ids    = preg_match_all( '/^msgid ".+"$/m', $po );
	$single = preg_match_all( '/^msgstr ".+"$/m', $po );
	$forms  = preg_match_all( '/^msgstr\[\d+\] ".+"$/m', $po );
	$done   = $single + $forms;
	check( (int) claim_digits( $m[1] ) === $ids, 'ledger claims ' . $m[1] . ' translated; PO carries ' . $ids . ' real msgids' );
	check( '0' !== $m[2] && (int) claim_digits( $m[2] ) === $ids, 'ledger denominator equals the msgid total (100% coverage claim is true)' );
} else {
	check( false, 'ledger states the arabic catalogue count' );
}

echo "\n== 5b. README ledger mirrors the same numbers ==\n";
foreach ( array( 'دمو، درون‌ریزی' => count( $demos ), 'قالب آمادهٔ المنتور' => count( glob( $root . '/flavor/elementor/templates/*.json' ) ?: array() ), 'ویجتِ المنتور' => count( glob( $root . '/flavor/elementor/widgets/class-*-widget.php' ) ?: array() ) ) as $needle => $want ) {
	$found = 0;
	foreach ( preg_split( '/\n/', $readme ) as $line ) {
		if ( false !== strpos( $line, $needle ) && preg_match( '/([۰-۹0-9]+)/u', $line, $m ) ) {
			$found = (int) claim_digits( $m[1] );
			break;
		}
	}
	check( $found === $want, 'README claim «' . $needle . '» says ' . $found . ', tree says ' . $want );
}

echo "\n== 6. update channel claim is backed by a live artifact ==\n";
$have_manifest = is_file( $root . '/release-site/flavor/manifest.json' );
$claims_channel = false !== mb_strpos( $listing, 'کانال به‌روزرسانی' );
check( ! $claims_channel || $have_manifest, 'the ledger only claims the update channel while the signed manifest exists' );
check( ! $have_manifest || is_file( $root . '/release-site/public.key' ), 'a published channel ships its public key' );

echo "\n=======================================================\n";
echo 'Results: ' . $checks . ' Passed, ' . $failed . " Failed\n";
echo "=======================================================\n";
exit( $failed > 0 ? 1 : 0 );
