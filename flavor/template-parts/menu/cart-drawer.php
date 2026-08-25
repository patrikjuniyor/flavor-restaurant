<?php
/**
 * Cart drawer host.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;
?>
<aside class="flavor-cart" id="flavor-cart" hidden>
	<button type="button" class="flavor-cart__handle" id="flavor-cart-toggle">
		<?php esc_html_e( 'سبد خرید', 'flavor' ); ?>
		<span id="flavor-cart-count">0</span>
	</button>
	<div class="flavor-cart__panel">
		<h2><?php esc_html_e( 'سبد شما', 'flavor' ); ?></h2>
		<div id="flavor-cart-lines"></div>
		<p class="flavor-cart__total" id="flavor-cart-total"></p>
	</div>
</aside>
