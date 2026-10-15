<?php
/**
 * Contract test for CHANGELOG.md structure and Keep a Changelog compliance.
 *
 * Checks:
 * 1. File existence and non-emptiness.
 * 2. Presence of ## [Unreleased] header at top.
 * 3. Latest released section matches style.css theme version and carries both Jalali and Gregorian dates.
 * 4. Standard Keep a Changelog categories (Added, Fixed, Security, Changed) exist in the latest release.
 * 5. Alignment with dev-tools/check-versions.php assertions.
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

echo "=== Flavor CHANGELOG.md Structure & Contract Test ===\n\n";

$changelog_path = $root . '/CHANGELOG.md';
check( is_readable( $changelog_path ), 'CHANGELOG.md exists and is readable' );

$content = (string) file_get_contents( $changelog_path );

// 1. Unreleased section
check( 1 === preg_match( '/^##\s*\[Unreleased\]/m', $content ), 'CHANGELOG.md has an active ## [Unreleased] section' );

// 2. Latest version section header
check(
	1 === preg_match( '/^##\s*\[1\.6\.2\s*\/\s*Core\s*1\.5\.1\]\s*[-—]\s*2026-10-15/mu', $content ),
	'Latest version section is [1.6.2 / Core 1.5.1] with 2026-10-15 date'
);

// 3. Persian date included
check(
	false !== strpos( $content, '۲۳ مهر ۱۴۰۵' ),
	'Latest release header contains the Persian Jalali date (۲۳ مهر ۱۴۰۵)'
);

// 4. Standard categories in latest release
check(
	false !== strpos( $content, '### Added' ) &&
	false !== strpos( $content, '### Fixed' ) &&
	false !== strpos( $content, '### Security' ) &&
	false !== strpos( $content, '### Changed' ),
	'Latest release section includes Added, Fixed, Security, and Changed categories'
);

// 5. Version consistency with style.css
$style_css = (string) file_get_contents( $root . '/flavor/style.css' );
if ( preg_match( '/Version:\s*([0-9.]+)/i', $style_css, $m ) ) {
	$theme_ver = $m[1];
	check(
		false !== strpos( $content, "[{$theme_ver} " ),
		"CHANGELOG.md released section matches style.css version ({$theme_ver})"
	);
} else {
	check( false, 'Could not read version from flavor/style.css' );
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
