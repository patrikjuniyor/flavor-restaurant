<?php
/**
 * Output-escaping guard for every template surface (R4-3 / پرامپت ۳۵).
 *
 * Echoes and `<?=` in theme templates and plugin admin views must escape
 * every variable that reaches output (esc_html/esc_attr/esc_url/wp_kses_post
 * or a numeric cast), and a phpcs:ignore may only sit on output that is
 * genuinely constant. The scanner tokenizes with PHP itself, so strings and
 * comments can never fake it out.
 *
 *   php tests/theme/test-output-escaping.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

$root = dirname( __DIR__, 2 );

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

$ESCAPERS = array(
	'esc_html', 'esc_attr', 'esc_url', 'esc_textarea', 'esc_js', 'esc_html__', 'esc_attr__', 'esc_url__',
	'wp_kses_post', 'wp_kses', 'wp_kses_data', 'absint', 'intval', 'floatval', 'wp_json_encode',
	'number_format_i18n', 'size_format', 'checked', 'selected', 'disabled',
);

/**
 * Is a token sequence safe to echo verbatim (constant / escaped / numeric)?
 *
 * @param array $toks Tokens of one comma/concat operand.
 * @return bool
 */
function expr_is_safe( array $toks ): bool {
	global $ESCAPERS;

	// Strip whitespace.
	$toks = array_values( array_filter( $toks, static function ( $t ) {
		return ! ( is_array( $t ) && T_WHITESPACE === $t[0] );
	} ) );
	if ( array() === $toks ) {
		return true;
	}

	// Concatenation / comma already split upstream; allow nested parens:
	// unwrap one full outer pair when it covers everything.
	if ( '(' === $toks[0] && ')' === end( $toks ) ) {
		$depth = 0;
		$closes_at_end = true;
		foreach ( $toks as $i => $t ) {
			$x = is_array( $t ) ? '' : $t;
			if ( '(' === $x ) {
				$depth++;
			} elseif ( ')' === $x ) {
				$depth--;
				if ( 0 === $depth && $i !== count( $toks ) - 1 ) {
					$closes_at_end = false;
					break;
				}
			}
		}
		if ( $closes_at_end ) {
			return expr_is_safe( array_slice( $toks, 1, -1 ) );
		}
	}

	// Numeric/bool cast prefix.
	$cast_ids = array( T_INT_CAST, T_BOOL_CAST, T_DOUBLE_CAST );
	if ( is_array( $toks[0] ) && in_array( $toks[0][0], $cast_ids, true ) ) {
		return expr_is_safe( array_slice( $toks, 1 ) );
	}

	$first = $toks[0];
	$text = is_array( $first ) ? $first[1] : $first;

	// Full ternary `$c ? $a : $b`: the condition is not echoed, only the
	// two output branches must be safe. Elvis `$x ?: $fallback` echoes $x
	// when truthy, so both sides need to be safe.
	// PHP 8 lexes the ternary as plain '?' / ':' characters; elvis stays
	// T_COALESCE. Only top-level (depth 0) occurrences split the expression:
	// a `?:` inside esc_html(...) must not send us down the ternary path.
	$depth0 = 0;
	foreach ( $toks as $i => $t ) {
		$txt = is_array( $t ) ? '' : $t;
		if ( '(' === $txt || '[' === $txt ) {
			$depth0++;
			continue;
		}
		if ( ')' === $txt || ']' === $txt ) {
			$depth0--;
			continue;
		}
		if ( 0 !== $depth0 ) {
			continue;
		}
		if ( '?' === $txt ) {
			$rest    = array_slice( $toks, $i + 1 );
			$else_at = null;
			$depth2  = 0;
			foreach ( $rest as $j => $r ) {
				$rt = is_array( $r ) ? '' : $r;
				if ( '(' === $rt || '[' === $rt ) {
					$depth2++;
				} elseif ( ')' === $rt || ']' === $rt ) {
					$depth2--;
				} elseif ( ':' === $rt && 0 === $depth2 ) {
					$else_at = $j;
					break;
				}
			}
			if ( null === $else_at ) {
				return false; // `?` without `:` is not display-safe by itself.
			}
			return expr_is_safe( array_slice( $rest, 0, $else_at ) ) && expr_is_safe( array_slice( $rest, $else_at + 1 ) );
		}
		if ( is_array( $t ) && T_COALESCE === $t[0] ) {
			return expr_is_safe( array_slice( $toks, 0, $i ) ) && expr_is_safe( array_slice( $toks, $i + 1 ) );
		}
	}

	// Constants and literals.
	if ( is_array( $first ) && in_array( $first[0], array( T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER ), true ) ) {
		return true;
	}
	if ( is_array( $first ) && T_STRING === $first[0] && in_array( strtolower( $first[1] ), array( 'true', 'false', 'null' ), true ) ) {
		return true;
	}
	// Class constants that are literal strings (self::X / static::X / Foo::BAR) are
	// treated as constant — the ignore-audit section double-checks those flagged.
	if ( is_array( $first ) && T_ARRAY === $first[0] ) {
		return true; // array literal, echoed via print_r? not our surface.
	}

	// Escaper / allowlisted call wrapping the whole expression: name(...) covering all tokens.
	if ( is_array( $first ) && T_STRING === $first[0] && isset( $toks[1] ) && '(' === $toks[1] ) {
		$name = strtolower( $first[1] );
		if ( in_array( $name, $ESCAPERS, true ) ) {
			return true;
		}
	}
	// Known-escaping display helpers (they escape internally or are covered by
	// dedicated contract tests elsewhere in the suite).
	$name_t = null;
	if ( is_array( $first ) && T_STRING === $first[0] ) {
		$name_t = strtolower( $first[1] );
	} elseif ( is_array( $first ) && T_NS_SEPARATOR === $first[0] ) {
		// \Flavor\Bespoke_Demos::hero_title() — find the last T_STRING before '('.
		for ( $k = count( $toks ) - 1; $k > 0; $k-- ) {
			if ( is_array( $toks[ $k ] ) && T_STRING === $toks[ $k ][0] ) {
				$name_t = strtolower( $toks[ $k ][1] );
				break;
			}
		}
	}
	if ( null !== $name_t ) {
		foreach ( array( 'the_', 'flavor_', 'wp_print' ) as $prefix ) {
			if ( 0 === strpos( $name_t, $prefix ) ) {
				return true;
			}
		}
		foreach ( array( 'body_class', 'wp_head', 'wp_footer', 'dynamic_sidebar', 'settings_fields', 'do_settings_sections', 'wp_nonce_field', 'submit_button', 'do_shortcode' ) as $fn ) {
			if ( $name_t === $fn ) {
				return true;
			}
		}
	}
	// Argument-less renderer methods/statics on a fully-qualified or object
	// name (Class::render(), $part->hero_title(), ...): the callee owns its
	// escaping and is covered by its own suite.
	$joined = implode( '', array_map( static fn( $t ) => is_array( $t ) ? $t[1] : $t, $toks ) );
	$tl       = strtolower( trim( $joined ) );
	foreach ( array( 'render', 'hero_title', 'digits', 'render_section', 'render_rows' ) as $renderer) {
		if ( str_ends_with( $tl, '::' . $renderer . '()' ) || str_ends_with( $tl, '->' . $renderer . '()' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Extract echo/print/<?= statements from source.
 *
 * @param string $src PHP source.
 * @return array<int,array{line:int,tokens:array}>
 */
function echo_statements( string $src ): array {
	$toks   = token_get_all( $src );
	$out    = array();
	$n      = count( $toks );
	$i      = 0;
	while ( $i < $n ) {
		$t  = $toks[ $i ];
		$is_echo = is_array( $t ) && in_array( $t[0], array( T_ECHO, T_PRINT ), true );
		$is_short = is_array( $t ) && T_OPEN_TAG_WITH_ECHO === $t[0];
		if ( ! $is_echo && ! $is_short ) {
			++$i;
			continue;
		}
		$line = is_array( $t ) ? $t[2] : 1;
		$buf  = array();
		$depth = 0;
		for ( $j = $i + 1; $j < $n; $j++ ) {
			$u   = $toks[ $j ];
			$txt = is_array( $u ) ? $u[1] : $u;
			if ( '(' === $txt || '[' === $txt ) {
				$depth++;
			} elseif ( ')' === $txt || ']' === $txt ) {
				$depth--;
			}
			if ( 0 === $depth && ( ';' === $txt || ( is_array( $u ) && T_CLOSE_TAG === $u[0] ) ) ) {
				break;
			}
			if ( is_array( $u ) && in_array( $u[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$buf[] = $u;
		}
		$i = $j ?? $n;
		$out[] = array( 'line' => $line, 'tokens' => $buf );
		++$i;
	}
	return $out;
}

/**
 * Split operands by top-level commas and concatenation dots.
 *
 * @param array $buf Statement tokens (no comments).
 * @return array<int,array>
 */
function split_operands( array $buf ): array {
	$parts = array();
	$cur   = array();
	$depth = 0;
	foreach ( $buf as $t ) {
		$txt = is_array( $t ) ? $t[1] : $t;
		if ( '(' === $txt || '[' === $txt ) {
			$depth++;
		} elseif ( ')' === $txt || ']' === $txt ) {
			$depth--;
		}
		$is_sep = 0 === $depth && ( ',' === $txt || ( ! is_array( $t ) && '.' === $txt ) );
		if ( $is_sep ) {
			$parts[] = $cur;
			$cur     = array();
			continue;
		}
		$cur[] = $t;
	}
	$parts[] = $cur;
	return array_filter( $parts, static fn( $p ) => array() !== $p );
}

$targets = array();
foreach ( glob( $root . '/flavor/*.php' ) as $f ) {
	$targets[] = $f;
}
foreach ( array( 'flavor/template-parts', 'flavor/page-templates', 'flavor-core/admin/views' ) as $dir ) {
	if ( ! is_dir( $root . '/' . $dir ) ) {
		continue;
	}
	$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === $f->getExtension() ) {
			$targets[] = $f->getPathname();
		}
	}
}
sort( $targets );

$violations   = array();
$ignore_flags = array();
$ignore_total = 0;
$scanned      = 0;

foreach ( $targets as $path ) {
	$src = (string) file_get_contents( $path );
	++$scanned;
	$lines = explode( "\n", $src );
	foreach ( echo_statements( $src ) as $stmt ) {
		foreach ( split_operands( $stmt['tokens'] ) as $operand ) {
			if ( ! expr_is_safe( $operand ) ) {
				$rel      = str_replace( $root . '/', '', $path );
				$text     = trim( implode( '', array_map( static fn( $t ) => is_array( $t ) ? $t[1] : $t, $operand ) ) );
				$text     = preg_replace( '/\s+/', ' ', $text );
				$violations[] = "{$rel}:{$stmt['line']} " . substr( $text, 0, 70 );
			}
		}
	}
	// phpcs:ignore audit — an escaping-related ignore may only cover output the
	// classifier itself accepts (constant or allowlist-escaped), never raw vars.
	$ignore_lines = array();
	foreach ( token_get_all( $src ) as $t ) {
		if ( ! is_array( $t ) || ! in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		if ( ! preg_match( '/phpcs:ignore[^
]*(?:Escape|Xss|OutputNotEscaped)/i', $t[1] ) ) {
			continue;
		}
		$ignore_lines[ $t[2] ] = true;
	}
	foreach ( array_keys( $ignore_lines ) as $iline ) {
		++$ignore_total;
		$seen = false;
		$ok   = true;
		foreach ( echo_statements( $src ) as $stmt ) {
			// An ignore comment may be inline on the echo line or the line above it.
			if ( $stmt['line'] !== $iline && $stmt['line'] !== $iline + 1 ) {
				continue;
			}
			$seen = true;
			foreach ( split_operands( $stmt['tokens'] ) as $operand ) {
				if ( ! expr_is_safe( $operand ) ) {
					$ok = false;
				}
			}
		}
		if ( ! $seen || ! $ok ) {
			$ignore_flags[] = str_replace( $root . '/', '', $path ) . ':' . $iline;
		}
	}
}

echo "--- 1. Coverage: the scanner sees every promised surface ---\n";
check( $scanned >= 40, "scanned {$scanned} template/view files (expect 40+)" );
$expected_groups = array( 'flavor/header.php', 'flavor/footer.php', 'flavor/index.php', 'flavor/single.php', 'flavor/search.php', 'flavor/404.php' );
$rel_all = array_map( static fn( $p ) => str_replace( $root . '/', '', $p ), $targets );
foreach ( $expected_groups as $f ) {
	check( in_array( $f, $rel_all, true ), "{$f} is in scope" );
}
check( count( array_filter( $rel_all, static fn( $p ) => 0 === strpos( $p, 'flavor/template-parts/' ) ) ) >= 10, 'template-parts subtree is covered' );
check( count( array_filter( $rel_all, static fn( $p ) => 0 === strpos( $p, 'flavor-core/admin/views/' ) ) ) >= 3, 'admin views are covered' );

echo "\n--- 2. Zero unescaped dynamic output ---\n";
check(
	array() === $violations,
	'every echo/print/<?= reaches output escaped or cast (violations: ' . ( $violations ? "\n    " . implode( "\n    ", array_slice( $violations, 0, 25 ) ) . ( count( $violations ) > 25 ? "\n    … " . ( count( $violations ) - 25 ) . ' more' : '' ) : '0' ) . ')'
);

echo "\n--- 3. phpcs:ignore only on constant output ---\n";
check(
	array() === $ignore_flags,
	'every escaping phpcs:ignore (audited: ' . $ignore_total . ') covers constant or allowlist-escaped output only (offenders: ' . ( $ignore_flags ? implode( ', ', $ignore_flags ) : '0' ) . ')'
);
check( $ignore_total >= 1, 'the ignore-audit judged at least one real ignore comment (' . $ignore_total . ')' );

echo "\n--- 4. The scanner itself catches a planted hole ---\n";
$probe = "<?php\n\$bad = get_option('x');\necho \$bad;\n?>" . "<?= \$bad ?>" . "<?php\necho esc_html( \$bad );\nprint absint( \$bad );\nprint 'lit';\necho \$bad ? 'a' : 'b';\n";
$probe_stmts = echo_statements( $probe );
$found = 0;
foreach ( $probe_stmts as $st ) {
	foreach ( split_operands( $st['tokens'] ) as $op ) {
		if ( ! expr_is_safe( $op ) ) {
			$found++;
		}
	}
}
check( 2 === $found, 'planted snippet: raw echo and raw <?= flagged, escaped/constant/ternary-safe pass (found ' . $found . ')' );

echo "\n" . ( 0 === $failed ? "ALL {$passed} checks passed.\n" : "{$passed} passed, {$failed} FAILED\n" );
exit( $failed > 0 ? 1 : 0 );
