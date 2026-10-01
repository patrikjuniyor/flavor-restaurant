<?php
/** Accessible product detail sheet; no product is added just by opening it. @package Flavor */
defined( 'ABSPATH' ) || exit;
?>
<div class="flavor-sheet" id="flavor-sheet" hidden>
	<div class="flavor-sheet__backdrop" data-close="sheet" aria-hidden="true"></div>
	<div class="flavor-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="flavor-sheet-title" tabindex="-1">
		<button type="button" class="flavor-sheet__close flavor-ui-icon-button" data-close="sheet" aria-label="<?php esc_attr_e( 'بستن جزئیات غذا', 'flavor' ); ?>"><?php \Flavor\UI::icon( 'close' ); ?></button>
		<div class="flavor-sheet__content">
			<div class="flavor-sheet__visual" id="flavor-sheet-visual"></div>
			<div class="flavor-sheet__copy"><h2 id="flavor-sheet-title"></h2><div id="flavor-sheet-body"></div></div>
		</div>
		<div class="flavor-sheet__footer">
			<div class="flavor-qty" role="group" aria-label="<?php esc_attr_e( 'تعداد غذا', 'flavor' ); ?>"><button type="button" data-q="-1" aria-label="<?php esc_attr_e( 'کاهش تعداد', 'flavor' ); ?>">−</button><output id="flavor-qty" aria-live="polite">۱</output><button type="button" data-q="1" aria-label="<?php esc_attr_e( 'افزایش تعداد', 'flavor' ); ?>">+</button></div>
			<div class="flavor-sheet__footer-price"><span class="flavor-sheet__footer-label"><?php esc_html_e( 'مبلغ با انتخاب‌های شما', 'flavor' ); ?></span><strong class="flavor-sheet__price" id="flavor-sheet-price"></strong></div>
			<button type="button" class="flavor-btn flavor-btn--primary" id="flavor-sheet-add"><?php \Flavor\UI::icon( 'plus', 18 ); ?><?php esc_html_e( 'افزودن به سبد', 'flavor' ); ?></button>
		</div>
	</div>
</div>
