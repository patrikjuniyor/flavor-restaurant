<?php
/**
 * Header template.
 *
 * The row itself is assembled by `Chrome_Builder`, which is the same code the
 * Customizer partials run — what the owner sees in the preview is what the
 * theme prints for visitors.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( \Flavor\Bespoke_Demos::active() ) {
	get_template_part( 'template-parts/demos/header' );
	return;
}

$layout = get_theme_mod( 'flavor_header_layout', 'default' );
$layout = in_array( $layout, array( 'default', 'centered', 'minimal', 'transparent' ), true ) ? $layout : 'default'
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="flavor-skip" href="#main"><?php esc_html_e( 'پرش به محتوای اصلی', 'flavor' ); ?></a>

<?php \Flavor\Chrome_Builder::render_topbar(); ?>

<header class="flavor-header flavor-header--<?php echo esc_attr( $layout ); ?>" id="flavor-site-header">
	<div class="flavor-container flavor-header__inner">
		<!-- Brand / Logo -->
		<div class="flavor-brand">
			<?php \Flavor\Chrome_Builder::render_brand(); ?>
		</div>

		<!-- Desktop Primary Nav -->
		<nav class="flavor-header__nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'flavor' ); ?>">
			<?php flavor_primary_nav(); ?>
		</nav>

		<!-- Header Actions, in the order the owner chose -->
		<div class="flavor-header__actions">
			<?php \Flavor\Chrome_Builder::render_header_actions(); ?>
		</div>
	</div>
</header>

<?php \Flavor\Chrome_Builder::render_mobile_drawer(); ?>

<main id="main" class="flavor-main">
