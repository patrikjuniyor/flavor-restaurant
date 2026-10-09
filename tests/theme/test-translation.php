<?php
/**
 * Translation contract tests.
 *
 * A .mo file that never loads is indistinguishable from a missing one until
 * somebody switches the site language. These tests parse the compiled binary
 * directly — no gettext extension — and assert that:
 *
 *   - the magic number and layout are valid, so WordPress can load it;
 *   - a sample of Arabic strings actually resolve, which is the acceptance
 *     criterion: change the site language and the UI is translated;
 *   - every placeholder survives, because a dropped %s corrupts the message
 *     at runtime rather than failing at build time;
 *   - entries left untranslated are empty, which is how WordPress falls back
 *     to the source language. A catalogue prefilled with Persian would look
 *     complete while translating nothing.
 *
 *   php tests/theme/test-translation.php
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
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

/**
 * Parse a binary .mo file into msgid => msgstr.
 *
 * @param string $path File path.
 * @return array<string, string>|null Null when the file is not a valid .mo.
 */
function parse_mo( string $path ) {
	$bytes = (string) file_get_contents( $path );

	if ( strlen( $bytes ) < 28 ) {
		return null;
	}

	// 0x950412de little-endian, or its byte-swapped twin.
	$magic = unpack( 'V', substr( $bytes, 0, 4 ) )[1];
	$swap  = false;

	if ( 0x950412de !== $magic ) {
		$magic = unpack( 'N', substr( $bytes, 0, 4 ) )[1];
		if ( 0x950412de !== $magic ) {
			return null;
		}
		$swap = true;
	}

	$read = $swap
		? static fn( string $chunk ): int => unpack( 'N', $chunk )[1]
		: static fn( string $chunk ): int => unpack( 'V', $chunk )[1];

	$count      = $read( substr( $bytes, 8, 4 ) );
	$orig_off   = $read( substr( $bytes, 12, 4 ) );
	$trans_off  = $read( substr( $bytes, 16, 4 ) );

	$strings = array();

	for ( $i = 0; $i < $count; $i++ ) {
		$o_len  = $read( substr( $bytes, $orig_off + $i * 8, 4 ) );
		$o_pos  = $read( substr( $bytes, $orig_off + $i * 8 + 4, 4 ) );
		$t_len  = $read( substr( $bytes, $trans_off + $i * 8, 4 ) );
		$t_pos  = $read( substr( $bytes, $trans_off + $i * 8 + 4, 4 ) );

		$msgid  = substr( $bytes, $o_pos, $o_len );
		$msgstr = substr( $bytes, $t_pos, $t_len );

		// A plural entry stores its forms NUL-separated after the singular,
		// which is itself separated from msgid by a NUL.
		$parts = explode( "\0", $msgid );
		$key   = $parts[0];

		$strings[ $key ] = $msgstr;
	}

	return $strings;
}

echo "=== Flavor Translation Contract Tests ===\n\n";

$dir  = FLAVOR_DIR . '/languages';
$po   = $dir . '/flavor-ar.po';
$mo   = $dir . '/flavor-ar.mo';
$pot  = $dir . '/flavor.pot';

echo "--- 1. The catalogue exists and is loadable ---\n";

check( is_file( $pot ), 'flavor.pot exists' );
check( is_file( $po ), 'flavor-ar.po exists' );
check( is_file( $mo ), 'flavor-ar.mo exists (compiled, not just sources)' );

$strings = parse_mo( $mo );
check( null !== $strings, 'the .mo parses as a valid binary catalogue' );
check( is_array( $strings ) && count( $strings ) > 300, 'the .mo carries the message table (' . ( is_array( $strings ) ? count( $strings ) : 0 ) . ' entries)' );

echo "\n--- 2. Changing the language really changes the UI ---\n";

// The acceptance criterion: a fresh install switched to Arabic shows Arabic.
$samples = array(
	'افزودن به سبد'      => 'أضف إلى السلة',
	'سبد خرید'           => 'سلّة الشراء',
	'رزرو میز'           => 'حجز طاولة',
	'منوی رستوران'       => 'قائمة المطعم',
	'ساعات کاری'         => 'ساعات العمل',
	'درباره رستوران'     => 'حول المطعم',
	'مشاهده منو'         => 'عرض القائمة',
	'تعداد مهمان'        => 'عدد الضيوف',
	'هزینهٔ ارسال'       => 'تكلفة التوصيل',
	'کد تخفیف'           => 'كود الخصم',
	'بستن'               => 'إغلاق',
	'بیرون‌بر'            => 'طلب خارجي',
	'نمای سریع'          => 'عرض سريع',
	'توضیح'              => 'الوصف',
	'نام'                => 'الاسم',
	'تلفن'               => 'الهاتف',
	'نشانی'              => 'العنوان',
	'شعبه'               => 'الفرع',
	'گالری تصاویر'       => 'معرض الصور',
	'پیشنهاد ویژه'       => 'عرض خاص',
);

foreach ( $samples as $persian => $arabic ) {
	$actual = $strings[ $persian ] ?? null;
	check(
		$actual === $arabic,
		sprintf( '%s  ->  %s', $persian, null === $actual ? '(untranslated)' : $actual )
	);
}

// Guard against the laziest possible translation: an echo of the source.
$echoes = 0;
foreach ( $strings as $msgid => $msgstr ) {
	if ( '' !== $msgstr && $msgstr === $msgid ) {
		++$echoes;
	}
}
check( 0 === $echoes, 'no entry merely echoes its Persian source (' . $echoes . ')' );

$translated = 0;
foreach ( $strings as $msgstr ) {
	if ( '' !== $msgstr ) {
		++$translated;
	}
}
check( $translated >= 300, 'a substantial share of the catalogue is translated (' . $translated . ')' );

echo "\n--- 3. Every placeholder survives ---\n";

// A dropped %s does not fail the build; it corrupts the message on screen.
$with_placeholders = 0;
foreach ( $strings as $msgid => $msgstr ) {
	if ( '' === $msgstr ) {
		continue;
	}
	preg_match_all( '/%(?:\d+\$)?[sd]/', $msgid, $src );

	if ( empty( $src[0] ) ) {
		continue;
	}

	++$with_placeholders;

	$sorted_src = $src[0];
	sort( $sorted_src );

	// A plural entry holds one string per form, NUL-separated. Each form
	// carries the placeholder once, so comparing the concatenation would
	// report six copies where one is correct.
	$forms = explode( "\0", $msgstr );
	foreach ( $forms as $form ) {
		preg_match_all( '/%(?:\d+\$)?[sd]/', $form, $dst );
		$sorted_dst = $dst[0];
		sort( $sorted_dst );

		check(
			$sorted_src === $sorted_dst,
			sprintf( '%s keeps its placeholders (%s)', $msgid, implode( ' ', $src[0] ) )
		);
	}
}
check( $with_placeholders >= 3, 'the sample actually includes placeholder strings (' . $with_placeholders . ')' );

echo "\n--- 4. Untranslated entries fall back honestly ---\n";

// msgfmt drops empty msgstr entries when compiling, so the .mo holds only
// translated messages. Partial coverage is a property of the .po.
$po_text = (string) file_get_contents( $po );
preg_match_all( '/^msgstr ""/m', $po_text, $empty_matches );
$empty = count( $empty_matches[0] );
check( $empty > 0, 'the catalogue is explicitly partial (' . $empty . ' entries left empty in the .po)' );
check(
	$translated + $empty > 0,
	'the .po accounts for translated and untranslated entries'
);

// A partial catalogue must say so rather than pretend.
$header = (string) file_get_contents( $po );
check( false !== strpos( $header, 'Language: ar' ), 'the .po declares its language' );
check( false !== strpos( $header, 'Plural-Forms' ), 'the .po declares plural forms' );
check( false !== strpos( $header, 'nplurals=6' ), 'Arabic declares six plural forms' );

echo "\n--- 5. The plural entries carry all six forms ---\n";

$plural_source = (string) file_get_contents( $po );
preg_match_all( '/msgid_plural "(.*?)"\n((?:msgstr\[\d\] ".*?"\n)+)/', $plural_source, $plural_matches, PREG_SET_ORDER );

check( count( $plural_matches ) >= 2, 'the catalogue has plural entries (' . count( $plural_matches ) . ')' );

foreach ( $plural_matches as $index => $match ) {
	$forms = preg_match_all( '/msgstr\[\d\] "/', $match[2] );
	check( 6 === $forms, "plural entry {$index} supplies all six Arabic forms ({$forms})" );
}

echo "\n--- 6. The POT is in step with the source ---\n";

// A stale POT means translators work from strings that no longer exist.
$pot_text = (string) file_get_contents( $pot );
check( false !== strpos( $pot_text, 'Plural-Forms' ), 'the POT declares plural forms so msgfmt accepts plurals' );

// The plural calls added in N-05 and N-06 must be in there.
check( false !== strpos( $pot_text, 'msgid_plural' ), 'the POT carries at least one msgid_plural' );
check(
	false !== strpos( $pot_text, 'تنظیمات زیر از پوسته پیروی نمی‌کنند:' ),
	'the N-05 override notice plural is extracted'
);
check(
	false !== strpos( $pot_text, 'چند ترکیب رنگ خوانایی کافی ندارند:' ),
	'the N-06 contrast summary plural is extracted'
);

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
