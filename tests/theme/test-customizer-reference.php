<?php
/**
 * Offline generator/guard for the Customizer control reference.
 *
 * docs/fa/08-jadval-kontrol-ha.md is the table merchants see when they ask
 * "what does this control actually do?". dev-tools/docs/export-customizer-
 * reference.php renders it from a live WordPress; this file renders the same
 * bytes from the same registrars against the recording fixture in
 * tests/theme/fixtures/customizer-ref-manager.php — no WordPress needed.
 *
 *   php tests/theme/test-customizer-reference.php          # guard: 9 checks
 *   php tests/theme/test-customizer-reference.php --write   # regenerate the doc
 *
 * Why both modes live here: a table that nobody can regenerate rots, and a
 * generator nobody verifies lies. One renderer, two roles — the guard fails
 * the suite the moment the committed table stops matching what the code
 * registers (that includes a control whose description was dropped).
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-live-preview.php'; // style of the real bootstrap; also a load-order canary.
require_once __DIR__ . '/fixtures/customizer-ref-manager.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-repeater.php';
require_once FLAVOR_DIR . '/inc/class-repeater-control.php';
require_once FLAVOR_DIR . '/inc/class-customizer.php';
require_once FLAVOR_DIR . '/inc/class-premium-customizer.php';
require_once FLAVOR_DIR . '/inc/class-ui-customizer.php';
require_once FLAVOR_DIR . '/inc/class-chrome-builder.php';

$passed = 0;
$failed = 0;
$mode_write = in_array( '--write', $argv, true );

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

echo "=== Flavor Customizer Reference (offline) ===\n\n";

/* ------------------------------------------------------------------ boot */

echo "--- 1. The recording manager records ---\n";

$manager = new \Flavor_Ref_Manager();
$probe   = $manager->add_control(
	'flavor_probe_control',
	array(
		'label'       => 'probe',
		'section'     => 'flavor_probe_section',
		'description' => 'probe desc',
	)
);
check(
	$probe instanceof \WP_Customize_Control
		&& 'probe desc' === $probe->description
		&& 10 === $probe->priority,
	'fixture keeps args and core defaults (priority 10 for controls, 160 for sections/panels)'
);
$again = $manager->add_control( 'flavor_probe_control', array( 'label' => 'probe 2', 'section' => 'flavor_probe_section' ) );
check(
	array_keys( $manager->controls() ) === array( 'flavor_probe_control' ) && 'probe 2' === $again->label,
	'registering the same id replaces the control but keeps its position (core map semantics)'
);

/* --------------------------------------------------------------- render */

// Same order functions.php wires the hooks: Customizer, UI (both prio 20 in the
// hook, UI added first), Premium, Chrome (prio 25) — ties resolve by this order.
$registrar_order = array(
	'\Flavor\Customizer',
	'\Flavor\UI_Customizer',
	'\Flavor\Premium_Customizer',
	'\Flavor\Chrome_Builder',
);
foreach ( $registrar_order as $registrar ) {
	$registrar::register( $manager );
}

/**
 * Render the reference markdown exactly like dev-tools/docs/export-customizer-reference.php.
 *
 * @param object $manager      Recording manager.
 * @param int    $carry_core   Core/Woo control count carried from the live export (0 offline).
 * @return array{0:string,1:int,2:int,3:int} markdown, flavor count, no-desc count, section count.
 */
function render_reference( $manager, int $carry_core ): array {
	$panels   = $manager->panels();
	$sections = $manager->sections();
	$controls = $manager->controls();

	$by_section = array();
	$core_count = $carry_core;
	$flavor     = 0;
	$no_desc    = 0;
	foreach ( $controls as $control ) {
		if ( 0 === strpos( $control->id, 'flavor_probe' ) ) {
			continue; // the fixture's own probe control, never part of the table.
		}
		if ( 0 !== strpos( $control->id, 'flavor_' ) ) {
			continue; // offline there are no extra core/Woo controls to see.
		}
		++$flavor;
		$desc = trim( \wp_strip_all_tags( (string) $control->description ) );
		if ( '' === $desc ) {
			++$no_desc;
		}
		$by_section[ $control->section ][] = array(
			'id'    => $control->id,
			'label' => trim( \wp_strip_all_tags( (string) $control->label ) ),
			'desc'  => $desc,
			'type'  => \get_class( $control ),
			'prio'  => (int) $control->priority,
		);
	}

	$section_rows = array();
	foreach ( $sections as $id => $section ) {
		if ( empty( $by_section[ $id ] ) ) {
			continue;
		}
		if ( 0 === strpos( (string) $id, 'flavor_probe' ) ) {
			continue;
		}
		$panel_id       = isset( $section->panel ) ? (string) $section->panel : '';
		$section_rows[] = array(
			'id'       => $id,
			'title'    => trim( \wp_strip_all_tags( (string) $section->title ) ),
			'panel'    => $panel_id,
			'panel_p'  => isset( $panels[ $panel_id ] ) ? (int) $panels[ $panel_id ]->priority : 9999,
			'priority' => (int) $section->priority,
		);
	}
	usort(
		$section_rows,
		static function ( $a, $b ) {
			return array( $a['panel_p'], $a['priority'] ) <=> array( $b['panel_p'], $b['priority'] );
		}
	);

	$out  = "<!-- GENERATED FILE. Do not edit by hand.\n";
	$out .= "     Source: dev-tools/docs/export-customizer-reference.php\n";
	$out .= "     Regenerate: wp eval-file dev-tools/docs/export-customizer-reference.php -->\n\n";
	$out .= "# جدول کنترل‌های شخصی‌سازی Flavor\n\n";
	$out .= "این جدول مستقیماً از خودِ وردپرس خوانده شده است: همان کنترل‌هایی که پنل شخصی‌سازی نشان می‌دهد.\n\n";
	$out  .= sprintf(
		"- کنترل‌های Flavor: **%d**\n- از این میان بدون توضیح: **%d**\n- بخش‌های Flavor: **%d**\n- کنترل‌های هسته و ووکامرس (در این جدول نیستند): **%d**\n\n",
		$flavor,
		$no_desc,
		count( $section_rows ),
		$core_count
	);
	$out .= "ستون «توضیح» همان متنی است که زیر کنترل در پنل دیده می‌شود. ستون «شناسه» نامِ کنترل در Customizer است و همان کلیدی است که در کد با آن کار می‌شود.\n\n";

	$current_panel = null;
	foreach ( $section_rows as $row ) {
		$panel_title = '';
		if ( '' !== $row['panel'] && isset( $panels[ $row['panel'] ] ) ) {
			$panel_title = trim( \wp_strip_all_tags( (string) $panels[ $row['panel'] ]->title ) );
		}
		if ( $panel_title !== $current_panel ) {
			$current_panel = $panel_title;
			if ( '' !== $panel_title ) {
				$out .= "\n## پنل: {$panel_title}\n\n";
			}
		}
		$out    .= "### {$row['title']}\n\n";
		$out    .= "| شناسه | برچسب | توضیح | نوع |\n|---|---|---|---|\n";
		$rows    = $by_section[ $row['id'] ];
		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['prio'] <=> $b['prio'];
			}
		);
		foreach ( $rows as $c ) {
			$desc  = '' === $c['desc'] ? '⚠️ بدون توضیح' : str_replace( '|', '\\|', $c['desc'] );
			$label = str_replace( '|', '\\|', $c['label'] );
			$type  = preg_replace( '/^.*\\\\/', '', $c['type'] );
			$out  .= "| `{$c['id']}` | {$label} | {$desc} | {$type} |\n";
		}
		$out .= "\n";
	}

	return array( $out, $flavor, $no_desc, count( $section_rows ) );
}

/*
 * Offline the manager only holds the theme's own registrations; the core/Woo
 * count is a live-only number, so it is carried across verbatim from the
 * committed doc rather than recounted (a value this renderer cannot see).
 */
$doc_path   = dirname( FLAVOR_DIR ) . '/docs/fa/08-jadval-kontrol-ha.md';
$committed  = is_readable( $doc_path ) ? (string) file_get_contents( $doc_path ) : '';
$carry_core = preg_match( '/کنترل‌های هسته و ووکامرس \(در این جدول نیستند\): \*\*(\d+)\*\*/u', $committed, $m ) ? (int) $m[1] : 0;

list( $rendered, $flavor, $no_desc, $section_count ) = render_reference( $manager, $carry_core );

echo "\n--- 2. Registrars produced the expected universe ---\n";
check( 144 === $flavor, "144 Flavor controls registered (got {$flavor})" );
check( 23 === $section_count, "23 Flavor sections carry rows (got {$section_count})" );

echo "\n--- 3. Every control carries guidance (R3-09) ---\n";
check( 0 === $no_desc, "no control is left without a description ({$no_desc} missing)" );

echo "\n--- 4. Row shape survives Persian pipes ---\n";
$rows_in_render = preg_grep( '/^\| `flavor_/', explode( "\n", $rendered ) );
$shape_ok       = true;
foreach ( $rows_in_render as $line ) {
	$cells = explode( ' | ', $line );
	if ( 4 !== count( $cells ) ) {
		$shape_ok = false;
		break;
	}
}
check( $shape_ok && 144 === count( $rows_in_render ), 'every row splits into exactly four cells when cut on " | " (escaped pipes inside cells stay inside)' );

echo "\n--- 5. Sections sort panel-first, rows sort by priority ---\n";
$order_ok       = true;
$last_panel_pos = 0;
foreach ( explode( "### ", $rendered ) as $chunk ) {
	if ( '' === trim( $chunk ) ) {
		continue;
	}
	$prio   = -1;
	$sorted = true;
	foreach ( preg_grep( '/^\| `flavor_/', explode( "\n", $chunk ) ) as $line ) {
		$cells  = explode( ' | ', $line );
		$id     = trim( $cells[0], "| `\n" );
		$found  = $manager->get_control( $id );
		if ( ! $found ) {
			$sorted = false;
			break;
		}
		if ( (int) $found->priority < $prio ) {
			$sorted = false;
			break;
		}
		$prio = (int) $found->priority;
	}
	if ( ! $sorted ) {
		$order_ok = false;
		break;
	}
}
check( $order_ok, 'inside every section the rows are non-decreasing in priority, as the pane shows them' );

echo "\n--- 6. The committed doc agrees with the code ---\n";
if ( $mode_write ) {
	file_put_contents( $doc_path, $rendered );
	echo "  [PASS] wrote {$doc_path}: {$flavor} Flavor controls, {$no_desc} without description, {$section_count} sections\n";
	++$passed;
	$same = true;
} else {
	$strip_counters = static function ( string $text ): string {
		$lines  = explode( "\n", $text );
		$keeep  = array();
		foreach ( $lines as $line ) {
			if ( preg_match( '/^- (کنترل‌های Flavor|از این میان بدون توضیح|بخش‌های Flavor|کنترل‌های هسته)/u', $line ) ) {
				continue; // volatile counters: asserted separately above.
			}
			$keeep[] = $line;
		}
		return implode( "\n", $keeep );
	};
	$same = '' !== $committed && $strip_counters( $rendered ) === $strip_counters( $committed );
	check( $same, 'rendered table equals the committed doc (counter lines excluded — checked in sections 2–3)' );
}

echo "\n--- 7. The header banner promises the live regeneration path ---\n";
check(
	strpos( $rendered, "Source: dev-tools/docs/export-customizer-reference.php\n     Regenerate: wp eval-file dev-tools/docs/export-customizer-reference.php" ) !== false,
	'the offline render keeps the live export banner byte-identical'
);

echo "\n--- 8. Descriptions are translatable ---\n";
// Collect every 'flavor'-domain literal the theme source passes around, run
// them through the same strip the exporter applies, and require each rendered
// description to be one of them. A control whose help text was assembled by
// hand (not __( '...', 'flavor' )) would render but never reach the POT.
$source_strings = array();
foreach ( (array) glob( FLAVOR_DIR . '/inc/*.php' ) as $inc_file ) {
	$body = (string) file_get_contents( $inc_file );
		if ( preg_match_all( "/__\( '([^']*)', 'flavor' \)/", $body, $m ) ) {
		foreach ( $m[1] as $literal ) {
			$source_strings[ trim( \wp_strip_all_tags( $literal ) ) ] = true;
		}
	}
}
$translatable = true;
foreach ( $manager->controls() as $control ) {
	if ( 0 !== strpos( $control->id, 'flavor_' ) || false !== strpos( $control->id, 'flavor_probe' ) ) {
		continue;
	}
	$desc = trim( \wp_strip_all_tags( (string) $control->description ) );
	if ( '' === $desc || ! isset( $source_strings[ $desc ] ) ) {
		$translatable = false;
		break;
	}
}
check( $translatable, 'every rendered description matches a __( $text, \'flavor\' ) literal in the theme source (so it reaches the POT)' );

echo "\n--- 9. Arabic catalogue covers the descriptions ---\n";
$po_path = FLAVOR_DIR . '/languages/flavor-ar.po';
if ( ! is_readable( $po_path ) ) {
	check( false, 'flavor/languages/flavor-ar.po exists' );
} else {
	$po      = (string) file_get_contents( $po_path );
	$missing = array();
	foreach ( $manager->controls() as $control ) {
		if ( 0 !== strpos( $control->id, 'flavor_' ) || false !== strpos( $control->id, 'flavor_probe' ) ) {
			continue;
		}
		$desc = trim( \wp_strip_all_tags( (string) $control->description ) );
		if ( '' === $desc || false !== strpos( $desc, '%d' ) ) {
			continue; // the gallery string is slot-numbered via sprintf upstream.
		}
		if ( false === strpos( $po, "\nmsgid \"" . $desc . "\"" ) ) {
			$missing[] = $control->id;
		}
	}
	check( array() === $missing, 'every control description has a msgid in the Arabic catalogue' . ( $missing ? ' (missing: ' . implode( ', ', array_slice( $missing, 0, 4 ) ) . '…)' : '' ) );
}

echo "\n" . str_repeat( '─', 50 ) . "\n";
if ( $failed ) {
	echo "نتیجه: {$passed} موفق، {$failed} ناموفق\n";
	exit( 1 );
}
echo "نتیجه: {$passed} موفق، 0 ناموفق\n";
exit( 0 );
