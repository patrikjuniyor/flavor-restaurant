<?php
/**
 * Offline Elementor page fixtures for the visual guard (tests/ui/elementor-visual.mjs).
 *
 * The three shipped templates (flavor-home, flavor-about, flavor-menu) are
 * Elementor export JSON: a list of sections whose widgets are plain PHP.
 * Elementor's own layout CSS is not needed here because the templates carry
 * no layout overrides — every section is a full-width single column — so the
 * tiny shim below reproduces Elementor's documented defaults for exactly the
 * structures the templates use. Everything else that shapes the pixels is the
 * theme's real CSS, loaded by path, plus the real design tokens (including
 * the dark-scheme overrides Dark_Mode generates).
 *
 * Usage:
 *   php tests/ui/elementor-pages.php [outdir]
 *
 * Writes <outdir>/<slug>-<scheme>.html for 3 templates × light/dark. The .mjs
 * guard serves these pages plus /assets/** and drives them in Chromium.
 *
 * @package Flavor
 */

namespace Flavor\Tests;

/* Relative theme URI: the guard's static server resolves /themes/flavor/**, so
   demo images and placeholders load exactly as a customer's would. */
define( 'FLAVOR_URI', '/themes/flavor' );

require_once __DIR__ . '/../theme/bootstrap.php';
require_once __DIR__ . '/fixtures/real-template-parts.php';
require_once __DIR__ . '/../theme/fixtures/elementor-stubs.php';
require_once FLAVOR_DIR . '/inc/class-theme-setup.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-ui-pages.php';
require_once FLAVOR_DIR . '/inc/class-wishlist.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/template-tags.php';
require_once FLAVOR_DIR . '/inc/class-dark-mode.php';
require_once FLAVOR_DIR . '/inc/class-elementor.php';

require_once FLAVOR_DIR . '/elementor/class-widget-base.php';
foreach ( \Flavor\Elementor::widget_files() as $widget_file ) {
	require_once FLAVOR_DIR . '/elementor/widgets/' . $widget_file;
}

$outdir = $argv[1] ?? dirname( __DIR__, 2 ) . '/.cache/ui-qa/elementor';
if ( ! is_dir( $outdir ) && ! mkdir( $outdir, 0777, true ) ) {
	fwrite( STDERR, "cannot create {$outdir}\n" );
	exit( 1 );
}

/* widget name → class, as Elementor would resolve it. */
$by_name = array();
foreach ( \Flavor\Elementor::widget_classes() as $class ) {
	$fqcn = '\\Flavor\\Elementor\\' . $class;
	/** @var \Flavor\Elementor\Widget_Base $probe */
	$probe     = new $fqcn();
	$by_name[ (string) $probe->get_name() ] = $fqcn;
}

/**
 * Render one template's content tree to markup.
 *
 * @param array                     $content Template content (Elementor export shape).
 * @param array<string, class-string> $by_name Widget name → class map.
 * @return string HTML.
 */
function render_content( array $content, array $by_name ): string {
	$html = '';
	foreach ( $content as $section ) {
		if ( 'section' !== ( $section['elType'] ?? '' ) ) {
			continue;
		}
		$html .= sprintf(
			'<section class="elementor-section elementor-top-section" data-id="%s" data-element_type="section">',
			esc_attr( (string) ( $section['id'] ?? '' ) )
		);
		$html .= '<div class="elementor-container elementor-column-gap-default">';
		foreach ( (array) ( $section['elements'] ?? array() ) as $column ) {
			if ( 'column' !== ( $column['elType'] ?? '' ) ) {
				continue;
			}
			$size   = (int) ( $column['settings']['_column_size'] ?? 100 );
			$html  .= sprintf(
				'<div class="elementor-column elementor-col-%d" data-id="%s" data-element_type="column">',
				$size ? $size : 100,
				esc_attr( (string) ( $column['id'] ?? '' ) )
			);
			$html  .= '<div class="elementor-widget-wrap elementor-element-populated">';
			foreach ( (array) ( $column['elements'] ?? array() ) as $widget ) {
				$name = (string) ( $widget['widgetType'] ?? '' );
				if ( 'widget' !== ( $widget['elType'] ?? '' ) || '' === $name ) {
					continue;
				}
				$settings = (array) ( $widget['settings'] ?? array() );
				$html    .= sprintf(
					'<div class="elementor-element elementor-widget" data-id="%s" data-element_type="widget" data-widget_type="%s">',
					esc_attr( (string) ( $widget['id'] ?? '' ) ),
					esc_attr( $name )
				);
				$html    .= '<div class="elementor-widget-container">';
				if ( isset( $by_name[ $name ] ) ) {
					$widget_class = $by_name[ $name ];
					/** @var \Flavor\Elementor\Widget_Base $instance */
					$instance = new $widget_class();
					$instance->set_test_settings( $settings );
					$html .= $instance->test_render();
				} else {
					// test-elementor-widgets.php already fails the suite for an
					// unknown widgetType; the fixture degrades loudly, never silently.
					$html .= '<!-- missing widget: ' . esc_html( $name ) . ' -->';
				}
				$html .= '</div></div>';
			}
			$html .= '</div></div>';
		}
		$html .= '</div></section>';
	}
	return $html;
}

/**
 * Minimal escaping without WordPress (the bootstrap's mock covers this too,
 * but the fixture must not depend on which of the two is loaded first).
 *
 * @param string $value Raw.
 * @return string
 */
function esc_attr( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}

/*
 * Elementor defaults for the structures these templates use — the real
 * frontend stylesheet is not shipped with the theme, so this mirrors the
 * documented numbers: 1140px boxed container, flex rows, percentage columns,
 * 20px inter-widget spacing (10px on narrow screens), 75px/100px section
 * padding. If a template ever starts overriding these, this fixture must grow
 * with it (the guard fails on unknown section settings below).
 */
$shim = <<<'CSS'
.elementor .elementor-section { width: 100%; }
.elementor .elementor-container { display: flex; flex-wrap: wrap; margin-left: auto; margin-right: auto; align-items: stretch; max-width: 1140px; }
.elementor .elementor-section > .elementor-container > .elementor-column { display: flex; }
.elementor .elementor-column { position: relative; min-height: 1px; }
.elementor .elementor-col-100 { width: 100%; flex-basis: 100%; }
.elementor .elementor-col-50 { width: 50%; flex-basis: 50%; }
.elementor .elementor-col-33 { width: 33.333%; flex-basis: 33.333%; }
.elementor .elementor-widget-wrap { display: flex; flex-direction: column; width: 100%; align-items: stretch; }
.elementor .elementor-widget { position: relative; }
.elementor .elementor-widget:not(:last-child) { margin-bottom: 20px; }
.elementor .elementor-section:not(:last-child) { padding-bottom: 75px; }
@media (max-width: 767px) {
  .elementor .elementor-widget:not(:last-child) { margin-bottom: 10px; }
  .elementor .elementor-section:not(:last-child) { padding-bottom: 45px; }
}
CSS;

/*
 * The templates use no per-section layout settings; if that changes, the
 * shim above is no longer a faithful stand-in and this fixture must grow.
 */
$unsupported = 0;
$tokens      = \Flavor\Design::css_variables();
foreach ( array( 'home', 'about', 'menu' ) as $slug ) {
	$file = FLAVOR_DIR . '/elementor/templates/flavor-' . $slug . '.json';
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) {
		fwrite( STDERR, "unreadable template: {$file}\n" );
		exit( 1 );
	}
	foreach ( (array) ( $data['content'] ?? array() ) as $section ) {
		if ( (array) ( $section['settings'] ?? array() ) !== array() ) {
			++$unsupported;
		}
		foreach ( (array) ( $section['elements'] ?? array() ) as $column ) {
			if ( (array) ( $column['settings'] ?? array() ) !== array() ) {
				foreach ( array_keys( (array) ( $column['settings'] ?? array() ) ) as $key ) {
					if ( ! in_array( $key, array( '_column_size', '_inline_size' ), true ) ) {
						++$unsupported;
					}
				}
			}
		}
	}
	if ( $unsupported ) {
		fwrite( STDERR, "flavor-{$slug}.json carries section/column layout settings the shim does not emulate ({$unsupported}); extend tests/ui/elementor-pages.php deliberately.\n" );
		exit( 1 );
	}

	$body = render_content( (array) $data['content'], $by_name );
	foreach ( array( 'light', 'dark' ) as $scheme ) {
		$dark_css = 'dark' === $scheme ? "\n<style id=\"flavor-dark-mode\">\n" . \Flavor\Dark_Mode::css() . "\n</style>\n" : '';
		$html     = "<!DOCTYPE html>\n<html lang=\"fa\" dir=\"rtl\" data-flavor-scheme=\"{$scheme}\">\n<head>\n"
			. "<meta charset=\"utf-8\" />\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n"
			. "<title>flavor-{$slug} — {$scheme}</title>\n"
			. '<link rel="stylesheet" href="/assets/css/fonts.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/main.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/marketing.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/dark-mode.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/premium.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/ui.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/ui-navigation.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/ui-pages.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/ui-menu.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/rtl.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/skins/modern-restaurant.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/motion.css" />' . "\n"
			. '<link rel="stylesheet" href="/assets/css/elementor-widgets.css" />' . "\n"
			. "<style id=\"flavor-tokens\">\n{$tokens}\n</style>\n{$dark_css}"
			. "<style id=\"elementor-layout-shim\">\n{$shim}\n</style>\n"
			. "</head>\n<body class=\"flavor-theme flavor-skin-modern-restaurant elementor-page\">\n"
			. "<div class=\"elementor elementor-frontend\" data-elementor-type=\"wp-page\">\n{$body}\n</div>\n"
			. "</body>\n</html>\n";
		$path = $outdir . "/flavor-{$slug}-{$scheme}.html";
		file_put_contents( $path, $html );
		echo "wrote {$path}\n";
	}
}
echo "6 pages in {$outdir}\n";
