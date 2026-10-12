<?php
/**
 * Input-sanitization guard for the plugin's REST and admin surfaces (R4-4 / پرامپت ۳۶).
 *
 * Every read of $_GET/$_POST/$_REQUEST, $request->get_param() or a decoded
 * JSON body inside flavor-core/includes/** and flavor-core/admin/views/**
 * must be neutralised either at the read (sanitize_*, absint/(int) casts,
 * esc_*, wp_kses, an internal allowlisted sanitizer) or immediately inside
 * the consuming call. Structured arrays that cannot take a scalar sanitizer
 * are only acceptable when the consumer re-sanitizes every field — the three
 * audited `phpcs:ignore ... ValidatedSanitizedInput` sites below were checked
 * by hand and that is exactly what they do (ProductModifiers::sanitize_selection /
 * per-row sanitize_text_field, PhoneOrderAdmin per-element (int)+sanitize_title).
 *
 *   php tests/theme/test-input-sanitization.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

$root   = dirname( __DIR__, 2 );
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

// Sanitizer / cast vocabulary accepted at (or directly around) the read site.
$SANITIZERS = array(
	'sanitize_text_field', 'sanitize_textarea_field', 'sanitize_key', 'sanitize_title',
	'sanitize_title_with_trailing_dash', 'sanitize_user', 'sanitize_email', 'sanitize_file_name',
	'sanitize_hex_color', 'sanitize_meta', 'sanitize_option', 'sanitize_domain', 'sanitize_domain_email',
	'absint', 'intval', 'floatval', 'settype', 'esc_url_raw', 'esc_sql', 'esc_like', 'esc_html', 'esc_attr',
	'wp_kses', 'wp_kses_post', 'wp_kses_data', 'wp_validate_boolean', 'rest_sanitize_boolean',
	'normalize_mobile', 'wc_format_coupon_code', 'clean_instructions', 'sanitize_selection',
	'wp_check_invalid_utf8',
);

/**
 * Recursively collect PHP files under the audit scope.
 *
 * @param string $dir Directory relative to repo root.
 * @return string[]
 */
function scope_files( string $dir ): array {
	global $root;
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$it  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) {
			$out[] = str_replace( $root . '/', '', $f->getPathname() );
		}
	}
	sort( $out );
	return $out;
}

$files = array_values(
	array_unique( array_merge(
		scope_files( $root . '/flavor-core/includes' ),
		scope_files( $root . '/flavor-core/admin/views' )
	) )
);

/**
 * Strip comments/whitespace bookkeeping: return [codeLines(1-based => text), commentLines(1-based => text)].
 *
 * @param string $src File source.
 * @return array{0:array<int,string>,1:array<int,string>}
 */
function split_lines( string $src ): array {
	$code = array();
	$comm = array();
	$i    = 1;
	foreach ( preg_split( '/\r\n|\r|\n/', $src ) as $line ) {
		$code[ $i ] = preg_replace( '#//.*$#', '', $line );
		if ( preg_match( '#//.*$|/\*.*?\*/|\*/#', $line ) ) {
			$comm[ $i ] = $line;
		}
		++$i;
	}
	return array( $code, $comm );
}

/**
 * Does a statement span (single source line, as the codebase formats one read
 * per line) neutralise the read?
 *
 * @param string $line      Code with comments stripped.
 * @param array  $sanitizers Vocabulary.
 * @return bool
 */
function line_sanitized( string $line, array $sanitizers ): bool {
	$lower = strtolower( $line );
	foreach ( $sanitizers as $s ) {
		if ( false !== strpos( $lower, $s ) ) {
			return true;
		}
	}
	if ( preg_match( '/\((?:int|float|bool)\)/', $lower ) && false === strpos( $line, '.' ) ) {
		return true;
	}
	// Number-only usage contexts.
	if ( preg_match( '/(?:<=?|>=?|<=>|===|!==)\s*[\x27"]/', $line ) && ! preg_match( '/\.\s*\$|\$\w+\s*\.|\bother\b/', $line ) ) {
		return true;
	}
	return false;
}

/**
 * Is every superglobal occurrence on this line merely an isset/empty existence test?
 *
 * @param string $line Line without comments.
 * @return bool
 */
function guarded_only( string $line ): bool {
	if ( preg_match_all( '/(?:\$_(?:GET|POST|REQUEST)|\$(?:body|payload))\s*\[/', $line, $m, PREG_OFFSET_CAPTURE ) < 1 ) {
		return false;
	}
	$guard = '/(?:isset|empty|array_key_exists|is_string|is_array|is_int|is_float|is_numeric|is_email|in_array)\s*\(\s*!?\s*$/';
	foreach ( $m[0] as $occ ) {
		$before = substr( $line, 0, $occ[1] );
		if ( ! preg_match( $guard, $before ) && ! preg_match( '/null\s*!==\s*$/', $before ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Rest of the enclosing function (for the assigned-then-sanitised pattern).
 *
 * @param array<int,string> $code Line map.
 * @param int               $ln   Line of the read.
 * @return string Text from $ln until the next function signature.
 */
function rest_of_function( array $code, int $ln ): string {
	$out = array();
	for ( $i = $ln + 1, $n = count( $code ) + 2; $i < $n; ++$i ) {
		if ( ! isset( $code[ $i ] ) ) {
			break;
		}
		if ( $i > $ln && preg_match( '/^\t(public|private|protected)\s.*function /', $code[ $i ] ) ) {
			break;
		}
		$out[] = $code[ $i ];
	}
	return implode( "\n", $out );
}

/**
 * `$v = <raw read>;` is safe when $v is sanitized later in the same function.
 *
 * @param string $line  Assigning line.
 * @param string $rest  Rest of function text.
 * @param array  $sanitizers Vocabulary.
 * @return bool
 */
function assigned_then_sanitized( string $line, string $rest, array $sanitizers ): bool {
	if ( ! preg_match( '/^\s*\$(\w+)\s*=/', $line, $m ) ) {
		return false;
	}
	$var       = $m[1];
	$sanitized = false;
	foreach ( explode( "\n", $rest ) as $l ) {
		if ( ! preg_match( '/\$' . $var . '\b/', $l ) ) {
			continue;
		}
		// Guarded conditions (bare truthiness or isset/empty/is_string wraps) are not sinks.
		if ( preg_match( '/^\s*(\} )?(elseif|if|while)\s*\(\s*!?\s*\$' . $var . '\s*\)\s*\{\s*$/', $l ) ) {
			continue;
		}
		$stripped = preg_replace( '/(?:isset|empty|is_string|is_array|is_int|is_numeric)\s*\(\s*!?\s*\$' . $var . '\s*\)/', '', $l );
		if ( $stripped !== $l && ! preg_match( '/\$' . $var . '\b/', $stripped ) ) {
			continue;
		}
		if ( line_sanitized( $l, $sanitizers ) || preg_match( '/\$' . $var . '\s*=/', $l ) ) {
			$sanitized = true;
			continue;
		}
		if ( $sanitized ) {
			continue;
		}
		return false;
	}
	return $sanitized;
}

/**
 * Is the superglobal occurrence on this line a write (assignment target)?
 *
 * @param string $line Line without comments.
 * @return bool
 */
function is_write( string $line ): bool {
	return (bool) preg_match( '/\$_(GET|POST|REQUEST)\s*\[[^\]]*\]\s*(=[^=]|\[)/', $line )
		|| (bool) preg_match( '/unset\s*\(\s*\$_(GET|POST|REQUEST)/', $line );
}

/**
 * Function name enclosing a 1-based line (brace-counting scan, comment-stripped).
 *
 * @param array<int,string> $code Line map.
 * @param int               $ln   Target line.
 * @return string
 */
function enclosing_function( array $code, int $ln ): string {
	$depth    = 0;
	$current  = '';
	$pending  = '';
	foreach ( $code as $i => $line ) {
		if ( preg_match( '/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)/', $line, $m ) ) {
			$pending = $m[1];
		} elseif ( preg_match( '/\bfunction\s*\(/', $line ) && '' === $pending ) {
			$pending = '{closure}';
		}
		$depth += substr_count( $line, '{' ) - substr_count( $line, '}' );
		if ( '' !== $pending && false !== strpos( $line, '{' ) ) {
			$current = $pending;
			$pending = '';
		}
		if ( $i >= $ln ) {
			return $current;
		}
	}
	return $current;
}

// get_json_params() bodies that hand the WHOLE array to a verified per-field
// sanitizer — every unsanitized $body[...] read inside these functions is OK.
$BODY_CONSUMERS = array(
	// Each listed function was hand-verified: the sink sanitizes every field
	// (CartSession/CheckoutService/ZoneChecker/MobileConfigManager/DiscountManager;
	// AuthController::update_me sanitizes per-field inside the function itself; RestController::set_context pre-limits order_mode via in_array whitelist; Webhook events pass through WebhookManager::validate_events allowlist filter).
	'flavor-core/includes/API/RestStore.php'                 => array( 'add_cart', 'checkout', 'zone_check' ),
	'flavor-core/includes/API/RestExperience.php'            => array( 'coupon' ),
	'flavor-core/includes/API/MobileProvisionController.php' => array( 'update_config' ),
	'flavor-core/includes/API/CartController.php'            => array( 'add_item', 'update_item' ),
	'flavor-core/includes/API/RestController.php'            => array( 'set_context' ),
	'flavor-core/includes/API/WebhookController.php'         => array( 'create_webhook', 'update_webhook' ),
	'flavor-core/includes/API/AuthController.php'            => array( 'update_me' ),
	'flavor-core/includes/API/OrderController.php'           => array( 'create_order' ),
);

// phpcs:ignore ValidatedSanitizedInput sites audited by hand (see header). Key: file => lines.
$AUDITED_IGNORES = array(
	'flavor-core/includes/WooCommerce/ProductModifiers.php' => array( 199, 257 ),
	'flavor-core/includes/Order/PhoneOrderAdmin.php'        => array( 212, 213 ),
);

/* ------------------------------------------------------------------ */
echo "--- 1. scope coverage ---\n";

$needles = array(
	'flavor-core/includes/API/SettingsController.php',
	'flavor-core/includes/API/RestStore.php',
	'flavor-core/includes/API/RestExperience.php',
	'flavor-core/includes/API/MobileProvisionController.php',
	'flavor-core/includes/Reporting/ReportAdmin.php',
	'flavor-core/includes/Support/CacheHints.php',
	'flavor-core/admin/views/executive-dashboard.php',
	'flavor-core/admin/views/kitchen-dashboard.php',
);
$missing = array_values( array_diff( $needles, $files ) );
check( count( $files ) >= 60, 'audit scope is the whole plugin surface (files: ' . count( $files ) . ')' );
check( array() === $missing, 'all files named by the prompt are in scope (missing: ' . implode( ',', $missing ) . ')' );

/* ------------------------------------------------------------------ */
echo "\n--- 2. superglobal reads sanitized or guarded ---\n";

$violations = array();
$reads_seen = 0;
$ignore_exempt = 0;
$exempt_seen = array();

foreach ( $files as $rel ) {
	$src  = file_get_contents( $root . '/' . $rel );
	list( $code, $comm ) = split_lines( $src );
	foreach ( $code as $ln => $line ) {
		if ( ! preg_match( '/\$_(GET|POST|REQUEST)\s*\[|\->get_param\s*\(/', $line ) ) {
			continue;
		}
		if ( is_write( $line ) ) {
			continue; // assignment/unset into a superglobal: audited separately.
		}
		++$reads_seen;
		$safe = guarded_only( $line ) || line_sanitized( $line, $SANITIZERS )
			|| assigned_then_sanitized( $line, rest_of_function( $code, $ln ), $SANITIZERS );
		if ( $safe ) {
			continue;
		}
		// Functions hand-verified to forward only to sanitizing sinks (see $BODY_CONSUMERS).
		if ( in_array( enclosing_function( $code, $ln ), $BODY_CONSUMERS[ $rel ] ?? array(), true ) ) {
			continue;
		}
		if ( in_array( $ln, $AUDITED_IGNORES[ $rel ] ?? array(), true ) && preg_match( '/phpcs:ignore/i', $comm[ $ln ] ?? '' ) ) {
			++$ignore_exempt;
			$exempt_seen[ $rel ][] = $ln;
			continue;
		}
		$snippet = trim( substr( $line, 0, 110 ) );
		$violations[] = "{$rel}:{$ln} {$snippet}";
	}
}
check( $reads_seen >= 150, 'the scanner saw the real volume of input reads (reads: ' . $reads_seen . ')' );
check( array() === $violations, 'every superglobal/get_param read sanitized, cast or guarded (violations: ' . count( $violations ) . ( $violations ? ":\n    " . implode( "\n    ", array_slice( $violations, 0, 12 ) ) : '' ) . ')' );

/* ------------------------------------------------------------------ */
echo "\n--- 3. decoded JSON bodies: sanitized per field or handed to an allowlisted sanitizer ---\n";

$body_violations = array();
$body_reads      = 0;
foreach ( $files as $rel ) {
	$src  = file_get_contents( $root . '/' . $rel );
	if ( false === strpos( $src, 'get_json_params' ) ) {
		continue;
	}
	list( $code, $comm ) = split_lines( $src );
	foreach ( $code as $ln => $line ) {
		if ( ! preg_match( '/\$(body|payload)\s*\[/', $line ) ) {
			continue;
		}
		++$body_reads;
		if ( preg_match( '/\$(?:body|payload)\s*\[[^\]]*\]\s*=/', $line ) ) {
			continue; // write
		}
		if ( guarded_only( $line ) || line_sanitized( $line, $SANITIZERS )
			|| assigned_then_sanitized( $line, rest_of_function( $code, $ln ), $SANITIZERS ) ) {
			continue;
		}
		$fn   = enclosing_function( $code, $ln );
		$allo = $BODY_CONSUMERS[ $rel ] ?? array();
		if ( in_array( $fn, $allo, true ) ) {
			continue;
		}
		$body_violations[] = sprintf( '%s:%d [%s] %s', $rel, $ln, $fn, trim( substr( $line, 0, 100 ) ) );
	}
}
check( $body_reads >= 8, 'JSON body destructuring really audited (reads: ' . $body_reads . ')' );
check( array() === $body_violations, 'no raw $body[...] reaches storage/output outside allowlisted sanitizer consumers (violations: ' . count( $body_violations ) . ( $body_violations ? ":\n    " . implode( "\n    ", array_slice( $body_violations, 0, 8 ) ) : '' ) . ')' );

/* ------------------------------------------------------------------ */
echo "\n--- 4. every ValidatedSanitizedInput phpcs:ignore is on the audited list ---\n";

$unaudited = array();
$audited_total = 0;
foreach ( $files as $rel ) {
	$src  = file_get_contents( $root . '/' . $rel );
	list( $code, $comm ) = split_lines( $src );
	foreach ( $comm as $ln => $comment ) {
		if ( ! preg_match( '/phpcs:ignore.*ValidatedSanitizedInput/i', $comment ) ) {
			continue;
		}
		++$audited_total;
		$in_list = isset( $AUDITED_IGNORES[ $rel ] ) && in_array( $ln, $AUDITED_IGNORES[ $rel ], true );
		$now_clean = line_sanitized( $code[ $ln ], $SANITIZERS ) || guarded_only( $code[ $ln ] );
		if ( ! $in_list && ! $now_clean ) {
			$unaudited[] = "{$rel}:{$ln}";
		}
	}
}
check( $audited_total >= 4, 'the ignore-audit walked every ValidatedSanitizedInput comment (found: ' . $audited_total . ')' );
check( array() === $unaudited, 'each escaping-suppression comment is hand-audited or no longer needed (unaudited: ' . implode( ',', $unaudited ) . ')' );

/* ------------------------------------------------------------------ */
echo "\n--- 5. the scanner itself catches a planted hole ---\n";

// One raw superglobal read + one raw $body read must be flagged; their
// sanitized twins and a guarded isset must stay clean.
$probe = "<?php\n"
	. "\$x = \$_GET['page_id'];\n"
	. "\$y = absint( \$_GET['page_id'] );\n"
	. "if ( isset( \$_POST['nonce_field'] ) ) { \$ok = 1; }\n"
	. "\$z = \$request->get_param('evil');\n"
	. "\$w = sanitize_text_field( \$request->get_param('ok') );\n";
$probe_lines = array();
foreach ( explode( "\n", $probe ) as $i => $l ) {
	$probe_lines[ $i + 1 ] = $l;
}
$found = 0;
foreach ( $probe_lines as $l ) {
	if ( preg_match( '/\$_(GET|POST|REQUEST)\s*\[|\->get_param\s*\(/', $l ) && ! is_write( $l ) && ! guarded_only( $l ) && ! line_sanitized( $l, $SANITIZERS ) ) {
		++$found;
	}
}
check( 2 === $found, 'planted snippet: raw \$_GET and raw get_param flagged, sanitized+isset-exempt (found ' . $found . ')' );

if ( $failed > 0 ) {
	echo "\n{$passed} passed, {$failed} FAILED\n";
	exit( 1 );
}
echo "\nALL " . ( $passed ) . " checks passed.\n";
exit( 0 );
