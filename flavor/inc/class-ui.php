<?php
/**
 * Shared storefront presentation. Business rules stay in WooCommerce/Core.
 * The landing pages keep their art direction; inner pages share UI primitives.
 *
 * @package Flavor
 */
namespace Flavor;
defined( 'ABSPATH' ) || exit;

class UI {
	public static function init(): void {
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ), 30 );
	}

	public static function inner(): bool {
		if ( function_exists( 'is_account_page' ) && ( is_account_page() || is_checkout() || is_cart() ) ) { return true; }
		return ! is_front_page() && ! is_home();
	}

	/** One allowlist supplies front-end defaults, controls, validation and previews. */
	public static function schema(): array {
		return array(
			'menu_layout' => array( 'default' => 'grid', 'label' => __( 'نمای پیش‌فرض منو', 'flavor' ), 'description' => __( 'کارتی یا فهرستی؛ مشتری همچنان می‌تواند نمای همین صفحه را عوض کند.', 'flavor' ), 'choices' => array( 'grid' => __( 'کارتی', 'flavor' ), 'list' => __( 'فهرستی', 'flavor' ) ) ),
			'density' => array( 'default' => 'comfortable', 'label' => __( 'فاصله‌گذاری کارت غذا', 'flavor' ), 'description' => __( 'فاصلهٔ محتوا در هر دو نمای منو؛ اندازهٔ دکمه‌ها و اهداف لمس کوچک نمی‌شود.', 'flavor' ), 'choices' => array( 'comfortable' => __( 'راحت و باز', 'flavor' ), 'compact' => __( 'جمع‌وجور', 'flavor' ) ) ),
			'image_ratio' => array( 'default' => 'landscape', 'label' => __( 'قاب عکس در منوی کارتی', 'flavor' ), 'description' => __( 'افقی ۳ به ۲ یا مربع؛ تصویر اصلی و فایل‌های دمو تغییر نمی‌کنند. در فهرست، عکس با ارتفاع ردیف هماهنگ است.', 'flavor' ), 'choices' => array( 'landscape' => __( 'افقی — ۳ به ۲', 'flavor' ), 'square' => __( 'مربع — ۱ به ۱', 'flavor' ) ) ),
			'card_style' => array( 'default' => 'skin', 'label' => __( 'گوشهٔ کارت و پنل غذا', 'flavor' ), 'description' => __( 'پیش‌فرض از پوسته می‌آید؛ این گزینه چیدمان مستقل صفحهٔ اصلی دمو را عوض نمی‌کند.', 'flavor' ), 'choices' => array( 'skin' => __( 'پیش‌فرض پوسته', 'flavor' ), 'soft' => __( 'نرم و گرد', 'flavor' ), 'sharp' => __( 'زاویه‌دار', 'flavor' ) ) ),
			'mobile_nav' => array( 'default' => 'contextual', 'label' => __( 'نوار دسترسی موبایل', 'flavor' ), 'description' => __( 'کامل با خانه، یا کوتاه بدون خانه؛ حساب/تماس/رزرو همچنان از امکانات واقعی صفحه انتخاب می‌شوند.', 'flavor' ), 'choices' => array( 'contextual' => __( 'کامل و متناسب با صفحه', 'flavor' ), 'minimal' => __( 'کوتاه و سه‌گزینه‌ای', 'flavor' ) ) ),
		);
	}

	public static function settings(): array {
		$result = array();
		foreach ( self::schema() as $key => $definition ) {
			$value = get_theme_mod( 'flavor_ui_' . $key, $definition['default'] );
			$result[ $key ] = is_string( $value ) && array_key_exists( $value, $definition['choices'] ) ? $value : $definition['default'];
		}
		return $result;
	}

	public static function body_class( array $classes ): array {
		if ( ! in_array( get_theme_mod( 'flavor_header_sticky', 'yes' ), array( 'yes', 'on', '1', 1, true ), true ) ) { $classes[] = 'flavor-header-not-sticky'; }
		if ( self::inner() ) {
			$classes[] = 'flavor-ui';
			foreach ( self::settings() as $key => $value ) {
				$classes[] = 'flavor-ui-' . str_replace( '_', '-', $key ) . '-' . $value;
			}
			if ( is_page_template( 'page-templates/template-menu.php' ) ) {
				$classes[] = 'flavor-ui-menu';
				if ( ! empty( self::context()['table'] ) ) { $classes[] = 'flavor-ui-qr'; }
			}
		}
		return $classes;
	}

	public static function assets(): void {
		wp_enqueue_style( 'flavor-ui-navigation', FLAVOR_URI . '/assets/css/ui-navigation.css', array( 'flavor-main' ), (string) filemtime( FLAVOR_DIR . '/assets/css/ui-navigation.css' ) );
		wp_add_inline_style( 'flavor-ui-navigation', self::variables() );
		wp_enqueue_script( 'flavor-ui-navigation-js', FLAVOR_URI . '/assets/js/navigation.js', array( 'flavor-main-js' ), (string) filemtime( FLAVOR_DIR . '/assets/js/navigation.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( ! self::inner() ) { return; }
		$deps = array( 'flavor-main', 'flavor-marketing' );
		$skin = 'flavor-skin-' . sanitize_html_class( Design::current_skin() );
		if ( wp_style_is( $skin, 'enqueued' ) ) { $deps[] = $skin; }
		if ( wp_style_is( 'flavor-search', 'enqueued' ) ) { $deps[] = 'flavor-search'; }
		foreach ( array( 'ui', 'ui-menu', 'ui-checkout' ) as $file ) {
			wp_enqueue_style( 'flavor-' . $file, FLAVOR_URI . '/assets/css/' . $file . '.css', $deps, (string) filemtime( FLAVOR_DIR . '/assets/css/' . $file . '.css' ) );
			$deps = array( 'flavor-' . $file );
		}
		wp_add_inline_style( 'flavor-ui', self::variables() );
		wp_enqueue_script( 'flavor-ui-js', FLAVOR_URI . '/assets/js/ui.js', array( 'flavor-main-js' ), (string) filemtime( FLAVOR_DIR . '/assets/js/ui.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	/** Relative luminance/contrast, used for readable controls in all 12 skins. */
	public static function luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) { return 0; }
		$channels = array();
		for ( $i = 0; $i < 6; $i += 2 ) {
			$v = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$channels[] = $v <= .04045 ? $v / 12.92 : pow( ( $v + .055 ) / 1.055, 2.4 );
		}
		return .2126 * $channels[0] + .7152 * $channels[1] + .0722 * $channels[2];
	}
	public static function contrast( string $a, string $b ): float {
		$a = self::luminance( $a ); $b = self::luminance( $b );
		return ( max( $a, $b ) + .05 ) / ( min( $a, $b ) + .05 );
	}
	public static function variables(): string {
		$t = Design::resolved();
		$button_ink = self::contrast( $t['primary'], '#ffffff' ) >= self::contrast( $t['primary'], '#000000' ) ? '#ffffff' : '#000000';
		$muted = self::contrast( $t['muted'], $t['surface'] ) >= 4.5 && self::contrast( $t['muted'], $t['bg'] ) >= 4.5 && self::contrast( $t['muted'], $t['surface_alt'] ) >= 4.5 ? $t['muted'] : $t['ink'];
		return 'body.flavor-theme{--ui-action-ink:' . $button_ink . ';--ui-muted:' . $muted . ';}';
	}

	/** Only published branches and validated active tables can be advertised. */
	public static function context(): array {
		$id = Enqueue::current_branch_id();
		$branch = $id ? get_post( $id ) : null;
		if ( ! $branch || 'publish' !== $branch->post_status || 'flavor_branch' !== $branch->post_type ) {
			return array( 'id' => 0, 'name' => '', 'modes' => array(), 'table' => '', 'table_id' => 0 );
		}
		$modes = get_post_meta( $id, '_flavor_order_modes', true );
		$modes = is_array( $modes ) ? array_values( array_intersect( $modes, array( 'dine_in', 'takeaway', 'delivery' ) ) ) : array( 'dine_in', 'takeaway', 'delivery' );
		$ctx = class_exists( \FlavorCore\Order\OrderModes::class ) ? \FlavorCore\Order\OrderModes::get() : array();
		$table_label = ''; $table_id = 0;
		if ( ! empty( $ctx['table_id'] ) && class_exists( \FlavorCore\Table\TableRepository::class ) ) {
			$table = \FlavorCore\Table\TableRepository::find( (int) $ctx['table_id'] );
			if ( $table && ! empty( $table['is_active'] ) && (int) $table['branch_id'] === $id ) { $table_label = (string) $table['table_number']; $table_id = (int) $table['id']; }
		}
		if ( ! $table_id && ! empty( $ctx['table_token'] ) && is_string( $ctx['table_token'] ) && class_exists( \FlavorCore\Table\TableRepository::class ) ) {
			$table = \FlavorCore\Table\TableRepository::find_by_token( $ctx['table_token'] );
			if ( $table && ! empty( $table['is_active'] ) && (int) $table['branch_id'] === $id ) { $table_label = (string) $table['table_number']; $table_id = (int) $table['id']; }
		}
		if ( ! in_array( 'dine_in', $modes, true ) ) { $table_label = ''; $table_id = 0; }
		return array( 'id' => $id, 'name' => $branch->post_title, 'modes' => $modes, 'table' => $table_label, 'table_id' => $table_id );
	}

	public static function reservation_available( int $branch_id = 0 ): bool {
		$context = self::context(); $id = $branch_id ?: $context['id'];
		$modes = $id ? get_post_meta( $id, '_flavor_order_modes', true ) : array();
		if ( ! $id || ! is_array( $modes ) || ! in_array( 'dine_in', $modes, true ) || ! class_exists( \FlavorCore\Table\TableRepository::class ) ) { return false; }
		foreach ( \FlavorCore\Table\TableRepository::for_branch( $id ) as $table ) { if ( ! empty( $table['is_active'] ) ) { return true; } }
		return false;
	}
	public static function account_available(): bool {
		return function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 && 'publish' === get_post_status( wc_get_page_id( 'myaccount' ) );
	}

	public static function mode_labels(): array {
		return array( 'dine_in' => __( 'سالن', 'flavor' ), 'takeaway' => __( 'بیرون‌بر', 'flavor' ), 'delivery' => __( 'ارسال', 'flavor' ) );
	}

	public static function url( string $page ): string {
		if ( 'account' === $page && function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
			if ( self::account_available() && $url ) { return $url; }
			return Bespoke_Demos::page_url( 'contact' );
		}
		return Bespoke_Demos::page_url( $page );
	}

	public static function icon( string $name, int $size = 20 ): void {
		$paths = array(
			'bag' => '<path d="M5 7h14l1 14H4L5 7Z"/><path d="M9 8V5a3 3 0 0 1 6 0v3"/>',
			'pin' => '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
			'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
			'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
			'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
			'plus' => '<path d="M12 5v14M5 12h14"/>',
			'check' => '<path d="m5 12 4 4L19 6"/>',
			'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
			'home' => '<path d="m3 10 9-7 9 7v11h-6v-7H9v7H3V10Z"/>',
			'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
			'leaf' => '<path d="M20 4C9 2 3 7 5 14c2 6 12 7 15-10Z"/><path d="M4 21 15 10"/>',
			'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 2v6m10-6v6M3 11h18"/>',
			'phone' => '<path d="m7 3 3 5-2 2a15 15 0 0 0 6 6l2-2 5 3v3c-9 2-20-9-18-18l4 1Z"/>',
			'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
			'list' => '<path d="M9 5h12M9 12h12M9 19h12M3 5h1M3 12h1M3 19h1"/>',
			'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/>',
			'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.5 1.5m11.2 11.2 1.5 1.5M2 12h2m16 0h2M4.9 19.1l1.5-1.5M17.6 6.4l1.5-1.5"/>',
			'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
			'heart' => '<path d="M12 20s-7-4.4-7-9.4A4.1 4.1 0 0 1 12 7.6a4.1 4.1 0 0 1 7 2.9c0 5-7 9.5-7 9.5Z"/>',
			'arrow_up' => '<path d="M12 19V5m-7 7 7-7 7 7"/>',
			'whatsapp' => '<path d="M20.5 11.6a8.4 8.4 0 0 1-12.4 7.4L3.5 20.5l1.5-4.6a8.4 8.4 0 1 1 15.5-4.3Z"/><path d="M9 8.4c.4 0 .6.1.8.6l.7 1.5c.1.3 0 .5-.1.7l-.5.5c.9 1.6 1.5 2.2 3.1 3.1l.5-.5c.2-.2.4-.2.7-.1l1.6.8c.4.2.5.4.5.7 0 .9-.8 1.6-1.8 1.6-3.3 0-7.4-4.1-7.4-7.3 0-1 .8-1.6 1.9-1.6Z"/>',
			'shield' => '<path d="M12 3l7 3v6c0 4.4-2.9 7.6-7 9-4.1-1.4-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/>',
			'truck' => '<path d="M3 7h11v9H3zM14 10h3.6l3.4 3.4V16h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
			'tag' => '<path d="M20 12.5 12.5 20 4 11.5V4h7.5L20 12.5Z"/><circle cx="8" cy="8" r="1.3"/>',
			'star' => '<path d="m12 4 2.4 5 5.6.8-4 3.9 1 5.6-5-2.7-5 2.7 1-5.6-4-3.9 5.6-.8L12 4Z"/>',
			'sort' => '<path d="M7 5v14m0 0-3-3m3 3 3-3M17 19V5m0 0-3 3m3-3 3 3"/>',
			'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
			'send' => '<path d="M21 3 10.5 13.5M21 3l-7 18-3.5-7.5L3 10l18-7Z"/>',
			'trash' => '<path d="M4 7h16M9 7V5h6v2m-8 0 1 13h8l1-13"/>',
			'lock' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		);
		$size = max( 12, min( 64, $size ) );
		echo '<svg aria-hidden="true" focusable="false" width="' . esc_attr( (string) $size ) . '" height="' . esc_attr( (string) $size ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">';
		echo $paths[ $name ] ?? $paths['info']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG.
		echo '</svg>';
	}
}
