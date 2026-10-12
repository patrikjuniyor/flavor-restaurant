<?php
/**
 * Nonce / capability coverage for every admin-post & admin_init handler (R4-1).
 *
 * A handler that mutates data must prove *who* may trigger it
 * (current_user_can) and *that the click was intentional*
 * (check_admin_referer / wp_verify_nonce). Handlers that delegate the check
 * to a shared gate (self::guard(), ::authorize()) are compliant — so the
 * scanner follows one level of same-file helper calls before declaring a
 * violation. Parsing uses PHP's own tokenizer, not regex-over-source, so
 * braces and quotes inside heredoc templates cannot fool the extractor.
 *
 *   php tests/theme/test-nonce-coverage.php
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

/**
 * Extract code-only bodies for every function/method in a PHP file.
 *
 * Comments are dropped, strings are kept, brace depth is token-accurate.
 *
 * @param string $src PHP source.
 * @return array<string,string> function name => body source (incl. braces).
 */
function function_bodies( string $src ): array {
	$tokens = token_get_all( $src );
	$bodies = array();
	$n      = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $tokens[ $i ];
		if ( ! is_array( $t ) || T_FUNCTION !== $t[0] ) {
			continue;
		}
		// Find the name: next T_STRING (closures have none, we skip them).
		$name = null;
		$j    = $i;
		for ( $j = $i + 1; $j < $n; $j++ ) {
			if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				continue;
			}
			if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
				$name = $tokens[ $j ][1];
			}
			break;
		}
		if ( null === $name ) {
			continue;
		}
		// Find the opening brace of the body.
		$k = $j;
		while ( $k < $n ) {
			$tk = $tokens[ $k ];
			if ( ! is_array( $tk ) ) {
				if ( '{' === $tk ) {
					break;
				}
				if ( ';' === $tk ) {
					$k = $n; // abstract/interface signature — no body.
					break;
				}
			} elseif ( T_STRING === $tk[0] && 'abstract' === strtolower( $tk[1] ) ) {
				$k = $n;
				break;
			}
			++$k;
		}
		if ( $k >= $n ) {
			continue;
		}
		$depth = 0;
		$body  = '';
		for ( ; $k < $n; $k++ ) {
			$tk   = $tokens[ $k ];
			$text = is_array( $tk ) ? ( T_COMMENT === $tk[0] || T_DOC_COMMENT === $tk[0] ? '' : $tk[1] ) : $tk;
			$body .= $text;
			if ( '{' === $text ) {
				$depth++;
			} elseif ( '}' === $text ) {
				$depth--;
				if ( 0 === $depth ) {
					break;
				}
			}
		}
		if ( ! isset( $bodies[ $name ] ) ) {
			$bodies[ $name ] = $body;
		}
	}
	return $bodies;
}

$MUTATION_RE     = '/update_option\(|delete_option\(|add_option\(|set_theme_mod\(|remove_theme_mod\(|wp_insert_post|wp_update_post|wp_delete_post|->save\(|->insert\(|->update\(|->delete\(|\$wpdb\s*->\s*(?:insert|update|delete|query)|file_put_contents|move_uploaded_file|wp_update_user|wp_insert_user|wp_set_password/i';
$REQUEST_RE      = '/\$_(?:POST|GET|REQUEST|FILES)\b|filter_input\s*\(/';
$NONCE_RE        = '/check_admin_referer\s*\(|wp_verify_nonce\s*\(/';
$CAP_RE          = '/current_user_can\s*\(/';
$HOOK_RE         = '/add_action\(\s*\'(admin_post_[a-z0-9_\-]+|admin_init)\'\s*,\s*array\(\s*[^,]+,\s*\'([A-Za-z0-9_]+)\'\s*\)/i';
$HOOK_RE_CONCAT  = '/add_action\(\s*\'admin_post_[\'"]?\s*\.\s*(?:self|static|__CLASS__)::([A-Za-z0-9_]+)\s*,\s*array\(\s*[^,]+,\s*\'([A-Za-z0-9_]+)\'\s*\)/i';

/**
 * Does a handler body satisfy a requirement, directly or via a same-file
 * gate helper it calls?
 *
 * @param string $handler_body Handler body from function_bodies().
 * @param array  $bodies       All bodies of the file (name => body).
 * @param string $re           Requirement regex.
 * @return bool
 */
function satisfied( string $handler_body, array $bodies, string $re ): bool {
	if ( preg_match( $re, $handler_body ) ) {
		return true;
	}
	if ( preg_match_all( '/::([a-z_][a-z0-9_]*)\s*\(|\$this->([a-z_][a-z0-9_]*)\s*\(/i', $handler_body, $calls ) ) {
		foreach ( $calls[1] as $callee ) {
			if ( isset( $bodies[ $callee ] ) && preg_match( $re, $bodies[ $callee ] ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Literal nonce-action names verified in a file (directly or gate-called).
 *
 * @param string $src PHP source.
 * @return array<int,string>
 */
function verified_actions( string $src ): array {
	$actions = array();
	if ( preg_match_all( "/check_admin_referer\(\s*'([^'.()]+)'\s*[,)]/", $src, $mm ) ) {
		$actions = array_merge( $actions, $mm[1] );
	}
	if ( preg_match_all( "/::(?:guard|authorize)\(\s*'([^'.()]+)'\s*\)/", $src, $mm ) ) {
		$actions = array_merge( $actions, $mm[1] );
	}
	return array_values( array_unique( $actions ) );
}

$files       = array();
$issue_files = array();
foreach ( array( 'flavor/inc', 'flavor-core/includes', 'dev-tools' ) as $dir ) {
	$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === $f->getExtension() ) {
			$files[]       = $f->getPathname();
			$issue_files[] = $f->getPathname();
		}
	}
}
// Nonces are often issued in the page templates, not the admin class.
foreach ( array( 'flavor-core/admin', 'flavor/views' ) as $dir ) {
	if ( ! is_dir( $root . '/' . $dir ) ) {
		continue;
	}
	$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && 'php' === $f->getExtension() ) {
			$issue_files[] = $f->getPathname();
		}
	}
}
sort( $files );

$all_src             = '';
$audit_pairs         = array();
$admin_post_count    = 0;
$files_with_handlers = array();
$viol_nonce          = array();
$viol_cap            = array();
$actions_seen        = array();

foreach ( $files as $path ) {
	$src = (string) file_get_contents( $path );
	$all_src .= $src;
	$bodies = function_bodies( $src );
	$rel    = str_replace( $root . '/', '', $path );

	$hooks = array();
	if ( preg_match_all( $HOOK_RE, $src, $ms, PREG_SET_ORDER ) ) {
		foreach ( $ms as $m ) {
			$hooks[] = array( $m[1], $m[2] );
		}
	}
	// Concatenated registrations: add_action( 'admin_post_' . self::ACTION, ... ).
	if ( preg_match_all( $HOOK_RE_CONCAT, $src, $ms, PREG_SET_ORDER ) ) {
		foreach ( $ms as $m ) {
			$hooks[] = array( 'admin_post_' . $m[1], $m[2] );
		}
	}
	foreach ( $hooks as $hook ) {
		$method = $hook[1];
		if ( ! isset( $bodies[ $method ] ) ) {
			continue; // inherited/external callback — out of scope.
		}
		$files_with_handlers[ basename( $path ) ] = true;
		if ( 0 === strpos( $hook[0], 'admin_post_' ) ) {
			$admin_post_count++;
		}
		$audit_pairs[] = array( $rel, $method, $bodies[ $method ], $bodies, $hook[0] );
	}

	foreach ( verified_actions( $src ) as $a ) {
		$actions_seen[ $a ] = true;
	}
}

foreach ( $audit_pairs as $rec ) {
	list( $rel, $method, $body, $bodies, $hook ) = $rec;
	$mutates   = (bool) preg_match( $MUTATION_RE, $body );
	$reads_req = (bool) preg_match( $REQUEST_RE, $body );
	$is_post   = 0 === strpos( $hook, 'admin_post_' );
	// admin_post fires from a request URL/action name by definition, so any
	// mutation there must be nonce-gated. admin_init needs an explicit read.
	if ( ! $mutates || ( ! $is_post && ! $reads_req ) ) {
		continue;
	}
	if ( ! satisfied( $body, $bodies, $NONCE_RE ) ) {
		$viol_nonce[] = "{$rel}::{$method}";
	}
	if ( ! satisfied( $body, $bodies, $CAP_RE ) ) {
		$viol_cap[] = "{$rel}::{$method}";
	}
}

echo "--- 1. The scanner sees the handlers the mission lists ---\n";

check( $admin_post_count >= 19, "at least 19 admin-post handlers discovered (found {$admin_post_count})" );

$expected = array(
	'class-demo-importer.php',
	'class-update-channel.php',
	'class-launch-center.php',
	'class-onboarding.php',
	'class-transfer.php',
	'PhoneOrderAdmin.php',
	'TableAdmin.php',
	'ZoneAdmin.php',
	'LoyaltyAdmin.php',
	'ScheduleAdmin.php',
	'AvailabilityAdmin.php',
	'ReservationAdmin.php',
	'ReportAdmin.php',
	'MobileAppAdmin.php',
	'flavor-github-updater.php',
);
foreach ( $expected as $file ) {
	check( isset( $files_with_handlers[ $file ] ), "{$file} registers at least one audited handler" );
}

echo "\n--- 2. Every mutating handler is nonce-gated (directly or via a gate) ---\n";

check(
	array() === $viol_nonce,
	'zero mutating handlers missing a nonce check (violations: ' . ( $viol_nonce ? implode( ', ', $viol_nonce ) : '0' ) . ')'
);
check( count( $audit_pairs ) >= 20, 'the audit covered ' . count( $audit_pairs ) . ' hook registrations' );

echo "\n--- 3. Every mutating handler is capability-gated ---\n";

check(
	array() === $viol_cap,
	'zero mutating handlers missing a current_user_can check (violations: ' . ( $viol_cap ? implode( ', ', $viol_cap ) : '0' ) . ')'
);

echo "\n--- 4. Nonce actions verified are also issued by a form/link ---\n";

$issue_src = '';
foreach ( array_unique( $issue_files ) as $path ) {
	$issue_src .= (string) file_get_contents( $path );
}
$orphan = array();
foreach ( array_keys( $actions_seen ) as $a ) {
	$q = preg_quote( $a, '/' );
	if ( ! preg_match( "/wp_nonce_field\(\s*'{$q}'|wp_create_nonce\(\s*'{$q}'|wp_nonce_url\([^,]+,\s*'{$q}'/", $issue_src ) ) {
		$orphan[] = $a;
	}
}
check( array() === $orphan, 'every verified nonce action has a matching issuer (orphans: ' . ( $orphan ? implode( ', ', $orphan ) : '0' ) . ')' );
check( count( $actions_seen ) >= 12, 'the action inventory is non-trivial (found ' . count( $actions_seen ) . ' literal actions)' );

echo "\n--- 5. The detector really catches a planted hole ---\n";

$broken  = "<?php\nclass Bad_Admin {\n\tpublic static function save(): void {\n\t\t\$v = sanitize_text_field( \$_POST['v'] );\n\t\tupdate_option( 'bad_opt', \$v );\n\t}\n}\n";
$baddies = function_bodies( $broken );
check(
	isset( $baddies['save'] )
	&& preg_match( $MUTATION_RE, $baddies['save'] )
	&& ! preg_match( $NONCE_RE, $baddies['save'] ),
	'planted handler without a nonce is identified as a violation'
);

$gated = "<?php\nclass Good_Admin {\n\tpublic static function save(): void {\n\t\tself::guard( 'good' );\n\t\tupdate_option( 'good_opt', 1 );\n\t}\n\tpublic static function guard( \$a ): void {\n\t\tif ( ! current_user_can( 'manage_options' ) ) { wp_die(); }\n\t\tcheck_admin_referer( \$a );\n\t}\n}\n";
$goods = function_bodies( $gated );
check(
	isset( $goods['save'] ) && satisfied( $goods['save'], $goods, $NONCE_RE ) && satisfied( $goods['save'], $goods, $CAP_RE ),
	'a handler that delegates to a gate helper is accepted'
);

echo "\n--- 6. Today's fix stays fixed (updater capability regression pin) ---\n";

$updater_src = (string) file_get_contents( $root . '/dev-tools/flavor-github-updater.php' );
$updater     = function_bodies( $updater_src );
$handler     = $updater['handle_update_request'] ?? null;
check(
	null !== $handler && preg_match( $CAP_RE, $handler ) === 1,
	'github updater handler checks current_user_can before mutating (R4-1)'
);
check(
	null !== $handler && str_contains( (string) $handler, 'manage_options' ),
	'the updater capability is manage_options, not a viewer cap'
);

echo "\n" . ( 0 === $failed ? "ALL {$passed} checks passed.\n" : "{$passed} passed, {$failed} FAILED\n" );
exit( $failed > 0 ? 1 : 0 );
