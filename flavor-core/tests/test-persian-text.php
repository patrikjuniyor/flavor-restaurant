<?php
/**
 * Persian text normalisation used by the smart search.
 *
 * @package FlavorCore
 */

use FlavorCore\Support\PersianText;

class Flavor_Persian_Text_Test extends WP_UnitTestCase {

	public function test_folds_arabic_letters() {
		$this->assertSame( 'کباب کوبیده', PersianText::normalize( 'كباب كوبيده' ) );
	}

	public function test_converts_persian_digits() {
		$this->assertSame( 'سیخ 2', PersianText::normalize( 'سیخ ۲' ) );
	}

	public function test_strips_diacritics_and_punctuation() {
		$this->assertSame( 'قرمه سبزی', PersianText::normalize( 'قُرمه‌سبزی!' ) );
	}

	public function test_tokens_drop_stop_words() {
		$tokens = PersianText::tokens( 'پیتزا با پنیر' );
		$this->assertContains( 'پیتزا', $tokens );
		$this->assertContains( 'پنیر', $tokens );
		$this->assertNotContains( 'با', $tokens );
	}

	public function test_stem_removes_plural_suffix() {
		$this->assertSame( 'نوشیدنی', PersianText::stem( 'نوشیدنیها' ) );
	}

	public function test_similarity_is_typo_tolerant() {
		$this->assertGreaterThan( 0.8, PersianText::similarity( 'کوبیده', 'کبیده' ) );
		$this->assertSame( 1.0, PersianText::similarity( 'پیتزا', 'پیتزا' ) );
		$this->assertLessThan( 0.5, PersianText::similarity( 'پیتزا', 'دلستر' ) );
	}

	public function test_levenshtein_is_utf8_aware() {
		$this->assertSame( 1, PersianText::levenshtein_utf8( 'سالاد', 'سلاد' ) );
	}
}
