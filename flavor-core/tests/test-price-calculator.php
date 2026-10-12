<?php
/**
 * Price / modifier / currency tests (run inside a WP test install).
 *
 * @package FlavorCore
 */

class Flavor_Price_Calculator_Test extends WP_UnitTestCase {

	public function test_rial_to_toman_floors() {
		$this->assertSame( 250000, \FlavorCore\WooCommerce\Currency::convert( 2500000, 'irr', 'irt' ) );
		$this->assertSame( 250000, \FlavorCore\WooCommerce\Currency::convert( 2500001, 'irr', 'irt' ) );
		$this->assertSame( 2500000, \FlavorCore\WooCommerce\Currency::convert( 250000, 'irt', 'irr' ) );
	}

	public function test_same_unit_is_noop() {
		$this->assertSame( 42, \FlavorCore\WooCommerce\Currency::convert( 42, 'irr', 'irr' ) );
	}

	public function test_persian_digits() {
		// Digit-only contract: the Persian thousands separator (٬) comes from
		// number_format_i18n() at display time (Currency::format), never from this
		// converter — punctuation must survive the round trip unchanged.
		$this->assertSame( '۲۵,۰۰۰', \FlavorCore\WooCommerce\Currency::to_persian_digits( '25,000' ) );
		$this->assertSame( '25000', \FlavorCore\WooCommerce\Currency::to_latin_digits( '۲۵۰۰۰' ) );
		$this->assertSame( '25,000', \FlavorCore\WooCommerce\Currency::to_latin_digits( \FlavorCore\WooCommerce\Currency::to_persian_digits( '25,000' ) ) );
	}

	public function test_mobile_normalize() {
		$this->assertSame( '09121234567', \FlavorCore\Support\Iran::normalize_mobile( '+98 912 123 4567' ) );
		$this->assertSame( '09121234567', \FlavorCore\Support\Iran::normalize_mobile( '00989121234567' ) );
		$this->assertSame( '', \FlavorCore\Support\Iran::normalize_mobile( '12345' ) );
	}

	public function test_modifier_extra_sum() {
		$sum = \FlavorCore\WooCommerce\ProductModifiers::selection_extra(
			array(
				array( 'price' => 150000 ),
				array( 'price' => 100000 ),
			)
		);
		$this->assertSame( 250000, $sum );
	}
}
