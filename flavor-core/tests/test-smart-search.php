<?php
/**
 * Smart search ranking and highlighting.
 *
 * @package FlavorCore
 */

use FlavorCore\Search\SmartSearch;
use FlavorCore\Support\PersianText;

class Flavor_Smart_Search_Test extends WP_UnitTestCase {

	public function test_highlight_wraps_matching_word() {
		$html = SmartSearch::highlight( 'کباب کوبیده', PersianText::tokens( 'کوبیده' ) );
		$this->assertStringContainsString( '<mark>کوبیده</mark>', $html );
		$this->assertStringContainsString( 'کباب', $html );
	}

	public function test_highlight_escapes_html() {
		$html = SmartSearch::highlight( '<b>پیتزا</b>', array() );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_highlight_tolerates_typos() {
		$html = SmartSearch::highlight( 'قورمه سبزی', PersianText::tokens( 'قرمه' ) );
		$this->assertStringContainsString( '<mark>', $html );
	}

	public function test_log_ignores_empty_result_sets() {
		delete_option( SmartSearch::LOG_OPTION );
		SmartSearch::log( 'سوشی', 0 );
		$this->assertSame( array(), (array) get_option( SmartSearch::LOG_OPTION, array() ) );
	}

	public function test_log_counts_successful_queries() {
		delete_option( SmartSearch::LOG_OPTION );
		SmartSearch::log( 'کباب', 3 );
		SmartSearch::log( 'كباب', 3 );
		$log = (array) get_option( SmartSearch::LOG_OPTION, array() );
		$this->assertSame( 2, (int) ( $log['کباب'] ?? 0 ) );
	}

	public function test_search_returns_expected_shape() {
		$payload = SmartSearch::search( 'چیزی که نیست', array( 'limit' => 5 ) );
		$this->assertArrayHasKey( 'results', $payload );
		$this->assertArrayHasKey( 'facets', $payload );
		$this->assertArrayHasKey( 'suggestion', $payload );
		$this->assertArrayHasKey( 'total', $payload );
	}
}
