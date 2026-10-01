<?php
/** Bespoke demo header, shared by landing and commerce pages. @package Flavor */
defined( 'ABSPATH' ) || exit;
$demo   = \Flavor\Bespoke_Demos::demo();
$topbar = get_theme_mod( 'flavor_header_topbar', $demo['theme_mods']['flavor_header_topbar'] ?? '' );
$phone  = (string) get_theme_mod( 'flavor_phone', $demo['phone'] ?? '' );
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>" dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="flavor-skip" href="#main"><?php esc_html_e( 'پرش به محتوای اصلی', 'flavor' ); ?></a>
<?php if ( $topbar ) : ?>
	<aside class="fd-topbar" aria-label="<?php esc_attr_e( 'اطلاعیه', 'flavor' ); ?>"><div class="flavor-container"><?php echo esc_html( $topbar ); ?></div></aside>
<?php endif; ?>
<header class="flavor-header fd-header" id="flavor-site-header">
	<div class="flavor-container flavor-header__inner">
		<div class="fd-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="fd-brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<span class="fd-brand__mark"><?php \Flavor\Bespoke_Demos::icon( $demo['landing']['brand_icon'] ?? 'leaf', 27 ); ?></span>
					<span><strong class="fd-brand__name"><?php flavor_site_name(); ?></strong><span class="fd-brand__caption"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'brand_caption' ) ); ?></span></span>
				</a>
			<?php endif; ?>
		</div>
		<nav class="flavor-header__nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'flavor' ); ?>"><?php flavor_primary_nav(); ?></nav>
		<div class="flavor-header__actions">
			<?php if ( $phone ) : ?><a class="fd-header__phone" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'تماس با %s', 'flavor' ), get_bloginfo( 'name' ) ) ); ?>"><?php \Flavor\Bespoke_Demos::icon( 'phone', 18 ); ?><bdi><?php echo esc_html( \Flavor\Bespoke_Demos::digits( $phone ) ); ?></bdi></a><?php endif; ?>
			<a class="fd-button fd-button--primary fd-header__cta" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( $demo['landing']['primary_action'] ?? 'menu' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'primary_label', __( 'مشاهده منو', 'flavor' ) ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 18 ); ?></a>
			<button type="button" class="flavor-hamburger" id="flavor-drawer-toggle" aria-label="<?php esc_attr_e( 'باز کردن منو', 'flavor' ); ?>" aria-expanded="false" aria-controls="flavor-mobile-drawer"><span></span><span></span><span></span></button>
		</div>
	</div>
</header>
<div class="flavor-drawer-overlay" id="flavor-drawer-overlay" aria-hidden="true"></div>
<aside class="flavor-drawer fd-drawer" id="flavor-mobile-drawer" role="dialog" aria-modal="true" aria-labelledby="fd-drawer-title" aria-hidden="true" inert>
	<div class="flavor-drawer__header">
		<strong id="fd-drawer-title"><?php flavor_site_name(); ?></strong>
		<button type="button" class="flavor-drawer__close" id="flavor-drawer-close" aria-label="<?php esc_attr_e( 'بستن منو', 'flavor' ); ?>">×</button>
	</div>
	<nav class="flavor-drawer__body" aria-label="<?php esc_attr_e( 'منوی موبایل', 'flavor' ); ?>"><?php flavor_primary_nav(); ?></nav>
	<div class="fd-drawer__actions"><a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( $demo['landing']['primary_action'] ?? 'menu' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'primary_label' ) ); ?></a><?php flavor_social_links(); ?></div>
</aside>
<main id="main" class="flavor-main">
