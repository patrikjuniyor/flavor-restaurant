<?php
/** Form: centered typographic masthead, wide photo, compact live menu. @package Flavor */
defined( 'ABSPATH' ) || exit;
$demo = \Flavor\Bespoke_Demos::demo();
$image = get_theme_mod( 'flavor_hero_image', '' ) ?: \Flavor\Bespoke_Demos::asset( 'hero.jpg' );
?>
<section class="fd-hero fd-form-hero" aria-labelledby="fd-hero-title"><div class="flavor-container"><div class="fd-form-hero__copy"><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'eyebrow' ) ); ?></span><h1 id="fd-hero-title"><?php echo \Flavor\Bespoke_Demos::hero_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1><p class="fd-hero__text"><?php echo esc_html( get_theme_mod( 'flavor_hero_text', $demo['hero_text'] ) ); ?></p><div class="fd-hero__actions"><a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::page_url( 'reservation' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'primary_label' ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 19 ); ?></a><a class="fd-text-link" href="#menu"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'secondary_label' ) ); ?></a></div></div><figure class="fd-form-hero__art"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( \Flavor\Bespoke_Demos::value( 'hero_alt' ) ); ?>" width="1440" height="720" fetchpriority="high" loading="eager" decoding="async" /><figcaption><span lang="en" dir="ltr">FORM — A SEASONAL TABLE</span><span><?php echo esc_html( implode( ' · ', $demo['landing']['perks'] ) ); ?></span></figcaption></figure></div></section>
<?php get_template_part( 'template-parts/demos/menu' ); ?>
<?php get_template_part( 'template-parts/demos/story' ); ?>
<?php get_template_part( 'template-parts/demos/process' ); ?>
<?php if ( \Flavor\Bespoke_Demos::enabled( 'feature' ) ) : ?>
<section class="fd-section fd-form-experience" id="experience" aria-labelledby="fd-experience-title"><div class="flavor-container"><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_eyebrow' ) ); ?></span><h2 id="fd-experience-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_title' ) ); ?></h2><p class="fd-prose"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_text' ) ); ?></p><a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::page_url( 'reservation' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_label' ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 19 ); ?></a><span class="fd-form-experience__hours"><?php echo esc_html( get_theme_mod( 'flavor_hours', $demo['landing']['hours'] ) ); ?></span></div></section>
<?php endif; ?>
<?php get_template_part( 'template-parts/demos/faq' ); ?>
<?php get_template_part( 'template-parts/demos/visit' ); ?>
