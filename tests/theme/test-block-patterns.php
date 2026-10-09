<?php
/**
 * Block pattern contract tests.
 *
 * Patterns are shipped as raw block markup strings containing class names
 * like `has-primary-background-color`. Those class names only produce CSS if
 * `primary` is a real slug in flavor/theme.json, and nothing else in the repo
 * checks that. A renamed palette slug would silently strip the brand colour
 * out of every section a customer had already inserted.
 *
 * These tests keep pattern markup and the theme.json palette in step, and
 * assert the markup itself is well formed.
 *
 *   php tests/theme/test-block-patterns.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';

require_once FLAVOR_DIR . '/inc/class-block-patterns.php';

use Flavor\Block_Patterns;

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

echo "=== Flavor Block Pattern Contract Tests ===\n\n";

$patterns = Block_Patterns::patterns();
$theme    = json_decode( (string) file_get_contents( FLAVOR_DIR . '/theme.json' ), true );

$palette_slugs = array();
foreach ( (array) ( $theme['settings']['color']['palette'] ?? array() ) as $colour ) {
	$palette_slugs[] = (string) ( $colour['slug'] ?? '' );
}

echo "--- 1. Every pattern is complete ---\n";

check( count( $patterns ) >= 6, 'a usable library of patterns is registered (' . count( $patterns ) . ')' );
check( count( $palette_slugs ) > 0, 'theme.json exposes a palette to validate against (' . count( $palette_slugs ) . ' slugs)' );

foreach ( $patterns as $slug => $pattern ) {
	check(
		'' !== (string) ( $pattern['title'] ?? '' ),
		"{$slug} has a title"
	);
	check(
		'' !== (string) ( $pattern['description'] ?? '' ),
		"{$slug} has a description"
	);
	check(
		! empty( $pattern['keywords'] ),
		"{$slug} carries inserter keywords"
	);
	check(
		str_starts_with( $slug, 'flavor/' ),
		"{$slug} is namespaced under flavor/"
	);
}

echo "\n--- 2. Block markup is well formed ---\n";

/**
 * Verify every opening block comment is closed, in the right order.
 *
 * Handles both container blocks (`<!-- wp:x -->` ... `<!-- /wp:x -->`) and
 * void blocks (`<!-- wp:x /-->`).
 *
 * @param string $markup Block markup.
 * @return array<int, string> Problems found.
 */
$validate_markup = static function ( string $markup ): array {
	$problems = array();
	$stack    = array();

	// The attributes between the block name and `-->` are arbitrary JSON, so
	// the pattern must consume them; matching only `name --> ` misses every
	// block that carries attributes, which is nearly all of them.
	if ( preg_match_all( '/<!--\s+(\/?)wp:([a-z0-9-]+\/[a-z0-9-]+|[a-z0-9-]+)(.*?)-->/is', $markup, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $match ) {
			$is_close = '/' === $match[1];
			$name     = strtolower( $match[2] );
			$is_void  = str_ends_with( trim( (string) ( $match[3] ?? '' ) ), '/' );

			if ( $is_void ) {
				continue;
			}
			if ( $is_close ) {
				$open = array_pop( $stack );
				if ( $open !== $name ) {
					$problems[] = sprintf( 'closing </wp:%s> does not match open <wp:%s>', $name, (string) $open );
				}
				continue;
			}
			$stack[] = $name;
		}
	}

	if ( ! empty( $stack ) ) {
		$problems[] = 'unclosed blocks: ' . implode( ', ', $stack );
	}

	return $problems;
};

foreach ( $patterns as $slug => $pattern ) {
	$problems = $validate_markup( (string) $pattern['content'] );
	check(
		array() === $problems,
		"{$slug} markup is balanced" . ( $problems ? ' — ' . $problems[0] : '' )
	);
}

echo "\n--- 3. Colours resolve to the theme.json palette ---\n";

foreach ( $patterns as $slug => $pattern ) {
	$content = (string) $pattern['content'];
	$bad     = array();

	// Brand colours appear as has-<slug>-color and has-<slug>-background-color.
	// Two look-alikes are core's own state classes rather than palette slugs:
	// has-text-color ("this element has a text colour at all") and
	// has-link-color. Both must be ignored or every pattern reports a phantom
	// "unknown colour".
	$generic = array( 'text', 'link', 'background' );

	preg_match_all( '/has-([a-z0-9-]+)-background-color\b/i', $content, $mb );
	preg_match_all( '/has-([a-z0-9-]+)-color\b(?!-)/i', $content, $mf );

	// A background class also matches the plain `-color` rule; strip it so it
	// is only validated once.
	$foreground = array_diff( $mf[1] ?? array(), $mb[1] ?? array() );

	foreach ( array_unique( $mb[1] ?? array() ) as $used ) {
		if ( ! in_array( $used, $palette_slugs, true ) ) {
			$bad[] = $used . ' (class)';
		}
	}
	foreach ( array_unique( $foreground ) as $used ) {
		// has-ink-background-color also matches the foreground rule as
		// "ink-background"; it was already validated as "ink" above.
		if ( str_ends_with( $used, '-background' ) ) {
			continue;
		}
		if ( in_array( $used, $generic, true ) ) {
			continue;
		}
		if ( ! in_array( $used, $palette_slugs, true ) ) {
			$bad[] = $used . ' (class)';
		}
	}

	// Preset reference form: "backgroundColor":"primary" / var:preset|color|primary.
	preg_match_all( '/"(?:text|background)Color"\s*:\s*"([a-z0-9-]+)"/i', $content, $m2 );
	foreach ( array_unique( $m2[1] ) as $used ) {
		if ( ! in_array( $used, $palette_slugs, true ) ) {
			$bad[] = $used . ' (attr)';
		}
	}
	preg_match_all( '/var:preset\|color\|([a-z0-9-]+)/i', $content, $m3 );
	foreach ( array_unique( $m3[1] ) as $used ) {
		if ( ! in_array( $used, $palette_slugs, true ) ) {
			$bad[] = $used . ' (preset)';
		}
	}

	check(
		array() === $bad,
		"{$slug} only uses palette colours that exist" . ( $bad ? ' — unknown: ' . implode( ', ', $bad ) : '' )
	);
}

echo "\n--- 4. Spacing presets resolve to the theme.json scale ---\n";

$spacing_slugs = array();
foreach ( (array) ( $theme['settings']['spacing']['spacingSizes'] ?? array() ) as $step ) {
	$spacing_slugs[] = (string) ( $step['slug'] ?? '' );
}

foreach ( $patterns as $slug => $pattern ) {
	$bad = array();
	preg_match_all( '/var:preset\|spacing\|([0-9]+)/', (string) $pattern['content'], $m );
	foreach ( array_unique( $m[1] ) as $used ) {
		if ( ! in_array( $used, $spacing_slugs, true ) ) {
			$bad[] = $used;
		}
	}
	check(
		array() === $bad,
		"{$slug} only uses spacing steps that exist" . ( $bad ? ' — unknown: ' . implode( ', ', $bad ) : '' )
	);
}

echo "\n--- 5. Content is translatable and non-empty ---\n";

foreach ( $patterns as $slug => $pattern ) {
	$content = (string) $pattern['content'];
	check( strlen( trim( $content ) ) > 120, "{$slug} carries real content (" . strlen( trim( $content ) ) . ' chars)' );
	check(
		(bool) preg_match( '/\p{Arabic}/u', $content ),
		"{$slug} ships Persian copy by default"
	);
}

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
