<?php
/**
 * Header.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="flavor-skip" href="#main"><?php esc_html_e( 'پرش به محتوا', 'flavor' ); ?></a>
<header class="flavor-header">
	<div class="flavor-header__inner">
		<div class="flavor-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="flavor-brand__name" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php flavor_site_name(); ?></a>
			<?php endif; ?>
		</div>
		<nav class="flavor-header__nav" aria-label="<?php esc_attr_e( 'اصلی', 'flavor' ); ?>">
			<?php flavor_primary_nav(); ?>
		</nav>
	</div>
</header>
<main id="main" class="flavor-main">
