<?php
/**
 * Offline keyboard-navigation fixtures for tests/ui/keyboard.mjs (prompt 52).
 *
 * Same contract as a11y-pages.php: the six-stub offline WordPress mock from
 * tests/theme/bootstrap.php renders the REAL header.php, footer.php and page
 * templates with the REAL theme CSS. Two differences, both needed because a
 * keyboard suite must drive the shipped JS instead of revealing panels by
 * hand:
 *
 *   1. A primary menu is assigned. has_nav_menu()/wp_nav_menu() are defined
 *      BEFORE the bootstrap (every stub there is function_exists-guarded),
 *      and the walker stub renders the standard WordPress skeleton with the
 *      theme's own Nav_Mega::classes(), Nav_Mega::link_attributes() and
 *      Nav_Mega::description() deciding every class and attribute — the same
 *      three calls the live nav_menu_* filters make. Six children promote a
 *      branch to a mega panel; two children stay a plain dropdown; both get
 *      the branch semantics because link_attributes() gates on count > 0.
 *
 *   2. wp_footer() fires do_action( 'wp_footer' ) with Wishlist::init()
 *      registered, so the wishlist dialog panel renders through the real
 *      hook instead of a copied fragment, and the footer injection adds the
 *      real storefront scripts in the production dependency order
 *      (digits → main → navigation → ui → auth → menu → search → premium →
 *      motion) plus the real payloads: Enqueue::menu_strings(),
 *      UI::strings(), UI::dietary_labels(), UI::digits_config(),
 *      UI::settings() and Premium_Assets::payload(). A documented fetch
 *      stub answers the Flavor Core REST routes with the MenuController's
 *      own response shape so menu.js/search.js run their real code paths.
 *
 * Usage:
 *   php tests/ui/keyboard-pages.php [outdir]
 *
 * Writes <outdir>/page-menu.html and page-home.html. Any PHP diagnostic in
 * the build output fails the suite (same contract as a11y-pages.php).
 *
 * @package Flavor
 */

/* Relative theme URI, like a11y-pages.php: the suite's static server
   resolves /themes/flavor/**, so CSS and JS load as a customer's would. */
if ( ! defined( 'FLAVOR_URI' ) ) {
	define( 'FLAVOR_URI', '/themes/flavor' );
}

/* ------------------------------------------------------------------ */
/*  Menu stubs — defined before bootstrap so the guarded stubs there    */
/*  (has_nav_menu => false) never win.                                  */
/* ------------------------------------------------------------------ */

if ( ! function_exists( 'has_nav_menu' ) ) {
	/**
	 * The fixture site has a primary menu assigned.
	 *
	 * @param string $location Location.
	 */
	function has_nav_menu( string $location ): bool {
		return 'primary' === $location;
	}
}

if ( ! function_exists( 'is_page_template' ) ) {
	/**
	 * The menu surface really runs on its template — mobile-nav.php gates
	 * the in-page cart button (#flavor-mobile-cart-btn) on this, and a
	 * keyboard suite for the cart dialog needs the same entry point a
	 * phone shopper gets. Defined before bootstrap so its always-false
	 * stub never wins.
	 *
	 * @param string|array<string> $template Template path(s).
	 */
	function is_page_template( $template = '' ): bool {
		if ( 'menu' !== ( $GLOBALS['_kb_page'] ?? '' ) ) {
			return false;
		}
		if ( '' === $template ) {
			return true;
		}
		foreach ( (array) $template as $candidate ) {
			if ( 'page-templates/template-menu.php' === $candidate ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_nav_menu' ) ) {
	/**
	 * Walker stand-in: the standard WordPress skeleton (ul.flavor-nav >
	 * li.menu-item > a, ul.sub-menu one level down) with every theme-owned
	 * class and attribute produced by the real Nav_Mega filter callbacks —
	 * the same ones test-premium-features.php pins. Descriptions ride
	 * through Nav_Mega::description() so the markup matches production.
	 *
	 * @param array<string, mixed> $args Nav args.
	 */
	function wp_nav_menu( array $args = array() ): void {
		$menu_class = (string) ( $args['menu_class'] ?? 'menu' );
		$filter     = (object) array(
			'theme_location' => (string) ( $args['theme_location'] ?? 'primary' ),
			'menu'           => 7,
		);
		$items      = (array) ( $GLOBALS['_mock_menu_items'] ?? array() );

		$anchor = static function ( object $item, int $depth ) use ( $filter ): string {
			$atts = \Flavor\Nav_Mega::link_attributes( array(), $item, $filter, $depth );
			$html = '<a href="' . esc_url( (string) ( $item->url ?? '#' ) ) . '"';
			foreach ( $atts as $name => $value ) {
				$html .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
			}
			$html .= '>' . esc_html( (string) ( $item->title ?? '' ) ) . '</a>';
			return \Flavor\Nav_Mega::description( $html, $item, $depth, $filter );
		};

		echo '<ul class="' . esc_attr( $menu_class ) . '">';
		foreach ( $items as $item ) {
			if ( 0 !== (int) ( $item->menu_item_parent ?? 0 ) ) {
				continue;
			}
			$classes = \Flavor\Nav_Mega::classes( array( 'menu-item' ), $item, $filter, 0 );
			echo '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">';
			echo $anchor( $item, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_url/esc_attr/esc_html above.
			$children = array_values(
				array_filter(
					$items,
					static function ( $child ) use ( $item ): bool {
						return (int) ( $child->menu_item_parent ?? 0 ) === (int) $item->ID;
					}
				)
			);
			if ( $children ) {
				echo '<ul class="sub-menu">';
				foreach ( $children as $child ) {
					$child_classes = \Flavor\Nav_Mega::classes( array( 'menu-item' ), $child, $filter, 1 );
					echo '<li class="' . esc_attr( implode( ' ', $child_classes ) ) . '">';
					echo $anchor( $child, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same esc_* assembly.
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}

require_once __DIR__ . '/../theme/bootstrap.php';

/* Flavor Core, exactly like a11y-pages.php: the cart drawer, the checkout
   form and has_core() gates all assume the plugin a live shop runs. */
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

/* The a11y-pages.php class list plus the premium layer: premium.js carries
   the mega-menu keyboard behavior and Premium_Assets::payload() is the real
   flavorPremium source (mega.enabled, wishlist.enabled, premium strings). */
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
	'class-premium-assets',
) as $class ) {
	$file = FLAVOR_DIR . '/inc/' . $class . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

require_once FLAVOR_DIR . '/inc/template-tags.php';

/* ------------------------------------------------------------------ */
/*  The a11y-pages.php stub set (kept identical so both fixtures render */
/*  the same production markup); _kb_page drives the conditionals.      */
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
	/**
	 * Theme skin classes on <body>, like a11y-pages.php.
	 *
	 * @param string|array<string> $class Extra classes.
	 */
	function body_class( $class = '' ): void {
		$list = is_array( $class ) ? $class : ( $class ? preg_split( '/\s+/', trim( $class ) ) : array() );
		if ( class_exists( '\\Flavor\\Theme_Setup' ) ) {
			$list = \Flavor\Theme_Setup::body_class( $list );
		}
		if ( class_exists( '\\Flavor\\Bespoke_Demos' ) ) {
			$list = \Flavor\Bespoke_Demos::body_class( $list );
		}
		/* UI::body_class carries the .flavor-ui scope class — every sheet,
		   cart and checkout rule in ui-menu.css lives under it, and live
		   shops get it from the same filter. Without it the dialogs render
		   unstyled in-flow and "visible" assertions measure the wrong box. */
		if ( class_exists( '\\Flavor\\UI' ) ) {
			$list = \Flavor\UI::body_class( $list );
		}
		echo 'class="' . implode( ' ', array_unique( array_filter( $list ) ) ) . '"';
	}
}
if ( ! function_exists( 'wp_body_open' ) ) {
	function wp_body_open(): void {}
}
if ( ! function_exists( 'wp_head' ) ) {
	/**
	 * Real stylesheets in the theme's enqueue order, the design tokens and
	 * the flavor-js marker class. Production adds that class through
	 * wp_add_inline_script on flavor-digits (class-enqueue.php); the
	 * fixture echoes the identical one-liner so the CSS disclosure rules
	 * see the same <html> a live shop has.
	 */
	function wp_head(): void {
		echo flavor_kb_head_assets(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static <link>/<style>/<script> assembly below.
	}
}
if ( ! function_exists( 'wp_footer' ) ) {
	/**
	 * Real hook: Wishlist::init() below registers the panel on wp_footer,
	 * so the wishlist dialog a keyboard user can open from the drawer
	 * renders through production code, not a copied fragment.
	 */
	function wp_footer(): void {
		do_action( 'wp_footer' );
	}
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
	function is_front_page(): bool { return 'home' === ( $GLOBALS['_kb_page'] ?? '' ); }
}
if ( ! function_exists( 'is_page' ) ) {
	/**
	 * Per-surface page conditional, like a11y-pages.php.
	 *
	 * @param string|array<string> $page Slug(s).
	 */
	function is_page( $page = '' ): bool {
		$current = (string) ( $GLOBALS['_kb_page'] ?? '' );
		if ( '' === $current ) {
			return false;
		}
		if ( '' === $page ) {
			return true;
		}
		foreach ( (array) $page as $candidate ) {
			if ( $candidate === $current ) {
				return true;
			}
		}
		return false;
	}
}
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
/* Premium_Assets::payload() resolves its endpoints through these; the core
   mock does not carry them and offline they are fixture-relative paths. */
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string { return '/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string { return '/wp-json/' . ltrim( $path, '/' ); }
}

/**
 * Stylesheet links + tokens + the flavor-js marker, in enqueue order.
 *
 * @return string Head HTML.
 */
function flavor_kb_head_assets(): string {
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
	$titles = array(
		'home' => 'خانه',
		'menu' => 'منوی غذا',
	);
	$page_label = $titles[ (string) ( $GLOBALS['_kb_page'] ?? '' ) ] ?? '';
	$title      = get_bloginfo( 'name' ) . ( '' !== $page_label ? ' — ' . $page_label : '' );

	$html = '<title>' . esc_html( $title ) . '</title>' . "\n";
	foreach ( $css as $file ) {
		$html .= '<link rel="stylesheet" href="/assets/css/' . $file . '" />' . "\n";
	}
	$html .= "<style id=\"flavor-tokens\">\n" . \Flavor\Design::css_variables() . "\n</style>\n";
	/* Mirrors the wp_add_inline_script( 'flavor-digits', ..., 'before' )
	   production payload: disclosure CSS only hides nav panels once JS can
	   open them, so a no-JS shop keeps today's always-visible fallback. */
	$html .= "<script>document.documentElement.classList.add('flavor-js');</script>\n";
	return $html;
}

/* ------------------------------------------------------------------ */
/*  Seed the fixture site                                              */
/* ------------------------------------------------------------------ */

$GLOBALS['_mock_theme_mods']['flavor_skin']                    = 'modern-restaurant';
$GLOBALS['_mock_theme_mods']['flavor_free_delivery_threshold'] = 250000;

/* The primary menu: one mega branch (six children ≥ the threshold of five),
   one plain dropdown branch (two children — still branch semantics because
   Nav_Mega::link_attributes gates on count > 0), flat links around them,
   and one description that rides the real Nav_Mega::description(). */
$nav_item = static function ( int $id, int $parent, string $title, string $url, string $description = '' ): stdClass {
	return (object) array(
		'ID'               => $id,
		'menu_item_parent' => $parent,
		'title'            => $title,
		'url'              => $url,
		'classes'          => array(),
		'description'      => $description,
	);
};
$GLOBALS['_mock_menu_items'] = array(
	$nav_item( 21, 0, 'خانه', '/page-home.html' ),
	$nav_item( 22, 0, 'منوی غذا', '/page-menu.html' ),
	$nav_item( 23, 22, 'پیش‌غذا', '/page-menu.html#cat-7' ),
	$nav_item( 24, 22, 'غذاهای اصلی', '/page-menu.html#cat-5', 'همبرگر دست‌ساز با نان تازه' ),
	$nav_item( 25, 22, 'کباب‌ها', '/page-menu.html#cat-5' ),
	$nav_item( 26, 22, 'نوشیدنی‌ها', '/page-menu.html#cat-6' ),
	$nav_item( 27, 22, 'دسرها', '/page-menu.html#cat-6' ),
	$nav_item( 28, 22, 'ویژهٔ امروز', '/page-menu.html#cat-7' ),
	$nav_item( 31, 0, 'شعبه‌ها', '/page-home.html#branches' ),
	$nav_item( 32, 31, 'شعبهٔ شمال', '/page-home.html#branch-1' ),
	$nav_item( 33, 31, 'شعبهٔ مرکز', '/page-home.html#branch-2' ),
	$nav_item( 34, 0, 'رزرو میز', '/page-home.html#reservation' ),
	$nav_item( 35, 0, 'تماس', '/page-home.html#contact' ),
);

/* The eight dishes the a11y fixture seeds, so the server-rendered grid and
   the REST stub describe the same shop. */
$dishes = array(
	array( 'چلو کباب سلطانی', 480000, 35, 950, array( 'vegetarian' => false ) ),
	array( 'خورش قیمه بادمجان', 320000, 45, 620, array() ),
	array( 'جوجه کباب زعفرانی', 395000, 30, 710, array() ),
	array( 'قیمه نثار کرمانی', 350000, 40, 680, array() ),
	array( 'ماهی قزل‌آلا شکم‌پر', 520000, 50, 540, array() ),
	array( 'کوفته تبریزی', 285000, 40, 490, array() ),
	array( 'آش رشته سنتی', 155000, 25, 310, array( 'vegetarian' => true ) ),
	array( 'ته‌چین مرغ و زعفران', 340000, 55, 720, array() ),
);
$GLOBALS['_mock_wc_products'] = array();
foreach ( $dishes as $index => $dish ) {
	$GLOBALS['_mock_wc_products'][ $index + 1 ] = new WC_Product( $index + 1, $dish[0], $dish[1] );
}

/* The wishlist panel renders on wp_footer through its real hook. */
\Flavor\Wishlist::init();

/* ------------------------------------------------------------------ */
/*  The REST fixture data — MenuController's own response shape         */
/* ------------------------------------------------------------------ */

$categories = array(
	array( 'id' => 5, 'name' => 'کباب‌ها', 'slug' => 'kebab', 'count' => 3 ),
	array( 'id' => 6, 'name' => 'خورش‌ها', 'slug' => 'stew', 'count' => 2 ),
	array( 'id' => 7, 'name' => 'پیش‌غذا', 'slug' => 'starter', 'count' => 3 ),
);

$rest_dishes = array();
foreach ( $dishes as $index => $dish ) {
	$id    = 101 + $index;
	$price = (int) $dish[1];
	$cat   = $categories[ $index % 3 ];
	$rest_dishes[] = array(
		'id'             => $id,
		'name'           => $dish[0],
		'slug'           => 'dish-' . $id,
		'short_desc'     => $dish[0] . ' با برنج ایرانی و گوجه کبابی.',
		'price'          => $price,
		'price_html'     => class_exists( '\\FlavorCore\\WooCommerce\\Currency' ) ? \FlavorCore\WooCommerce\Currency::format( $price ) : number_format( $price ) . ' تومان',
		'regular_price'  => 0,
		'on_sale'        => false,
		'discount_pct'   => 0,
		'image'          => '',
		'prep_time'      => (int) $dish[2],
		'calories'       => (int) $dish[3],
		'dietary'        => array_keys( array_filter( $dish[4] ) ),
		'allergens'      => array(),
		'rating_avg'     => 4.6,
		'rating_count'   => 18,
		/* The last dish is unavailable: its add button ships disabled and
		   the keyboard suite proves Tab skips it. */
		'available'      => 8 !== ( $index + 1 ),
		'in_schedule'    => false,
		'available_at'   => '',
		'categories'     => array( array( 'id' => $cat['id'], 'name' => $cat['name'], 'slug' => $cat['slug'] ) ),
		'has_modifiers'  => 1 === ( $index + 1 ),
		'permalink'      => '/page-menu.html#item-' . $id,
	);
}

/* Detail extra for the first dish only: one required single-choice group,
   so the sheet renders a real fieldset with radios between the close
   button and the quantity row. */
$detail_extra = array(
	'101' => array(
		'modifier_groups' => array(
			array(
				'type'     => 'cook',
				'title'    => '',
				'multi'    => false,
				'required' => true,
				'options'  => array(
					array( 'id' => 9001, 'name' => 'کم‌پخت', 'price' => 0, 'is_default' => true ),
					array( 'id' => 9002, 'name' => 'زغالی', 'price' => 0, 'is_default' => false ),
				),
			),
		),
	),
);

$search_results = array();
foreach ( array_slice( $rest_dishes, 0, 3 ) as $dish ) {
	$search_results[] = array(
		'id'         => $dish['id'],
		'name'       => $dish['name'],
		'short'      => $dish['short_desc'],
		'image'      => '',
		'permalink'  => $dish['permalink'],
		'price_html' => $dish['price_html'],
		'prep_time'  => $dish['prep_time'],
		'calories'   => $dish['calories'],
		'available'  => $dish['available'],
	);
}

/* ------------------------------------------------------------------ */
/*  The payloads — real builders, no hand-copied strings                */
/* ------------------------------------------------------------------ */

$payload = array(
	'rest'    => '/wp-json/flavor/v1/',
	'nonce'   => wp_create_nonce( 'wp_rest' ),
	'menuUrl' => '/page-menu.html',
	'hasCore' => \Flavor\Theme_Setup::has_core(),
	'i18n'    => array(
		'add'      => __( 'افزودن', 'flavor' ),
		'loading'  => __( 'در حال بارگذاری منو…', 'flavor' ),
		'empty'    => __( 'آیتمی پیدا نشد.', 'flavor' ),
		'offline'  => __( 'افزونه Flavor Core فعال نیست. منو از ووکامرس خوانده نمی‌شود.', 'flavor' ),
		'menu'     => \Flavor\Enqueue::menu_strings(),
		'ui'       => \Flavor\UI::strings(),
		'catering' => \Flavor\Enqueue::catering_strings(),
		'bespoke'  => \Flavor\Bespoke_Demos::strings(),
	),
);
if ( class_exists( '\\FlavorCore\\WooCommerce\\Currency' ) ) {
	$payload['currency'] = array(
		'storage' => \FlavorCore\WooCommerce\Currency::storage_unit(),
		'display' => \FlavorCore\WooCommerce\Currency::display_unit(),
		'label'   => \FlavorCore\WooCommerce\Currency::display_label(),
	);
}
$payload['dietaryLabels'] = \Flavor\UI::dietary_labels();
$payload['digits']        = \Flavor\UI::digits_config();
$payload['ajax']          = '/wp-admin/admin-ajax.php';
/* Offline there is no branch context: branchId 0 is what a live shop with
   no QR/table context sends, and defaultMode takeaway matches it. */
$payload['branchId']   = 0;
$payload['ui']         = \Flavor\UI::settings();
$payload['accountUrl'] = '/page-home.html#account';
$payload['defaultMode'] = 'takeaway';

/* The search i18n merge, key-for-key the literals class-enqueue.php adds —
   same source strings, so the catalogues do not move. */
$search_payload         = $payload;
$search_payload['i18n'] = array_merge(
	$payload['i18n'],
	array(
		'recent'       => __( 'جست‌وجوهای اخیر', 'flavor' ),
		'popular'      => __( 'پرجست‌وجوها', 'flavor' ),
		'noResults'    => __( 'چیزی پیدا نشد.', 'flavor' ),
		'didYouMean'   => __( 'منظورتان این بود؟', 'flavor' ),
		'resultsFound' => __( 'نتیجه برای', 'flavor' ),
		'unavailable'  => __( 'ناموجود', 'flavor' ),
		'minutes'      => __( 'دقیقه', 'flavor' ),
		'kcal'         => __( 'کالری', 'flavor' ),
		'error'        => __( 'جست‌وجو ناموفق بود. دوباره تلاش کنید.', 'flavor' ),
	)
);

$premium_payload = \Flavor\Premium_Assets::payload();

/* ------------------------------------------------------------------ */
/*  Footer injection: fetch stub + payloads + the real scripts          */
/* ------------------------------------------------------------------ */

$fetch_stub = <<<'JS'
(function () {
	'use strict';
	var DISHES = __DISHES__;
	var DETAIL_EXTRA = __DETAIL_EXTRA__;
	var CATS = __CATS__;
	var SEARCH = __SEARCH__;
	var cart = { items: [], seq: 0, token: 'fixture-cart-token' };
	function money(n) { return new Intl.NumberFormat('fa-IR').format(n) + ' تومان'; }
	function recalc() {
		cart.count = cart.items.reduce(function (s, i) { return s + i.quantity; }, 0);
		cart.subtotal = cart.items.reduce(function (s, i) { return s + i.unit * i.quantity; }, 0);
	}
	function payload() {
		recalc();
		return {
			count: cart.count,
			items: cart.items.map(function (i) {
				return {
					key: i.key, product_id: i.product_id, name: i.name, quantity: i.quantity,
					unit_price_html: money(i.unit), line_total_html: money(i.unit * i.quantity),
					modifiers: [], instructions: i.instructions, image: ''
				};
			}),
			subtotal: cart.subtotal, subtotal_html: money(cart.subtotal),
			total: cart.subtotal, total_html: money(cart.subtotal),
			cart_token: cart.token, supports_item_edit: true
		};
	}
	function json(body, status) {
		return new Response(JSON.stringify(body), { status: status || 200, headers: { 'Content-Type': 'application/json' } });
	}
	function ok(data) { return Promise.resolve(json({ success: true, data: data })); }
	window.fetch = function (input, init) {
		var url = String((input && input.url) || input);
		var method = (((init && init.method) || 'GET') + '').toUpperCase();
		var body = {};
		try { body = init && init.body ? JSON.parse(init.body) : {}; } catch (e) { body = {}; }
		var path = url.indexOf('/wp-json/flavor/v1/') >= 0 ? url.split('/wp-json/flavor/v1/')[1] : url;
		path = (path || '').split('?')[0].replace(/\/$/, '');
		if (path === 'menu') { return ok(DISHES); }
		if (path === 'categories') { return ok(CATS); }
		if (path === 'search') { return ok({ results: SEARCH }); }
		if (path === 'context') { return ok({}); }
		if (path === 'me') { return ok({ logged_in: false }); }
		if (path === 'cart') { return ok(payload()); }
		if (path === 'cart/items' && method === 'POST') {
			var dish = null;
			DISHES.forEach(function (d) { if (d.id === Number(body.product_id)) { dish = d; } });
			cart.seq += 1;
			cart.items.push({
				key: 'fk' + cart.seq, product_id: Number(body.product_id),
				name: dish ? dish.name : String(body.product_id), unit: dish ? Number(dish.price) : 0,
				quantity: Math.max(1, Number(body.quantity) || 1), instructions: body.instructions || ''
			});
			return ok(payload());
		}
		var m = /^cart\/items\/(.+)$/.exec(path);
		if (m && method === 'PUT') {
			cart.items.forEach(function (i) {
				if (i.key === decodeURIComponent(m[1])) {
					i.quantity = Math.max(1, Number(body.quantity) || i.quantity);
					if (body.instructions != null) { i.instructions = String(body.instructions); }
				}
			});
			return ok(payload());
		}
		if (m && method === 'DELETE') {
			cart.items = cart.items.filter(function (i) { return i.key !== decodeURIComponent(m[1]); });
			return ok(payload());
		}
		var d = /^dishes\/(\d+)$/.exec(path);
		if (d) {
			var base = null;
			DISHES.forEach(function (x) { if (x.id === Number(d[1])) { base = x; } });
			if (!base) { return Promise.resolve(json({ success: false, code: 'not_found', message: 'fixture has no dish ' + d[1] }, 404)); }
			var detail = Object.assign({}, base, DETAIL_EXTRA[d[1]] || {}, {
				description: base.short_desc, gallery: [], reviews: []
			});
			return ok(detail);
		}
		return Promise.resolve(json({ success: false, code: 'fixture_no_stub', message: 'keyboard fixture has no stub for ' + path }, 404));
	};
})();
JS;

$fetch_stub = str_replace(
	array( '__DISHES__', '__DETAIL_EXTRA__', '__CATS__', '__SEARCH__' ),
	array(
		wp_json_encode( $rest_dishes, JSON_UNESCAPED_UNICODE ),
		wp_json_encode( $detail_extra, JSON_UNESCAPED_UNICODE ),
		wp_json_encode( $categories, JSON_UNESCAPED_UNICODE ),
		wp_json_encode( $search_results, JSON_UNESCAPED_UNICODE ),
	),
	$fetch_stub
);

/* Production order (dependency-resolved defer): digits → main → navigation
   → ui → auth → menu → search → premium → motion. */
$script_files = array( 'digits.js', 'main.js', 'navigation.js', 'ui.js', 'auth.js', 'menu.js', 'search.js', 'premium.js', 'motion.js' );
$script_tags  = '';
foreach ( $script_files as $file ) {
	$script_tags .= '<script src="/themes/flavor/assets/js/' . $file . '" defer></script>' . "\n";
}

$footer_scripts = "\n<script>window.flavorData = " . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ";</script>\n"
	. "<script>window.flavorSearchData = " . wp_json_encode( $search_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ";</script>\n"
	. "<script>window.flavorPremium = " . wp_json_encode( $premium_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ";</script>\n"
	. '<script>' . $fetch_stub . '</script>' . "\n"
	. $script_tags;

/* ------------------------------------------------------------------ */
/*  Render                                                             */
/* ------------------------------------------------------------------ */

$outdir = $argv[1] ?? dirname( __DIR__, 2 ) . '/.cache/ui-qa/keyboard';
if ( ! is_dir( $outdir ) && ! mkdir( $outdir, 0777, true ) ) {
	fwrite( STDERR, "cannot create {$outdir}\n" );
	exit( 1 );
}

$pages = array(
	'menu' => 'page-templates/template-menu.php',
	'home' => 'front-page.php',
);

$failed = 0;
foreach ( $pages as $slug => $relative ) {
	$GLOBALS['_kb_page'] = $slug;
	$path                = FLAVOR_DIR . '/' . $relative;
	if ( ! is_readable( $path ) ) {
		echo str_pad( $slug, 8 ) . " page:absent — {$relative}\n";
		++$failed;
		continue;
	}
	ob_start();
	try {
		include $path;
		$html = (string) ob_get_clean();
	} catch ( \Throwable $e ) {
		ob_end_clean();
		echo str_pad( $slug, 8 ) . ' page:error — ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n";
		++$failed;
		continue;
	}

	/* The mock advertises one absolute sample image for product cards; the
	   suite's server cannot reach that host (same correction a11y-pages.php
	   and elementor-pages.php make). */
	$html = str_replace(
		'https://restaurant.example.com/assets/images/sample-dish.jpg',
		'/themes/flavor/demos/modern-restaurant/category-burger.jpg',
		$html
	);

	if ( false === strpos( $html, '<!DOCTYPE' ) ) {
		echo str_pad( $slug, 8 ) . " page:error — the template did not render a full document\n";
		++$failed;
		continue;
	}
	if ( false === strpos( $html, '</body>' ) ) {
		echo str_pad( $slug, 8 ) . " page:error — no </body> to inject the scripts into\n";
		++$failed;
		continue;
	}

	$html = str_replace( '</body>', $footer_scripts . '</body>', $html );

	file_put_contents( $outdir . '/page-' . $slug . '.html', $html );
	echo str_pad( $slug, 8 ) . ' page:ok ' . strlen( $html ) . " bytes\n";
}

exit( $failed ? 1 : 0 );
