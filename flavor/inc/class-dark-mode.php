<?php
/**
 * Day/night colour scheme with a palette derived from the active skin.
 *
 * Commercial restaurant themes sell "dark + light mode" as a headline feature.
 * Flavor does not ship one grey dark theme: the night palette is computed from
 * the active skin, so all twelve demos keep their own colour identity, and the
 * text contrast is enforced against WCAG AA instead of being eyeballed.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dark_Mode
 */
class Dark_Mode {

	/**
	 * localStorage key, shared with the pre-paint boot script.
	 */
	const STORAGE_KEY = 'flavorScheme';

	/**
	 * Allowed owner modes.
	 */
	const MODES = array( 'auto', 'light', 'dark' );

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'head' ), 1 );
		add_filter( 'language_attributes', array( self::class, 'html_attributes' ) );
	}

	/**
	 * Is the feature on for this site?
	 */
	public static function enabled(): bool {
		return 'no' !== get_theme_mod( 'flavor_color_scheme_enable', 'yes' );
	}

	/**
	 * May the visitor override the owner's default?
	 */
	public static function toggle_enabled(): bool {
		return self::enabled() && 'no' !== get_theme_mod( 'flavor_color_scheme_toggle', 'yes' );
	}

	/**
	 * Owner default: auto (follow the device), or a fixed scheme.
	 */
	public static function mode(): string {
		$mode = (string) get_theme_mod( 'flavor_color_scheme', 'auto' );
		return in_array( $mode, self::MODES, true ) ? $mode : 'auto';
	}

	/**
	 * Print state on <html> so CSS reacts before the first paint.
	 *
	 * @param string $output Existing attributes.
	 * @return string
	 */
	public static function html_attributes( string $output ): string {
		if ( is_admin() || ! self::enabled() ) {
			return $output;
		}

		$output .= ' data-flavor-scheme="' . esc_attr( self::mode() ) . '"';

		if ( ! self::toggle_enabled() ) {
			$output .= ' data-flavor-scheme-locked="1"';
		}

		return $output;
	}

	/**
	 * Palette for a skin. Pass a slug to inspect a skin other than the active one.
	 *
	 * @param string $skin Skin slug, or '' for the resolved site tokens.
	 * @return array<string, string>
	 */
	public static function palette( string $skin = '' ): array {
		$base = '' === $skin ? Design::resolved() : Design::tokens( $skin );

		// Midnight skins already live at night: deepening them keeps the toggle
		// visible instead of a no-op, without washing the brand out.
		$already_dark = self::luminance( (string) $base['bg'] ) < 0.22;

		if ( $already_dark ) {
			$bg          = self::mix( $base['bg'], '#000000', 0.45 );
			$surface     = self::mix( $base['surface'], '#000000', 0.12 );
			$surface_alt = self::mix( $base['surface_alt'], '#000000', 0.22 );
			$ink         = self::mix( $base['ink'], '#ffffff', 0.04 );
			$muted       = self::mix( $base['muted'], '#ffffff', 0.10 );
			$line        = self::mix( $base['line'], '#ffffff', 0.08 );
		} else {
			$bg          = self::mix( $base['bg'], '#07090d', 0.90 );
			$surface     = self::mix( $base['surface'], '#12151b', 0.88 );
			$surface_alt = self::mix( $base['surface_alt'], '#181c23', 0.86 );
			$ink         = self::mix( $base['ink'], '#ffffff', 0.90 );
			$muted       = self::mix( $base['muted'], '#ffffff', 0.42 );
			// Hairlines lift off the night surface instead of staying white.
			$line        = self::mix( $surface, '#ffffff', 0.13 );
		}

		// Readability first: body copy on both canvas and cards, mute/metadata,
		// then the brand colours that are used for links and small icons.
		$ink   = self::ensure( $ink, $bg, 4.5 );
		$ink   = self::ensure( $ink, $surface, 4.5 );
		$muted = self::ensure( $muted, $surface, 4.5 );
		$muted = self::ensure( $muted, $surface_alt, 4.5 );

		// Brand colours are lifted through HSL, not toward white: a crimson brand
		// stays crimson at night instead of turning into a washed-out rose.
		$primary = self::ensure( $base['primary'], $surface, 4.5, true );
		$accent  = self::ensure( $base['accent'], $surface, 3.0, true );

		$action_ink = self::contrast( $primary, '#ffffff' ) >= self::contrast( $primary, '#000000' ) ? '#ffffff' : '#000000';

		return array(
			'bg'          => $bg,
			'surface'     => $surface,
			'surface_alt' => $surface_alt,
			'ink'         => $ink,
			'muted'       => $muted,
			'line'        => $line,
			'primary'     => $primary,
			'accent'      => $accent,
			'action_ink'  => $action_ink,
			'theme_color' => $surface,
		);
	}

	/**
	 * CSS custom-property block for the night scheme.
	 *
	 * Both the explicit choice (`data-flavor-scheme="dark"`, written by the
	 * toggle) and the untouched `auto` state are covered, so a visitor without
	 * JavaScript still gets their device preference.
	 *
	 * @param string $skin Skin slug, or '' for the active site.
	 * @return string
	 */
	public static function css( string $skin = '' ): string {
		$p = self::palette( $skin );

		$declarations = '--flavor-bg:' . $p['bg'] . ';'
			. '--flavor-surface:' . $p['surface'] . ';'
			. '--flavor-surface-rgb:' . Design::hex2rgb( $p['surface'] ) . ';'
			. '--flavor-surface-alt:' . $p['surface_alt'] . ';'
			. '--flavor-ink:' . $p['ink'] . ';'
			. '--flavor-ink-rgb:' . Design::hex2rgb( $p['ink'] ) . ';'
			. '--flavor-muted:' . $p['muted'] . ';'
			. '--flavor-line:' . $p['line'] . ';'
			. '--flavor-primary:' . $p['primary'] . ';'
			. '--flavor-primary-rgb:' . Design::hex2rgb( $p['primary'] ) . ';'
			. '--flavor-accent:' . $p['accent'] . ';'
			. '--flavor-accent-rgb:' . Design::hex2rgb( $p['accent'] ) . ';'
			. '--ui-action-ink:' . $p['action_ink'] . ';'
			. '--ui-muted:' . $p['muted'] . ';'
			. '--flavor-card-shadow:0 18px 45px rgba(0,0,0,0.45);'
			. '--flavor-shadow-sm:0 2px 8px rgba(0,0,0,0.3);'
			. '--flavor-shadow-md:0 8px 20px rgba(0,0,0,0.38);'
			. '--flavor-shadow-lg:0 16px 36px rgba(0,0,0,0.46);'
			. '--flavor-shadow-xl:0 24px 48px rgba(0,0,0,0.55);'
			. '--flavor-shadow-drawer:-12px 0 40px rgba(0,0,0,0.6);'
			. '--flavor-shadow-modal:0 20px 60px rgba(0,0,0,0.7);'
			. '--flavor-success-bg:' . self::mix( '#16a34a', $p['surface'], 0.72 ) . ';'
			. '--flavor-warning-bg:' . self::mix( '#d97706', $p['surface'], 0.72 ) . ';'
			. '--flavor-danger-bg:' . self::mix( '#dc2626', $p['surface'], 0.72 ) . ';'
			. '--flavor-info-bg:' . self::mix( '#0284c7', $p['surface'], 0.72 ) . ';';

		$dark = ':root[data-flavor-scheme="dark"], html[data-flavor-scheme="dark"] body{' . $declarations . '}';
		$auto = '@media (prefers-color-scheme:dark){:root[data-flavor-scheme="auto"]{' . $declarations . '}html[data-flavor-scheme="auto"] body{' . $declarations . '}}';

		return $dark . $auto;
	}

	/**
	 * Boot script + palette + a dark browser-chrome colour.
	 */
	public static function head(): void {
		if ( ! self::enabled() ) {
			return;
		}

		echo '<style id="flavor-night-palette">' . self::css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated custom properties.

		$p = self::palette();
		printf(
			'<meta name="theme-color" media="(prefers-color-scheme: dark)" content="%s" />' . "\n",
			esc_attr( $p['theme_color'] )
		);

		if ( ! self::toggle_enabled() ) {
			return;
		}

		// Runs before the first paint: without it a night-mode visitor sees a
		// white flash on every navigation (the classic premium-theme complaint).
		?>
<script id="flavor-scheme-boot">(function(){try{var d=document.documentElement;if(d.hasAttribute("data-flavor-scheme-locked"))return;var s=localStorage.getItem("<?php echo esc_js( self::STORAGE_KEY ); ?>");if(s==="dark"||s==="light"){d.setAttribute("data-flavor-scheme",s);}}catch(e){}})();</script>
		<?php
	}

	/**
	 * Accessible toggle button. The two icons are swapped with CSS, so the
	 * button is correct even when the script never runs.
	 *
	 * @param string $context Where the button sits (header, drawer, dock).
	 * @param string $extra_class Extra classes.
	 */
	public static function toggle( string $context = 'header', string $extra_class = '' ): void {
		if ( ! self::toggle_enabled() ) {
			return;
		}

		$label = __( 'تغییر حالت شب و روز', 'flavor' );

		echo '<button type="button" class="flavor-scheme-toggle flavor-scheme-toggle--' . esc_attr( sanitize_html_class( $context ) ) . ' ' . esc_attr( $extra_class ) . '"'
			. ' data-flavor-scheme-toggle aria-label="' . esc_attr( $label ) . '" title="' . esc_attr( $label ) . '" aria-pressed="false">'
			. '<span class="flavor-scheme-toggle__icon flavor-scheme-toggle__icon--sun">';
		UI::icon( 'sun', 20 );
		echo '</span><span class="flavor-scheme-toggle__icon flavor-scheme-toggle__icon--moon">';
		UI::icon( 'moon', 20 );
		echo '</span></button>';
	}

	/**
	 * Normalise a hex colour, or '' when it is not one.
	 *
	 * @param string $hex Colour.
	 * @return string
	 */
	public static function normalize( string $hex ): string {
		$hex = strtolower( ltrim( trim( $hex ), '#' ) );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return preg_match( '/^[0-9a-f]{6}$/', $hex ) ? $hex : '';
	}

	/**
	 * Blend two colours. `$ratio` 0 keeps `$from`, 1 returns `$to`.
	 *
	 * @param string $from  Start colour.
	 * @param string $to    Target colour.
	 * @param float  $ratio Blend ratio between 0 and 1.
	 * @return string
	 */
	public static function mix( string $from, string $to, float $ratio ): string {
		$a = self::normalize( $from );
		$b = self::normalize( $to );

		if ( '' === $a ) {
			return '' === $b ? '#000000' : '#' . $b;
		}
		if ( '' === $b ) {
			return '#' . $a;
		}

		$ratio = max( 0.0, min( 1.0, $ratio ) );
		$out   = '#';

		for ( $i = 0; $i < 3; $i++ ) {
			$left  = hexdec( substr( $a, $i * 2, 2 ) );
			$right = hexdec( substr( $b, $i * 2, 2 ) );
			$value = (int) round( $left + ( $right - $left ) * $ratio );
			$out  .= str_pad( dechex( max( 0, min( 255, $value ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * Relative luminance (WCAG 2.1).
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	public static function luminance( string $hex ): float {
		$hex = self::normalize( $hex );

		if ( '' === $hex ) {
			return 0.0;
		}

		$channels = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$v          = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
			$channels[] = $v <= 0.04045 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * Contrast ratio between two colours.
	 *
	 * @param string $a First colour.
	 * @param string $b Second colour.
	 * @return float
	 */
	public static function contrast( string $a, string $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );

		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Nudge a foreground colour until it clears a contrast target.
	 *
	 * The pole is picked from the background, so the adjustment always moves
	 * away from the surface instead of toward it.
	 *
	 * @param string $fg           Foreground colour.
	 * @param string $bg           Background colour.
	 * @param float  $target       Minimum ratio.
	 * @param bool   $preserve_hue Lift through HSL so the brand hue survives.
	 * @return string
	 */
	public static function ensure( string $fg, string $bg, float $target, bool $preserve_hue = false ): string {
		$fg = self::normalize( $fg );

		if ( '' === $fg ) {
			return '#ffffff';
		}

		$pole = self::luminance( $bg ) > 0.35 ? '#000000' : '#ffffff';

		for ( $step = 0; $step < 24; $step++ ) {
			if ( self::contrast( $fg, $bg ) >= $target ) {
				break;
			}
			$fg = $preserve_hue ? self::lighten( $fg, $pole === '#000000' ? -0.06 : 0.06 ) : self::mix( $fg, $pole, 0.07 );
		}

		return $fg;
	}

	/**
	 * Move a colour along the HSL lightness axis, keeping hue and saturation.
	 *
	 * @param string $hex   Colour.
	 * @param float  $delta Lightness delta between -1 and 1.
	 * @return string
	 */
	public static function lighten( string $hex, float $delta ): string {
		$hex = self::normalize( $hex );

		if ( '' === $hex ) {
			return '#ffffff';
		}

		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;
		$d   = $max - $min;

		$h = 0.0;
		$s = 0.0;

		if ( $d > 0 ) {
			$s = $d / ( 1 - abs( 2 * $l - 1 ) );

			if ( $max === $r ) {
				$h = fmod( ( $g - $b ) / $d, 6 );
			} elseif ( $max === $g ) {
				$h = ( $b - $r ) / $d + 2;
			} else {
				$h = ( $r - $g ) / $d + 4;
			}

			$h = $h * 60;
			if ( $h < 0 ) {
				$h += 360;
			}
		}

		$l = max( 0.0, min( 1.0, $l + $delta ) );

		// Neutral greys have no hue to protect.
		if ( $s <= 0.0001 ) {
			$level = (int) round( $l * 255 );
			return sprintf( '#%02x%02x%02x', $level, $level, $level );
		}

		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;

		switch ( (int) floor( $h / 60 ) ) {
			case 0:
				$out = array( $c, $x, 0 );
				break;
			case 1:
				$out = array( $x, $c, 0 );
				break;
			case 2:
				$out = array( 0, $c, $x );
				break;
			case 3:
				$out = array( 0, $x, $c );
				break;
			case 4:
				$out = array( $x, 0, $c );
				break;
			default:
				$out = array( $c, 0, $x );
		}

		return sprintf(
			'#%02x%02x%02x',
			(int) round( ( $out[0] + $m ) * 255 ),
			(int) round( ( $out[1] + $m ) * 255 ),
			(int) round( ( $out[2] + $m ) * 255 )
		);
	}
}
