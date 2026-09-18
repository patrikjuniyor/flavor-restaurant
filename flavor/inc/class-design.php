<?php
/**
 * Design tokens and presets for Flavor Theme.
 *
 * Provides a unified design system supporting 11 commercial restaurant presets,
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
				'desc'  => __( 'نارنجی سرعتی با تمرکز بالا بر سفارش آنلاین و ارسال', 'flavor' ),
			),
			'catering'          => array(
				'title' => __( 'کترینگ و تشریفات سازمانی', 'flavor' ),
				'desc'  => __( 'سرمه‌ای رسمی و نقره‌ای برای رویدادها و مجالس', 'flavor' ),
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
				'primary'      => '#8a5a2b',
				'secondary'    => '#5c6b3a',
				'accent'       => '#c47d39',
				'bg'           => '#f8f4ee',
				'surface'      => '#ffffff',
				'surface_alt'  => '#f1ebe1',
				'ink'          => '#241b14',
				'muted'        => '#6e5f53',
				'line'         => '#e5dcce',
				'card_shadow'  => '0 8px 24px rgba(43,33,24,0.06)',
				'radius'       => '16px',
				'btn_radius'   => '12px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'luxury-dining'     => array(
				'primary'      => '#c5a880',
				'secondary'    => '#8c7355',
				'accent'       => '#e2caa5',
				'bg'           => '#121212',
				'surface'      => '#1a1a1a',
				'surface_alt'  => '#242424',
				'ink'          => '#f7f4ef',
				'muted'        => '#b5a995',
				'line'         => '#2e2a24',
				'card_shadow'  => '0 12px 32px rgba(0,0,0,0.6)',
				'radius'       => '12px',
				'btn_radius'   => '8px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'persian-traditional' => array(
				'primary'      => '#881337',
				'secondary'    => '#b45309',
				'accent'       => '#d97706',
				'bg'           => '#faf5ee',
				'surface'      => '#ffffff',
				'surface_alt'  => '#f3e8d6',
				'ink'          => '#261410',
				'muted'        => '#6b544b',
				'line'         => '#e6d5bd',
				'card_shadow'  => '0 8px 20px rgba(136,19,55,0.06)',
				'radius'       => '16px',
				'btn_radius'   => '12px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'cafe-bistro'       => array(
				'primary'      => '#6f4e37',
				'secondary'    => '#4a5d4e',
				'accent'       => '#a07855',
				'bg'           => '#f9f6f0',
				'surface'      => '#ffffff',
				'surface_alt'  => '#efe8dc',
				'ink'          => '#2b2118',
				'muted'        => '#6b5b4f',
				'line'         => '#e6dcd0',
				'card_shadow'  => '0 6px 20px rgba(111,78,55,0.06)',
				'radius'       => '16px',
				'btn_radius'   => '24px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'fast-food'         => array(
				'primary'      => '#dc2626',
				'secondary'    => '#f59e0b',
				'accent'       => '#ea580c',
				'bg'           => '#fffbf0',
				'surface'      => '#ffffff',
				'surface_alt'  => '#fef3c7',
				'ink'          => '#1c1917',
				'muted'        => '#57534e',
				'line'         => '#fde68a',
				'card_shadow'  => '0 10px 24px rgba(220,38,38,0.08)',
				'radius'       => '20px',
				'btn_radius'   => '9999px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'pizza-italian'     => array(
				'primary'      => '#b91c1c',
				'secondary'    => '#15803d',
				'accent'       => '#d97706',
				'bg'           => '#fffdfa',
				'surface'      => '#ffffff',
				'surface_alt'  => '#fef2f2',
				'ink'          => '#1f2937',
				'muted'        => '#6b7280',
				'line'         => '#fee2e2',
				'card_shadow'  => '0 8px 24px rgba(185,28,28,0.07)',
				'radius'       => '16px',
				'btn_radius'   => '12px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'bakery-pastry'     => array(
				'primary'      => '#db2777',
				'secondary'    => '#0d9488',
				'accent'       => '#f472b6',
				'bg'           => '#fdf4f8',
				'surface'      => '#ffffff',
				'surface_alt'  => '#fce7f3',
				'ink'          => '#371b2b',
				'muted'        => '#836577',
				'line'         => '#fbcfe8',
				'card_shadow'  => '0 8px 24px rgba(219,39,119,0.06)',
				'radius'       => '20px',
				'btn_radius'   => '16px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'juice-bar'         => array(
				'primary'      => '#16a34a',
				'secondary'    => '#ea580c',
				'accent'       => '#65a30d',
				'bg'           => '#f0fdf4',
				'surface'      => '#ffffff',
				'surface_alt'  => '#dcfce7',
				'ink'          => '#14281d',
				'muted'        => '#476352',
				'line'         => '#bbf7d0',
				'card_shadow'  => '0 8px 24px rgba(22,163,74,0.08)',
				'radius'       => '24px',
				'btn_radius'   => '9999px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'dark-luxe'         => array(
				'primary'      => '#f59e0b',
				'secondary'    => '#d97706',
				'accent'       => '#fbbf24',
				'bg'           => '#0a0a0a',
				'surface'      => '#141414',
				'surface_alt'  => '#1f1f1f',
				'ink'          => '#fafafa',
				'muted'        => '#a3a3a3',
				'line'         => '#262626',
				'card_shadow'  => '0 12px 36px rgba(0,0,0,0.8)',
				'radius'       => '16px',
				'btn_radius'   => '12px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'minimal-clean'     => array(
				'primary'      => '#18181b',
				'secondary'    => '#52525b',
				'accent'       => '#27272a',
				'bg'           => '#ffffff',
				'surface'      => '#fafafa',
				'surface_alt'  => '#f4f4f5',
				'ink'          => '#09090b',
				'muted'        => '#71717a',
				'line'         => '#e4e4e7',
				'card_shadow'  => '0 4px 16px rgba(0,0,0,0.04)',
				'radius'       => '8px',
				'btn_radius'   => '6px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'cloud-kitchen'     => array(
				'primary'      => '#ff6b35',
				'secondary'    => '#1f2937',
				'accent'       => '#f97316',
				'bg'           => '#f4f4f6',
				'surface'      => '#ffffff',
				'surface_alt'  => '#ebebee',
				'ink'          => '#111827',
				'muted'        => '#4b5563',
				'line'         => '#e5e7eb',
				'card_shadow'  => '0 8px 24px rgba(255,107,53,0.08)',
				'radius'       => '16px',
				'btn_radius'   => '12px',
				'font_heading' => 'Vazirmatn',
				'font_body'    => 'Vazirmatn',
			),
			'catering'          => array(
				'primary'      => '#1e3a8a',
				'secondary'    => '#0284c7',
				'accent'       => '#3b82f6',
				'bg'           => '#f8fafc',
				'surface'      => '#ffffff',
				'surface_alt'  => '#f1f5f9',
				'ink'          => '#0f172a',
				'muted'        => '#475569',
				'line'         => '#e2e8f0',
				'card_shadow'  => '0 8px 24px rgba(30,58,138,0.06)',
				'radius'       => '12px',
				'btn_radius'   => '8px',
				'font_heading' => 'Vazirmatn',
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
			if ( is_string( $mod ) && '' !== trim( $mod ) ) {
				$base[ $key ] = trim( $mod );
			}
		}

		// Backward compatibility for legacy customizer keys.
		$legacy_accent = get_theme_mod( 'flavor_accent', '' );
		if ( $legacy_accent && ! get_theme_mod( 'flavor_primary', '' ) ) {
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
			--flavor-font-heading: '{$t['font_heading']}', 'Vazirmatn', Tahoma, sans-serif;
			--flavor-font-body: '{$t['font_body']}', 'Vazirmatn', Tahoma, sans-serif;

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

		return $css;
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
