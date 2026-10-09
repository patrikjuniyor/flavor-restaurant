<?php
/**
 * Bespoke demo header, shared by landing and commerce pages.
 *
 * The chrome (announcement bar, brand, action elements, drawer) is built by
 * `Chrome_Builder` like every other skin, so the header builder works on the
 * bespoke demos too; only the skin-specific classes differ.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

$demo   = \Flavor\Bespoke_Demos::demo();
$drawer = \Flavor\Chrome_Builder::has_mobile_nav();
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
<?php \Flavor\Chrome_Builder::render_topbar( 'bespoke' ); ?>
<header class="flavor-header fd-header" id="flavor-site-header">
	<div class="flavor-container flavor-header__inner">
		<div class="fd-brand">
			<?php \Flavor\Chrome_Builder::render_brand( 'bespoke' ); ?>
		</div>
		<nav class="flavor-header__nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'flavor' ); ?>"><?php flavor_primary_nav(); ?></nav>
		<div class="flavor-header__actions">
			<?php \Flavor\Chrome_Builder::render_header_actions( 'bespoke' ); ?>
		</div>
	</div>
</header>
<?php if ( $drawer ) : ?>
<div class="flavor-drawer-overlay" id="flavor-drawer-overlay" aria-hidden="true"></div>
<aside class="flavor-drawer fd-drawer" id="flavor-mobile-drawer" role="dialog" aria-modal="true" aria-labelledby="fd-drawer-title" aria-hidden="true" inert>
	<div class="flavor-drawer__header">
		<strong id="fd-drawer-title"><?php flavor_site_name(); ?></strong>
		<button type="button" class="flavor-drawer__close" id="flavor-drawer-close" aria-label="<?php esc_attr_e( 'بستن منو', 'flavor' ); ?>">×</button>
	</div>
	<nav class="flavor-drawer__body" aria-label="<?php esc_attr_e( 'منوی موبایل', 'flavor' ); ?>">
		<?php flavor_primary_nav(); ?>
		<?php \Flavor\Chrome_Builder::render_drawer_search(); ?>
	</nav>
	<div class="fd-drawer__actions"><a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( $demo['landing']['primary_action'] ?? 'menu' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'primary_label' ) ); ?></a><?php flavor_social_links(); ?></div>
</aside>
<?php endif; ?>
<main id="main" class="flavor-main">
