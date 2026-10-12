<?php
/**
 * P46 gate — the REST response contract is machine-maintained.
 *
 * Prompt 46 unified error handling: every flavor route answers through
 * BaseApiController's envelope helpers (respond_success / respond_error /
 * relay_error), statuses follow the fixed semantic table (400 input, 401
 * unauthenticated, 403 forbidden, 404 not found, 409 idempotency/state
 * conflict, 429 rate), and the two response shapes WordPress itself
 * produces outside a callback's reach (permission_callback WP_Errors and
 * core route errors) are normalised on the wire by the ResponseEnvelope
 * middleware. This gate keeps it that way — parsed with PHP's own
 * tokenizer, so strings and comments cannot mask a violation:
 *
 *   1. every API class that registers routes extends BaseApiController
 *      (the helpers are always in scope)
 *   2. route CALLBACK bodies never answer in a native/raw shape:
 *      a) no new \WP_Error, rest_ensure_response, wp_send_json, echo,
 *         exit/die or wp_die inside a callback body
 *      b) every return in a callback body is `return;`, `return $this->…`,
 *         or `return $var;` where $var was assigned from a respond_ or
 *         relay_ helper call in the same body (the ObservabilityController
 *         pattern: build, add a header, return) — a raw `return $wp_error;`
 *         relay or a bare `return $array;` both fail here
 *   3. permission callbacks stay inside the WP protocol (true|WP_Error)
 *      but every literal WP_Error there carries a flavor_* code and an
 *      explicit 'status' of 400/401/403
 *   4. respond_error call sites honour the semantic status table:
 *      *not_found*→404, *unauthorized*→401, *forbidden*→403, *rate*→429,
 *      and *idempotency*, *conflict*, *transition* → 409; other literal codes must
 *      use 400/422/500/503 (documented allowlist: invalid_cart_item→404,
 *      the cart line-item resource is genuinely absent). Dynamic-code
 *      relays (get_error_code()) carry the source status and are counted,
 *      not mapped.
 *   5. the wire normaliser exists and is wired: ResponseEnvelope hooks
 *      rest_post_dispatch with the 3-arg signature, guards on the /flavor/
 *      route prefix, is idempotent (skips payloads that already have a
 *      success key), mutates via set_data (status+headers survive), and
 *      Plugin.php instantiates it; BaseApiController::relay_error exists
 *      and the legacy controllers actually use it
 *   6. wp_send_json_* stays confined to the admin-ajax shim (RestSearch::
 *      ajax, itself wired via wp_ajax_* add_action) — never in a route
 *   7. the prompt's status table stays pinned at its sources: RateLimit
 *      guard emits flavor_rate/429, CheckoutService emits
 *      flavor_idempotency_conflict/409, BaseApiController emits
 *      flavor_unauthorized/401 and flavor_forbidden/403
 *
 * Run: php tests/theme/test-rest-response-contract.php
 */

$root = dirname( __DIR__, 2 );
$pass = 0;
$fail = 0;
function check( $cond, $label ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  [PASS] $label\n";
	} else {
		$fail++;
		echo "  [FAIL] $label\n";
	}
}

/* ------------------------------------------------------------------ */
/* Tokenizer helpers.                                                  */
/* ------------------------------------------------------------------ */

/**
 * Read a token's text regardless of the array/string duality.
 *
 * @param array|string $t Token.
 * @return string
 */
function tk_text( $t ): string {
	return is_array( $t ) ? (string) $t[1] : (string) $t;
}

/**
 * Map every named function/method in a token list to its body span
 * [open-brace index, close-brace index]. Closures (no T_STRING name) are
 * skipped; brace counting is exact because the tokenizer folds strings
 * and comments into single tokens.
 *
 * @param array $tokens token_get_all() output.
 * @return array<string, array{0:int,1:int}>
 */
function method_spans( array $tokens ): array {
	$spans = array();
	$n     = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $tokens[ $i ];
		if ( ! is_array( $t ) || T_FUNCTION !== $t[0] ) {
			continue;
		}
		$name = null;
		for ( $j = $i + 1; $j < $n; $j++ ) {
			$tj = $tokens[ $j ];
			if ( is_array( $tj ) && T_WHITESPACE === $tj[0] ) {
				continue;
			}
			if ( '&' === $tj ) { // by-reference return
				continue;
			}
			if ( is_array( $tj ) && T_STRING === $tj[0] ) {
				$name = $tj[1];
			}
			break;
		}
		if ( null === $name ) {
			continue; // closure
		}
		$paren = 0;
		$start = null;
		$depth = 0;
		for ( $j = $i + 1; $j < $n; $j++ ) {
			$tj = $tokens[ $j ];
			if ( is_array( $tj ) && ( T_CURLY_OPEN === $tj[0] || T_DOLLAR_OPEN_CURLY_BRACES === $tj[0] ) ) {
				if ( null !== $start ) {
					$depth++;
				}
				continue;
			}
			if ( is_string( $tj ) ) {
				if ( '(' === $tj ) {
					$paren++;
					continue;
				}
				if ( ')' === $tj ) {
					$paren--;
					continue;
				}
				if ( '{' === $tj ) {
					if ( null === $start && 0 === $paren ) {
						$start = $j;
						$depth = 1;
					} elseif ( null !== $start ) {
						$depth++;
					}
					continue;
				}
				if ( '}' === $tj && null !== $start ) {
					$depth--;
					if ( 0 === $depth ) {
						$spans[ $name ] = array( $start, $j );
						break;
					}
				}
			}
		}
	}
	return $spans;
}

/**
 * Split the argument list of a call whose open paren is at index $open.
 *
 * @param array $tokens Token list.
 * @param int   $open   Index of the '(' token.
 * @return array<int, array<int, array|string>> one token list per argument.
 */
function call_args( array $tokens, int $open ): array {
	$args  = array();
	$cur   = array();
	$depth = 0;
	$n     = count( $tokens );
	for ( $j = $open + 1; $j < $n; $j++ ) {
		$tj = $tokens[ $j ];
		$txt = tk_text( $tj );
		if ( is_string( $tj ) ) {
			if ( '(' === $tj || '[' === $tj ) {
				$depth++;
			} elseif ( ')' === $tj || ']' === $tj ) {
				if ( 0 === $depth && ')' === $tj ) {
					$args[] = $cur;
					return $args;
				}
				$depth--;
			} elseif ( ',' === $tj && 0 === $depth ) {
				$args[] = $cur;
				$cur    = array();
				continue;
			}
		} elseif ( is_array( $tj ) && ( T_CURLY_OPEN === $tj[0] || T_DOLLAR_OPEN_CURLY_BRACES === $tj[0] ) ) {
			$depth++;
		} elseif ( is_string( $tj ) && '}' === $tj ) {
			$depth--;
		}
		$cur[] = $tj;
	}
	$args[] = $cur;
	return $args;
}

/**
 * First significant (non-whitespace, non-comment) token text from a list.
 *
 * @param array $list Tokens.
 * @return string
 */
function first_sig( array $list ): string {
	foreach ( $list as $t ) {
		if ( is_array( $t ) && in_array( $t[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			continue;
		}
		return tk_text( $t );
	}
	return '';
}

/**
 * The single-quoted string value of an argument, or null when the argument
 * is not one literal string.
 *
 * @param array $list Argument tokens.
 * @return string|null
 */
function arg_literal_string( array $list ): ?string {
	$sig = array();
	foreach ( $list as $t ) {
		if ( is_array( $t ) && T_WHITESPACE === $t[0] ) {
			continue;
		}
		$sig[] = $t;
	}
	if ( 1 === count( $sig ) && is_array( $sig[0] ) && T_CONSTANT_ENCAPSED_STRING === $sig[0][0] ) {
		return substr( $sig[0][1], 1, -1 );
	}
	return null;
}

/**
 * The literal int value of an argument, or null when not a bare int.
 *
 * @param array $list Argument tokens.
 * @return int|null
 */
function arg_literal_int( array $list ): ?int {
	$sig = array();
	foreach ( $list as $t ) {
		if ( is_array( $t ) && T_WHITESPACE === $t[0] ) {
			continue;
		}
		$sig[] = $t;
	}
	if ( 1 === count( $sig ) && is_array( $sig[0] ) && T_LNUMBER === $sig[0][0] ) {
		return (int) $sig[0][1];
	}
	return null;
}

/* ------------------------------------------------------------------ */
/* Corpus.                                                             */
/* ------------------------------------------------------------------ */

$api_dir  = $root . '/flavor-core/includes/API';
$api_files = array();
foreach ( glob( $api_dir . '/*.php' ) as $f ) {
	$api_files[ basename( $f ) ] = $f;
}
ksort( $api_files );
// A silent-empty glob would vacuously pass every clause below (the P45
// lesson): assert the real corpus size first.
check( count( $api_files ) >= 16, 'API corpus is loaded (' . count( $api_files ) . ' files)' );

$parsed = array(); // file => [src, tokens, spans, callbacks, permissions]
foreach ( $api_files as $base => $path ) {
	$src    = file_get_contents( $path );
	$tokens = token_get_all( $src );
	$spans  = method_spans( $tokens );

	$callbacks = array();
	$perms     = array();
	if ( preg_match_all( "/'callback'\s*=>\s*array\(\s*\\\$this\s*,\s*'([A-Za-z_]\w*)'\s*\)/", $src, $m ) ) {
		$callbacks = $m[1];
	}
	if ( preg_match_all( "/'permission_callback'\s*=>\s*array\(\s*\\\$this\s*,\s*'([A-Za-z_]\w*)'\s*\)/", $src, $m2 ) ) {
		$perms = $m2[1];
	}

	$parsed[ $base ] = array(
		'src'         => $src,
		'tokens'      => $tokens,
		'spans'       => $spans,
		'callbacks'   => array_values( array_unique( $callbacks ) ),
		'permissions' => array_values( array_unique( $perms ) ),
		'registers'   => false !== strpos( $src, 'register_rest_route' ),
	);
}

$total_callbacks = 0;
$total_perms     = 0;
foreach ( $parsed as $p ) {
	$total_callbacks += count( $p['callbacks'] );
	$total_perms     += count( $p['permissions'] );
}
check( $total_callbacks >= 40, "route callbacks enumerated from the registration arrays ({$total_callbacks})" );
check( $total_perms >= 12, "permission callbacks enumerated ({$total_perms})" );

/* ------------------------------------------------------------------ */
echo "--- 1. every route-registering controller extends the base ---\n";

$no_extend = array();
foreach ( $parsed as $base => $p ) {
	if ( ! $p['registers'] || 'BaseApiController.php' === $base ) {
		continue;
	}
	if ( ! preg_match( '/class\s+\w+\s+extends\s+BaseApiController\b/', $p['src'] ) ) {
		$no_extend[] = $base;
	}
}
check( array() === $no_extend, 'all route-registering API classes extend BaseApiController' . ( $no_extend ? ': ' . implode( ', ', $no_extend ) : ' (16 classes)' ) );

/* ------------------------------------------------------------------ */
echo "\n--- 2. callback bodies never answer in a native/raw shape ---\n";

$BANNED_FUNCS = array( 'rest_ensure_response', 'wp_send_json', 'wp_send_json_error', 'wp_send_json_success', 'wp_die' );
$raw_shape    = array();
$raw_return   = array();
$scanned      = 0;

foreach ( $parsed as $base => $p ) {
	$tokens = $p['tokens'];
	$spans  = $p['spans'];
	foreach ( $p['callbacks'] as $method ) {
		if ( ! isset( $spans[ $method ] ) ) {
			$raw_shape[] = "{$base}::{$method} (method body not found)";
			continue;
		}
		$scanned++;
		list( $from, $to ) = $spans[ $method ];

		// Nested closures (array_map/array_filter transformers) have their own
		// returns that never reach the REST layer — locate their spans so the
		// return discipline below only judges the callback's own returns.
		$closure_spans = array();
		for ( $i = $from; $i <= $to; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) {
				continue;
			}
			$next = null;
			for ( $j = $i + 1; $j <= $to; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
					continue;
				}
				$next = $tokens[ $j ];
				break;
			}
			// A named function has T_STRING next; a closure has '('.
			if ( null === $next || ( is_array( $next ) && T_STRING === $next[0] ) ) {
				continue;
			}
			$paren = 0;
			$start = null;
			$depth = 0;
			for ( $j = $i + 1; $j <= $to; $j++ ) {
				$tj = $tokens[ $j ];
				if ( is_array( $tj ) && ( T_CURLY_OPEN === $tj[0] || T_DOLLAR_OPEN_CURLY_BRACES === $tj[0] ) ) {
					if ( null !== $start ) {
						$depth++;
					}
					continue;
				}
				if ( is_string( $tj ) ) {
					if ( '(' === $tj ) {
						$paren++;
					} elseif ( ')' === $tj ) {
						$paren--;
					} elseif ( '{' === $tj ) {
						if ( null === $start && 0 === $paren ) {
							$start = $j;
							$depth = 1;
						} elseif ( null !== $start ) {
							$depth++;
						}
					} elseif ( '}' === $tj && null !== $start ) {
						$depth--;
						if ( 0 === $depth ) {
							$closure_spans[] = array( $start, $j );
							break;
						}
					}
				}
			}
		}

		// Variables legitimately returnable: assigned from $this->respond_*/relay_*.
		$envelope_vars = array();
		// 2a: banned constructs. 2b: return discipline.
		for ( $i = $from; $i <= $to; $i++ ) {
			$t = $tokens[ $i ];
			if ( ! is_array( $t ) ) {
				continue;
			}
			// new \WP_Error / new WP_Error
			if ( T_NEW === $t[0] ) {
				for ( $j = $i + 1; $j <= $to; $j++ ) {
					if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
						continue;
					}
					$cls = tk_text( $tokens[ $j ] );
					if ( in_array( $cls, array( '\WP_Error', 'WP_Error' ), true ) ) {
						$raw_shape[] = "{$base}::{$method} (new WP_Error)";
					}
					break;
				}
			}
			if ( T_STRING === $t[0] && in_array( $t[1], $BANNED_FUNCS, true ) ) {
				$raw_shape[] = "{$base}::{$method} ({$t[1]})";
			}
			if ( in_array( $t[0], array( T_ECHO, T_EXIT, T_PRINT ), true ) ) {
				$raw_shape[] = "{$base}::{$method} ({$t[1]})";
			}
			// $var = $this->respond_*|relay_*  → collect $var
			if ( T_VARIABLE === $t[0] ) {
				$nx = array();
				for ( $j = $i + 1; $j <= $to && count( $nx ) < 4; $j++ ) {
					if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
						continue;
					}
					$nx[] = tk_text( $tokens[ $j ] );
				}
				if ( ( $nx[0] ?? '' ) === '=' && ( $nx[1] ?? '' ) === '$this' && ( $nx[2] ?? '' ) === '->'
					&& 0 === strpos( (string) ( $nx[3] ?? '' ), 'respond_' ) || ( ( $nx[0] ?? '' ) === '=' && ( $nx[1] ?? '' ) === '$this' && ( $nx[2] ?? '' ) === '->' && 'relay_error' === ( $nx[3] ?? '' ) ) ) {
					$envelope_vars[] = $t[1];
				}
			}
			// return discipline
			if ( T_RETURN === $t[0] ) {
				$in_closure = false;
				foreach ( $closure_spans as $cs ) {
					if ( $i > $cs[0] && $i < $cs[1] ) {
						$in_closure = true;
						break;
					}
				}
				if ( $in_closure ) {
					continue; // array_map/filter transformer, not a REST answer.
				}
				$seq = array();
				for ( $j = $i + 1; $j <= $to; $j++ ) {
					if ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
						continue;
					}
					$seq[] = $tokens[ $j ];
					if ( ';' === tk_text( $tokens[ $j ] ) || count( $seq ) >= 4 ) {
						break;
					}
				}
				$s0 = tk_text( $seq[0] ?? '' );
				if ( ';' === $s0 ) {
					continue; // bare return;
				}
				if ( '$this' === $s0 && '->' === tk_text( $seq[1] ?? '' ) ) {
					$mname = (string) tk_text( $seq[2] ?? '' );
					if ( 0 === strpos( $mname, 'respond_' ) || 'relay_error' === $mname ) {
						continue;
					}
					$raw_return[] = "{$base}::{$method} (return \$this->{$mname})";
					continue;
				}
				if ( is_array( $seq[0] ?? null ) && T_VARIABLE === $seq[0][0] && in_array( $seq[0][1], $envelope_vars, true ) && ';' === tk_text( $seq[1] ?? '' ) ) {
					continue; // return $response; built from respond_*
				}
				$raw_return[] = "{$base}::{$method} (return {$s0}…)";
			}
		}
	}
}
check( $scanned >= 40, "callback bodies actually scanned ({$scanned})" );
check( array() === $raw_shape, 'no callback answers via WP_Error/rest_ensure_response/wp_send_json/echo/exit' . ( $raw_shape ? ': ' . implode( '; ', array_slice( array_unique( $raw_shape ), 0, 8 ) ) : '' ) );
check( array() === $raw_return, 'every callback return goes through the envelope helpers' . ( $raw_return ? ': ' . implode( '; ', array_slice( array_unique( $raw_return ), 0, 8 ) ) : '' ) );

/* ------------------------------------------------------------------ */
echo "\n--- 3. permission callbacks: WP protocol, flavor_ codes, honest statuses ---\n";

$perm_bad    = array();
$perm_literals = 0;
foreach ( $parsed as $base => $p ) {
	$tokens = $p['tokens'];
	$spans  = $p['spans'];
	// BaseApiController registers no routes itself, but its protocol methods
	// ARE the permission callbacks of half the API (referenced cross-file as
	// array( $this, 'require_authenticated' ) etc.) — scan them by name.
	$scan = 'BaseApiController.php' === $base
		? array( 'require_authenticated', 'require_capability', 'resolve_branch_scope' )
		: $p['permissions'];
	foreach ( $scan as $method ) {
		if ( ! isset( $spans[ $method ] ) ) {
			continue; // e.g. Base helpers referenced cross-file; scanned under their own file.
		}
		list( $from, $to ) = $spans[ $method ];
		for ( $i = $from; $i <= $to; $i++ ) {
			$t = $tokens[ $i ];
			if ( ! is_array( $t ) || T_NEW !== $t[0] ) {
				continue;
			}
			// Confirm it is a WP_Error construction and find its '('.
			$cls = null;
			$open = null;
			for ( $j = $i + 1; $j <= $to; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
					continue;
				}
				if ( null === $cls ) {
					$cls = tk_text( $tokens[ $j ] );
					continue;
				}
				if ( '(' === $tokens[ $j ] ) {
					$open = $j;
				}
				break;
			}
			if ( ! in_array( (string) $cls, array( '\WP_Error', 'WP_Error' ), true ) || null === $open ) {
				continue;
			}
			$perm_literals++;
			$args   = call_args( $tokens, $open );
			$code   = arg_literal_string( $args[0] ?? array() );
			$status = null;
			$data_src = '';
			foreach ( $args[2] ?? array() as $dt ) {
				$data_src .= tk_text( $dt );
			}
			if ( preg_match( "/'status'\s*=>\s*(\d{3})/", $data_src, $sm ) ) {
				$status = (int) $sm[1];
			}
			if ( null === $code || 0 !== strpos( $code, 'flavor_' ) ) {
				$perm_bad[] = "{$base}::{$method} code=" . var_export( $code, true );
			}
			if ( ! in_array( $status, array( 400, 401, 403 ), true ) ) {
				$perm_bad[] = "{$base}::{$method} status=" . var_export( $status, true );
			}
		}
	}
}
check( $perm_literals >= 6, "permission-level WP_Error literals seen ({$perm_literals})" );
check( array() === $perm_bad, 'permission WP_Errors all carry flavor_* codes and 400/401/403 statuses' . ( $perm_bad ? ': ' . implode( '; ', array_slice( $perm_bad, 0, 8 ) ) : '' ) );

/* ------------------------------------------------------------------ */
echo "\n--- 4. respond_error statuses follow the semantic table ---\n";

$SEMANTIC = array(
	'not_found'   => 404,
	'unauthorized' => 401,
	'forbidden'   => 403,
	'rate'        => 429,
	'idempotency' => 409,
	'conflict'    => 409,
	'transition'  => 409,
);
// Hand-audited exceptions: the code name does not contain the keyword but
// the status is semantically right (the cart line-item resource is absent).
$ALLOWED_OTHER = array( 400, 422, 500, 503 );
$CODE_ALLOWLIST = array( 'invalid_cart_item' => 404 );

$status_bad = array();
$literal_calls = 0;
$relay_calls   = 0;
foreach ( $parsed as $base => $p ) {
	$tokens = $p['tokens'];
	$n      = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		$t = $tokens[ $i ];
		if ( ! is_array( $t ) || T_STRING !== $t[0] || 'respond_error' !== $t[1] ) {
			continue;
		}
		$open = null;
		for ( $j = $i + 1; $j < min( $i + 3, $n ); $j++ ) {
			if ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
				continue;
			}
			if ( '(' === $tokens[ $j ] ) {
				$open = $j;
			}
			break;
		}
		if ( null === $open ) {
			continue; // the declaration itself, in BaseApiController.
		}
		$args   = call_args( $tokens, $open );
		$code   = arg_literal_string( $args[0] ?? array() );
		$status = arg_literal_int( $args[2] ?? array() );
		if ( null === $code ) {
			$relay_calls++; // dynamic relay: status comes from the source error.
			continue;
		}
		$literal_calls++;
		if ( null === $status ) {
			continue; // literal code, dynamic status (relay hybrid) — trusted source.
		}
		$expected = null;
		foreach ( $SEMANTIC as $needle => $code_status ) {
			if ( false !== strpos( $code, $needle ) ) {
				$expected = $code_status;
				break;
			}
		}
		if ( null !== $expected ) {
			if ( $status !== $expected ) {
				$status_bad[] = "{$base}: {$code} uses {$status}, the table says {$expected}";
			}
			continue;
		}
		if ( isset( $CODE_ALLOWLIST[ $code ] ) ) {
			if ( $status !== $CODE_ALLOWLIST[ $code ] ) {
				$status_bad[] = "{$base}: {$code} uses {$status}, allowlist says {$CODE_ALLOWLIST[$code]}";
			}
			continue;
		}
		if ( ! in_array( $status, $ALLOWED_OTHER, true ) ) {
			$status_bad[] = "{$base}: {$code} uses {$status}, expected one of " . implode( '/', $ALLOWED_OTHER );
		}
	}
}
check( $literal_calls >= 30, "literal-code respond_error calls mapped ({$literal_calls})" );
check( $relay_calls >= 8, "dynamic relays counted ({$relay_calls}) — their status rides the source WP_Error" );
check( array() === $status_bad, 'every literal error code sits on its semantic status' . ( $status_bad ? ': ' . implode( '; ', array_slice( $status_bad, 0, 8 ) ) : '' ) );

/* ------------------------------------------------------------------ */
echo "\n--- 5. the wire normaliser exists, is wired, and is safe ---\n";

$env_file = $root . '/flavor-core/includes/Support/ResponseEnvelope.php';
$env_src  = is_readable( $env_file ) ? file_get_contents( $env_file ) : '';
check( '' !== $env_src, 'Support/ResponseEnvelope.php exists' );
check(
	false !== strpos( $env_src, "add_filter( 'rest_post_dispatch', array( \$this, 'on_rest_dispatch' ), 10, 3 )" ),
	'normaliser hooks rest_post_dispatch with the 3-arg signature'
);
check(
	false !== strpos( $env_src, "'/flavor/'" ) && false !== strpos( $env_src, 'get_route' ),
	'normaliser only touches /flavor/* routes (other namespaces keep the native shape)'
);
check(
	false !== strpos( $env_src, "isset( \$data['success'] )" ),
	'normaliser is idempotent (already-enveloped payloads pass through)'
);
check(
	false !== strpos( $env_src, 'set_data(' ) && false === strpos( $env_src, 'new \WP_REST_Response' ),
	'normaliser mutates via set_data — status and SecurityHeaders output survive'
);
$plugin_src = file_get_contents( $root . '/flavor-core/includes/Plugin.php' );
check(
	false !== strpos( $plugin_src, '( new Support\ResponseEnvelope() )->hooks()' ),
	'Plugin bootstrap wires the normaliser'
);
$base_src = $parsed['BaseApiController.php']['src'];
check(
	false !== strpos( $base_src, 'public function relay_error( \WP_Error $error ): \WP_REST_Response' ),
	'BaseApiController::relay_error is the sanctioned WP_Error relay'
);
$relay_users = 0;
foreach ( array( 'RestStore.php', 'RestExperience.php', 'RestSearch.php' ) as $legacy ) {
	if ( false !== strpos( $parsed[ $legacy ]['src'], 'relay_error(' ) ) {
		$relay_users++;
	}
}
check( 3 === $relay_users, 'all three legacy relay sites use it (RestStore/RestExperience/RestSearch)' );

/* ------------------------------------------------------------------ */
echo "\n--- 6. wp_send_json stays inside the admin-ajax shim ---\n";

$ajax_bad = array();
foreach ( $parsed as $base => $p ) {
	$spans = $p['spans'];
	foreach ( $p['tokens'] as $i => $t ) {
		if ( is_array( $t ) && T_STRING === $t[0] && 0 === strpos( $t[1], 'wp_send_json' ) ) {
			$inside_ajax = false;
			if ( 'RestSearch.php' === $base && isset( $spans['ajax'] ) ) {
				list( $from, $to ) = $spans['ajax'];
				$inside_ajax = $i > $from && $i < $to;
			}
			if ( ! $inside_ajax ) {
				$ajax_bad[] = $base;
			}
		}
	}
}
$search_src = $parsed['RestSearch.php']['src'];
check( array() === $ajax_bad, 'wp_send_json_* appears only inside RestSearch::ajax' . ( $ajax_bad ? ': ' . implode( ', ', array_unique( $ajax_bad ) ) : '' ) );
check(
	false !== strpos( $search_src, "add_action( 'wp_ajax_flavor_search', array( \$this, 'ajax' ) )" ),
	'the shim itself is an admin-ajax action, not a REST route'
);
check(
	false !== strpos( $search_src, "'code'    => 'flavor_rate'" ),
	'the ajax refusal mirrors the REST error entry ({code,message})'
);

/* ------------------------------------------------------------------ */
echo "\n--- 7. the prompt's status table stays pinned at its sources ---\n";

$rl_src = file_get_contents( $root . '/flavor-core/includes/Support/RateLimit.php' );
check(
	false !== strpos( $rl_src, "'flavor_rate'" ) && (bool) preg_match( "/'flavor_rate'.*?'status'\s*=>\s*429/s", $rl_src ),
	'rate limiting emits flavor_rate with 429'
);
$co_src = file_get_contents( $root . '/flavor-core/includes/WooCommerce/CheckoutService.php' );
check(
	(bool) preg_match( "/'flavor_idempotency_conflict'.*?'status'\s*=>\s*409/s", $co_src ),
	'idempotency replay conflict emits flavor_idempotency_conflict with 409'
);
check(
	(bool) preg_match( "/'flavor_unauthorized'.*?'status'\s*=>\s*401/s", $base_src )
		&& (bool) preg_match( "/'flavor_forbidden'.*?'status'\s*=>\s*403/s", $base_src ),
	'the base guard emits flavor_unauthorized/401 and flavor_forbidden/403'
);
$kt_src = '';
foreach ( $parsed as $p ) {
	$kt_src .= $p['src'];
}
check(
	(bool) preg_match( "/'flavor_not_found'.*?404/s", $kt_src ) && (bool) preg_match( "/'flavor_bad_transition'.*?409/s", $kt_src ),
	'not-found stays 404 and state-transition conflict stays 409 in the route callbacks'
);
check(
	(bool) preg_match( "/'flavor_bad'.*?400|'flavor_cart'.*?400|'flavor_mobile'.*?400/s", $kt_src ),
	'input validation errors stay 400'
);

echo str_repeat( '=', 55 ) . "\nResults: $pass Passed, $fail Failed\n";
exit( $fail ? 1 : 0 );
