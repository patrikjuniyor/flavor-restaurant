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
		return ! is_front_page() && ! is_home();
	}

	public static function settings(): array {
		$choices = array(
			'menu_layout' => array( 'grid', 'list' ),
			'density'     => array( 'comfortable', 'compact' ),
			'image_ratio'=> array( 'landscape', 'square' ),
			'card_style' => array( 'skin', 'soft', 'sharp' ),
			'mobile_nav' => array( 'contextual', 'minimal' ),
		);
		$result = array();
		foreach ( $choices as $key => $allowed ) {
			$value = get_theme_mod( 'flavor_ui_' . $key, $allowed[0] );
			$result[ $key ] = in_array( $value, $allowed, true ) ? $value : $allowed[0];
		}
		return $result;
	}

	public static function body_class( array $classes ): array {
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
		$muted = self::contrast( $t['muted'], $t['surface'] ) >= 4.5 && self::contrast( $t['muted'], $t['bg'] ) >= 4.5 ? $t['muted'] : $t['ink'];
		return '.flavor-ui{--ui-action-ink:' . $button_ink . ';--ui-muted:' . $muted . ';}';
	}

	/** Only published branches and validated active tables can be advertised. */
	public static function context(): array {
		$id = Enqueue::current_branch_id();
		$branch = $id ? get_post( $id ) : null;
		if ( ! $branch || 'publish' !== $branch->post_status || 'flavor_branch' !== $branch->post_type ) {
			return array( 'id' => 0, 'name' => '', 'modes' => array(), 'table' => '' );
		}
		$modes = get_post_meta( $id, '_flavor_order_modes', true );
		$modes = is_array( $modes ) ? array_values( array_intersect( $modes, array( 'dine_in', 'takeaway', 'delivery' ) ) ) : array( 'dine_in', 'takeaway', 'delivery' );
		$ctx = class_exists( \FlavorCore\Order\OrderModes::class ) ? \FlavorCore\Order\OrderModes::get() : array();
		$table_label = '';
		if ( ! empty( $ctx['table_id'] ) && class_exists( \FlavorCore\Table\TableRepository::class ) ) {
			$table = \FlavorCore\Table\TableRepository::find( (int) $ctx['table_id'] );
			if ( $table && ! empty( $table['is_active'] ) && (int) $table['branch_id'] === $id ) { $table_label = (string) $table['table_number']; }
		}
		return array( 'id' => $id, 'name' => $branch->post_title, 'modes' => $modes, 'table' => $table_label );
	}

	public static function mode_labels(): array {
		return array( 'dine_in' => __( 'سالن', 'flavor' ), 'takeaway' => __( 'بیرون‌بر', 'flavor' ), 'delivery' => __( 'ارسال', 'flavor' ) );
	}

	public static function url( string $page ): string {
		if ( 'account' === $page && function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
			if ( $url ) { return $url; }
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
		);
		$size = max( 12, min( 64, $size ) );
		echo '<svg aria-hidden="true" focusable="false" width="' . esc_attr( (string) $size ) . '" height="' . esc_attr( (string) $size ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">';
		echo $paths[ $name ] ?? $paths['info']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal SVG.
		echo '</svg>';
	}
}
