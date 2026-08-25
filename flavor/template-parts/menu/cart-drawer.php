<?php
/**
 * Cart drawer + quick checkout.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;
?>
<aside class="flavor-cart" id="flavor-cart">
	<button type="button" class="flavor-cart__handle" id="flavor-cart-toggle">
		<?php esc_html_e( 'سبد خرید', 'flavor' ); ?>
		<span id="flavor-cart-count">0</span>
	</button>
	<div class="flavor-cart__panel" id="flavor-cart-panel" hidden>
		<h2><?php esc_html_e( 'سبد شما', 'flavor' ); ?></h2>
		<div class="flavor-modes" id="flavor-modes">
			<button type="button" data-mode="dine_in"><?php esc_html_e( 'سالن', 'flavor' ); ?></button>
			<button type="button" data-mode="takeaway"><?php esc_html_e( 'بیرون‌بر', 'flavor' ); ?></button>
			<button type="button" data-mode="delivery"><?php esc_html_e( 'ارسال', 'flavor' ); ?></button>
		</div>
		<div id="flavor-cart-lines"></div>
		<p class="flavor-cart__total" id="flavor-cart-total"></p>
		<form id="flavor-checkout" class="flavor-checkout">
			<label><?php esc_html_e( 'نام', 'flavor' ); ?>
				<input type="text" name="name" id="flavor-name" autocomplete="name" />
			</label>
			<label><?php esc_html_e( 'موبایل', 'flavor' ); ?>
				<input type="tel" name="mobile" id="flavor-mobile" dir="ltr" inputmode="tel" placeholder="09xxxxxxxxx" required />
			</label>
			<div class="flavor-otp" id="flavor-otp-box">
				<button type="button" id="flavor-otp-send"><?php esc_html_e( 'ارسال کد ورود', 'flavor' ); ?></button>
				<input type="text" id="flavor-otp-code" inputmode="numeric" maxlength="6" placeholder="<?php esc_attr_e( 'کد', 'flavor' ); ?>" hidden />
			</div>
			<div id="flavor-table-box" hidden>
				<label><?php esc_html_e( 'شماره میز', 'flavor' ); ?>
					<select id="flavor-table"></select>
				</label>
			</div>
			<div id="flavor-address-box" hidden>
				<label><?php esc_html_e( 'شهر', 'flavor' ); ?>
					<input type="text" id="flavor-city" />
				</label>
				<label><?php esc_html_e( 'محله', 'flavor' ); ?>
					<input type="text" id="flavor-hood" />
				</label>
				<label><?php esc_html_e( 'نشانی', 'flavor' ); ?>
					<input type="text" id="flavor-line" />
				</label>
				<p class="flavor-zone-msg" id="flavor-zone-msg"></p>
			</div>
			<fieldset id="flavor-pay-box">
				<legend><?php esc_html_e( 'پرداخت', 'flavor' ); ?></legend>
			</fieldset>
			<p class="flavor-checkout__err" id="flavor-checkout-err" hidden></p>
			<button type="submit" class="flavor-btn flavor-btn--primary" id="flavor-place">
				<?php esc_html_e( 'ثبت سفارش', 'flavor' ); ?>
			</button>
		</form>
	</div>
</aside>
