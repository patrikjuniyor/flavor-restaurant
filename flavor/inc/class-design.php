<?php
/**
 * Design tokens and presets for Flavor Theme.
 *
 * Provides a unified design system supporting 12 commercial restaurant presets,
 * dynamic theme customization, CSS custom properties, and typography scales.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Design
 */
class Design {

	/**
	 * Available restaurant presets with localized titles and descriptions.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function skins(): array {
		return array(
			'modern-restaurant' => array(
				'title' => __( 'رستوران مدرن', 'flavor' ),
				'desc'  => __( 'استایل شیک و مینیمال با رنگ‌های گرم قهوه‌ای و بژ', 'flavor' ),
			),
			'luxury-dining'     => array(
				'title' => __( 'رستوران لوکس و مجلل', 'flavor' ),
				'desc'  => __( 'پس‌زمینه تیره اشرافی با المان‌های طلایی و شامپاینی', 'flavor' ),
			),
			'persian-traditional' => array(
				'title' => __( 'رستوران سنتی و اصیل ایرانی', 'flavor' ),
				'desc'  => __( 'طرح اصیل زرشکی، طلایی و لاجوردی با حس نوستالژیک', 'flavor' ),
			),
			'cafe-bistro'       => array(
				'title' => __( 'کافه و بیسترو', 'flavor' ),
				'desc'  => __( 'فضای آرام کافه‌ای با تونالیته چوب، زیتونی و کرم', 'flavor' ),
			),
			'fast-food'         => array(
				'title' => __( 'فست‌فود و برگر بار', 'flavor' ),
				'desc'  => __( 'رنگ‌های شاد، جذاب و پرانرژی قرمز، خردلی و دودی', 'flavor' ),
			),
			'pizza-italian'     => array(
				'title' => __( 'پیتزا و رستوران ایتالیایی', 'flavor' ),
				'desc'  => __( 'ترکیب اشتهاآور قرمز گوجه‌ای، سبز ریحان و سفید', 'flavor' ),
			),
			'bakery-pastry'     => array(
				'title' => __( 'قنادی و نانوایی فرانسوی', 'flavor' ),
				'desc'  => __( 'رنگ‌های ملایم پاستلی، صورتی ملیح و سبز نعنایی', 'flavor' ),
			),
			'juice-bar'         => array(
				'title' => __( 'آبمیوه، اسموتی و بار سلامت', 'flavor' ),
				'desc'  => __( 'طیف تازه و ارگانیک سبز، نارنجی مرکباتی و لیمویی', 'flavor' ),
			),
			'dark-luxe'         => array(
				'title' => __( 'رستوران دارک پریمیوم', 'flavor' ),
				'desc'  => __( 'طراحی مدرن تیره با کنتراست فوق‌العاده بالا و نورپردازی نرم', 'flavor' ),
			),
			'minimal-clean'     => array(
				'title' => __( 'مینیمال و معاصر', 'flavor' ),
				'desc'  => __( 'سفید خالص، خطوط باریک با تمرکز ۱۰۰٪ روی تصاویر غذا', 'flavor' ),
			),
			'cloud-kitchen'     => array(
				'title' => __( 'آشپزخانه ابری و دلیوری', 'flavor' ),
				'desc'  => __( 'کبالتی و لیمویی با تمرکز بر سفارش آنلاین و بررسی محدودهٔ ارسال', 'flavor' ),
			),
			'catering'          => array(
				'title' => __( 'کترینگ و تشریفات سازمانی', 'flavor' ),
				'desc'  => __( 'سرمه‌ای، عاجی و برنجی با مسیر اختصاصی برنامه‌ریزی پذیرایی سازمانی', 'flavor' ),
			),
		);
	}

	/**
	 * Default design tokens per preset.
	 *
	 * @param string $skin Preset slug.
	 * @return array<string, string>
	 */
	public static function tokens( string $skin ): array {
		$map = array(
			'modern-restaurant' => array(
				'primary'      => '#a74e2c',
				'secondary'    => '#26352b',
				'accent'       => '#d6a64f',
				'bg'           => '#f4efe7',
				'surface'      => '#fffaf4',
				'surface_alt'  => '#ece4d9',
				'ink'          => '#201c19',
				'muted'        => '#6b625b',
				'line'         => '#ded4c7',
				'card_shadow'  => '0 18px 45px rgba(42,31,22,0.07)',
				'radius'       => '24px',
				'btn_radius'   => '14px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'luxury-dining'     => array(
				'primary'      => '#cbb186',
				'secondary'    => '#8f7756',
				'accent'       => '#ead8b8',
				'bg'           => '#090909',
				'surface'      => '#121212',
				'surface_alt'  => '#191816',
				'ink'          => '#f4efe6',
				'muted'        => '#aaa092',
				'line'         => '#302b24',
				'card_shadow'  => '0 22px 55px rgba(0,0,0,0.42)',
				'radius'       => '2px',
				'btn_radius'   => '2px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'persian-traditional' => array(
				'primary'      => '#691a2c',
				'secondary'    => '#0d5c63',
				'accent'       => '#c8963e',
				'bg'           => '#f8f0df',
				'surface'      => '#fffaf0',
				'surface_alt'  => '#efe1c6',
				'ink'          => '#281a16',
				'muted'        => '#705c51',
				'line'         => '#dfcda8',
				'card_shadow'  => '0 18px 45px rgba(63,35,23,0.08)',
				'radius'       => '3px',
				'btn_radius'   => '3px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'cafe-bistro'       => array(
				'primary'      => '#b7643f',
				'secondary'    => '#536047',
				'accent'       => '#d3a878',
				'bg'           => '#f3ecdf',
				'surface'      => '#fffaf2',
				'surface_alt'  => '#e8e5da',
				'ink'          => '#2d2119',
				'muted'        => '#75675c',
				'line'         => '#ded2c2',
				'card_shadow'  => '0 18px 45px rgba(61,46,34,0.075)',
				'radius'       => '28px',
				'btn_radius'   => '9999px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'fast-food'         => array(
				'primary'      => '#e2361f',
				'secondary'    => '#ffc928',
				'accent'       => '#ff7a1a',
				'bg'           => '#fff6dd',
				'surface'      => '#ffffff',
				'surface_alt'  => '#fff0bd',
				'ink'          => '#171512',
				'muted'        => '#675e52',
				'line'         => '#e1d4b8',
				'card_shadow'  => '8px 9px 0 #171512',
				'radius'       => '24px',
				'btn_radius'   => '9999px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'pizza-italian'     => array(
				'primary'      => '#b83b2e',
				'secondary'    => '#24523c',
				'accent'       => '#d9af6c',
				'bg'           => '#f7efdf',
				'surface'      => '#fffaf1',
				'surface_alt'  => '#eee5d5',
				'ink'          => '#292019',
				'muted'        => '#76675b',
				'line'         => '#dfd0b8',
				'card_shadow'  => '0 18px 45px rgba(61,40,26,0.08)',
				'radius'       => '25px',
				'btn_radius'   => '9999px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'bakery-pastry'     => array(
				'primary'      => '#a83e5b',
				'secondary'    => '#718265',
				'accent'       => '#d9a8b2',
				'bg'           => '#fbf5e9',
				'surface'      => '#fffdf8',
				'surface_alt'  => '#f2e8e2',
				'ink'          => '#36252a',
				'muted'        => '#7b676d',
				'line'         => '#e1d2c7',
				'card_shadow'  => '0 18px 45px rgba(77,52,59,0.075)',
				'radius'       => '28px',
				'btn_radius'   => '9999px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'juice-bar'         => array(
				'primary'      => '#275d3d',
				'secondary'    => '#244f38',
				'accent'       => '#d99a32',
				'bg'           => '#fafbf3',
				'surface'      => '#ffffff',
				'surface_alt'  => '#edf1e4',
				'ink'          => '#203c2b',
				'muted'        => '#596852',
				'line'         => '#dfe4d5',
				'card_shadow'  => '0 12px 28px rgba(39,93,61,0.05)',
				'radius'       => '26px',
				'btn_radius'   => '9999px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'dark-luxe'         => array(
				'primary'      => '#d1a586',
				'secondary'    => '#1d232b',
				'accent'       => '#a87c5a',
				'bg'           => '#0e1116',
				'surface'      => '#171c23',
				'surface_alt'  => '#13171d',
				'ink'          => '#f4eee7',
				'muted'        => '#b0a9a0',
				'line'         => '#30353b',
				'card_shadow'  => 'none',
				'radius'       => '4px',
				'btn_radius'   => '2px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'minimal-clean'     => array(
				'primary'      => '#263e32',
				'secondary'    => '#e9ede8',
				'accent'       => '#8a988a',
				'bg'           => '#fcfcf9',
				'surface'      => '#ffffff',
				'surface_alt'  => '#eff1eb',
				'ink'          => '#242725',
				'muted'        => '#637063',
				'line'         => '#dde3db',
				'card_shadow'  => 'none',
				'radius'       => '4px',
				'btn_radius'   => '4px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'cloud-kitchen'     => array(
				'primary'      => '#2554df',
				'secondary'    => '#173154',
				'accent'       => '#d7f468',
				'bg'           => '#f5f7fc',
				'surface'      => '#ffffff',
				'surface_alt'  => '#edf2fb',
				'ink'          => '#172a43',
				'muted'        => '#596b82',
				'line'         => '#dce4f1',
				'card_shadow'  => '0 5px 18px rgba(32,69,140,.04)',
				'radius'       => '22px',
				'btn_radius'   => '12px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
			'catering'          => array(
				'primary'      => '#253e55',
				'secondary'    => '#172c40',
				'accent'       => '#a67945',
				'bg'           => '#f9f6f0',
				'surface'      => '#fffdf8',
				'surface_alt'  => '#eee9df',
				'ink'          => '#1b2b39',
				'muted'        => '#59636b',
				'line'         => '#dcd7ce',
				'card_shadow'  => 'none',
				'radius'       => '2px',
				'btn_radius'   => '2px',
				'font_heading' => 'Estedad',
				'font_body'    => 'Vazirmatn',
			),
		);

		// Alias legacy keys for backward compatibility.
		$legacy_aliases = array(
			'modern-cafe' => 'cafe-bistro',
			'traditional' => 'persian-traditional',
			'fine-dining' => 'luxury-dining',
			'pastry'      => 'bakery-pastry',
		);

		if ( isset( $legacy_aliases[ $skin ] ) ) {
			$skin = $legacy_aliases[ $skin ];
		}

		return $map[ $skin ] ?? $map['modern-restaurant'];
	}

	/**
	 * Active skin slug.
	 */
	public static function current_skin(): string {
		$skin = (string) get_theme_mod( 'flavor_skin', 'modern-restaurant' );
		$skins = self::skins();

		// Handle legacy aliases.
		if ( 'modern-cafe' === $skin ) {
			return 'cafe-bistro';
		}
		if ( 'traditional' === $skin ) {
			return 'persian-traditional';
		}
		if ( 'fine-dining' === $skin ) {
			return 'luxury-dining';
		}
		if ( 'pastry' === $skin ) {
			return 'bakery-pastry';
		}

		return array_key_exists( $skin, $skins ) ? $skin : 'modern-restaurant';
	}

	/**
	 * Resolved tokens merging preset defaults with customizer user overrides.
	 *
	 * @return array<string, string>
	 */
	public static function resolved(): array {
		$base = self::tokens( self::current_skin() );

		$keys = array(
			'primary',
			'secondary',
			'accent',
			'bg',
			'surface',
			'surface_alt',
			'ink',
			'muted',
			'line',
			'radius',
			'btn_radius',
			'font_heading',
			'font_body',
		);

		foreach ( $keys as $key ) {
			$mod = get_theme_mod( 'flavor_' . $key, '' );
			if ( ! is_string( $mod ) || '' === trim( $mod ) ) { continue; }
			$mod = trim( $mod );
			// Defense in depth for direct theme-mod writes and legacy imports too.
			if ( in_array( $key, array( 'radius', 'btn_radius' ), true ) ) {
				$valid = (bool) preg_match( '/^\d{1,4}(?:\.\d{1,2})?(?:px|rem)$/D', $mod );
			} elseif ( in_array( $key, array( 'font_heading', 'font_body' ), true ) ) {
				$valid = (bool) preg_match( '/^[\p{L}\p{N} _-]{1,64}$/uD', $mod );
			} else {
				$valid = (bool) preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $mod );
			}
			if ( $valid ) { $base[ $key ] = $mod; }
		}

		// Backward compatibility for legacy customizer keys.
		$legacy_accent = get_theme_mod( 'flavor_accent', '' );
		if ( is_string( $legacy_accent ) && preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $legacy_accent ) && ! get_theme_mod( 'flavor_primary', '' ) ) {
			$base['primary'] = $legacy_accent;
		}

		return $base;
	}

	/**
	 * Build CSS Custom Properties string for injection into <head>.
	 *
	 * @return string
	 */
	public static function css_variables(): string {
		$t = self::resolved();

		$container_width = sanitize_text_field( (string) get_theme_mod( 'flavor_container_width', '1240px' ) );
		if ( ! preg_match( '/^\d+(px|rem|vw|%)$/', $container_width ) ) {
			$container_width = '1240px';
		}

		// The logo is printed at its intrinsic size by core, so these tokens
		// are the only thing keeping a large upload inside the header row.
		$logo_height        = Theme_Setup::logo_height();
		$logo_height_mobile = max( 24, (int) round( $logo_height * 0.72 ) );
		$logo_max_width     = min( 420, max( 120, $logo_height * 4 ) );
		$gutter_mobile      = self::responsive_mod( 'flavor_gutter_mobile', array( '24px', '32px', '40px' ), '32px' );
		$gutter_tablet      = self::responsive_mod( 'flavor_gutter_tablet', array( '32px', '40px', '48px' ), '' );
		$gutter_desktop     = self::responsive_mod( 'flavor_gutter_desktop', array( '32px', '48px', '64px', '80px' ), '48px' );
		$body_size          = self::responsive_mod( 'flavor_body_size', array( '14px', '15px', '16px', '17px', '18px' ), '15px' );
		$heading_mobile     = self::responsive_mod( 'flavor_heading_size_mobile', array( 'clamp(1.7rem, 7vw, 2.5rem)', 'clamp(1.9rem, 8vw, 3rem)', 'clamp(2.1rem, 9vw, 3.4rem)' ), 'clamp(1.9rem, 8vw, 3rem)' );
		$heading_tablet     = self::responsive_mod( 'flavor_heading_size_tablet', array( 'clamp(2rem, 6vw, 3.6rem)', 'clamp(2.2rem, 7vw, 4rem)', 'clamp(2.4rem, 8vw, 4.6rem)' ), '' );
		$heading_desktop    = self::responsive_mod( 'flavor_heading_size_desktop', array( 'clamp(2rem, 3vw, 3.6rem)', 'clamp(2.2rem, 4vw, 4.5rem)', 'clamp(2.6rem, 5vw, 5.4rem)' ), 'clamp(2.2rem, 4vw, 4.5rem)' );
		$space_mobile       = self::responsive_mod( 'flavor_section_space_mobile', array( '40px', '56px', '72px' ), '56px' );
		$space_tablet       = self::responsive_mod( 'flavor_section_space_tablet', array( '64px', '80px', '96px' ), '' );
		$space_desktop      = self::responsive_mod( 'flavor_section_space_desktop', array( '64px', '88px', '112px', '136px' ), '88px' );

		$primary_rgb   = self::hex2rgb( $t['primary'] );
		$secondary_rgb = self::hex2rgb( $t['secondary'] );
		$accent_rgb    = self::hex2rgb( $t['accent'] );
		$ink_rgb       = self::hex2rgb( $t['ink'] );
		$surface_rgb   = self::hex2rgb( $t['surface'] );

		$css = ":root {
			--flavor-primary: {$t['primary']};
			--flavor-primary-rgb: {$primary_rgb};
			--flavor-secondary: {$t['secondary']};
			--flavor-secondary-rgb: {$secondary_rgb};
			--flavor-accent: {$t['accent']};
			--flavor-accent-rgb: {$accent_rgb};
			--flavor-bg: {$t['bg']};
			--flavor-surface: {$t['surface']};
			--flavor-surface-rgb: {$surface_rgb};
			--flavor-surface-alt: {$t['surface_alt']};
			--flavor-ink: {$t['ink']};
			--flavor-ink-rgb: {$ink_rgb};
			--flavor-muted: {$t['muted']};
			--flavor-line: {$t['line']};
			--flavor-card-shadow: {$t['card_shadow']};
			--flavor-radius: {$t['radius']};
			--flavor-btn-radius: {$t['btn_radius']};
			--flavor-container-max: {$container_width};
			--flavor-container-gutter: {$gutter_mobile};
			--flavor-container-gutter-tablet: {$gutter_tablet};
			--flavor-container-gutter-desktop: {$gutter_desktop};
			--flavor-body-size: {$body_size};
			--flavor-heading-size: {$heading_mobile};
			--flavor-heading-size-tablet: {$heading_tablet};
			--flavor-heading-size-desktop: {$heading_desktop};
			--flavor-section-space: {$space_mobile};
			--flavor-section-space-tablet: {$space_tablet};
			--flavor-section-space-desktop: {$space_desktop};
			--flavor-font-heading: '{$t['font_heading']}', 'Vazirmatn', Tahoma, sans-serif;
			--flavor-font-body: '{$t['font_body']}', 'Vazirmatn', Tahoma, sans-serif;

			/* Site logo box */
			--flavor-logo-height: {$logo_height}px;
			--flavor-logo-height-mobile: {$logo_height_mobile}px;
			--flavor-logo-max-width: {$logo_max_width}px;

			/* System feedback tokens */
			--flavor-success: #16a34a;
			--flavor-success-bg: #dcfce7;
			--flavor-warning: #d97706;
			--flavor-warning-bg: #fef3c7;
			--flavor-danger: #dc2626;
			--flavor-danger-bg: #fee2e2;
			--flavor-info: #0284c7;
			--flavor-info-bg: #e0f2fe;

			/* Spacing 4px grid scale */
			--flavor-space-1: 4px;
			--flavor-space-2: 8px;
			--flavor-space-3: 12px;
			--flavor-space-4: 16px;
			--flavor-space-5: 20px;
			--flavor-space-6: 24px;
			--flavor-space-8: 32px;
			--flavor-space-10: 40px;
			--flavor-space-12: 48px;
			--flavor-space-16: 64px;
			--flavor-space-20: 80px;
			--flavor-space-24: 96px;

			/* Elevation Shadows */
			--flavor-shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
			--flavor-shadow-md: 0 8px 20px rgba(0, 0, 0, 0.06);
			--flavor-shadow-lg: 0 16px 36px rgba(0, 0, 0, 0.09);
			--flavor-shadow-xl: 0 24px 48px rgba(0, 0, 0, 0.14);
			--flavor-shadow-drawer: -12px 0 40px rgba(0, 0, 0, 0.22);
			--flavor-shadow-modal: 0 20px 60px rgba(0, 0, 0, 0.3);

			/* Transitions */
			--flavor-ease: cubic-bezier(0.16, 1, 0.3, 1);
			--flavor-transition-fast: 150ms var(--flavor-ease);
			--flavor-transition-normal: 250ms var(--flavor-ease);
			--flavor-transition-slow: 400ms var(--flavor-ease);
		}";

		$tablet_css  = '';
		$tablet_css .= '' !== $gutter_tablet ? '--flavor-container-gutter: var(--flavor-container-gutter-tablet);' : '';
		$tablet_css .= '' !== $heading_tablet ? '--flavor-heading-size: var(--flavor-heading-size-tablet);' : '';
		$tablet_css .= '' !== $space_tablet ? '--flavor-section-space: var(--flavor-section-space-tablet);' : '';

		$css .= "\nbody.flavor-theme { font-size: var(--flavor-body-size); }\nbody.flavor-theme h1, body.flavor-theme .flavor-hero h1, body.flavor-theme .fd-hero h1 { font-size: var(--flavor-heading-size); }\nbody.flavor-theme .flavor-container { width: min(var(--flavor-container-max), calc(100% - (2 * var(--flavor-container-gutter)))); }\nbody.flavor-theme .flavor-section, body.flavor-theme .fd-section { padding-block: var(--flavor-section-space); }\n";
		if ( '' !== $tablet_css ) {
			$css .= '@media (min-width: 768px) { :root { ' . $tablet_css . ' } }' . "\n";
		}
		$css .= "@media (min-width: 1024px) { :root { --flavor-container-gutter: var(--flavor-container-gutter-desktop); --flavor-heading-size: var(--flavor-heading-size-desktop); --flavor-section-space: var(--flavor-section-space-desktop); } }\n";

		return $css;
	}

	/**
	 * Resolve a finite responsive Customizer token.
	 *
	 * @param string          $key     Theme mod key.
	 * @param array<int,string> $allowed Allowed values.
	 * @param string          $default Fallback.
	 * @return string
	 */
	private static function responsive_mod( string $key, array $allowed, string $default ): string {
		$value = get_theme_mod( $key, $default );
		return is_string( $value ) && in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * Convert HEX to RGB string (e.g. #8a5a2b -> 138, 90, 43).
	 *
	 * @param string $hex Hex code.
	 * @return string
	 */
	public static function hex2rgb( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
			$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
			$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
		} elseif ( 6 === strlen( $hex ) ) {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		} else {
			return '0, 0, 0';
		}
		return "{$r}, {$g}, {$b}";
	}
}
