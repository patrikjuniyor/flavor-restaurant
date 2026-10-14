<?php
/**
 * Print-receipt contract tests (prompt 62).
 *
 * The 80mm thermal receipt is two artifacts that must stay one system:
 * the theme sheet flavor/assets/css/print-receipt.css (the documented
 * pre-wiring orphan until this prompt) and FlavorCore\Receipt\ReceiptRenderer,
 * which inlines that sheet into the standalone kitchen/cashier document.
 * This gate pins what the live print probe (80mm viewport, print media,
 * PDF MediaBox) proved on a real-shaped order sample:
 *
 *  - paper: @page 80mm auto + 4mm margin, 72mm body column, fixed table
 *    tracks with break-word so a long zero-width-non-joiner dish name wraps
 *    inside the column instead of widening the paper;
 *  - digits: every raw number (order no, qty, table, Jalali datetime) goes
 *    through the storefront digit style, money through Currency::format;
 *  - money: the cashier breakdown prints subtotal / discount / delivery /
 *    payable straight from the ticket row the card now carries;
 *  - table QR: dine-in cashier receipts embed the bundled monochrome SVG
 *    encoder (QrGenerator) pointed at TableRepository::public_url();
 *  - ink: zero background declarations in the sheet AND in the renderer's
 *    built-in fallback, so a plain-paper save stays fully legible;
 *  - the bundled QR library guard keys off a runtime constant: PHP
 *    early-binds unconditional top-level classes at compile time, so the old
 *    class_exists('QRCode') guard always fired and every define() below it
 *    (QR_ERROR_CORRECT_LEVEL_M included) never loaded — a latent fatal on
 *    every QR download, now pinned away.
 *
 *   php tests/theme/test-print-receipt.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

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
		echo '  [PASS] ' . $message . PHP_EOL;
	} else {
		++$failed;
		echo '  [FAIL] ' . $message . PHP_EOL;
	}
}

/**
 * Read a repo-relative file.
 *
 * @param string $rel Path below repo root.
 * @return string Contents.
 */
function read_rel( string $rel ): string {
	$body = file_get_contents( dirname( __DIR__, 2 ) . '/' . $rel );
	if ( false === $body ) {
		echo '  [FAIL] missing file ' . $rel . PHP_EOL;
		exit( 1 );
	}
	return $body;
}

echo "== prompt 62: print receipt contract ==\n";

$root   = dirname( __DIR__, 2 );
$sheet  = read_rel( 'flavor/assets/css/print-receipt.css' );
$render = read_rel( 'flavor-core/includes/Receipt/ReceiptRenderer.php' );
$repo   = read_rel( 'flavor-core/includes/Order/KitchenTicketRepository.php' );
$lib    = read_rel( 'flavor-core/includes/lib/qrcode.php' );
$dead   = read_rel( 'tests/theme/test-dead-code.php' );

// --- 1. paper geometry -----------------------------------------------------
check( (bool) preg_match( '/@page\s*\{[^}]*size:\s*80mm auto/', $sheet ), 'sheet declares @page size 80mm auto' );
check( (bool) preg_match( '/@page\s*\{[^}]*margin:\s*4mm/', $sheet ), 'sheet declares @page margin 4mm' );
check( (bool) preg_match( '/body\.flavor-receipt\s*\{[^}]*width:\s*72mm/', $sheet ), 'receipt column is 72mm inside the 80mm paper' );
check( str_contains( $sheet, 'table-layout: fixed' ), 'receipt tables use fixed tracks (no max-content widening)' );
check( (bool) preg_match( '/\.flavor-receipt th,\s*\.flavor-receipt td\s*\{[^}]*overflow-wrap:\s*break-word/s', $sheet ), 'receipt cells break long unbroken strings' );
check( (bool) preg_match( '/@media print\s*\{[^}]*\.no-print\s*\{[^}]*display:\s*none/s', $sheet ), 'screen-only print button hides on paper' );
check( (bool) preg_match( '/\.flavor-receipt__qr svg\s*\{[^}]*width:\s*28mm/', $sheet ), 'table QR prints at a scannable 28mm' );

// --- 2. ink policy ---------------------------------------------------------
check( ! preg_match( '/background\s*:/', $sheet ), 'sheet paints zero backgrounds (ink save)' );
$fallback = '';
if ( preg_match( '/public static function print_css.*?return\s*(".*?);\n\t\}/s', $render, $m ) ) {
	$fallback = $m[1];
}
check( '' !== $fallback && str_contains( $fallback, '80mm auto' ), 'renderer fallback carries the same 80mm page' );
check( '' !== $fallback && ! preg_match( '/background/', $fallback ), 'renderer fallback paints zero backgrounds too' );

// --- 3. wiring: the sheet has a consumer ------------------------------------
check( str_contains( $render, "'/assets/css/print-receipt.css'" ), 'renderer inlines the theme print sheet (orphan wired)' );
check( ! str_contains( $dead, "'flavor/assets/css/print-receipt.css' === \$a" ), 'dead-code gate no longer carries the pre-wiring exception' );
check( str_contains( $render, 'class="flavor-receipt"' ), 'receipt document wears the sheet body class' );

// --- 4. digits & date -------------------------------------------------------
check( (bool) preg_match( '/function num\( string \$value \).*?Currency::to_persian_digits/s', $render ), 'raw numbers pass the storefront digit style' );
check( str_contains( $render, "Settings::get( 'digits', 'persian' )" ), 'digit style respects the digits setting' );
check( str_contains( $render, 'Jalali::format_datetime' ), 'receipt date prints in Jalali' );

// --- 5. money ---------------------------------------------------------------
foreach ( array( 'subtotal', 'discount_total', 'delivery_fee', 'total' ) as $part ) {
	check( str_contains( $render, "Currency::format( (int) \$card['" . $part . "'] )" ), "cashier breakdown prints {$part}" );
}
check( (bool) preg_match( '/<tr class="tot"><td><\?php esc_html_e\( \'قابل پرداخت\'.*?Currency::format\( \(int\) \$card\[\'total\'\] \)/s', $render ), 'payable row carries the ticket total' );
foreach ( array( 'subtotal', 'discount_total', 'delivery_fee' ) as $part ) {
	check( (bool) preg_match( '/\'' . $part . '\'\s+=> \(int\) \$ticket\[\'' . $part . '\'\]/', $repo ), "to_card carries {$part}" );
}

// --- 6. table QR ------------------------------------------------------------
check( (bool) preg_match( '/\'cashier\' === \$kind && ! empty\( \$card\[\'table_id\'\] \)/', $render ), 'QR is a dine-in cashier feature' );
check( str_contains( $render, 'QrGenerator::svg( TableRepository::public_url( $table ), 256 )' ), 'QR svg encodes the table public url' );
check( str_contains( $render, "if ( '' !== \$qr_svg ) : ?>" ), 'QR block renders whenever the svg is present (no suppression)' );
check( str_contains( $render, "__( 'اسکن برای منوی میز %s', 'flavor-core' )" ), 'QR caption names the table' );

// --- 7. bundled QR library guard --------------------------------------------
check( str_contains( $lib, "defined( 'QR_ERROR_CORRECT_LEVEL_M' )" ), 'QR library guard keys off a runtime constant' );
check( ! str_contains( $lib, "class_exists( 'QRCode', false )" ), 'early-bound class guard is gone (defines load again)' );

// --- 8. live pure checks ----------------------------------------------------
$core = $root . '/flavor-core/includes/';
require_once $core . 'Activator.php';
require_once $core . 'Support/Settings.php';
require_once $core . 'WooCommerce/Currency.php';
require_once $core . 'Support/Jalali.php';
if ( ! defined( 'FLAVOR_CORE_PATH' ) ) {
	define( 'FLAVOR_CORE_PATH', $root . '/flavor-core/' );
}
require_once $core . 'Table/QrGenerator.php';

$matrix = \FlavorCore\Table\QrGenerator::matrix( 'https://restaurant.example.com/branch/shaniz-center/table/12/' );
$n      = count( $matrix );
$dark   = 0;
foreach ( $matrix as $row ) {
	$dark += array_sum( $row );
}
check( $n >= 21 && count( $matrix[0] ) === $n, "QR matrix is square ({$n}x{$n})" );
check( $dark > 50, "QR matrix carries dark modules ({$dark})" );
$svg = \FlavorCore\Table\QrGenerator::svg( 'https://restaurant.example.com/branch/shaniz-center/table/12/', 256 );
check( str_contains( $svg, '<svg' ) && ! preg_match( '/background/i', $svg ), 'QR svg is standalone and background-free' );

$money = \FlavorCore\WooCommerce\Currency::format( 45000000 );
check( preg_match( '/[۰-۹]/', $money ) && ! preg_match( '/[0-9]/', $money ), "money prints in Persian digits ({$money})" );
$date = \FlavorCore\Support\Jalali::format_datetime( '2026-10-14 19:42:00' );
check( preg_match( '/۱۴۰۵/', $date ) && ! preg_match( '/[0-9]/', $date ), "datetime prints Jalali + Persian digits ({$date})" );

// --- 9. catalogue carries the new strings ------------------------------------
$pot = read_rel( 'flavor-core/languages/flavor-core.pot' );
foreach ( array( 'جمع جزئی', 'تخفیف', 'هزینهٔ ارسال', 'قابل پرداخت', 'اسکن برای منوی میز %s' ) as $msgid ) {
	check( str_contains( $pot, 'msgid "' . $msgid . '"' ), "pot carries msgid {$msgid}" );
}

echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed > 0 ? 1 : 0 );
