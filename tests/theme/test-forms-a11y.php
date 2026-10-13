<?php
/**
 * Forms accessibility contract (prompt 53).
 *
 * Four forms face the customer: the reservation picker, the account/OTP
 * login, the cart-drawer checkout and — new with this prompt — the contact
 * message form. The prompt's acceptance line is that every field carries a
 * real <label>, every failure is TEXT that states its solution, wired to the
 * field through aria-describedby + aria-invalid, announced through a live
 * region, and that success says so too. Colour is never the only channel and
 * a form is never visible-but-dead without its script.
 *
 * The contact endpoint (Flavor\Contact_Form, the theme's first REST route)
 * is exercised for real against the Core mock: nonce gate, honeypot,
 * rate limit, sanitisation, validation branches and mail delivery.
 *
 *   php tests/theme/test-forms-a11y.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once FLAVOR_DIR . '/inc/class-enqueue.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-contact-form.php';

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
function check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

/**
 * Read a theme file as UTF-8 text.
 *
 * @param string $rel Path under flavor/.
 * @return string File contents.
 */
function read( string $rel ): string {
	return (string) file_get_contents( FLAVOR_DIR . '/' . $rel );
}

/**
 * Every non-hidden input/select/textarea must have a matching <label for>.
 *
 * @param string $html Template source.
 * @return array List of control ids without a label.
 */
function unlabeled_controls( string $html ): array {
	preg_match_all( '/<label[^>]*\bfor="([^"]+)"/', $html, $labels );
	$labeled = array_flip( $labels[1] );
	preg_match_all( '/<(input|select|textarea)\b[^>]*\bid="([^"]+)"/', $html, $controls );
	$missing = array();
	foreach ( $controls[2] as $index => $id ) {
		$tag = $controls[1][ $index ];
		if ( ! isset( $labeled[ $id ] ) ) {
			$missing[] = $tag . '#' . $id;
		}
	}
	return $missing;
}

$reservation_tpl = read( 'template-parts/reservation/reservation-form.php' );
$reservation_js  = read( 'assets/js/reservation.js' );
$login_tpl       = read( 'template-parts/ui/login.php' );
$auth_js         = read( 'assets/js/auth.js' );
$cart_tpl        = read( 'template-parts/menu/cart-drawer.php' );
$menu_js         = read( 'assets/js/menu.js' );
$contact_tpl     = read( 'page-templates/template-contact.php' );
$contact_js      = read( 'assets/js/contact.js' );
$ui_js           = read( 'assets/js/ui.js' );
$enqueue_php     = read( 'inc/class-enqueue.php' );
$functions_php   = read( 'functions.php' );
$ui_css          = read( 'assets/css/ui.css' );
$dark_css        = read( 'assets/css/dark-mode.css' );

echo "\n--- 1. Reservation form: labels, field errors, live regions ---\n";

$missing = unlabeled_controls( $reservation_tpl );
check( array() === $missing, 'every reservation control has a real <label for>' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
check(
	3 === preg_match_all( '/<p class="flavor-ui-field__err" id="flavor-res-(?:party|name|mobile)-err" hidden><\/p>/', $reservation_tpl ),
	'party, name and mobile each carry a hidden field-error host'
);
check(
	false !== strpos( $reservation_tpl, 'id="flavor-res-err" role="alert" tabindex="-1"' ) && false !== strpos( $reservation_tpl, 'id="flavor-res-ok" role="status" tabindex="-1"' ),
	'the form-level failure is an assertive alert and the success a status, both focusable'
);
check(
	false !== strpos( $reservation_tpl, 'aria-describedby="flavor-res-mobile-hint"' ) && false !== strpos( $reservation_tpl, 'id="flavor-res-mobile-hint"' ),
	'the mobile input permanently describes its 11-digit rule through a hint'
);

echo "\n--- 2. reservation.js: client validation with solution text ---\n";

check( false !== strpos( $reservation_js, 'flavor-i18n-map: flavorData.i18n.reservation' ), 'reservation.js reads the i18n map marker' );
check( false !== strpos( $reservation_js, "/^09\\d{9}\$/" ), 'the mobile format is validated before submission' );
check( false !== strpos( $reservation_js, 'partySize < 1 || partySize > 20' ), 'the party size is validated against its 1-20 range' );
check(
	false !== strpos( $reservation_js, 'ui.fieldError(' ) && false !== strpos( $reservation_js, 'ui.fieldClear(' ),
	'field failures go through the shared fieldError/fieldClear helpers'
);
check(
	substr_count( $reservation_js, 'err.focus({ preventScroll: true })' ) >= 2,
	'a form-level failure moves focus to the alert so it is announced'
);
check( false !== strpos( $reservation_js, "ui.announce(t('sending'))" ), 'submitting announces its busy state politely' );

echo "\n--- 3. Login / OTP: labels, field errors, assertive live region ---\n";

$missing = unlabeled_controls( $login_tpl );
check( array() === $missing, 'every login control has a real <label for>' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
check(
	false !== strpos( $login_tpl, 'id="flavor-account-mobile-err" hidden' ) && false !== strpos( $login_tpl, 'id="flavor-account-code-err" hidden' ),
	'the mobile and code fields each carry a hidden field-error host'
);
check(
	false !== strpos( $login_tpl, 'role="status"' ) && false !== strpos( $login_tpl, 'aria-live="polite"' ),
	'the login status region starts polite'
);
check(
	false !== strpos( $auth_js, "setAttribute('aria-live',error?'assertive':'polite')" ),
	'an auth failure escalates the live region to assertive and success calms it back'
);
check(
	false !== strpos( $auth_js, "ui.fieldError(mobile,t('mobile_invalid_otp'))" ) && false !== strpos( $auth_js, "ui.fieldError(code,t('check_code'))" ),
	'mobile and code failures land on their fields, not only the panel'
);
check(
	false !== strpos( $auth_js, 'ui.fieldClear(mobile)' ) && false !== strpos( $auth_js, 'ui.fieldClear(code)' ),
	'editing a field clears its error immediately'
);

$ui_strings = \Flavor\UI::strings();
check(
	false !== strpos( $ui_strings['mobile_invalid_otp'], '۱۱ رقم و با ۰۹ شروع شود' ),
	'the OTP mobile error states the 11-digit rule and the 09 prefix'
);
check(
	false !== strpos( $ui_strings['check_code'], '۴ تا ۶ رقم' ),
	'the code error states the expected digit range'
);

echo "\n--- 4. Checkout: the mobile error belongs on the field ---\n";

$missing = unlabeled_controls( $cart_tpl );
check( array() === $missing, 'every cart-drawer control has a real <label for>' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
check(
	false !== strpos( $cart_tpl, 'id="flavor-mobile-err" hidden' ) && false !== strpos( $cart_tpl, 'aria-describedby="flavor-mobile-hint"' ) && false !== strpos( $cart_tpl, 'id="flavor-mobile-hint"' ),
	'the checkout mobile field has an error host and a permanent format hint'
);
check( false !== strpos( $cart_tpl, 'id="flavor-otp-code-err" hidden' ), 'the drawer OTP code field has an error host' );
check(
	false !== strpos( $cart_tpl, "esc_html_e( 'در حال دریافت سبد…', 'flavor' )" ) && false === strpos( $cart_tpl, '>در حال دریافت سبد' ),
	'the cart loading line is translated copy, not a raw Persian literal'
);
check(
	false !== strpos( $menu_js, "if (ui) ui.fieldError(mobile, t('mobile_invalid')); else failure(t('mobile_invalid'))" ),
	'checkout marks the mobile field itself invalid, with the panel alert only as fallback'
);
check(
	false !== strpos( $menu_js, 'ui.fieldClear(checkoutMobile)' ),
	'typing in the checkout mobile field clears its error'
);

$menu_strings = \Flavor\Enqueue::menu_strings();
check( false !== strpos( $menu_strings['mobile_invalid'], '۱۱ رقم و با ۰۹ شروع شود' ), 'the checkout mobile error states the rule too' );

echo "\n--- 5. Contact page: the form exists and is wired, not decorative ---\n";

check( false !== strpos( $contact_tpl, 'flavor-contact-form' ) && false !== strpos( $contact_tpl, 'id="flavor-contact-form"' ), 'the contact page ships a message form' );
check(
	(bool) preg_match( '/if \( is_email\( \$email \) \) :.*flavor-contact-form/s', $contact_tpl ),
	'the form renders only when a valid store e-mail is configured'
);
check( false !== strpos( $contact_tpl, '<form id="flavor-contact-form" class="flavor-checkout" hidden>' ), 'the form stays hidden until its script wires it' );
$missing = unlabeled_controls( $contact_tpl );
check( array() === $missing, 'every contact control has a real <label for>' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
check(
	4 === preg_match_all( '/<p class="flavor-ui-field__err" id="flavor-contact-(?:name|mobile|email|message)-err" hidden><\/p>/', $contact_tpl ),
	'all four validated fields carry a hidden field-error host'
);
check(
	substr_count( $contact_tpl, 'aria-describedby="flavor-contact-reply-hint"' ) === 2 && false !== strpos( $contact_tpl, 'id="flavor-contact-reply-hint"' ),
	'mobile and e-mail both describe the reply-channel rule through a shared hint'
);
check(
	false !== strpos( $contact_tpl, 'flavor-contact-hp" aria-hidden="true"' ) && false !== strpos( $contact_tpl, 'id="flavor-contact-website" tabindex="-1"' ),
	'the honeypot is hidden from assistive tech and the tab order'
);
check(
	false !== strpos( $contact_tpl, 'id="flavor-contact-err" role="alert" tabindex="-1"' ) && false !== strpos( $contact_tpl, 'id="flavor-contact-ok" role="status" tabindex="-1"' ),
	'contact failure is an assertive alert and success a status, both focusable'
);
check( false !== strpos( $contact_tpl, '<noscript>' ), 'a no-script visitor is told how to reach the store instead' );

echo "\n--- 6. contact.js: validation with solution text ---\n";

check( false !== strpos( $contact_js, 'flavor-i18n-map: flavorData.i18n.contact' ), 'contact.js reads the i18n map marker' );
check( false !== strpos( $contact_js, "/^09\\d{9}\$/" ) && false !== strpos( $contact_js, 'message.length < 10' ), 'mobile format and message length are validated client-side' );
check(
	false !== strpos( $contact_js, 'ui.fieldError(' ) && false !== strpos( $contact_js, 'ui.fieldClear(' ),
	'contact failures go through the shared field helpers'
);
check( false !== strpos( $contact_js, 'form.hidden = false' ), 'the script reveals the form once it can work' );
check( false !== strpos( $contact_js, "ui.request('contact'" ), 'submission goes to the flavor/v1/contact route through ui.request' );

echo "\n--- 7. Shared helpers and styles ---\n";

check(
	false !== strpos( $ui_js, 'fieldError: fieldError, fieldClear: fieldClear' ),
	'FlavorUI exports fieldError and fieldClear'
);
check(
	false !== strpos( $ui_js, "input.setAttribute('aria-invalid', 'true')" ) && false !== strpos( $ui_js, "input.setAttribute('aria-describedby'" ),
	'fieldError marks the input invalid and points aria-describedby at the message'
);
check(
	false !== strpos( $ui_js, "input.removeAttribute('aria-invalid')" ),
	'fieldClear removes the invalid state again'
);
check( false !== strpos( $ui_css, '.flavor-ui .flavor-ui-field__err{' ), 'the field-error host is styled in ui.css' );
check( false !== strpos( $ui_css, '.flavor-ui .flavor-ui-field input[aria-invalid=true]' ), 'an invalid field also shows a danger border' );
check( false !== strpos( $ui_css, '.flavor-contact-hp{' ) && false !== strpos( $ui_css, 'clip:rect(0,0,0,0)' ), 'the honeypot is visually hidden in ui.css' );
check( 2 === substr_count( $dark_css, '.flavor-ui .flavor-ui-field__err' ), 'dark mode overrides the field-error colour in both scheme blocks' );

echo "\n--- 8. Enqueue wiring and the unified mobile message ---\n";

check(
	(bool) preg_match( "/'reservation'\s*=>\s*self::reservation_strings\(\)/", $enqueue_php ) && (bool) preg_match( "/'contact'\s*=>\s*self::contact_strings\(\)/", $enqueue_php ),
	'both maps ride in the flavorData.i18n payload'
);
check(
	(bool) preg_match( "/is_page_template\( 'page-templates\/template-contact\.php' \).*flavor-contact/s", $enqueue_php ),
	'contact.js is enqueued only on the contact template'
);
$reservation_strings = \Flavor\Enqueue::reservation_strings();
$contact_strings     = \Flavor\Enqueue::contact_strings();
check(
	count( $reservation_strings ) >= 18 && in_array( '', $reservation_strings, true ) === false,
	'the reservation map is populated with non-empty strings (' . count( $reservation_strings ) . ' keys)'
);
check(
	count( $contact_strings ) >= 8 && in_array( '', $contact_strings, true ) === false,
	'the contact map is populated with non-empty strings (' . count( $contact_strings ) . ' keys)'
);
$unified = 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود؛ مانند ۰۹۱۲۱۲۳۴۵۶۷.';
check(
	$unified === $reservation_strings['mobile_invalid']
		&& $unified === $contact_strings['mobile_invalid']
		&& $unified === $menu_strings['mobile_invalid']
		&& $unified === $ui_strings['mobile_invalid_otp'],
	'one mobile-rule sentence serves all four forms (one msgid, four maps)'
);
check(
	false !== strpos( $functions_php, "require_once FLAVOR_DIR . '/inc/class-contact-form.php';" ) && false !== strpos( $functions_php, 'Flavor\Contact_Form::init();' ),
	'functions.php loads and boots Contact_Form'
);

echo "\n--- 9. Contact_Form endpoint, executed against the mock ---\n";

$GLOBALS['_mock_rest_routes'] = array();
\Flavor\Contact_Form::routes();
$route = null;
foreach ( $GLOBALS['_mock_rest_routes'] as $candidate ) {
	if ( 'flavor/v1' === $candidate['ns'] && '/contact' === $candidate['route'] ) {
		$route = $candidate;
	}
}
check( null !== $route && 'POST' === $route['methods'], 'POST flavor/v1/contact is registered' );
check(
	null !== $route && is_array( $route['perm'] ) && 'Flavor\Contact_Form' === $route['perm'][0] && 'nonce' === $route['perm'][1],
	'the route is guarded by the wp_rest nonce callback'
);

$nonce_request = new \WP_REST_Request( 'POST', '/flavor/v1/contact' );
$nonce_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
check( \Flavor\Contact_Form::nonce( $nonce_request ), 'a genuine wp_rest nonce passes the gate' );
$forged = new \WP_REST_Request( 'POST', '/flavor/v1/contact' );
$forged->set_header( 'X-WP-Nonce', 'not-a-nonce' );
check( ! \Flavor\Contact_Form::nonce( $forged ), 'a forged nonce is rejected' );
$absent = new \WP_REST_Request( 'POST', '/flavor/v1/contact' );
check( ! \Flavor\Contact_Form::nonce( $absent ), 'a missing nonce header is rejected' );

unset( $GLOBALS['_mock_theme_mods']['flavor_ui_contact_email'] );
check( '' === \Flavor\Contact_Form::recipient(), 'without a configured address there is no recipient' );
$GLOBALS['_mock_theme_mods']['flavor_ui_contact_email'] = 'not-an-email';
check( '' === \Flavor\Contact_Form::recipient(), 'an invalid configured address is refused' );
$GLOBALS['_mock_theme_mods']['flavor_ui_contact_email'] = 'hello@flavor.test';
check( 'hello@flavor.test' === \Flavor\Contact_Form::recipient(), 'a valid configured address is the recipient' );

/**
 * Build a POST request with a JSON body.
 *
 * @param array $params Body fields.
 * @return \WP_REST_Request Prepared request.
 */
function contact_request( array $params ): \WP_REST_Request {
	$request = new \WP_REST_Request( 'POST', '/flavor/v1/contact' );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$request->set_json_params( $params );
	return $request;
}

$_SERVER['REMOTE_ADDR'] = '198.51.100.10';
$GLOBALS['_mock_sent_mail'] = array();

$response = \Flavor\Contact_Form::deliver( contact_request( array(
	'name'    => 'زهرا کریمی',
	'message' => 'سلام، برای پنجشنبه شب میز چهار نفره کنار پنجره دارید؟',
	'mobile'  => '09121234567',
	'email'   => '',
	'website' => '',
) ) );
$data = $response->get_data();
check( 200 === $response->get_status() && true === $data['success'], 'a valid message is delivered' );
check( 1 === count( $GLOBALS['_mock_sent_mail'] ) && 'hello@flavor.test' === $GLOBALS['_mock_sent_mail'][0]['to'], 'the mail goes to the configured store address' );
check(
	false !== strpos( $GLOBALS['_mock_sent_mail'][0]['body'], 'زهرا کریمی' ) && false !== strpos( $GLOBALS['_mock_sent_mail'][0]['body'], '09121234567' ),
	'the mail body carries the name, message and reply channel'
);
check( '' !== $data['data']['message'], 'the success response tells the visitor what happens next' );

$GLOBALS['_mock_sent_mail'] = array();
$response = \Flavor\Contact_Form::deliver( contact_request( array(
	'name'    => 'Ali',
	'message' => 'Do you cater corporate events for fifty guests?',
	'mobile'  => '',
	'email'   => 'ali@example.com',
	'website' => '',
) ) );
$headers = $GLOBALS['_mock_sent_mail'][0]['headers'] ?? array();
$reply   = '';
foreach ( $headers as $header ) {
	if ( 0 === strpos( $header, 'Reply-To:' ) ) {
		$reply = $header;
	}
}
check( '' !== $reply && false !== strpos( $reply, 'ali@example.com' ), 'an e-mail reply channel becomes the Reply-To header' );

$GLOBALS['_mock_sent_mail'] = array();
$response = \Flavor\Contact_Form::deliver( contact_request( array(
	'name'    => 'Bot',
	'message' => 'buy cheap things now',
	'mobile'  => '09121234567',
	'website' => 'http://spam.example',
) ) );
$data = $response->get_data();
check(
	true === $data['success'] && 0 === count( $GLOBALS['_mock_sent_mail'] ),
	'a filled honeypot reports success but sends nothing'
);

$response = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => '', 'message' => 'یک پیام بلند enough', 'mobile' => '09121234567' ) ) );
check( 400 === $response->get_status() && 'name_required' === $response->get_data()['code'], 'a missing name is refused with its machine code' );
$response = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => 'زهرا', 'message' => 'کوتاه', 'mobile' => '09121234567' ) ) );
check( 400 === $response->get_status() && 'message_short' === $response->get_data()['code'], 'a message under ten characters is refused' );
$response = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => 'زهرا', 'message' => 'یک پیام بلند enough', 'mobile' => '', 'email' => '' ) ) );
check( 400 === $response->get_status() && 'reply_required' === $response->get_data()['code'], 'no reply channel at all is refused' );
$response = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => 'زهرا', 'message' => 'یک پیام بلند enough', 'mobile' => '0912' ) ) );
$failure  = $response->get_data();
check(
	400 === $response->get_status() && 'mobile_invalid' === $failure['code'] && false !== strpos( $failure['message'], '۱۱ رقم' ),
	'an invalid mobile is refused with the 11-digit rule in the message'
);
$response = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => 'زهرا', 'message' => 'یک پیام بلند enough', 'email' => 'ali@@bad' ) ) );
check( 400 === $response->get_status() && 'email_invalid' === $response->get_data()['code'], 'an invalid e-mail is refused' );

unset( $GLOBALS['_mock_theme_mods']['flavor_ui_contact_email'] );
$response = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => 'زهرا', 'message' => 'یک پیام بلند enough', 'mobile' => '09121234567' ) ) );
check( 503 === $response->get_status() && 'recipient_missing' === $response->get_data()['code'], 'without a configured address the endpoint answers 503, clearly' );
$GLOBALS['_mock_theme_mods']['flavor_ui_contact_email'] = 'hello@flavor.test';

$GLOBALS['_mock_sent_mail'] = array();
$response = \Flavor\Contact_Form::deliver( contact_request( array(
	'name'    => 'حسین',
	'message' => '<script>alert(1)</script> پیام با تاسکریپت',
	'mobile'  => '09121234567',
) ) );
check(
	false === strpos( $GLOBALS['_mock_sent_mail'][0]['body'], '<script' ),
	'script tags never survive into the mail body'
);

$GLOBALS['_mock_mail_fail'] = true;
$_SERVER['REMOTE_ADDR']     = '198.51.100.11';
$response                   = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => 'زهرا', 'message' => 'یک پیام بلند enough', 'mobile' => '09121234567' ) ) );
check( 500 === $response->get_status() && 'mail_failed' === $response->get_data()['code'], 'a wp_mail failure is reported honestly as 500' );
unset( $GLOBALS['_mock_mail_fail'] );

$_SERVER['REMOTE_ADDR'] = '198.51.100.12';
$statuses               = array();
for ( $i = 0; $i < 4; ++$i ) {
	$response   = \Flavor\Contact_Form::deliver( contact_request( array( 'name' => 'زهرا', 'message' => 'یک پیام بلند enough', 'mobile' => '09121234567' ) ) );
	$statuses[] = $response->get_status();
}
check(
	array( 200, 200, 200, 429 ) === $statuses && 'rate_limited' === $response->get_data()['code'],
	'the fourth delivery from one IP inside ten minutes is rate-limited with 429'
);
unset( $_SERVER['REMOTE_ADDR'] );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
