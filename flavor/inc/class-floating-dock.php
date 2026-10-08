<?php
/**
 * Floating action dock: back-to-top with a scroll-progress ring, plus instant
 * contact shortcuts.
 *
 * On phones the dock docks above the bottom navigation instead of on top of it,
 * and the whole cluster is hidden for anyone who asked for reduced motion until
 * they actually scroll.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Floating_Dock
 */
class Floating_Dock {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_footer', array( self::class, 'render' ), 11 );
	}

	/**
	 * Is the dock on?
	 */
	public static function enabled(): bool {
		return 'no' !== get_theme_mod( 'flavor_dock_enable', 'yes' );
	}

	/**
	 * Phone number as a `tel:` target.
	 */
	public static function phone(): string {
		$phone = (string) get_theme_mod( 'flavor_phone', '' );

		return trim( preg_replace( '/\D+/', '', self::latin_digits( $phone ) ) );
	}

	/**
	 * WhatsApp deep link. An explicit social link wins; otherwise the contact
	 * number is converted to the international format Iranian numbers need.
	 */
	public static function whatsapp_url(): string {
		$explicit = (string) get_theme_mod( 'flavor_social_whatsapp', '' );

		if ( '' !== trim( $explicit ) ) {
			return $explicit;
		}

		if ( 'no' === get_theme_mod( 'flavor_dock_whatsapp', 'yes' ) ) {
			return '';
		}

		$digits = self::phone();

		if ( '' === $digits ) {
			return '';
		}

		if ( 0 === strpos( $digits, '0098' ) ) {
			$digits = substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '98' ) ) {
			$digits = substr( $digits, 2 );
		} elseif ( 0 === strpos( $digits, '0' ) ) {
			$digits = substr( $digits, 1 );
		}

		return strlen( $digits ) >= 10 ? 'https://wa.me/98' . $digits : '';
	}

	/**
	 * Persian digits, kept local so the class stays usable on its own.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function latin_digits( string $value ): string {
		$from = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$to   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( $from, $to, $value );
	}

	/**
	 * Print the dock.
	 */
	public static function render(): void {
		if ( ! self::enabled() || is_admin() ) {
			return;
		}

		$phone = self::phone();
		$wa    = self::whatsapp_url();
		$top   = 'no' !== get_theme_mod( 'flavor_dock_top', 'yes' );
		$call  = 'no' !== get_theme_mod( 'flavor_dock_call', 'yes' ) && '' !== $phone;

		if ( ! $top && ! $call && '' === $wa ) {
			return;
		}

		// 2πr for r=19, used by the progress ring.
		$circumference = 119.38;
		?>
		<div class="flavor-dock" id="flavor-dock" data-flavor-dock hidden>
			<?php if ( '' !== $wa ) : ?>
				<a class="flavor-dock__btn flavor-dock__btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'گفت‌وگو در واتس‌اپ', 'flavor' ); ?>" title="<?php esc_attr_e( 'گفت‌وگو در واتس‌اپ', 'flavor' ); ?>"><?php UI::icon( 'whatsapp', 20 ); ?></a>
			<?php endif; ?>

			<?php if ( $call ) : ?>
				<a class="flavor-dock__btn" href="tel:<?php echo esc_attr( $phone ); ?>" aria-label="<?php esc_attr_e( 'تماس تلفنی با رستوران', 'flavor' ); ?>" title="<?php esc_attr_e( 'تماس تلفنی', 'flavor' ); ?>"><?php UI::icon( 'phone', 20 ); ?></a>
			<?php endif; ?>

			<?php if ( $top ) : ?>
				<button type="button" class="flavor-dock__btn flavor-dock__btn--top" data-flavor-top aria-label="<?php esc_attr_e( 'بازگشت به بالای صفحه', 'flavor' ); ?>" title="<?php esc_attr_e( 'بازگشت به بالا', 'flavor' ); ?>">
					<svg class="flavor-dock__ring" viewBox="0 0 44 44" aria-hidden="true" focusable="false">
						<circle class="flavor-dock__ring-track" cx="22" cy="22" r="19" fill="none" stroke-width="2"></circle>
						<circle class="flavor-dock__ring-fill" cx="22" cy="22" r="19" fill="none" stroke-width="2"
							stroke-dasharray="<?php echo esc_attr( (string) $circumference ); ?>"
							stroke-dashoffset="<?php echo esc_attr( (string) $circumference ); ?>" data-flavor-top-ring></circle>
					</svg>
					<?php UI::icon( 'arrow_up', 20 ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}
}
