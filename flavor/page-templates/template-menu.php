<?php
/**
 * Template Name: منوی سفارش
 * Template Post Type: page
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="flavor-menu-page" id="flavor-menu-root">
	<?php get_template_part( 'template-parts/menu/smart-search' ); ?>
	<?php get_template_part( 'template-parts/menu/category-nav' ); ?>
	<?php get_template_part( 'template-parts/menu/item-card' ); ?>
	<?php get_template_part( 'template-parts/menu/item-modal' ); ?>
	<?php get_template_part( 'template-parts/menu/cart-drawer' ); ?>
</div>
<?php
get_footer();
