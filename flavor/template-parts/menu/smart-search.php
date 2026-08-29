<?php
/**
 * Smart AJAX search box for the menu page.
 *
 * Progressive enhancement: without JS the form still submits to the site search.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="flavor-search" id="flavor-search" data-flavor-search>
	<form class="flavor-search__form" role="search" method="get"
		action="<?php echo esc_url( home_url( '/' ) ); ?>" autocomplete="off">
		<label class="screen-reader-text" for="flavor-search-input">
			<?php esc_html_e( 'جست‌وجوی هوشمند در منو', 'flavor' ); ?>
		</label>

		<div class="flavor-search__field">
			<svg class="flavor-search__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
				<circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"></circle>
				<line x1="16.5" y1="16.5" x2="21" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>
			</svg>

			<input
				type="search"
				id="flavor-search-input"
				name="s"
				class="flavor-search__input"
				placeholder="<?php esc_attr_e( 'مثلاً: کباب کوبیده، پیتزا بدون پنیر، نوشیدنی سرد…', 'flavor' ); ?>"
				role="combobox"
				aria-expanded="false"
				aria-autocomplete="list"
				aria-controls="flavor-search-results"
				aria-describedby="flavor-search-hint"
			/>

			<button type="button" class="flavor-search__clear" id="flavor-search-clear" hidden
				aria-label="<?php esc_attr_e( 'پاک کردن جست‌وجو', 'flavor' ); ?>">×</button>

			<span class="flavor-search__spinner" id="flavor-search-spinner" hidden aria-hidden="true"></span>
		</div>

		<p class="flavor-search__hint" id="flavor-search-hint">
			<?php esc_html_e( 'غلط املایی، «ي» و «ك» عربی و اعداد فارسی هم پشتیبانی می‌شود.', 'flavor' ); ?>
		</p>

		<div class="flavor-search__chips" id="flavor-search-chips" hidden></div>
	</form>

	<div class="flavor-search__panel" id="flavor-search-panel" hidden>
		<p class="flavor-search__status" id="flavor-search-status" role="status" aria-live="polite"></p>
		<ul class="flavor-search__results" id="flavor-search-results" role="listbox"
			aria-label="<?php esc_attr_e( 'نتایج جست‌وجو', 'flavor' ); ?>"></ul>
	</div>
</section>
