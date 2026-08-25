<?php
/**
 * Reservation form — Jalali calendar + slots.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

$has_core = defined( 'FLAVOR_CORE_VERSION' );
?>
<div class="flavor-container flavor-res" id="flavor-res" <?php echo $has_core ? '' : ''; ?>>
	<h1><?php esc_html_e( 'رزرو میز', 'flavor' ); ?></h1>
	<?php if ( ! $has_core ) : ?>
		<p><?php esc_html_e( 'برای رزرو، افزونه Flavor Core را فعال کنید.', 'flavor' ); ?></p>
	<?php else : ?>
		<form id="flavor-res-form" class="flavor-checkout">
			<label><?php esc_html_e( 'شعبه', 'flavor' ); ?>
				<select id="flavor-res-branch"></select>
			</label>
			<label><?php esc_html_e( 'تعداد نفرات', 'flavor' ); ?>
				<input type="number" id="flavor-res-party" value="2" min="1" max="20" />
			</label>
			<label><?php esc_html_e( 'ترجیح بخش', 'flavor' ); ?>
				<select id="flavor-res-section">
					<option value=""><?php esc_html_e( 'فرقی ندارد', 'flavor' ); ?></option>
					<option value="indoor"><?php esc_html_e( 'سالن', 'flavor' ); ?></option>
					<option value="outdoor"><?php esc_html_e( 'فضای باز', 'flavor' ); ?></option>
					<option value="window"><?php esc_html_e( 'کنار پنجره', 'flavor' ); ?></option>
					<option value="bar"><?php esc_html_e( 'بار', 'flavor' ); ?></option>
				</select>
			</label>
			<div class="flavor-cal" id="flavor-cal">
				<div class="flavor-cal__nav">
					<button type="button" id="flavor-cal-prev">‹</button>
					<strong id="flavor-cal-title"></strong>
					<button type="button" id="flavor-cal-next">›</button>
				</div>
				<div class="flavor-cal__week" id="flavor-cal-week"></div>
				<div class="flavor-cal__grid" id="flavor-cal-grid"></div>
			</div>
			<div id="flavor-res-slots" class="flavor-slots"></div>
			<label><?php esc_html_e( 'نام', 'flavor' ); ?>
				<input type="text" id="flavor-res-name" required />
			</label>
			<label><?php esc_html_e( 'موبایل', 'flavor' ); ?>
				<input type="tel" id="flavor-res-mobile" dir="ltr" required placeholder="09xxxxxxxxx" />
			</label>
			<label><?php esc_html_e( 'توضیحات', 'flavor' ); ?>
				<input type="text" id="flavor-res-note" maxlength="200" />
			</label>
			<p class="flavor-checkout__err" id="flavor-res-err" hidden></p>
			<p id="flavor-res-ok" hidden></p>
			<button type="submit" class="flavor-btn flavor-btn--primary"><?php esc_html_e( 'ثبت درخواست رزرو', 'flavor' ); ?></button>
		</form>
	<?php endif; ?>
</div>
