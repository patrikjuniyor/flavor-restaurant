<?php
/**
 * Hero section with support for Fullscreen, Split, and Minimal layouts.
 *
 * @package Flavor
 *
 * @var array<string, string> $args title, text, cta, image, url.
 */

defined( 'ABSPATH' ) || exit;

$badge = get_theme_mod( 'flavor_hero_badge', __( 'طعم اصیل و ماندگار', 'flavor' ) );
$title = $args['title'] ?? get_theme_mod( 'flavor_hero_title', get_bloginfo( 'name' ) );
$text  = $args['text'] ?? get_theme_mod( 'flavor_hero_text', get_bloginfo( 'description' ) );
$cta1  = $args['cta'] ?? get_theme_mod( 'flavor_hero_cta', __( 'مشاهده منو و سفارش آنلاین', 'flavor' ) );
$cta2  = get_theme_mod( 'flavor_hero_cta2', __( 'رزرو آنلاین میز', 'flavor' ) );
$style = get_theme_mod( 'flavor_hero_style', 'fullscreen' );

$url1 = $args['url'] ?? '';
if ( ! $url1 ) {
	$menu = get_page_by_path( 'menu' );
	$url1 = $menu ? get_permalink( $menu ) : home_url( '/menu/' );
}

$res_page = get_page_by_path( 'reservation' );
$url2     = $res_page ? get_permalink( $res_page ) : home_url( '/reservation/' );

$image = $args['image'] ?? get_theme_mod( 'flavor_hero_image', '' );
if ( ! $image && has_post_thumbnail() ) {
	$image = get_the_post_thumbnail_url( get_the_ID(), 'flavor-hero' );
}
$skin = \Flavor\Design::current_skin();
if ( ! $image ) {
	$image = FLAVOR_URI . '/demos/' . $skin . '/hero.jpg';
}
?>
<section class="flavor-hero flavor-hero--<?php echo esc_attr( $style ); ?>" aria-label="<?php esc_attr_e( 'بخش اصلی', 'flavor' ); ?>">
	<?php if ( 'split' !== $style && $image ) : ?>
		<img class="flavor-hero__bg" src="<?php echo esc_url( $image ); ?>" alt="" fetchpriority="high" decoding="async" />
		<div class="flavor-hero__overlay"></div>
	<?php endif; ?>

	<div class="flavor-container flavor-hero__container">
		<div class="flavor-hero__content">
			<?php if ( $badge ) : ?>
				<div class="flavor-hero__badge">
					<span class="flavor-hero__badge-dot"></span>
					<?php echo esc_html( $badge ); ?>
				</div>
			<?php endif; ?>

			<h1 class="flavor-hero__title"><?php echo esc_html( $title ); ?></h1>
			<p class="flavor-hero__text"><?php echo esc_html( $text ); ?></p>

			<div class="flavor-hero__actions">
				<?php if ( $cta1 ) : ?>
					<a class="flavor-btn flavor-btn--primary flavor-btn--lg" href="<?php echo esc_url( $url1 ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
						<?php echo esc_html( $cta1 ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $cta2 ) : ?>
					<a class="flavor-btn flavor-btn--outline-light flavor-btn--lg" href="<?php echo esc_url( $url2 ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
						<?php echo esc_html( $cta2 ); ?>
					</a>
				<?php endif; ?>
			</div>

			<div class="flavor-hero__features">
				<div class="flavor-hero__feat">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					<span><?php esc_html_e( 'آماده‌سازی سریع (۱۵ تا ۳۰ دقیقه)', 'flavor' ); ?></span>
				</div>
				<div class="flavor-hero__feat">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
					<span><?php esc_html_e( 'ارسال گرم با بسته‌بندی عایق', 'flavor' ); ?></span>
				</div>
				<div class="flavor-hero__feat">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
					<span><?php esc_html_e( 'ضمانت ۱۰۰٪ تازگی مواد اوليه', 'flavor' ); ?></span>
				</div>
			</div>
		</div>

		<?php if ( 'split' === $style && $image ) : ?>
			<div class="flavor-hero__media">
				<div class="flavor-hero__img-frame">
					<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" fetchpriority="high" />
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
