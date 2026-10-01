<?php
/** Server-rendered fallback replaced by live branch availability after JS loads. @package Flavor */
defined( 'ABSPATH' ) || exit;
$products = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'status' => 'publish', 'limit' => 100, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) : array();
?>
<section class="flavor-menu" aria-label="<?php esc_attr_e( 'غذاها و نوشیدنی‌ها', 'flavor' ); ?>">
	<p class="flavor-menu__status" id="flavor-menu-status" role="status" aria-live="polite"><?php if ( ! \Flavor\Theme_Setup::has_core() ) { esc_html_e( 'سفارش سریع فعلاً فعال نیست؛ جزئیات محصولات را ببینید یا برای هماهنگی تماس بگیرید.', 'flavor' ); } ?></p>
	<div class="flavor-menu-retry" id="flavor-menu-retry" hidden><button type="button" class="flavor-btn flavor-btn--outline" id="flavor-menu-reload"><?php esc_html_e( 'تلاش دوباره', 'flavor' ); ?></button></div>
	<div class="flavor-menu__grid" id="flavor-menu-grid">
		<?php foreach ( $products as $product ) : $image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'medium_large' ) : ''; ?>
		<article class="flavor-card flavor-food-card" data-id="<?php echo esc_attr( (string) $product->get_id() ); ?>">
			<?php if ( $image ) : ?><a class="flavor-food-card__media" href="<?php echo esc_url( $product->get_permalink() ); ?>"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" width="600" height="400" loading="lazy" /></a><?php endif; ?>
			<div class="flavor-food-card__body"><h2 class="flavor-food-card__title"><a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h2><p class="flavor-food-card__desc"><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p><div class="flavor-food-card__footer"><strong class="flavor-food-card__price"><?php echo wp_kses_post( \Flavor\Bespoke_Demos::price( $product->get_price() ) ); ?></strong><a class="flavor-btn flavor-btn--outline flavor-btn--sm" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php esc_html_e( 'جزئیات محصول', 'flavor' ); ?></a></div></div>
		</article>
		<?php endforeach; ?>
	</div>
	<?php if ( ! $products ) : ?><div class="flavor-ui-empty" id="flavor-menu-empty"><?php \Flavor\UI::icon( 'menu', 38 ); ?><h2><?php esc_html_e( 'منو هنوز آماده نیست.', 'flavor' ); ?></h2><p><?php esc_html_e( 'هنوز محصولی برای نمایش منتشر نشده است. برای اطلاع از منو با مجموعه تماس بگیرید.', 'flavor' ); ?></p></div><?php endif; ?>
	<noscript><p class="flavor-menu-static-note"><?php esc_html_e( 'برای سفارش سریع و بررسی موجودی شعبه، JavaScript را فعال کنید. در این حالت جزئیات منتشرشدهٔ محصول از ووکامرس قابل مشاهده است.', 'flavor' ); ?></p></noscript>
</section>
