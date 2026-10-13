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
 * Writes <outdir>/flavor-<slug>-<scheme>.html for 3 templates × light/dark,
 * the two data-state pages and the direction probe page — plus the LTR twin
 * of every one under <outdir>/ltr/ (prompt 57). The .mjs guard serves these
 * pages plus /assets/** and drives them in Chromium, in both directions.
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

require_once FLAVOR_DIR . '/inc/class-repeater.php';
require_once FLAVOR_DIR . '/elementor/class-widget-base.php';
foreach ( \Flavor\Elementor::widget_files() as $widget_file ) {
	require_once FLAVOR_DIR . '/elementor/widgets/' . $widget_file;
}

$outdir = $argv[1] ?? dirname( __DIR__, 2 ) . '/.cache/ui-qa/elementor';
if ( ! is_dir( $outdir ) && ! mkdir( $outdir, 0777, true ) ) {
	fwrite( STDERR, "cannot create {$outdir}\n" );
	exit( 1 );
}

if ( ! is_dir( $outdir . '/ltr' ) && ! mkdir( $outdir . '/ltr', 0777, true ) ) {
	fwrite( STDERR, "cannot create {$outdir}/ltr\n" );
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
function page_shim(): string {
return <<<'CSS'
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
}

/*
 * The templates use no per-section layout settings; if that changes, the
 * shim above is no longer a faithful stand-in and this fixture must grow.
 */
/**
 * Page assembly shared by the template pages, the data-state pages and the
 * direction probe page.
 *
 * @param string   $slug       Page slug.
 * @param string   $scheme     light|dark.
 * @param string   $body       Rendered Elementor structure.
 * @param string   $dir        rtl|ltr — the twin sweep renders every page in both.
 * @param string[] $extra_css  Extra sheets to link (the probe page needs the demo layers).
 * @param string   $html_class Optional class on <html> (the probe page opts into flavor-anim-ready).
 * @return string Full HTML document.
 */
function assemble_page( string $slug, string $scheme, string $body, string $dir = 'rtl', array $extra_css = array(), string $html_class = '' ): string {
	/*
	 * The mock environment advertises one absolute sample image for every
	 * product card. Pointed at its host it never loads here, and a broken <img>
	 * measures as layout overflow — so the fixture swaps it for a demo image
	 * the guard actually serves. Links (hrefs) stay as the mock emits them.
	 */
	$body = str_replace(
		'https://restaurant.example.com/assets/images/sample-dish.jpg',
		'/themes/flavor/demos/modern-restaurant/category-burger.jpg',
		$body
	);
	$tokens = \Flavor\Design::css_variables();
	$dark   = 'dark' === $scheme ? "\n<style id=\"flavor-dark-mode\">\n" . \Flavor\Dark_Mode::css() . "\n</style>\n" : '';
	/* rtl.css ships only for RTL locales — class-enqueue.php gates it on
	   is_rtl(). The LTR twin must not load it: its body-level direction:rtl
	   would override the document's dir attribute. */
	$rtl_sheet = 'rtl' === $dir ? '<link rel="stylesheet" href="/assets/css/rtl.css" />' . "\n" : '';
	$extra     = implode(
		'',
		array_map(
			static function ( string $file ): string {
				return '<link rel="stylesheet" href="/assets/css/' . $file . '" />' . "\n";
			},
		$extra_css
		)
	);
	$html_attr = '' !== $html_class ? " class=\"{$html_class}\"" : '';
	return "<!DOCTYPE html>\n<html lang=\"fa\" dir=\"{$dir}\"{$html_attr} data-flavor-scheme=\"{$scheme}\">\n<head>\n"
		. "<meta charset=\"utf-8\" />\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n"
		. "<title>flavor-" . $slug . " — {$scheme}</title>\n"
		. '<link rel="stylesheet" href="/assets/css/fonts.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/main.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/marketing.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/dark-mode.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/premium.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/ui.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/ui-navigation.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/ui-pages.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/ui-menu.css" />' . "\n"
		. $rtl_sheet
		. '<link rel="stylesheet" href="/assets/css/skins/modern-restaurant.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/motion.css" />' . "\n"
		. '<link rel="stylesheet" href="/assets/css/elementor-widgets.css" />' . "\n"
		. $extra
		. "<style id=\"flavor-tokens\">\n{$tokens}\n</style>\n{$dark}"
		. "<style id=\"elementor-layout-shim\">\n" . page_shim() . "\n</style>\n"
		. "</head>\n<body class=\"flavor-theme flavor-skin-modern-restaurant elementor-page\">\n"
		. "<div class=\"elementor elementor-frontend\" data-elementor-type=\"wp-page\">\n{$body}\n</div>\n"
		. "</body>\n</html>\n";
}

foreach ( array( 'home', 'about', 'menu' ) as $slug ) {
	$file = FLAVOR_DIR . '/elementor/templates/flavor-' . $slug . '.json';
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) {
		fwrite( STDERR, "unreadable template: {$file}\n" );
		exit( 1 );
	}
	$unsupported = 0;
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
		foreach ( array( 'rtl', 'ltr' ) as $dir ) {
			$path = ( 'rtl' === $dir ? $outdir : $outdir . '/ltr' ) . '/flavor-' . $slug . '-' . $scheme . '.html';
			file_put_contents( $path, assemble_page( $slug, $scheme, $body, $dir ) );
			echo 'wrote ' . $path . "\n";
		}
	}
}

/*
 * R3-03: the data-backed widgets rendered in both of their states.
 *
 * flavor_menu answers an actual product query; flavor_offers and
 * flavor_gallery answer their settings. Each can therefore render "filled"
 * or "empty", and both branches must produce a real section — a readable
 * Persian message where there is no data, never a bare hole. The guard
 * asserts these states structurally and runs the same overlap/contrast/
 * overflow checks and screenshots over both pages.
 */
$state_contents = array(
	'filled' => array(
		'flavor_menu'    => array( 'title' => 'منوی گارد', 'count' => 6, 'orderby' => 'date', 'show_price' => 'yes' ),
		'flavor_offers'  => array(
			'title'       => 'پیشنهادِ ویژهٔ گارد',
			'text'        => 'تا پایان هفته، با کد زیر روی سفارش آنلاین تخفیف بگیرید.',
			'code'        => 'GUARD15',
			'button_text' => 'سفارش',
		),
		'flavor_gallery' => array(
			'gallery' => array(
				array( 'url' => '/themes/flavor/demos/modern-restaurant/category-main.jpg' ),
				array( 'url' => '/themes/flavor/demos/modern-restaurant/category-pasta.jpg' ),
			),
		),
	),
	'empty'  => array(
		'flavor_menu'    => array( 'title' => 'منوی گارد' ),
		'flavor_offers'  => array( 'title' => '', 'text' => '', 'code' => '' ),
		'flavor_gallery' => array( 'gallery' => array() ),
	),
);

$state_index = 0;
foreach ( $state_contents as $state => $widgets ) {
	$content = array();
	foreach ( $widgets as $type => $widget_settings ) {
		++$state_index;
		$content[] = array(
			'id'       => 'uif' . $state_index,
			'elType'   => 'section',
			'settings' => array(),
			'elements' => array(
				array(
					'id'       => 'uifc' . $state_index,
					'elType'   => 'column',
					'settings' => array( '_column_size' => 100 ),
					'elements' => array(
						array(
							'id'         => 'uifw' . $state_index,
							'elType'     => 'widget',
							'widgetType' => $type,
							'settings'   => $widget_settings,
						),
					),
				),
			),
		);
	}
	$products_backup = $GLOBALS['_mock_wc_products'] ?? array();
	if ( 'empty' === $state ) {
		$GLOBALS['_mock_wc_products'] = array();
	}
	$body = render_content( $content, $by_name );
	$GLOBALS['_mock_wc_products'] = $products_backup;
	foreach ( array( 'light', 'dark' ) as $scheme ) {
		foreach ( array( 'rtl', 'ltr' ) as $dir ) {
			$path = ( 'rtl' === $dir ? $outdir : $outdir . '/ltr' ) . '/flavor-states-' . $state . '-' . $scheme . '.html';
			file_put_contents( $path, assemble_page( 'states-' . $state, $scheme, $body, $dir ) );
			echo 'wrote ' . $path . "\n";
		}
	}
}
/*
 * Prompt 57 — the direction probe page. Not an Elementor export: it carries
 * every direction-bound component the theme ships (arrow icons from both
 * front-end icon() producers, the opt-in marquee, the parked drawer, the
 * motion.css start/end entrances and the mirrored keyframe families, the
 * bespoke demo hero/FAQ/visit entrance states, and the marketing experience
 * badge) so the twin sweep can measure one deterministic page per direction
 * instead of hoping a component happens to appear on a template.
 */
ob_start();
?>
<div class="flavor-container" style="padding-block:24px">
	<div class="flavor-anim-marquee"><span>پیامِ تاپ‌بار — طعمِ تازهٔ هر روز • سفارش آنلاین • ارسال سریع •</span></div>
	<aside class="flavor-drawer" aria-hidden="true"><div class="flavor-drawer__header"><span>یادداشتِ گشت</span></div></aside>
	<p>
		<a class="flavor-btn" href="#twin"><?php \Flavor\UI::icon( 'arrow', 18 ); ?><span>همهٔ سفارش‌ها</span></a>
		<a class="fd-button fd-button--primary" href="#twin">رزرو میز<?php \Flavor\Bespoke_Demos::icon( 'arrow', 19 ); ?></a>
	</p>
	<p class="flavor-anim-enter" data-flavor-anim="start">ورود از لبهٔ خوانش</p>
	<p class="flavor-anim-enter" data-flavor-anim="end">ورود از لبهٔ مقابل</p>
	<div class="flavor-hero__media" style="height:120px;background:#ddd"></div>
	<nav class="flavor-header__nav" aria-label="ناوبریِ گشتِ دوقلو"><ul class="flavor-nav"><li><a href="#twin">ستونِ یک</a></li><li class="flavor-mega flavor-mega--cols-2"><a href="#twin" data-flavor-nav="branch" aria-expanded="true">ستونِ دو</a><ul class="sub-menu"><li><a href="#twin">آیتمِ مگا</a></li></ul></li><li><a href="#twin">ستونِ سه</a></li></ul></nav>
	<script>window.flavorPremium = { mega: { enabled: true } };</script>
	<script src="/themes/flavor/assets/js/premium.js"></script>
	<div class="flavor-wish__item">آیتمِ علاقه‌مندی</div>
	<div class="flavor-cart-line">خطِ سبد</div>
	<div class="flavor-anim-shimmer" style="position:relative;height:48px;background:#eee"></div>
	<section class="fd-hero"><div class="fd-hero__copy fd-anim-enter"><h2>قهرمانِ دوطور</h2><p>متنِ آزمونِ گشت.</p></div><div class="fd-hero__art fd-anim-enter" style="height:100px;background:#ccc"></div></section>
	<div class="fd-faq"><details class="fd-anim-enter"><summary>پرسشِ دوطور؟</summary><p>پاسخِ کوتاه.</p></details></div>
	<div class="fd-visit__card" style="height:72px;background:#222"></div>
	<div class="flavor-about__media" style="position:relative;height:200px;background:#bbb"><span class="flavor-about__exp-badge"><strong>۱۲</strong><span>سال تجربه</span></span></div>
</div>
<?php
$direction_body = (string) ob_get_clean();
foreach ( array( 'rtl', 'ltr' ) as $dir ) {
	$path = ( 'rtl' === $dir ? $outdir : $outdir . '/ltr' ) . '/flavor-states-direction-light.html';
	file_put_contents(
		$path,
		assemble_page(
			'states-direction',
			'light',
			$direction_body,
			$dir,
			array( 'bespoke-demos.css', 'demo-animations.css' ),
			'flavor-anim-ready'
		)
	);
	echo 'wrote ' . $path . "\n";
}
echo '22 pages in ' . $outdir . " (11 rtl + 11 ltr)\n";
