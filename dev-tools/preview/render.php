<?php
/**
 * Motion preview renderer.
 *
 * Renders the real demo pages through the project's own offline WordPress
 * mock, so the preview is assembled from shipped template markup rather than
 * a hand-written approximation that would drift from it.
 *
 * The mock only models the paginated shape of wc_get_products(); the demo
 * templates call it unpaginated, exactly as WooCommerce documents. Rather
 * than edit the repo's test fixture, build-preview.sh copies the repo to
 * /tmp and applies that single correction there. Nothing in the repository
 * is modified.
 *
 * Usage: php render.php <skin> [out.json]
 *
 * Environment:
 *   FLAVOR_PREVIEW_WORK   Repo copy the harness boots against (build-preview.sh makes it)
 *   FLAVOR_PREVIEW_PAGES  Where the per-skin JSON is written (default /tmp)
 */

$skin = $argv[1] ?? 'modern-restaurant';
$out  = $argv[2] ?? ( ( getenv( 'FLAVOR_PREVIEW_PAGES' ) ?: '/tmp' ) . '/page-' . $skin . '.json' );

$root = getenv( 'FLAVOR_PREVIEW_WORK' ) ?: '/tmp/flavor-preview';
require_once $root . '/tests/theme/bootstrap.php';

/* ------------------------------------------------------------------ */
/*  Theme classes                                                      */
/* ------------------------------------------------------------------ */

foreach ( array(
	'class-customizer',
	'class-design',
	'class-ui',
	'class-ui-pages',
	'class-engagement',
	'class-shopping-extras',
	'class-dark-mode',
	'class-floating-dock',
	'class-nav-mega',
	'class-bespoke-demos',
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
   call. It reaches for media functions this fixture has no attachments for,
   so the two stubs above have to exist before it loads. */
require_once FLAVOR_DIR . '/inc/template-tags.php';

/* ------------------------------------------------------------------ */
/*  Core surface the fixture site has no data for                      */
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
		/* The mock's add_filter() is a no-op, so the theme's own body_class
		   callback (registered on the WordPress `body_class` filter) has to be
		   called directly or every body renders classless — and the whole
		   skin layer is scoped to body.flavor-skin-*. */
		if ( class_exists( '\\Flavor\\Theme_Setup' ) ) {
			$list = \Flavor\Theme_Setup::body_class( $list );
		}
		/* The bespoke packs gate their whole animation layer on
		   body.flavor-bespoke, which this hook adds. */
		if ( class_exists( '\\Flavor\\Bespoke_Demos' ) ) {
			$list = \Flavor\Bespoke_Demos::body_class( $list );
		}
		echo 'class="' . implode( ' ', array_unique( array_filter( $list ) ) ) . '"';
	}
}
if ( ! function_exists( 'wp_body_open' ) ) {
	function wp_body_open(): void {}
}
if ( ! function_exists( 'wp_head' ) ) {
	function wp_head(): void {}
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
	function has_post_thumbnail(): bool { return false; }
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
if ( ! function_exists( 'wpautop' ) ) {
	function wpautop( $text, $br = true ): string {
		$text = trim( (string) $text );
		return '' === $text ? '' : '<p>' . preg_replace( '/\n\s*\n/', "</p>\n<p>", $text ) . '</p>';
	}
}
if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool { return true; }
}
if ( ! function_exists( 'is_page' ) ) {
	function is_page( $page = '' ): bool { return false; }
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

/* ------------------------------------------------------------------ */
/*  Seed the fixture site                                              */
/* ------------------------------------------------------------------ */

$GLOBALS['_mock_theme_mods']['flavor_skin'] = $skin;
$GLOBALS['_mock_theme_mods']['flavor_free_delivery_threshold'] = 250000;
$GLOBALS['_mock_theme_mods']['flavor_offer_deadline'] = date( 'Y-m-d H:i:s', time() + 5400 );

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

/* ------------------------------------------------------------------ */
/*  Render                                                             */
/* ------------------------------------------------------------------ */

/**
 * Render a file, returning either its markup or the failure that stopped it.
 *
 * @param string $relative Path relative to the theme root.
 */
function flavor_preview_render( string $relative ): array {
	$path = FLAVOR_DIR . '/' . $relative;

	if ( ! is_readable( $path ) ) {
		return array( 'status' => 'absent' );
	}

	ob_start();
	try {
		include $path;
	} catch ( \Throwable $e ) {
		ob_end_clean();
		return array(
			'status'  => 'error',
			'message' => get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine(),
		);
	}

	return array( 'status' => 'ok', 'bytes' => (int) ob_get_length(), 'html' => ob_get_clean() );
}

$result = array(
	'skin'  => $skin,
	'parts' => array(),
);

/* The whole landing page, header to footer, exactly as front-page.php builds
   it: the seven classic demos get the modular marketing sections, the five
   bespoke packs get their own composition, and the theme picks between them. */
$result['parts']['page'] = flavor_preview_render( 'front-page.php' );

/* Every template part on its own, so a single failure cannot hide the rest. */
$result['parts']['hero']         = flavor_preview_render( 'template-parts/marketing/hero.php' );
$result['parts']['featured']     = flavor_preview_render( 'template-parts/marketing/featured.php' );
$result['parts']['menu_grid']    = flavor_preview_render( 'template-parts/demos/menu.php' );
$result['parts']['filters']      = flavor_preview_render( 'template-parts/menu/filters.php' );
$result['parts']['item_card']    = flavor_preview_render( 'template-parts/menu/item-card.php' );
$result['parts']['item_modal']   = flavor_preview_render( 'template-parts/menu/item-modal.php' );
$result['parts']['cart_drawer']  = flavor_preview_render( 'template-parts/menu/cart-drawer.php' );
$result['parts']['smart_search'] = flavor_preview_render( 'template-parts/menu/smart-search.php' );

/**
 * Render a callback into a string, swallowing failures: the preview is worth
 * more with one missing surface than with none.
 *
 * @param callable $fn Callback.
 */
function flavor_preview_capture( callable $fn ): string {
	ob_start();
	try {
		$fn();
	} catch ( \Throwable $e ) {
		ob_end_clean();
		return '<!-- ' . htmlspecialchars( $e->getMessage() ) . ' -->';
	}
	return ob_get_clean();
}

$result['extras'] = array(
	'night_css'     => \Flavor\Dark_Mode::css( $skin ),
	'dock'          => flavor_preview_capture( array( '\\Flavor\\Floating_Dock', 'render' ) ),
	/* free_delivery_standalone() only paints on the menu template; the bar
	   itself is what this preview needs, so render it with a subtotal. */
	'free_delivery' => flavor_preview_capture( function () { \Flavor\Shopping_Extras::free_delivery_markup( 180000, true, true ); } ),
	'cart_drawer'   => isset( $result['parts']['cart_drawer'] ) ? ( $result['parts']['cart_drawer']['html'] ?? '' ) : '',
	'countdown'     => flavor_preview_capture( function () { \Flavor\Engagement::countdown( 'offer', time() + 5400, 'پیشنهاد امشب' ); } ),
	'wishlist'      => flavor_preview_capture( array( '\\Flavor\\Wishlist', 'header_button' ) ),
	'cart_lines'    => flavor_preview_capture( function () {
		for ( $i = 1; $i <= 3; $i++ ) {
			echo '<li class="flavor-cart-line"><span>غذای نمونه ' . (int) $i . '</span><strong>۲۹۵٬۰۰۰ تومان</strong></li>';
		}
	} ),
);

/* Skin catalogue for the switcher, straight from the theme. */
$result['skins'] = array();
foreach ( \Flavor\Design::skins() as $slug => $meta ) {
	$result['skins'][ $slug ] = array(
		'title' => $meta['title'],
		'desc'  => $meta['desc'],
		'kind'  => in_array( $slug, array( 'juice-bar', 'dark-luxe', 'minimal-clean', 'cloud-kitchen', 'catering' ), true ) ? 'bespoke' : 'classic',
	);
}

file_put_contents( $out, json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

printf( "%-22s page:%s%s\n", $skin, $result['parts']['page']['status'], isset( $result['parts']['page']['message'] ) ? ' — ' . $result['parts']['page']['message'] : '' );
