<?php
/**
 * Generate the Persian reference of every Flavor Customizer control.
 *
 * Why this exists: a hand-written list of Customizer controls goes stale the
 * day someone adds a control. This asks WordPress itself what it registered
 * (through the real customize_register hook, in the same order the pane
 * shows them) and writes that out, so the reference cannot drift from the
 * code without the diff showing it.
 *
 * Run it inside a real WordPress install that has the theme active:
 *
 *   wp eval-file dev-tools/docs/export-customizer-reference.php
 *
 * Output: docs/fa/08-jadval-kontrol-ha.md (override with FLAVOR_REF_OUT).
 *
 * Only controls whose id starts with `flavor_` are listed. WordPress core
 * and WooCommerce register their own controls on the same hook; those are
 * documented by their owners and counted separately in the footer.
 *
 * @package Flavor
 */

if ( ! function_exists( 'do_action' ) ) {
	fwrite( STDERR, "Run this through WP-CLI: wp eval-file dev-tools/docs/export-customizer-reference.php\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';

$manager = new WP_Customize_Manager();
do_action( 'customize_register', $manager );

$panels   = $manager->panels();
$sections = $manager->sections();
$controls = $manager->controls();

$by_section = array();
$core_count = 0;
$flavor     = 0;
$no_desc    = 0;
foreach ( $controls as $control ) {
	if ( 0 !== strpos( $control->id, 'flavor_' ) ) {
		++$core_count;
		continue;
	}
	++$flavor;
	$desc = trim( wp_strip_all_tags( (string) $control->description ) );
	if ( '' === $desc ) {
		++$no_desc;
	}
	$by_section[ $control->section ][] = array(
		'id'    => $control->id,
		'label' => trim( wp_strip_all_tags( (string) $control->label ) ),
		'desc'  => $desc,
		'type'  => get_class( $control ),
		'prio'  => (int) $control->priority,
	);
}

// Sort sections the way the pane does: panel first, then section priority.
$section_rows = array();
foreach ( $sections as $id => $section ) {
	if ( empty( $by_section[ $id ] ) ) {
		continue;
	}
	$panel_id = isset( $section->panel ) ? (string) $section->panel : '';
	$section_rows[] = array(
		'id'       => $id,
		'title'    => trim( wp_strip_all_tags( (string) $section->title ) ),
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
$out .= sprintf(
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
		$panel_title = trim( wp_strip_all_tags( (string) $panels[ $row['panel'] ]->title ) );
	}
	if ( $panel_title !== $current_panel ) {
		$current_panel = $panel_title;
		if ( '' !== $panel_title ) {
			$out .= "\n## پنل: {$panel_title}\n\n";
		}
	}
	$out .= "### {$row['title']}\n\n";
	$out .= "| شناسه | برچسب | توضیح | نوع |\n|---|---|---|---|\n";
	$rows = $by_section[ $row['id'] ];
	usort(
		$rows,
		static function ( $a, $b ) {
			return $a['prio'] <=> $b['prio'];
		}
	);
	foreach ( $rows as $c ) {
		$desc = '' === $c['desc'] ? '⚠️ بدون توضیح' : str_replace( '|', '\\|', $c['desc'] );
		$label = str_replace( '|', '\\|', $c['label'] );
		$type  = preg_replace( '/^.*\\\\/', '', $c['type'] );
		$out  .= "| `{$c['id']}` | {$label} | {$desc} | {$type} |\n";
	}
	$out .= "\n";
}

$target = getenv( 'FLAVOR_REF_OUT' );
if ( ! $target ) {
	$target = dirname( __DIR__, 2 ) . '/docs/fa/08-jadval-kontrol-ha.md';
}
file_put_contents( $target, $out );
echo "wrote {$target}: {$flavor} Flavor controls, {$no_desc} without description, " . count( $section_rows ) . " sections\n";
