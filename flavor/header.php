<?php
/**
 * Header template.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

$topbar = get_theme_mod( 'flavor_header_topbar', '' );
$layout = get_theme_mod( 'flavor_header_layout', 'default' );
$phone  = get_theme_mod( 'flavor_phone', '02188001234' );

$menu_page = get_page_by_path( 'menu' );
$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="flavor-skip" href="#main"><?php esc_html_e( 'پرش به محتوای اصلی', 'flavor' ); ?></a>

<?php if ( $topbar ) : ?>
	<aside class="flavor-topbar" aria-label="<?php esc_attr_e( 'پیام ویژه', 'flavor' ); ?>">
		<div class="flavor-container flavor-topbar__inner">
			<span><?php echo esc_html( $topbar ); ?></span>
		</div>
	</aside>
<?php endif; ?>

<header class="flavor-header flavor-header--<?php echo esc_attr( $layout ); ?>" id="flavor-site-header">
	<div class="flavor-container flavor-header__inner">
		<!-- Brand / Logo -->
		<div class="flavor-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="flavor-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php flavor_site_name(); ?>
				</a>
			<?php endif; ?>
		</div>

		<!-- Desktop Primary Nav -->
		<nav class="flavor-header__nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'flavor' ); ?>">
			<?php flavor_primary_nav(); ?>
		</nav>

		<!-- Header Actions -->
		<div class="flavor-header__actions">
			<?php if ( $phone ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/\D+/', '', $phone ) ); ?>" class="flavor-header__phone" aria-label="<?php esc_attr_e( 'تماس تلفنی', 'flavor' ); ?>">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
					<span><?php echo esc_html( $phone ); ?></span>
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( $menu_url ); ?>" class="flavor-btn flavor-btn--primary flavor-btn--sm flavor-header__order-btn">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
				<?php esc_html_e( 'سفارش آنلاین', 'flavor' ); ?>
			</a>

			<!-- Mobile Hamburger Toggle -->
			<button type="button" class="flavor-hamburger" id="flavor-drawer-toggle" aria-label="<?php esc_attr_e( 'باز کردن منو', 'flavor' ); ?>" aria-expanded="false" aria-controls="flavor-mobile-drawer">
				<span></span>
				<span></span>
				<span></span>
			</button>
		</div>
	</div>
</header>

<!-- Mobile Navigation Drawer -->
<div class="flavor-drawer-overlay" id="flavor-drawer-overlay" aria-hidden="true"></div>
<aside class="flavor-drawer" id="flavor-mobile-drawer" aria-label="<?php esc_attr_e( 'منوی موبایل', 'flavor' ); ?>" aria-hidden="true">
	<div class="flavor-drawer__header">
		<span class="flavor-drawer__title"><?php flavor_site_name(); ?></span>
		<button type="button" class="flavor-drawer__close" id="flavor-drawer-close" aria-label="<?php esc_attr_e( 'بستن منو', 'flavor' ); ?>">✕</button>
	</div>
	<div class="flavor-drawer__body">
		<nav class="flavor-drawer__nav">
			<?php flavor_primary_nav(); ?>
		</nav>
		<div class="flavor-drawer__contact">
			<a href="tel:<?php echo esc_attr( preg_replace( '/\D+/', '', $phone ) ); ?>" class="flavor-btn flavor-btn--primary flavor-btn--full">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
				<?php echo esc_html( sprintf( /* translators: phone */ __( 'تماس تلفنی: %s', 'flavor' ), $phone ) ); ?>
			</a>
			<?php flavor_social_links(); ?>
		</div>
	</div>
</aside>

<main id="main" class="flavor-main">
