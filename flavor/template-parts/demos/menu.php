<?php
/** Live WooCommerce menu with progressively enhanced category filtering. @package Flavor */
defined( 'ABSPATH' ) || exit;
if ( 'no' === get_theme_mod( 'flavor_featured_enable', 'yes' ) ) { return; }
$demo     = \Flavor\Bespoke_Demos::demo();
$products = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'status' => 'publish', 'limit' => 8, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) : array();
$cats     = array();
foreach ( $products as $product ) {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) { $cats[ $term->slug ] = $term->name; }
	}
}
$menu_url = \Flavor\Bespoke_Demos::page_url( 'menu' );
?>
<section class="fd-section fd-menu" id="menu" aria-labelledby="fd-menu-title">
	<div class="flavor-container">
		<div class="fd-section-head"><div><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'menu_eyebrow' ) ); ?></span><h2 id="fd-menu-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'menu_title' ) ); ?></h2><p><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'menu_text' ) ); ?></p></div><a class="fd-text-link" href="<?php echo esc_url( $menu_url ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'menu_link', __( 'مشاهده منوی کامل', 'flavor' ) ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 19 ); ?></a></div>
		<?php if ( $products ) : ?>
			<div class="fd-menu__filters" role="group" aria-label="<?php esc_attr_e( 'فیلتر دسته‌بندی منو', 'flavor' ); ?>" hidden data-fd-filters>
				<button type="button" aria-pressed="true" data-fd-filter="all" aria-controls="fd-products"><?php esc_html_e( 'همه', 'flavor' ); ?></button>
				<?php foreach ( $cats as $slug => $name ) : ?><button type="button" aria-pressed="false" data-fd-filter="<?php echo esc_attr( $slug ); ?>" aria-controls="fd-products"><?php echo esc_html( $name ); ?></button><?php endforeach; ?>
			</div>
			<p class="screen-reader-text" role="status" aria-live="polite" data-fd-filter-status></p>
			<div class="fd-products" id="fd-products">
				<?php foreach ( $products as $product ) :
					$id       = $product->get_id();
					$terms    = get_the_terms( $id, 'product_cat' );
					$terms    = $terms && ! is_wp_error( $terms ) ? $terms : array();
					$slugs    = wp_list_pluck( $terms, 'slug' );
					$image_id = $product->get_image_id();
					$image    = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : \Flavor\Bespoke_Demos::asset( 'hero.jpg' );
					$link     = $menu_url . '#item-' . $id;
					$prep     = (int) get_post_meta( $id, '_flavor_prep_time', true );
					?>
					<article class="fd-product" data-fd-categories="<?php echo esc_attr( implode( ' ', $slugs ) ); ?>">
						<a class="fd-product__media" href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( $product->get_name() ); ?>"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" loading="lazy" decoding="async" width="600" height="600" /><?php if ( $terms ) : ?><span class="fd-product__category"><?php echo esc_html( $terms[0]->name ); ?></span><?php endif; ?></a>
						<div class="fd-product__body"><h3><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></h3><p><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
							<?php if ( $prep ) : ?><span class="fd-product__prep"><?php \Flavor\Bespoke_Demos::icon( 'clock', 14 ); ?><?php echo esc_html( \Flavor\Bespoke_Demos::digits( (string) $prep ) . ' ' . __( 'دقیقه آماده‌سازی', 'flavor' ) ); ?></span><?php endif; ?>
							<div class="fd-product__bottom"><span class="fd-product__price"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span><a class="fd-product__order" href="<?php echo esc_url( $link ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'مشاهده و سفارش %s', 'flavor' ), $product->get_name() ) ); ?>"><?php \Flavor\Bespoke_Demos::icon( 'plus', 20 ); ?></a></div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="fd-empty"><p><?php esc_html_e( 'منو هنوز منتشر نشده است. پس از فعال‌سازی ووکامرس و درون‌ریزی دمو، محصولات واقعی اینجا نمایش داده می‌شوند.', 'flavor' ); ?></p><a class="fd-button fd-button--outline" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>"><?php esc_html_e( 'تماس برای اطلاع از منو', 'flavor' ); ?></a></div>
		<?php endif; ?>
	</div>
</section>
