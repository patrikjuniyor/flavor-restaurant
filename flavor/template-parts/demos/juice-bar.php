<?php
/** Limo — an airy, citrus-colored, fresh-juice landing page. @package Flavor */
defined( 'ABSPATH' ) || exit;
$demo  = \Flavor\Bespoke_Demos::demo();
$image = get_theme_mod( 'flavor_hero_image', '' ) ?: \Flavor\Bespoke_Demos::asset( 'hero.jpg' );
?>
<section class="fd-hero fd-hero--juice" aria-labelledby="fd-hero-title">
	<div class="flavor-container fd-hero__grid">
		<div class="fd-hero__copy"><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'eyebrow' ) ); ?></span><h1 id="fd-hero-title"><?php echo \Flavor\Bespoke_Demos::hero_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by helper. ?></h1><p class="fd-hero__text"><?php echo esc_html( get_theme_mod( 'flavor_hero_text', $demo['hero_text'] ) ); ?></p>
			<div class="fd-hero__actions"><a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::page_url( 'menu' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'primary_label' ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 20 ); ?></a><a class="fd-text-link" href="#story"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'secondary_label' ) ); ?></a></div>
			<ul class="fd-hero__perks"><?php foreach ( $demo['landing']['perks'] as $perk ) : ?><li><?php \Flavor\Bespoke_Demos::icon( 'check', 14 ); ?><?php echo esc_html( $perk ); ?></li><?php endforeach; ?></ul>
		</div>
		<figure class="fd-hero__art"><img class="fd-hero__image" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( \Flavor\Bespoke_Demos::value( 'hero_alt' ) ); ?>" width="1200" height="1200" fetchpriority="high" loading="eager" decoding="async" /><span class="fd-fresh-seal" aria-hidden="true"><span lang="en">FRESH</span><?php \Flavor\Bespoke_Demos::icon( 'leaf', 26 ); ?><span lang="en">DAILY</span></span><figcaption class="fd-hero__caption"><div><small><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'hero_caption_tag' ) ); ?></small><strong><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'hero_caption' ) ); ?></strong></div><?php \Flavor\Bespoke_Demos::icon( 'glass', 28 ); ?></figcaption></figure>
	</div>
</section>
<?php if ( \Flavor\Bespoke_Demos::enabled( 'process' ) ) : ?>
<section class="fd-freshness" aria-label="<?php esc_attr_e( 'از میوه تا لیوان', 'flavor' ); ?>"><div class="flavor-container fd-freshness__grid"><?php foreach ( $demo['landing']['steps'] as $step ) : ?><div class="fd-freshness__item"><span class="fd-freshness__icon"><?php \Flavor\Bespoke_Demos::icon( $step['icon'], 26 ); ?></span><div><h2><?php echo esc_html( $step['title'] ); ?></h2><p><?php echo esc_html( $step['text'] ); ?></p></div></div><?php endforeach; ?></div></section>
<?php endif; ?>
<?php get_template_part( 'template-parts/demos/menu' ); ?>
<?php get_template_part( 'template-parts/demos/story' ); ?>
<?php if ( \Flavor\Bespoke_Demos::enabled( 'feature' ) ) : ?>
<section class="fd-section fd-juice-feature" id="special" aria-labelledby="fd-special-title"><div class="flavor-container"><div class="fd-juice-feature__card"><div class="fd-juice-feature__copy"><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_eyebrow' ) ); ?></span><h2 id="fd-special-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_title' ) ); ?></h2><p><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_text' ) ); ?></p><a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::page_url( 'menu' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_label' ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 19 ); ?></a></div><figure><img src="<?php echo esc_url( \Flavor\Bespoke_Demos::asset( 'category-shots.jpg' ) ); ?>" alt="<?php esc_attr_e( 'شات زنجبیل و لیمو کنار مواد تازه', 'flavor' ); ?>" width="700" height="700" loading="lazy" decoding="async" /></figure></div></div></section>
<?php endif; ?>
<?php get_template_part( 'template-parts/demos/faq' ); ?>
<?php get_template_part( 'template-parts/demos/visit' ); ?>
