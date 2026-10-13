<?php
/**
 * Offline main-page fixtures for the accessibility guard (tests/ui/a11y.mjs).
 *
 * Renders the six shipped page surfaces — home, menu, reservation, contact,
 * about and order tracking — through the project's own offline WordPress mock
 * (tests/theme/bootstrap.php), exactly the way dev-tools/preview/render.php
 * renders the demo landing pages: real header.php/footer.php chrome, real
 * page templates, real template parts, real theme CSS by path. The cart
 * drawer and the checkout form live inside the menu template (gated on
 * Flavor Core being present), so the plugin autoloader is registered too —
 * the a11y guard reveals those panels in the browser and scans them as the
 * "cart" and "checkout" surfaces the prompt names.
 *
 * Usage:
 *   php tests/ui/a11y-pages.php [outdir]
 *
 * Writes <outdir>/page-<slug>.html for every surface. The .mjs guard serves
 * these pages plus /assets/** and /themes/flavor/** and drives them in
 * Chromium with @axe-core/playwright.
 *
 * Any PHP warning/notice in the build output fails the guard (same contract
 * as elementor-pages.php): the fixture must render as clean as production.
 *
 * @package Flavor
 */

/* Relative theme URI, like elementor-pages.php: the guard's static server
   resolves /themes/flavor/**, so demo images load as a customer's would. */
if ( ! defined( 'FLAVOR_URI' ) ) {
	define( 'FLAVOR_URI', '/themes/flavor' );
}

require_once __DIR__ . '/../theme/bootstrap.php';

/*
 * Flavor Core, so Theme_Setup::has_core() is true and the class_exists()
 * probes (TableRepository for reservation capacity) resolve — the cart
 * drawer, the checkout form and the reservation picker all gate on the
 * plugin being there, and a live shop always has it.
 */
if ( ! defined( 'FLAVOR_CORE_PATH' ) ) {
	define( 'FLAVOR_CORE_PATH', dirname( __DIR__, 2 ) . '/flavor-core/' );
}
if ( ! defined( 'FLAVOR_CORE_VERSION' ) ) {
	define( 'FLAVOR_CORE_VERSION', '1.5.1' );
}
if ( ! defined( 'FLAVOR_CORE_REST_NAMESPACE' ) ) {
	define( 'FLAVOR_CORE_REST_NAMESPACE', 'flavor/v1' );
}
if ( ! defined( 'FLAVOR_CORE_REST_V2_NAMESPACE' ) ) {
	define( 'FLAVOR_CORE_REST_V2_NAMESPACE', 'flavor/v2' );
}
require_once FLAVOR_CORE_PATH . 'includes/Autoloader.php';
\FlavorCore\Autoloader::register();

/* Theme classes — the render.php list (header.php needs Chrome_Builder, the
   marketing gallery needs Repeater; both were missing there until prompt 51
   repaired the preview build). */
foreach ( array(
	'class-customizer',
	'class-chrome-builder',
	'class-repeater',
	'class-design',
	'class-ui',
	'class-ui-pages',
	'class-engagement',
	'class-shopping-extras',
	'class-dark-mode',
	'class-floating-dock',
	'class-nav-mega',
	'class-bespoke-demos',
	'class-branch-map',
	'class-wishlist',
	'class-theme-setup',
	'class-enqueue',
) as $class ) {
	$file = FLAVOR_DIR . '/inc/' . $class . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

/* template-tags.php brings the nav/branding helpers the header and footer
   call (flavor_primary_nav and friends). */
require_once FLAVOR_DIR . '/inc/template-tags.php';

/* ------------------------------------------------------------------ */
/*  Core surface the fixture site has no data for — render.php's stubs */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'attachment_url_to_postid' ) ) {
	function attachment_url_to_postid( string $url ): int { return 0; }
}
if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $id, $size = '', $icon = false, $attr = array() ): string { return ''; }
}
if ( ! function_exists( 'get_header' ) ) {
	function get_header( $name = '' ): void { include FLAVOR_DIR . '/header.php'; }
}
if ( ! function_exists( 'get_footer' ) ) {
	function get_footer( $name = '' ): void { include FLAVOR_DIR . '/footer.php'; }
}
if ( ! function_exists( 'language_attributes' ) ) {
	function language_attributes(): void { echo 'dir="rtl" lang="fa-IR"'; }
}
if ( ! function_exists( 'body_class' ) ) {
	function body_class( $class = '' ): void {
		$list = is_array( $class ) ? $class : ( $class ? preg_split( '/\s+/', trim( $class ) ) : array() );
		/* The theme registers its body_class callbacks on the WordPress
		   filter; call them directly so the skin classes land on <body>
		   (the whole skin layer is scoped to body.flavor-skin-*). */
		if ( class_exists( '\\Flavor\\Theme_Setup' ) ) {
			$list = \Flavor\Theme_Setup::body_class( $list );
		}
		if ( class_exists( '\\Flavor\\Bespoke_Demos' ) ) {
			$list = \Flavor\Bespoke_Demos::body_class( $list );
		}
		/* UI::body_class adds the flavor-ui layer on every inner page — the
		   whole ui.css sheet is scoped to it, so without this the fixture
		   would scan those pages with the UI styles inert (prompt 54: the
		   breadcrumb link fell out of its scoped rule and axe saw the raw
		   global link colour). Mirrored here rather than called, because the
		   real method also branches on is_page_template(), which this
		   offline mock does not define. */
		if ( class_exists( '\\Flavor\\UI' ) && \Flavor\UI::inner() ) {
			$list[] = 'flavor-ui';
			foreach ( \Flavor\UI::settings() as $ui_key => $ui_value ) {
				$list[] = 'flavor-ui-' . str_replace( '_', '-', (string) $ui_key ) . '-' . $ui_value;
			}
			if ( 'menu' === ( $GLOBALS['_a11y_page'] ?? '' ) ) {
				$list[] = 'flavor-ui-menu';
			}
		}
		echo 'class="' . implode( ' ', array_unique( array_filter( $list ) ) ) . '"';
	}
}
if ( ! function_exists( 'wp_body_open' ) ) {
	function wp_body_open(): void {}
}
if ( ! function_exists( 'wp_head' ) ) {
	/* Not a no-op here: the guard serves the real stylesheets from disk, so
	   the fixture links them exactly in the theme's enqueue order instead of
	   inlining. wp_footer() stays empty — no JS: the a11y scan reads the
	   shipped server-rendered markup, and the guard reveals the JS-opened
	   panels itself. */
	function wp_head(): void {
		echo flavor_a11y_head_assets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static <link>/<style> assembly below.
	}
}
if ( ! function_exists( 'wp_footer' ) ) {
	function wp_footer(): void {}
}
if ( ! function_exists( 'has_custom_logo' ) ) {
	function has_custom_logo(): bool { return false; }
}
if ( ! function_exists( 'get_custom_logo' ) ) {
	function get_custom_logo(): string { return ''; }
}
if ( ! function_exists( 'the_custom_logo' ) ) {
	function the_custom_logo(): void {}
}
if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( $text, $count = 55, $more = null ): string {
		$words = preg_split( '/\s+/u', (string) $text );
		return count( $words ) <= $count ? (string) $text : implode( ' ', array_slice( $words, 0, $count ) ) . '…';
	}
}
if ( ! function_exists( 'has_post_thumbnail' ) ) {
	function has_post_thumbnail( $post = null ): bool { return false; }
}
if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
	function get_the_post_thumbnail_url( $post = null, $size = '' ): string { return ''; }
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID(): int { return 1; }
}
if ( ! function_exists( 'have_posts' ) ) {
	function have_posts(): bool { return false; }
}
if ( ! function_exists( 'the_post' ) ) {
	function the_post(): void {}
}
if ( ! function_exists( 'get_the_content' ) ) {
	function get_the_content( $more = null ): string { return ''; }
}
if ( ! function_exists( 'bloginfo' ) ) {
	function bloginfo( $key = 'name' ): void { echo get_bloginfo( $key ); }
}
if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool { return 'home' === ( $GLOBALS['_a11y_page'] ?? '' ); }
}
if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ): bool {
		$current = (string) ( $GLOBALS['_a11y_page'] ?? '' );
		if ( '' === $current ) {
			return false;
		}
		if ( '' === $page ) {
			return true;
		}
		$slugs = array(
			'home'        => 'home',
			'menu'        => 'menu',
			'reservation' => 'reservation',
			'contact'     => 'contact',
			'about'       => 'about',
			'tracking'    => 'tracking',
		);
		foreach ( (array) $page as $candidate ) {
			if ( $candidate === $current || ( isset( $slugs[ $candidate ] ) && $slugs[ $candidate ] === $current ) ) {
				return true;
			}
		}
		return false;
	}
}
/* Conditional tags the UI layer probes. Offline there is no query: the
   fixture pages are never the blog index/archive/search, and is_page()/
   is_front_page() above answer per surface. */
if ( ! function_exists( 'is_home' ) ) {
	function is_home(): bool { return false; }
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ): bool { return false; }
}
if ( ! function_exists( 'is_archive' ) ) {
	function is_archive(): bool { return false; }
}
if ( ! function_exists( 'is_search' ) ) {
	function is_search(): bool { return false; }
}
if ( ! function_exists( 'is_404' ) ) {
	function is_404(): bool { return false; }
}
if ( ! function_exists( 'is_single' ) ) {
	function is_single( $post = '' ): bool { return false; }
}
/* WooCommerce shortcodes are not registered offline; the tracking template
   guards on shortcode_exists() and its native <details> stays empty — the
   shipped flavor form above it is what the scan reads. */
if ( ! function_exists( 'shortcode_exists' ) ) {
	function shortcode_exists( $tag ): bool { return false; }
}
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) { return $content; }
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ): string { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
}
if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $name ): string { return preg_replace( '/[^A-Za-z0-9_.-]/', '', (string) $name ); }
}
if ( ! function_exists( 'get_template_part' ) ) {
	function get_template_part( $slug, $name = '', $args = array() ): void {
		$file = FLAVOR_DIR . '/' . $slug . ( $name ? '-' . $name : '' ) . '.php';
		if ( is_readable( $file ) ) {
			include $file;
		}
	}
}

/**
 * The stylesheet <link> tags plus design tokens, in the theme's own enqueue
 * order (class-enqueue.php): fonts, main, marketing, rtl, skin, then the UI
 * layers. Served from /assets/** by the guard's static server — the real
 * files from flavor/assets, not copies.
 *
 * @return string Head HTML.
 */
function flavor_a11y_head_assets(): string {
	$css = array(
		'fonts.css',
		'main.css',
		'marketing.css',
		'rtl.css',
		'ui.css',
		'ui-menu.css',
		'ui-navigation.css',
		'ui-pages.css',
		'premium.css',
		'dark-mode.css',
		'search.css',
		'skins/modern-restaurant.css',
		'motion.css',
	);
	/* Production declares title-tag support (Theme_Setup) and WordPress
	   renders <title> inside wp_head; the fixture echoes the same shape so
	   the scan reads a document as complete as a live one. */
	$titles = array(
		'home'        => 'خانه',
		'menu'        => 'منوی غذا',
		'reservation' => 'رزرو میز',
		'contact'     => 'تماس',
		'about'       => 'درباره ما',
		'tracking'    => 'پیگیری سفارش',
	);
	$page_label = $titles[ (string) ( $GLOBALS['_a11y_page'] ?? '' ) ] ?? '';
	$title      = get_bloginfo( 'name' ) . ( '' !== $page_label ? ' — ' . $page_label : '' );

	$html = '<title>' . esc_html( $title ) . '</title>' . "\n";
	foreach ( $css as $file ) {
		$html .= '<link rel="stylesheet" href="/assets/css/' . $file . '" />' . "\n";
	}
	$html .= "<style id=\"flavor-tokens\">\n" . \Flavor\Design::css_variables() . "\n</style>\n";
	/* Live pages carry UI::variables() as an inline style on the ui sheet
	   handle; --ui-muted and --ui-action-ink are only defined there. */
	if ( class_exists( '\\Flavor\\UI' ) ) {
		$html .= "<style id=\"flavor-ui-vars\">\n" . \Flavor\UI::variables() . "\n</style>\n";
	}
	return $html;
}

/* ------------------------------------------------------------------ */
/*  Seed the fixture site                                              */
/* ------------------------------------------------------------------ */

$GLOBALS['_mock_theme_mods']['flavor_skin']                     = 'modern-restaurant';
$GLOBALS['_mock_theme_mods']['flavor_free_delivery_threshold']  = 250000;
$GLOBALS['_mock_theme_mods']['flavor_offer_deadline']           = date( 'Y-m-d H:i:s', time() + 5400 );
/* The contact e-mail gates the prompt-53 message form on the contact page. */
$GLOBALS['_mock_theme_mods']['flavor_ui_contact_email']         = 'hello@flavor.test';

/* The menu grid is server-rendered from wc_get_products — the same eight
   dishes the motion preview seeds. */
$dishes = array(
	array( 'چلو کباب سلطانی', 480000 ),
	array( 'خورش قیمه بادمجان', 320000 ),
	array( 'جوجه کباب زعفرانی', 395000 ),
	array( 'قیمه نثار کرمانی', 350000 ),
	array( 'ماهی قزل‌آلا شکم‌پر', 520000 ),
	array( 'کوفته تبریزی', 285000 ),
	array( 'آش رشته سنتی', 155000 ),
	array( 'ته‌چین مرغ و زعفران', 340000 ),
);
$GLOBALS['_mock_wc_products'] = array();
foreach ( $dishes as $index => $dish ) {
	$GLOBALS['_mock_wc_products'][ $index + 1 ] = new WC_Product( $index + 1, $dish[0], $dish[1] );
}

/* Branch 1 becomes a real dine-in branch with active tables, so the
   reservation form renders its live-capacity picker instead of the
   "no reservable branch" empty state (UI::reservation_available gates on
   _flavor_order_modes + TableRepository rows). */
update_post_meta( 1, '_flavor_order_modes', array( 'dine_in', 'takeaway', 'delivery' ) );
if ( class_exists( '\\FlavorCore\\Table\\TableRepository' ) ) {
	\FlavorCore\Table\TableRepository::create(
		array(
			'branch_id'    => 1,
			'table_number' => '7',
			'capacity'     => 4,
			'section'      => 'indoor',
			'is_active'    => 1,
		)
	);
	\FlavorCore\Table\TableRepository::create(
		array(
			'branch_id'    => 1,
			'table_number' => '12',
			'capacity'     => 6,
			'section'      => 'terrace',
			'is_active'    => 1,
		)
	);
}

/* ------------------------------------------------------------------ */
/*  Render                                                             */
/* ------------------------------------------------------------------ */

$outdir = $argv[1] ?? dirname( __DIR__, 2 ) . '/.cache/ui-qa/a11y';
if ( ! is_dir( $outdir ) && ! mkdir( $outdir, 0777, true ) ) {
	fwrite( STDERR, "cannot create {$outdir}\n" );
	exit( 1 );
}

$pages = array(
	'home'        => 'front-page.php',
	'menu'        => 'page-templates/template-menu.php',
	'reservation' => 'page-templates/template-reservation.php',
	'contact'     => 'page-templates/template-contact.php',
	'about'       => 'page-templates/template-about.php',
	'tracking'    => 'page-templates/template-order-tracking.php',
);

$failed = 0;
foreach ( $pages as $slug => $relative ) {
	$GLOBALS['_a11y_page'] = $slug;
	$path                  = FLAVOR_DIR . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		echo str_pad( $slug, 14 ) . " page:absent — {$relative}\n";
		++$failed;
		continue;
	}
	ob_start();
	try {
		include $path;
		$html = (string) ob_get_clean();
	} catch ( \Throwable $e ) {
		ob_end_clean();
		echo str_pad( $slug, 14 ) . ' page:error — ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
		++$failed;
		continue;
	}

	/*
	 * The mock advertises one absolute sample image for product cards; the
	 * guard's server cannot reach that host and a broken <img> measures as
	 * layout overflow — swap it for a demo image the guard actually serves
	 * (same correction elementor-pages.php makes).
	 */
	$html = str_replace(
		'https://restaurant.example.com/assets/images/sample-dish.jpg',
		'/themes/flavor/demos/modern-restaurant/category-burger.jpg',
		$html
	);

	if ( false === strpos( $html, '<!DOCTYPE' ) ) {
		echo str_pad( $slug, 14 ) . " page:error — the template did not render a full document\n";
		++$failed;
		continue;
	}

	file_put_contents( $outdir . '/page-' . $slug . '.html', $html );
	echo str_pad( $slug, 14 ) . ' page:ok ' . strlen( $html ) . " bytes\n";
}

exit( $failed ? 1 : 0 );
