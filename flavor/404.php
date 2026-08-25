<?php
/**
 * 404.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="flavor-container">
	<h1><?php esc_html_e( 'صفحه پیدا نشد', 'flavor' ); ?></h1>
	<p><?php esc_html_e( 'این آدرس وجود ندارد. از منو یک صفحه دیگر را انتخاب کنید.', 'flavor' ); ?></p>
</div>
<?php
get_footer();
