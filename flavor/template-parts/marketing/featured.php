<?php
/**
 * Featured Dishes / Chef's Specials section.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_featured_enable', 'yes' ) ) {
	return;
}

$title = get_theme_mod( 'flavor_featured_title', __( 'پیشنهادهای ویژه سرآشپز', 'flavor' ) );

$products = array();
if ( function_exists( 'wc_get_products' ) ) {
	$products = wc_get_products(
		array(
			'status'   => 'publish',
			'limit'    => 6,
			'orderby'  => 'popularity',
			'order'    => 'DESC',
		)
	);
}

if ( empty( $products ) ) {
	return;
}

$menu_page = get_page_by_path( 'menu' );
$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );
?>
<section class="flavor-section flavor-featured" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="flavor-container">
		<div class="flavor-section-header flavor-section-header--with-action">
			<div>
				<span class="flavor-section-header__tag"><?php esc_html_e( 'انتخاب ویژه', 'flavor' ); ?></span>
				<h2 class="flavor-section-header__title"><?php echo esc_html( $title ); ?></h2>
			</div>
			<a href="<?php echo esc_url( $menu_url ); ?>" class="flavor-btn flavor-btn--outline">
				<?php esc_html_e( 'مشاهده کل منو', 'flavor' ); ?>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
			</a>
		</div>

		<div class="flavor-featured__grid">
			<?php foreach ( $products as $product ) : ?>
				<?php
				$pid       = $product->get_id();
				$image     = wp_get_attachment_image_url( $product->get_image_id(), 'flavor-card' ) ?: ( FLAVOR_URI . '/screenshot.png' );
				$price     = (float) $product->get_price();
				$terms     = get_the_terms( $pid, 'product_cat' );
				$cat_name  = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
				$prep_time = (int) get_post_meta( $pid, '_flavor_prep_time', true );
				$calories  = (int) get_post_meta( $pid, '_flavor_calories', true );
				?>
				<article class="flavor-food-card">
					<div class="flavor-food-card__media">
						<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" loading="lazy" width="600" height="400" />
						<?php if ( $cat_name ) : ?>
							<span class="flavor-food-card__cat-badge"><?php echo esc_html( $cat_name ); ?></span>
						<?php endif; ?>
					</div>
					<div class="flavor-food-card__body">
						<h3 class="flavor-food-card__title">
							<a href="<?php echo esc_url( $menu_url . '#item-' . $pid ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
						</h3>
						<?php if ( $product->get_short_description() ) : ?>
							<p class="flavor-food-card__desc"><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
						<?php endif; ?>
						
						<div class="flavor-food-card__meta">
							<?php if ( $prep_time ) : ?>
								<span class="flavor-food-card__meta-item">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
									<?php echo esc_html( (string) $prep_time . ' ' . __( 'دقیقه', 'flavor' ) ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $calories ) : ?>
								<span class="flavor-food-card__meta-item">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
									<?php echo esc_html( (string) $calories . ' ' . __( 'کالری', 'flavor' ) ); ?>
								</span>
							<?php endif; ?>
						</div>

						<div class="flavor-food-card__footer">
							<div class="flavor-food-card__price">
								<?php echo wp_kses_post( wc_price( $price ) ); ?>
							</div>
							<a href="<?php echo esc_url( $menu_url . '#item-' . $pid ); ?>" class="flavor-btn flavor-btn--primary flavor-btn--sm">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
								<?php esc_html_e( 'سفارش', 'flavor' ); ?>
							</a>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
