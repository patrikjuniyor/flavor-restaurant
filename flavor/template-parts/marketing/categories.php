<?php
/**
 * Categories Showcase section.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_cats_enable', 'yes' ) ) {
	return;
}

$title = get_theme_mod( 'flavor_cats_title', __( 'دسته‌بندی‌های منوی رستوران', 'flavor' ) );

$cats = array();
if ( taxonomy_exists( 'product_cat' ) ) {
	$cats = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'parent'     => 0,
		)
	);
}

if ( empty( $cats ) || is_wp_error( $cats ) ) {
	return;
}

$menu_page = get_page_by_path( 'menu' );
$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );
?>
<section class="flavor-section flavor-categories" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="flavor-container">
		<div class="flavor-section-header">
			<span class="flavor-section-header__tag"><?php esc_html_e( 'تنوع کم‌نظیر', 'flavor' ); ?></span>
			<h2 class="flavor-section-header__title"><?php echo esc_html( $title ); ?></h2>
			<p class="flavor-section-header__desc"><?php esc_html_e( 'انتخاب از میان بهترین غذاهای اصلی، پیش‌غذا، نوشیدنی‌ها و دسرهای لذیذ', 'flavor' ); ?></p>
		</div>

		<div class="flavor-categories__grid">
			<?php foreach ( $cats as $cat ) : ?>
				<?php
				$thumb_id = get_term_meta( $cat->term_id, 'thumbnail_id', true );
				$img_url  = $thumb_id ? wp_get_attachment_image_url( (int) $thumb_id, 'medium' ) : '';
				$cat_url  = add_query_arg( 'cat', $cat->term_id, $menu_url );
				?>
				<a href="<?php echo esc_url( $cat_url ); ?>" class="flavor-cat-card">
					<?php if ( $img_url ) : ?>
						<div class="flavor-cat-card__bg" style="background-image: url('<?php echo esc_url( $img_url ); ?>');"></div>
					<?php else : ?>
						<div class="flavor-cat-card__ph">
							<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
						</div>
					<?php endif; ?>
					<div class="flavor-cat-card__content">
						<h3 class="flavor-cat-card__title"><?php echo esc_html( $cat->name ); ?></h3>
						<span class="flavor-cat-card__count">
							<?php echo esc_html( sprintf( /* translators: %d items */ __( '%d آیتم', 'flavor' ), $cat->count ) ); ?>
						</span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
