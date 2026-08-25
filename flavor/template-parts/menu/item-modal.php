<?php
/**
 * Bottom-sheet modifier modal.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="flavor-sheet" id="flavor-sheet" hidden>
	<div class="flavor-sheet__backdrop" data-close="sheet"></div>
	<div class="flavor-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="flavor-sheet-title">
		<button type="button" class="flavor-sheet__close" data-close="sheet">&times;</button>
		<h2 id="flavor-sheet-title"></h2>
		<div id="flavor-sheet-body"></div>
		<button type="button" class="flavor-btn flavor-btn--primary" id="flavor-sheet-add">
			<?php esc_html_e( 'افزودن به سبد', 'flavor' ); ?>
		</button>
	</div>
</div>
