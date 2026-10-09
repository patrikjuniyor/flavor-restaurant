<?php
/**
 * Build a POT catalog from the WordPress i18n function calls in the source.
 *
 * The shipped .pot files had drifted to an 11-line stub that declared
 * "Project-Id-Version: Flavor 0.1.0" and contained zero messages, while the
 * codebase carries close to two thousand translatable strings. A translator
 * opening that file would correctly conclude the project is not translatable.
 *
 * Usage:
 *   php dev-tools/build-pot.php
 *
 * @package Flavor
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

/**
 * Packages to scan: output file => [directories, domain, package name].
 */
$packages = array(
	$root . '/flavor/languages/flavor.pot'           => array(
		'dirs'   => array( $root . '/flavor' ),
		'domain' => 'flavor',
		'name'   => 'Flavor',
	),
	$root . '/flavor-core/languages/flavor-core.pot' => array(
		'dirs'   => array( $root . '/flavor-core' ),
		'domain' => 'flavor-core',
		'name'   => 'Flavor Core',
	),
);

/**
 * i18n calls we recognise: function => index of the $text argument.
 */
$functions = array(
	'__'              => 0,
	'esc_html__'      => 0,
	'esc_attr__'      => 0,
	'esc_html_e'      => 0,
	'esc_attr_e'      => 0,
	'_e'              => 0,
	'_x'              => 0,
	'_ex'             => 0,
	'esc_html_x'      => 0,
	'esc_attr_x'      => 0,
	'_n'              => 0,
	'_nx'             => 0,
	'_n_noop'         => 0,
	'_nx_noop'        => 0,
	'translate'       => 0,
	'esc_html'        => false, // Never treat escaping helpers as i18n calls.
);

/**
 * Collect PHP files under a directory, skipping vendored/generated code.
 *
 * @param string $dir Directory to walk.
 * @return string[]
 */
$collect = static function ( string $dir ): array {
	$files = array();
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		$path = $file->getPathname();
		if ( ! str_ends_with( $path, '.php' ) ) {
			continue;
		}
		// Vendored QR library and PHPUnit doubles are not ours to translate.
		if ( str_contains( $path, '/includes/lib/' ) || str_contains( $path, '/vendor/' ) ) {
			continue;
		}
		$files[] = $path;
	}
	sort( $files );
	return $files;
};

/**
 * Extract the string literal passed as argument N of a call.
 *
 * @param string $source Source text following the function name.
 * @param int    $index  Argument index.
 * @return string|null
 */
$arg_at = static function ( string $source, int $index ): ?string {
	$depth  = 0;
	$arg    = 0;
	$buffer = '';
	$quote  = '';
	$len    = strlen( $source );

	for ( $i = 0; $i < $len; $i++ ) {
		$c = $source[ $i ];

		if ( '' !== $quote ) {
			if ( '\\' === $c && $i + 1 < $len ) {
				$buffer .= $c . $source[ ++$i ];
				continue;
			}
			if ( $c === $quote ) {
				$quote = '';
				continue;
			}
			$buffer .= $c;
			continue;
		}

		if ( "'" === $c || '"' === $c ) {
			$quote = $c;
			continue;
		}

		if ( '(' === $c || '[' === $c ) {
			++$depth;
			if ( 1 === $depth ) {
				continue;
			}
		} elseif ( ')' === $c || ']' === $c ) {
			--$depth;
			if ( 0 === $depth ) {
				break;
			}
		} elseif ( ',' === $c && 1 === $depth ) {
			if ( $arg === $index ) {
				break;
			}
			++$arg;
			$buffer = '';
			continue;
		}

		if ( $depth >= 1 ) {
			$buffer .= ' ';
		}
	}

	return '' === trim( $buffer ) ? null : trim( $buffer );
};

/**
 * Decode a single-quoted or double-quoted PHP literal without eval().
 *
 * @param string $literal Raw literal including quotes (or already bare).
 * @return string
 */
$decode = static function ( string $literal ): string {
	$literal = trim( $literal );
	if ( strlen( $literal ) >= 2 ) {
		$first = $literal[0];
		$last  = substr( $literal, -1 );
		if ( ( "'" === $first && "'" === $last ) || ( '"' === $first && '"' === $last ) ) {
			$literal = substr( $literal, 1, -1 );
			if ( '"' === $first ) {
				$literal = str_replace(
					array( '\\n', '\\t', '\\r', '\\"', '\\\\', '\\$' ),
					array( "\n", "\t", "\r", '"', '\\', '$' ),
					$literal
				);
			} else {
				$literal = str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $literal );
			}
		}
	}
	return $literal;
};

/**
 * Escape a string for a POT entry.
 *
 * @param string $value Value to escape.
 * @return string
 */
$pot_escape = static function ( string $value ): string {
	return str_replace(
		array( '\\', '"', "\t", "\r" ),
		array( '\\\\', '\"', '\t', '\r' ),
		$value
	);
};

$total_messages = 0;

foreach ( $packages as $out_file => $package ) {
	$domain  = $package['domain'];
	$strings = array(); // msgid => [references, comments].

	foreach ( $package['dirs'] as $dir ) {
		foreach ( $collect( $dir ) as $file ) {
			$code = file_get_contents( $file );
			if ( false === $code ) {
				continue;
			}

			// Relative reference so the catalog stays portable.
			$reference = ltrim( str_replace( $root, '', $file ), '/' );
			$lines     = explode( "\n", $code );

			foreach ( $functions as $fn => $text_index ) {
				if ( false === $text_index ) {
					continue;
				}

				$offset = 0;
				while ( preg_match( '/(?<![a-zA-Z0-9_$>:])' . preg_quote( $fn, '/' ) . '\s*\(/i', $code, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
					$start  = $m[0][1] + strlen( $m[0][0] );
					$offset = $start;

					// Raw literal for the text argument.
					$raw_text = $arg_at( substr( $code, $start - 1 ), $text_index );
					if ( null === $raw_text ) {
						continue;
					}
					$text = $decode( $raw_text );
					if ( '' === $text ) {
						continue;
					}

					// Only keep calls bound to this package's text domain.
					$raw_domain = $arg_at( substr( $code, $start - 1 ), 1 );
					if ( null === $raw_domain || $decode( $raw_domain ) !== $domain ) {
						// _n( singular, plural, $number, domain ) and _x( text, context, domain ).
						$raw_domain = $arg_at( substr( $code, $start - 1 ), 3 );
						if ( null === $raw_domain || $decode( $raw_domain ) !== $domain ) {
							continue;
						}
					}

					$line = count( explode( "\n", substr( $code, 0, $m[0][1] ) ) );

					if ( ! isset( $strings[ $text ] ) ) {
						$strings[ $text ] = array(
							'files'    => array(),
							'comments' => array(),
						);
					}
					$strings[ $text ]['files'][ "{$reference}:{$line}" ] = true;

					// Carry a neighbouring "translators:" note when present.
					$above = $lines[ max( 0, $line - 3 ) ] ?? '';
					if ( preg_match( '/translators:\s*(.+)/i', $above, $cm ) ) {
						$strings[ $text ]['comments'][ trim( $cm[1] ) ] = true;
					}
				}
			}
		}
	}

	ksort( $strings, SORT_NATURAL | SORT_FLAG_CASE );

	$version = 'flavor' === strtolower( $domain )
		? trim( (string) shell_exec( "grep -m1 '^[Vv]ersion:' {$root}/flavor/style.css | cut -d: -f2" ) )
		: trim( (string) shell_exec( "grep -m1 '^[Vv]ersion:' {$root}/flavor-core/flavor-core.php | cut -d: -f2 | tr -d ' *'" ) );

	$date = gmdate( 'Y-m-d H:i+00:00' );

	// Note: an empty msgid entry other than the header is a duplicate
	// definition, so the Persian-source note belongs in the header comments.
	$pot  = "# Copyright (C) Flavor\n";
	$pot .= "# This file is distributed under the GPL-2.0-or-later.\n";
	$pot .= "#\n";
	$pot .= "# Source strings are written in Persian, so msgid is the Persian text\n";
	$pot .= "# itself and a new language is translated FROM Persian.\n";
	$pot .= "msgid \"\"\n";
	$pot .= "msgstr \"\"\n";
	$pot .= "\"Project-Id-Version: {$package['name']} {$version}\\n\"\n";
	$pot .= "\"Report-Msgid-Bugs-To: https://github.com/patrikjuniyor/flavor-restaurant/issues\\n\"\n";
	$pot .= "\"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n\"\n";
	$pot .= "\"Language-Team: LANGUAGE <LL@li.org>\\n\"\n";
	$pot .= "\"MIME-Version: 1.0\\n\"\n";
	$pot .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
	$pot .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
	$pot .= "\"POT-Creation-Date: {$date}\\n\"\n";
	$pot .= "\"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n\"\n";
	$pot .= "\"X-Generator: Flavor build-pot.php\\n\"\n";
	$pot .= "\"X-Domain: {$domain}\\n\"\n";
	$pot .= "\n";

	foreach ( $strings as $msgid => $meta ) {
		$refs = array_keys( $meta['files'] );
		sort( $refs, SORT_NATURAL | SORT_FLAG_CASE );

		$pot .= '#: ' . implode( ' ', $refs ) . "\n";
		foreach ( array_keys( $meta['comments'] ) as $comment ) {
			$pot .= "#. " . $comment . "\n";
		}
		$pot .= 'msgid "' . $pot_escape( (string) $msgid ) . '"' . "\n";
		$pot .= 'msgstr ""' . "\n\n";
	}

	if ( ! is_dir( dirname( $out_file ) ) ) {
		mkdir( dirname( $out_file ), 0755, true );
	}
	file_put_contents( $out_file, $pot );

	printf( "%-42s %d strings  ->  %s\n", $package['name'], count( $strings ), basename( $out_file ) );
	$total_messages += count( $strings );
}

printf( "\nTotal: %d translatable messages.\n", $total_messages );
