<?php
/**
 * Contract Test for Licensing & Third-Party Credits (Prompt 100).
 *
 * Validates:
 * 1. Root LICENSE file exists and specifies GPL-2.0-or-later.
 * 2. Bundled packages (flavor/, flavor-core/, flavor-child/) contain LICENSE.
 * 3. Root and package THIRD-PARTY.md exist and document:
 *    - GPL-2.0-or-later primary software.
 *    - Vazirmatn font (SIL OFL 1.1, Saber Rastikerdar).
 *    - Estedad font (SIL OFL 1.1, Amin Abedi).
 *    - qrcode.php library (MIT License, Kazuhiko Arase).
 *    - Explicit clarification that IRANSans is not bundled.
 * 4. PHP header declarations in flavor/style.css, flavor-core/flavor-core.php,
 *    and flavor-child/style.css specify GPL-2.0 or later.
 * 5. Fonts directory contains no unfree or unlicensed fonts.
 * 6. IRANSans directory contains ONLY documentation/README, no font binaries.
 */

$root = dirname( __DIR__, 2 );

function lic_fail( string $message ): void {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
}

function lic_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		lic_fail( $message );
	}
}

// 1. Root LICENSE
$root_license = $root . '/LICENSE';
lic_check( file_exists( $root_license ), 'Root LICENSE file must exist.' );
$license_text = (string) file_get_contents( $root_license );
lic_check( false !== strpos( $license_text, 'GNU GENERAL PUBLIC LICENSE' ), 'Root LICENSE must be GNU GPL.' );
lic_check( false !== strpos( $license_text, 'Version 2' ), 'Root LICENSE must be Version 2 or later.' );

// 2. Package LICENSE files
$package_licenses = array(
	$root . '/flavor/LICENSE',
	$root . '/flavor-core/LICENSE',
);
foreach ( $package_licenses as $pkg_lic ) {
	lic_check( file_exists( $pkg_lic ), "Package license must exist at {$pkg_lic}" );
	$txt = (string) file_get_contents( $pkg_lic );
	lic_check( false !== strpos( $txt, 'GNU GENERAL PUBLIC LICENSE' ), "Package license at {$pkg_lic} must be GPL." );
}

// 3. THIRD-PARTY.md files
$third_party_files = array(
	$root . '/THIRD-PARTY.md',
	$root . '/flavor/THIRD-PARTY.md',
	$root . '/flavor-core/THIRD-PARTY.md',
);
foreach ( $third_party_files as $tp_file ) {
	lic_check( file_exists( $tp_file ), "THIRD-PARTY.md must exist at {$tp_file}" );
	$tp_text = (string) file_get_contents( $tp_file );

	lic_check( false !== strpos( $tp_text, 'GPL-2.0' ), "{$tp_file} must credit GPL-2.0" );
	lic_check( false !== strpos( $tp_text, 'Vazirmatn' ) || false !== strpos( $tp_text, 'وزیرمتن' ), "{$tp_file} must credit Vazirmatn" );
	lic_check( false !== strpos( $tp_text, 'Estedad' ) || false !== strpos( $tp_text, 'استعداد' ), "{$tp_file} must credit Estedad" );
	lic_check( false !== strpos( $tp_text, 'OFL' ), "{$tp_file} must specify OFL for fonts" );
	lic_check( false !== strpos( $tp_text, 'qrcode.php' ) || false !== strpos( $tp_text, 'QRCode' ), "{$tp_file} must credit qrcode.php" );
	lic_check( false !== strpos( $tp_text, 'Kazuhiko Arase' ), "{$tp_file} must credit Kazuhiko Arase" );
	lic_check( false !== strpos( $tp_text, 'MIT' ), "{$tp_file} must specify MIT for QR library" );
	lic_check( false !== strpos( $tp_text, 'IRANSans' ), "{$tp_file} must document IRANSans policy" );
}

// 4. Headers in primary source files
$theme_style = (string) file_get_contents( $root . '/flavor/style.css' );
lic_check( (bool) preg_match( '/License:\s*GNU General Public License v2/i', $theme_style ), 'flavor/style.css must declare GPL v2 license' );

$core_main = (string) file_get_contents( $root . '/flavor-core/flavor-core.php' );
lic_check( (bool) preg_match( '/License:\s*GPL\s*v?2/i', $core_main ), 'flavor-core/flavor-core.php must declare GPL v2 license' );

$child_style = (string) file_get_contents( $root . '/flavor-child/style.css' );
lic_check( (bool) preg_match( '/License:\s*GNU General Public License v2/i', $child_style ), 'flavor-child/style.css must declare GPL v2 license' );

// 5. Check bundled font licenses
lic_check( file_exists( $root . '/flavor/assets/fonts/vazirmatn/OFL.txt' ), 'vazirmatn/OFL.txt must exist.' );
lic_check( file_exists( $root . '/flavor/assets/fonts/estedad/OFL.txt' ), 'estedad/OFL.txt must exist.' );

// 6. IRANSans directory must NOT bundle any font binaries
$iransans_dir = $root . '/flavor/assets/fonts/iransans';
if ( is_dir( $iransans_dir ) ) {
	$font_binaries = array_merge( (array) glob( $iransans_dir . '/*.woff2' ), (array) glob( $iransans_dir . '/*.woff' ), (array) glob( $iransans_dir . '/*.ttf' ) );
	lic_check( empty( $font_binaries ), 'flavor/assets/fonts/iransans must contain NO font binaries.' );
	lic_check( file_exists( $iransans_dir . '/README.md' ), 'flavor/assets/fonts/iransans must contain README.md explaining Bring-Your-Own-License.' );
}

// 7. QR code library header
$qrcode_file = (string) file_get_contents( $root . '/flavor-core/includes/lib/qrcode.php' );
lic_check( false !== strpos( $qrcode_file, 'Kazuhiko Arase' ), 'qrcode.php must credit author Kazuhiko Arase.' );
lic_check( false !== strpos( $qrcode_file, 'MIT' ), 'qrcode.php must declare MIT license.' );

echo "PASS: Licensing & Third-Party Credits contract passed (7 check areas, all green).\n";
exit( 0 );
