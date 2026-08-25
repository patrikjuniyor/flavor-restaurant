<?php
/**
 * Menu grid host. Cards are rendered client-side from REST.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="flavor-menu" aria-live="polite">
	<p class="flavor-menu__status" id="flavor-menu-status"><?php esc_html_e( 'در حال بارگذاری منو…', 'flavor' ); ?></p>
	<div class="flavor-menu__grid" id="flavor-menu-grid"></div>
</section>
