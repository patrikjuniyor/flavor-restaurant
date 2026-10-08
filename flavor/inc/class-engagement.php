<?php
/**
 * Conversion and consent surfaces: offer countdown, newsletter opt-in and the
 * cookie notice.
 *
 * Every one of them is opt-in from the Customizer, remembers a refusal, and
 * stays out of the way on cart/checkout. The popup is not a tracking device:
 * it posts one email, and the site owner decides where it goes through the
 * `flavor_newsletter_signup` action.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Engagement
 */
class Engagement {

	/**
	 * Option that stores collected addresses.
	 */
	const LEADS_OPTION = 'flavor_leads';

	/**
	 * Hard cap so the option never grows without bound.
	 */
	const LEADS_LIMIT = 1000;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_footer', array( self::class, 'consent' ), 9 );
		add_action( 'wp_footer', array( self::class, 'popup' ), 10 );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'product_countdown' ), 11 );
		add_action( 'wp_ajax_flavor_subscribe', array( self::class, 'handle_subscribe' ) );
		add_action( 'wp_ajax_nopriv_flavor_subscribe', array( self::class, 'handle_subscribe' ) );
	}

	/**
	 * Countdown enabled?
	 */
	public static function countdown_enabled(): bool {
		return 'no' !== get_theme_mod( 'flavor_offer_countdown_enable', 'yes' );
	}

	/**
	 * Timestamp the campaign ends, or 0. Persian digits are accepted so the
	 * owner can paste a Jalali-style entry without fighting the input.
	 */
	public static function deadline(): int {
		return self::parse_deadline( (string) get_theme_mod( 'flavor_offer_deadline', '' ) );
	}

	/**
	 * Parse a deadline typed by the owner.
	 *
	 * The value is read in the site's own timezone (WordPress runs PHP in UTC,
	 * so a bare `strtotime()` would shift every campaign by the site offset).
	 *
	 * @param string $value Raw value.
	 * @return int Unix timestamp, or 0 when the input is not a date.
	 */
	public static function parse_deadline( string $value ): int {
		$value = trim( self::latin_digits( $value ) );

		if ( '' === $value ) {
			return 0;
		}

		try {
			$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
			$date     = new \DateTime( $value, $timezone );
		} catch ( \Exception $error ) {
			unset( $error );
			return 0;
		}

		$stamp = $date->getTimestamp();

		return $stamp > 0 ? $stamp : 0;
	}

	/**
	 * Print a countdown, or nothing when there is no future deadline.
	 *
	 * @param string $context  Where it is used (for analytics/ids).
	 * @param int    $deadline Unix timestamp, 0 for the campaign default.
	 * @param string $label    Optional label above the clock.
	 */
	public static function countdown( string $context = 'offer', int $deadline = 0, string $label = '' ): void {
		if ( ! self::countdown_enabled() ) {
			return;
		}

		$deadline = $deadline > 0 ? $deadline : self::deadline();

		if ( $deadline <= time() ) {
			return;
		}

		$slug = sanitize_html_class( $context );

		echo '<div class="flavor-countdown" id="flavor-countdown-' . esc_attr( $slug ) . '" data-flavor-countdown="' . esc_attr( (string) $deadline ) . '"'
			. ' data-flavor-countdown-now="' . esc_attr( (string) time() ) . '" role="timer" aria-live="off">';

		if ( '' !== $label ) {
			echo '<span class="flavor-countdown__label">' . esc_html( $label ) . '</span>';
		}

		echo '<div class="flavor-countdown__clock">';

		foreach ( array( 'day' => __( 'روز', 'flavor' ), 'hour' => __( 'ساعت', 'flavor' ), 'minute' => __( 'دقیقه', 'flavor' ), 'second' => __( 'ثانیه', 'flavor' ) ) as $unit => $text ) {
			echo '<span class="flavor-countdown__unit"><strong data-cd="' . esc_attr( $unit ) . '">۰</strong><small>' . esc_html( $text ) . '</small></span>';
		}

		echo '</div>';

		// Without JavaScript the visitor still learns when the offer ends.
		echo '<noscript><span class="flavor-countdown__fallback">' . esc_html( sprintf( /* translators: %s: date. */ __( 'پایان پیشنهاد: %s', 'flavor' ), wp_date( 'Y/m/d H:i', $deadline ) ) ) . '</span></noscript>';
		echo '</div>';
	}

	/**
	 * Countdown for a product that is on sale until a known date: WooCommerce
	 * already stores the schedule, so the store never has to type it twice.
	 */
	public static function product_countdown(): void {
		if ( ! self::countdown_enabled() ) {
			return;
		}

		global $product;

		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}

		$until = get_post_meta( (int) $product->get_id(), '_sale_price_dates_to', true );
		$until = $until ? (int) $until : 0;

		if ( $until <= time() ) {
			return;
		}

		self::countdown( 'product-' . (int) $product->get_id(), $until, __( 'پیشنهاد ویژه تا پایان:', 'flavor' ) );
	}

	/**
	 * Cookie notice. It only writes one local flag; nothing is blocked or
	 * loaded behind the visitor's back.
	 */
	public static function consent(): void {
		if ( 'no' === get_theme_mod( 'flavor_cookie_notice_enable', 'yes' ) || is_admin() ) {
			return;
		}

		$text  = (string) get_theme_mod( 'flavor_cookie_text', '' );
		$privacy = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';

		if ( '' === trim( $text ) ) {
			$text = __( 'این سایت برای کارکرد درست سبد سفارش و ورود مشتری از حافظهٔ مرورگر استفاده می‌کند. با ادامهٔ بازدید، همین حالت ضروری را می‌پذیرید.', 'flavor' );
		}
		?>
		<div class="flavor-consent" id="flavor-consent" role="region" aria-label="<?php esc_attr_e( 'رضایت کوکی', 'flavor' ); ?>" hidden data-flavor-consent>
			<div class="flavor-consent__inner">
				<span class="flavor-consent__icon" aria-hidden="true"><?php UI::icon( 'lock', 20 ); ?></span>
				<p class="flavor-consent__text"><?php echo esc_html( $text ); ?></p>
				<div class="flavor-consent__actions">
					<button type="button" class="flavor-btn flavor-btn--primary flavor-btn--sm" data-flavor-consent-choice="all"><?php esc_html_e( 'قبول همه', 'flavor' ); ?></button>
					<button type="button" class="flavor-btn flavor-btn--outline flavor-btn--sm" data-flavor-consent-choice="essential"><?php esc_html_e( 'فقط ضروری', 'flavor' ); ?></button>
					<?php if ( $privacy ) : ?>
						<a class="flavor-ui-text-link" href="<?php echo esc_url( $privacy ); ?>"><?php esc_html_e( 'سیاست حریم خصوصی', 'flavor' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Newsletter opt-in, shown once and only to visitors who did not refuse
	 * marketing storage.
	 */
	public static function popup(): void {
		if ( 'no' === get_theme_mod( 'flavor_newsletter_enable', 'yes' ) || is_admin() ) {
			return;
		}

		if ( self::is_quiet_view() ) {
			return;
		}

		// Editors are the only people who hit this ten times a day while working.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return;
		}

		$mode   = 'exit' === get_theme_mod( 'flavor_newsletter_mode', 'exit' ) ? 'exit' : 'delay';
		$delay  = absint( get_theme_mod( 'flavor_newsletter_delay', 30 ) );
		$title  = (string) get_theme_mod( 'flavor_newsletter_title', '' );
		$text   = (string) get_theme_mod( 'flavor_newsletter_text', '' );
		$code   = (string) get_theme_mod( 'flavor_newsletter_code', '' );

		if ( '' === trim( $title ) ) {
			$title = __( 'اول از همه، تازه‌های منو را بدانید', 'flavor' );
		}
		if ( '' === trim( $text ) ) {
			$text = __( 'منوی فصل، پیشنهادهای ویژه و تخفیف‌های مناسبتی را یک هفته زودتر دریافت کنید. هر وقت خواستید لغو کنید.', 'flavor' );
		}
		?>
		<div class="flavor-popup" id="flavor-popup" role="dialog" aria-modal="false" aria-labelledby="flavor-popup-title" hidden
			data-flavor-popup data-popup-mode="<?php echo esc_attr( $mode ); ?>" data-popup-delay="<?php echo esc_attr( (string) ( $delay > 0 ? $delay : 30 ) ); ?>">
			<div class="flavor-popup__aside" aria-hidden="true">
				<span class="flavor-popup__badge"><?php UI::icon( 'star', 26 ); ?></span>
			</div>
			<div class="flavor-popup__body">
				<button type="button" class="flavor-ui-icon-button flavor-popup__close" data-flavor-popup-close aria-label="<?php esc_attr_e( 'بستن', 'flavor' ); ?>"><?php UI::icon( 'close' ); ?></button>
				<h2 id="flavor-popup-title"><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $text ); ?></p>
				<form class="flavor-popup__form" data-flavor-subscribe>
					<label class="screen-reader-text" for="flavor-popup-email"><?php esc_html_e( 'ایمیل شما', 'flavor' ); ?></label>
					<input type="email" id="flavor-popup-email" name="email" dir="ltr" required maxlength="120" autocomplete="email" placeholder="name@example.com" />
					<button type="submit" class="flavor-btn flavor-btn--primary"><?php UI::icon( 'send', 18 ); ?><?php esc_html_e( 'عضویت', 'flavor' ); ?></button>
				</form>
				<?php if ( '' !== trim( $code ) ) : ?>
					<p class="flavor-popup__code"><?php esc_html_e( 'کد تخفیف شما:', 'flavor' ); ?> <bdi><?php echo esc_html( $code ); ?></bdi></p>
				<?php endif; ?>
				<p class="flavor-popup__status" role="status" aria-live="polite" data-flavor-subscribe-status></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Views where a popup is never worth interrupting: paying, or reading an
	 * order status.
	 */
	public static function is_quiet_view(): bool {
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}

		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return true;
		}

		return is_page_template( 'page-templates/template-order-tracking.php' );
	}

	/**
	 * Validate an address. Returns '' when it is not usable.
	 *
	 * @param string $email Raw address.
	 * @return string
	 */
	public static function validate_email( string $email ): string {
		$email = sanitize_email( trim( self::latin_digits( $email ) ) );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Store one address.
	 *
	 * @param string $email  Validated address.
	 * @param string $source Where it came from.
	 * @return string saved | duplicate | full
	 */
	public static function store_lead( string $email, string $source = 'popup' ): string {
		$leads = get_option( self::LEADS_OPTION, array() );
		$leads = is_array( $leads ) ? $leads : array();

		foreach ( $leads as $lead ) {
			if ( is_array( $lead ) && isset( $lead['email'] ) && strtolower( (string) $lead['email'] ) === strtolower( $email ) ) {
				return 'duplicate';
			}
		}

		if ( count( $leads ) >= self::LEADS_LIMIT ) {
			return 'full';
		}

		$leads[] = array(
			'email'  => $email,
			'time'   => time(),
			'source' => sanitize_key( $source ),
		);

		update_option( self::LEADS_OPTION, $leads, false );

		/**
		 * Fires after a visitor joins the list, so a CRM or Mailchimp bridge
		 * can take over without this theme pretending to be an email service.
		 *
		 * @param string $email  Address.
		 * @param string $source Source.
		 */
		do_action( 'flavor_newsletter_signup', $email, sanitize_key( $source ) );

		if ( 'yes' === get_theme_mod( 'flavor_newsletter_notify', 'no' ) ) {
			$to = sanitize_email( (string) get_option( 'admin_email' ) );
			if ( $to ) {
				wp_mail( $to, __( 'عضو جدید خبرنامه', 'flavor' ), $email );
			}
		}

		return 'saved';
	}

	/**
	 * AJAX endpoint behind the opt-in form.
	 */
	public static function handle_subscribe(): void {
		if ( ! check_ajax_referer( 'flavor_subscribe', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'نشست منقضی شده است؛ صفحه را دوباره باز کنید.', 'flavor' ) ), 403 );
		}

		$email = self::validate_email( (string) wp_unslash( $_POST['email'] ?? '' ) );

		if ( '' === $email ) {
			wp_send_json_error( array( 'message' => __( 'ایمیل معتبر وارد کنید.', 'flavor' ) ), 400 );
		}

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'flavor_sub_' . md5( $ip );

		if ( '' !== $ip && get_transient( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'کمی بعد دوباره تلاش کنید.', 'flavor' ) ), 429 );
		}

		if ( '' !== $ip ) {
			set_transient( $key, 1, MINUTE_IN_SECONDS );
		}

		$result = self::store_lead( $email, 'popup' );

		if ( 'full' === $result ) {
			wp_send_json_error( array( 'message' => __( 'فهرست اعضا تکمیل است؛ با ما تماس بگیرید.', 'flavor' ) ), 409 );
		}

		wp_send_json_success(
			array(
				'message' => 'duplicate' === $result
					? __( 'این ایمیل از قبل ثبت شده است. ممنون!', 'flavor' )
					: __( 'ثبت شد. اولین خبر را به‌زودی می‌فرستیم.', 'flavor' ),
			)
		);
	}

	/**
	 * Convert Persian/Arabic digits to Latin ones (input forgiveness).
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function latin_digits( string $value ): string {
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );

		return str_replace( array_merge( $persian, $arabic ), array_merge( $latin, $latin ), $value );
	}
}
