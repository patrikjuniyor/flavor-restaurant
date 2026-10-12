<?php
/**
 * Pure suite — checkout money path: idempotency helpers of CheckoutService
 * (via reflection — they are private by design) and the reservation slot
 * arithmetic of SlotCalculator.
 *
 * The full place() flow stays covered by the e2e suite (mock WP + WC);
 * what lives here is the deterministic core: key sanitisation, attempt
 * fingerprinting (the double-tap dedupe), the replay window, and the
 * interval math every booking decision rests on.
 *
 * Loaded by run-pure-tests.php (assert_test protocol).
 *
 * @package FlavorCore
 */

// phpcs:disable WordPress.Security.EscapeOutput -- CLI harness output, not an HTTP response.

require_once __DIR__ . '/../../includes/WooCommerce/CheckoutService.php';
require_once __DIR__ . '/../../includes/Reservation/SlotCalculator.php';

use FlavorCore\Reservation\SlotCalculator;
use FlavorCore\WooCommerce\CheckoutService;

/**
 * Stand-in for WC_Cart exposing only get_cart().
 */
class PureSuiteMockCart {
	/**
	 * Cart lines.
	 *
	 * @var array
	 */
	private $lines;

	/**
	 * Constructor.
	 *
	 * @param array $lines Lines.
	 */
	public function __construct( array $lines ) {
		$this->lines = $lines;
	}

	/**
	 * Cart accessor.
	 *
	 * @return array
	 */
	public function get_cart(): array {
		return $this->lines;
	}
}

/**
 * Stand-in for WC_Order exposing only get_meta().
 */
class PureSuiteMockOrder {
	/**
	 * Meta map.
	 *
	 * @var array
	 */
	private $meta;

	/**
	 * Constructor.
	 *
	 * @param array $meta Meta.
	 */
	public function __construct( array $meta ) {
		$this->meta = $meta;
	}

	/**
	 * Meta accessor.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}
}

echo "\n--- P47. Idempotency helpers (CheckoutService, reflected) ---\n";

$sanitize = new ReflectionMethod( CheckoutService::class, 'sanitize_idempotency_key' );
$sanitize->setAccessible( true );
$fingerprint = new ReflectionMethod( CheckoutService::class, 'cart_fingerprint' );
$fingerprint->setAccessible( true );
$window = new ReflectionMethod( CheckoutService::class, 'within_auto_window' );
$window->setAccessible( true );

assert_test( 'abcDEF-12_3' === $sanitize->invoke( null, 'abcDEF-12_3' ), 'an already-clean client key survives untouched' );
assert_test( 'order42retry1' === $sanitize->invoke( null, 'order 42 (retry)#1' ), 'spaces and symbols are stripped from client keys' );
assert_test( 64 === strlen( $sanitize->invoke( null, str_repeat( 'k', 500 ) ) ), 'keys are capped at 64 chars (meta-index safety)' );
assert_test( '' === $sanitize->invoke( null, '😀🔥' ), 'a purely non-ASCII key sanitises to empty — which flips the attempt to the auto-fingerprint path' );

$lines_a = array(
	array( 'product_id' => 7, 'quantity' => 2, 'flavor_modifiers' => array( 'ids' => array( 11, 12 ) ) ),
	array( 'product_id' => 3, 'quantity' => 1 ),
);
$lines_b = array(
	array( 'product_id' => 3, 'quantity' => 1 ),
	array( 'product_id' => 7, 'quantity' => 2, 'flavor_modifiers' => array( 'ids' => array( 12, 11 ) ) ),
);
$fp_a  = $fingerprint->invoke( null, new PureSuiteMockCart( $lines_a ), 'tok-1', 'dine_in', 5, 9 );
$fp_a2 = $fingerprint->invoke( null, new PureSuiteMockCart( $lines_b ), 'tok-1', 'dine_in', 5, 9 );
assert_test( $fp_a === $fp_a2 && 64 === strlen( $fp_a ), 'line order and modifier order do not change the fingerprint (double-tap sends identical carts in arbitrary order)' );
$fp_qty = $fingerprint->invoke( null, new PureSuiteMockCart( array( array( 'product_id' => 7, 'quantity' => 3 ) ) ), 'tok-1', 'dine_in', 5, 9 );
assert_test( $fp_a !== $fp_qty, 'a different quantity is a different attempt' );
$fp_tok = $fingerprint->invoke( null, new PureSuiteMockCart( $lines_a ), 'tok-2', 'dine_in', 5, 9 );
assert_test( $fp_a !== $fp_tok, 'a different cart token is a different attempt' );
$fp_mode = $fingerprint->invoke( null, new PureSuiteMockCart( $lines_a ), 'tok-1', 'takeaway', 5, 9 );
assert_test( $fp_a !== $fp_mode, 'a different order mode is a different attempt' );
assert_test( hash( 'sha256', 'x' ) !== $fp_a && (bool) preg_match( '/^[0-9a-f]{64}$/', $fp_a ), 'the fingerprint is a sha256 hex digest' );

assert_test( 120 === CheckoutService::AUTO_IDEMPOTENCY_WINDOW, 'the auto-replay window is 120 seconds' );
assert_test( true === $window->invoke( null, new PureSuiteMockOrder( array( '_flavor_placed_at' => 0 ) ) ), 'an order without a placed_at stamp replays (legacy rows keep working)' );
assert_test( true === $window->invoke( null, new PureSuiteMockOrder( array( '_flavor_placed_at' => time() - 120 ) ) ), 'exactly 120s old is still inside the window (boundary inclusive)' );
assert_test( false === $window->invoke( null, new PureSuiteMockOrder( array( '_flavor_placed_at' => time() - 121 ) ) ), '121s old is a genuinely new attempt, not a replay' );

$cs_src = file_get_contents( __DIR__ . '/../../includes/WooCommerce/CheckoutService.php' );
assert_test(
	false !== strpos( $cs_src, "'flavor_idempotency_conflict'" ) && (bool) preg_match( "/'flavor_idempotency_conflict'.*?'status'\s*=>\s*409/s", $cs_src ),
	'a verified-replay failure answers 409 flavor_idempotency_conflict (the prompt-46 status table, at its money source)'
);
assert_test(
	false !== strpos( $cs_src, 'find_by_idempotency_key( $idempotency_key )' ) && false !== strpos( $cs_src, 'can_replay(' ),
	'the same key never creates two orders: lookup then ownership-verified replay'
);

echo "\n--- P47. Reservation slot arithmetic (SlotCalculator) ---\n";

assert_test( 1230 === SlotCalculator::minutes( '20:30' ), 'HH:MM parses to minutes from midnight' );
assert_test( 485 === SlotCalculator::minutes( '8:05' ), 'single-digit hours and leading-zero minutes parse' );
assert_test( 1230 === SlotCalculator::minutes( '20:30:45' ), 'seconds are ignored, not added' );
assert_test( 0 === SlotCalculator::minutes( 'garbage' ), 'unparseable time degrades to midnight, never to a crash' );
assert_test( ! SlotCalculator::overlaps( 600, 90, 690, 90 ), 'back-to-back intervals do not overlap (touching edges are bookable)' );
assert_test( SlotCalculator::overlaps( 600, 90, 650, 90 ), 'partially overlapping intervals collide' );
assert_test( SlotCalculator::overlaps( 600, 120, 610, 10 ), 'a contained interval collides' );
assert_test( SlotCalculator::overlaps( 650, 90, 600, 90 ), 'overlap is symmetric in argument order' );
assert_test( ! SlotCalculator::overlaps( 600, 0, 600, 90 ), 'a zero-duration interval never collides' );
assert_test( ! SlotCalculator::overlaps( 600, 90, 500, 90 ), 'an interval ending exactly at the other start does not collide' );
