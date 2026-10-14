<?php
/**
 * Smart search ranking, Persian normalisation, homophones, typos, and highlighting.
 *
 * @package FlavorCore
 */

use FlavorCore\Search\SmartSearch;
use FlavorCore\Search\SearchIndex;
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

	/**
	 * Real Persian food fixture documents for search testing.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_persian_documents(): array {
		return array(
			array(
				'id'           => 101,
				'name'         => 'چلوکباب کوبیده زعفرانی مخصوص',
				'name_norm'    => PersianText::normalize( 'چلوکباب کوبیده زعفرانی مخصوص' ),
				'name_tokens'  => PersianText::tokens( 'چلوکباب کوبیده زعفرانی مخصوص' ),
				'text_tokens'  => PersianText::tokens( 'چلوکباب کوبیده زعفرانی مخصوص دو سیخ گوشت گرم گوسفندی با برنج دودی ایرانی' ),
				'cat_tokens'   => PersianText::tokens( 'کباب اصیل ایرانی' ),
				'tag_tokens'   => PersianText::tokens( 'غذای سنتی کباب' ),
				'short'        => 'دو سیخ کباب کوبیده اعلا با برنج زعفرانی',
				'image'        => 'https://example.test/img/koobideh.jpg',
				'permalink'    => 'https://example.test/menu/koobideh',
				'price'        => 2800000,
				'price_html'   => '۲۸۰,۰۰۰ تومان',
				'prep_time'    => 25,
				'calories'     => 680,
				'dietary'      => array( 'حلال' ),
				'categories'   => array( array( 'id' => 10, 'name' => 'کباب سنتی', 'slug' => 'kebab' ) ),
				'available'    => true,
				'in_schedule'  => true,
				'available_at' => '',
				'popularity'   => 95,
				'rating'       => 4.9,
				'branch_id'    => 1,
			),
			array(
				'id'           => 102,
				'name'         => 'جوجه‌کباب با استخوان ارگانیک',
				'name_norm'    => PersianText::normalize( 'جوجه‌کباب با استخوان ارگانیک' ),
				'name_tokens'  => PersianText::tokens( 'جوجه‌کباب با استخوان ارگانیک' ),
				'text_tokens'  => PersianText::tokens( 'جوجه‌کباب با استخوان مرینیت زعفران و آب نارنج طبیعی' ),
				'cat_tokens'   => PersianText::tokens( 'کباب سنتی' ),
				'tag_tokens'   => PersianText::tokens( 'جوجه کباب مرغ' ),
				'short'        => 'جوجه کباب با استخوان زغالی',
				'image'        => 'https://example.test/img/joojeh.jpg',
				'permalink'    => 'https://example.test/menu/joojeh',
				'price'        => 2500000,
				'price_html'   => '۲۵۰,۰۰۰ تومان',
				'prep_time'    => 20,
				'calories'     => 540,
				'dietary'      => array( 'حلال' ),
				'categories'   => array( array( 'id' => 10, 'name' => 'کباب سنتی', 'slug' => 'kebab' ) ),
				'available'    => true,
				'in_schedule'  => true,
				'available_at' => '',
				'popularity'   => 85,
				'rating'       => 4.8,
				'branch_id'    => 1,
			),
			array(
				'id'           => 103,
				'name'         => 'خورش قورمه‌سبزی جاافتاده تهرانی',
				'name_norm'    => PersianText::normalize( 'خورش قورمه‌سبزی جاافتاده تهرانی' ),
				'name_tokens'  => PersianText::tokens( 'خورش قورمه‌سبزی جاافتاده تهرانی' ),
				'text_tokens'  => PersianText::tokens( 'خورش قورمه‌سبزی با سبزی معطر تازه لیمو عمانی و گوشت تکه‌ای گوسفندی' ),
				'cat_tokens'   => PersianText::tokens( 'خورشت ایرانی' ),
				'tag_tokens'   => PersianText::tokens( 'غذای محلی' ),
				'short'        => 'قورمه سبزی اصیل همراه با برنج طارم',
				'image'        => '',
				'permalink'    => 'https://example.test/menu/ghormeh',
				'price'        => 2200000,
				'price_html'   => '۲۲۰,۰۰۰ تومان',
				'prep_time'    => 15,
				'calories'     => 490,
				'dietary'      => array( 'حلال' ),
				'categories'   => array( array( 'id' => 20, 'name' => 'خورش ایرانی', 'slug' => 'khoresht' ) ),
				'available'    => true,
				'in_schedule'  => true,
				'available_at' => '',
				'popularity'   => 90,
				'rating'       => 4.95,
				'branch_id'    => 1,
			),
			array(
				'id'           => 104,
				'name'         => 'زرشک‌پلو با مرغ مجلسی',
				'name_norm'    => PersianText::normalize( 'زرشک‌پلو با مرغ مجلسی' ),
				'name_tokens'  => PersianText::tokens( 'زرشک‌پلو با مرغ مجلسی' ),
				'text_tokens'  => PersianText::tokens( 'زرشک‌پلو با سس انار زرشک اعلا زعفرانی و خلال پسته' ),
				'cat_tokens'   => PersianText::tokens( 'پلو سنتی' ),
				'tag_tokens'   => PersianText::tokens( 'زرشک پلو' ),
				'short'        => 'ران مرغ سس‌پز با برنج زعفرانی',
				'image'        => '',
				'permalink'    => 'https://example.test/menu/zereshk',
				'price'        => 2100000,
				'price_html'   => '۲۱۰,۰۰۰ تومان',
				'prep_time'    => 18,
				'calories'     => 620,
				'dietary'      => array( 'حلال' ),
				'categories'   => array( array( 'id' => 30, 'name' => 'پلو', 'slug' => 'polo' ) ),
				'available'    => true,
				'in_schedule'  => true,
				'available_at' => '',
				'popularity'   => 88,
				'rating'       => 4.7,
				'branch_id'    => 1,
			),
		);
	}

	/**
	 * Arabic vs Persian kaf and yeh normalization: «كباب» must rank identically to «کباب».
	 */
	public function test_persian_arabic_kaf_yeh_parity() {
		SearchIndex::flush();
		$docs = $this->sample_persian_documents();
		set_transient( 'flavor_search_idx_1', $docs, 3600 );

		$res_arabic  = SmartSearch::search( 'كباب', array( 'branch_id' => 1 ) );
		$res_persian = SmartSearch::search( 'کباب', array( 'branch_id' => 1 ) );

		$this->assertSame( count( $res_arabic['results'] ), count( $res_persian['results'] ) );
		$this->assertGreaterThan( 0, count( $res_arabic['results'] ) );
		$this->assertSame( $res_arabic['results'][0]['id'], $res_persian['results'][0]['id'] );
		$this->assertSame( $res_arabic['results'][0]['score'], $res_persian['results'][0]['score'] );

		// Arabic Yeh «كوبيده» vs Persian «کوبیده»
		$res_ar_yeh = SmartSearch::search( 'كوبيده', array( 'branch_id' => 1 ) );
		$res_fa_yeh = SmartSearch::search( 'کوبیده', array( 'branch_id' => 1 ) );
		$this->assertSame( 101, $res_ar_yeh['results'][0]['id'] );
		$this->assertSame( 101, $res_fa_yeh['results'][0]['id'] );
		$this->assertSame( $res_ar_yeh['results'][0]['score'], $res_fa_yeh['results'][0]['score'] );
	}

	/**
	 * Zero-width non-joiner (نیم‌فاصله) support: «جوجه‌کباب» must match «جوجه کباب» seamlessly.
	 */
	public function test_persian_half_space_zwnj_support() {
		SearchIndex::flush();
		$docs = $this->sample_persian_documents();
		set_transient( 'flavor_search_idx_1', $docs, 3600 );

		// With ZWNJ U+200C
		$res_zwnj  = SmartSearch::search( "جوجه\xE2\x80\x8Cکباب", array( 'branch_id' => 1 ) );
		// With regular space
		$res_space = SmartSearch::search( 'جوجه کباب', array( 'branch_id' => 1 ) );

		$this->assertGreaterThan( 0, count( $res_zwnj['results'] ) );
		$this->assertSame( 102, $res_zwnj['results'][0]['id'] );
		$this->assertSame( 102, $res_space['results'][0]['id'] );

		// زرشک‌پلو با نیم‌فاصله vs زرشک پلو
		$res_z_zwnj  = SmartSearch::search( "زرشک\xE2\x80\x8Cپلو", array( 'branch_id' => 1 ) );
		$res_z_space = SmartSearch::search( 'زرشک پلو', array( 'branch_id' => 1 ) );
		$this->assertSame( 104, $res_z_zwnj['results'][0]['id'] );
		$this->assertSame( 104, $res_z_space['results'][0]['id'] );
	}

	/**
	 * Typo and homophone tolerance: «قرمه» for «قورمه» and «کبیده» for «کوبیده».
	 */
	public function test_persian_typo_and_homophone_tolerance() {
		SearchIndex::flush();
		$docs = $this->sample_persian_documents();
		set_transient( 'flavor_search_idx_1', $docs, 3600 );

		// Typo «قرمه» matches «قورمه» with similarity >= FUZZY_THRESHOLD (0.80 >= 0.72)
		$res_ghormeh = SmartSearch::search( 'قرمه', array( 'branch_id' => 1 ) );
		$this->assertGreaterThan( 0, count( $res_ghormeh['results'] ) );
		$this->assertSame( 103, $res_ghormeh['results'][0]['id'] );
		$this->assertStringContainsString( 'قورمه‌سبزی', $res_ghormeh['results'][0]['name'] );

		// Typo «کبیده» for «کوبیده» (similarity 0.833 >= 0.72)
		$res_koobideh = SmartSearch::search( 'کبیده', array( 'branch_id' => 1 ) );
		$this->assertGreaterThan( 0, count( $res_koobideh['results'] ) );
		$this->assertSame( 101, $res_koobideh['results'][0]['id'] );
		$this->assertStringContainsString( 'کوبیده', $res_koobideh['results'][0]['name'] );
	}

	/**
	 * Result limit cap: max allowed results is hard-capped at 30 items.
	 */
	public function test_result_limit_ceiling_cap() {
		SearchIndex::flush();
		// Populate 40 distinct mock documents
		$docs = array();
		for ( $i = 1; $i <= 40; $i++ ) {
			$docs[] = array(
				'id'           => 200 + $i,
				'name'         => "غذای تستی شماره $i",
				'name_norm'    => PersianText::normalize( "غذای تستی شماره $i" ),
				'name_tokens'  => PersianText::tokens( "غذای تستی شماره $i" ),
				'text_tokens'  => array(),
				'cat_tokens'   => array(),
				'tag_tokens'   => array(),
				'short'        => '',
				'image'        => '',
				'permalink'    => '',
				'price'        => 100000,
				'price_html'   => '۱۰,۰۰۰ تومان',
				'prep_time'    => 10,
				'calories'     => 100,
				'dietary'      => array(),
				'categories'   => array(),
				'available'    => true,
				'in_schedule'  => true,
				'available_at' => '',
				'popularity'   => $i,
				'rating'       => 4.5,
				'branch_id'    => 1,
			);
		}
		set_transient( 'flavor_search_idx_1', $docs, 3600 );

		// Request 100 items — must be clamped to 30
		$res_large = SmartSearch::search( '', array( 'branch_id' => 1, 'limit' => 100 ) );
		$this->assertSame( 40, $res_large['total'] );
		$this->assertSame( 30, count( $res_large['results'] ) );
		$this->assertLessThanOrEqual( 30, count( $res_large['results'] ) );

		// Minimum limit floor is 1
		$res_small = SmartSearch::search( '', array( 'branch_id' => 1, 'limit' => 0 ) );
		$this->assertSame( 1, count( $res_small['results'] ) );
	}

	/**
	 * Autocomplete suggest endpoint terms for Arabic kaf and half-space queries.
	 */
	public function test_suggest_terms_normalisation() {
		SearchIndex::flush();
		$docs = $this->sample_persian_documents();
		set_transient( 'flavor_search_idx_1', $docs, 3600 );

		// Suggest with Arabic kaf «كباب»
		$terms_ar = SmartSearch::suggest( 'كباب', 1 );
		// Suggest with Persian kaf «کباب»
		$terms_fa = SmartSearch::suggest( 'کباب', 1 );

		$this->assertSame( $terms_ar, $terms_fa );
		$this->assertGreaterThan( 0, count( $terms_ar ) );

		// Suggest with half-space
		$terms_zwnj  = SmartSearch::suggest( "جوجه\xE2\x80\x8Cکباب", 1 );
		$terms_space = SmartSearch::suggest( 'جوجه کباب', 1 );
		$this->assertSame( $terms_zwnj, $terms_space );
		$this->assertStringContainsString( 'جوجه', $terms_zwnj[0] );
	}
}
