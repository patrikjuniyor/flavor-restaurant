<?php
/**
 * Template Name: منوی سفارش
 * Template Post Type: page
 * @package Flavor
 */
defined( 'ABSPATH' ) || exit;
$context = \Flavor\UI::context();
$labels  = \Flavor\UI::mode_labels();
get_header();
?>
<div class="flavor-container flavor-menu-page" id="flavor-menu-root">
	<nav class="flavor-ui-breadcrumb" aria-label="<?php esc_attr_e( 'مسیر صفحه', 'flavor' ); ?>"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'flavor' ); ?></a><span aria-hidden="true">/</span><span aria-current="page"><?php esc_html_e( 'منو', 'flavor' ); ?></span></nav>
	<div class="flavor-menu-masthead">
		<div><span class="flavor-ui-overline"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span><h1><?php esc_html_e( 'امروز، به سلیقهٔ شما.', 'flavor' ); ?></h1><p class="flavor-menu-masthead__text"><?php esc_html_e( 'ترکیبات را ببینید، انتخابتان را شخصی کنید و بعد به سبد اضافه کنید. قیمت نهایی پیش از افزودن نمایش داده می‌شود.', 'flavor' ); ?></p></div>
		<?php if ( $context['id'] ) : ?>
		<div class="flavor-menu-context"><span class="flavor-menu-context__icon"><?php \Flavor\UI::icon( 'pin', 24 ); ?></span><div><strong><?php echo esc_html( $context['name'] ); ?></strong><small><?php echo esc_html( implode( ' · ', array_intersect_key( $labels, array_flip( $context['modes'] ) ) ) ); ?></small><?php if ( $context['table'] ) : ?><small class="flavor-menu-context__table"><?php echo esc_html( sprintf( __( 'میز %s', 'flavor' ), \Flavor\Bespoke_Demos::digits( $context['table'] ) ) ); ?></small><?php endif; ?></div></div>
		<?php endif; ?>
	</div>
	<div class="flavor-menu-tools">
		<?php get_template_part( 'template-parts/menu/smart-search' ); ?>
		<div class="flavor-menu-view" role="group" aria-label="<?php esc_attr_e( 'شیوهٔ نمایش منو', 'flavor' ); ?>" data-ui-menu-view hidden><button type="button" data-ui-view="grid" aria-pressed="true" aria-label="<?php esc_attr_e( 'نمایش کارتی', 'flavor' ); ?>"><?php \Flavor\UI::icon( 'grid' ); ?></button><button type="button" data-ui-view="list" aria-pressed="false" aria-label="<?php esc_attr_e( 'نمایش فهرستی', 'flavor' ); ?>"><?php \Flavor\UI::icon( 'list' ); ?></button></div>
	</div>
	<?php get_template_part( 'template-parts/menu/category-nav' ); ?>
	<?php get_template_part( 'template-parts/menu/filters' ); ?>
	<?php get_template_part( 'template-parts/menu/item-card' ); ?>
	<?php get_template_part( 'template-parts/menu/item-modal' ); ?>
	<?php if ( \Flavor\Theme_Setup::has_core() ) { get_template_part( 'template-parts/menu/cart-drawer' ); } ?>
</div>
<?php get_footer(); ?>
