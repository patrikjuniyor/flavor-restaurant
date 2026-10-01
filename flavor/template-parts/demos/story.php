<?php
/** Brand story with a per-pack image and real editable copy. @package Flavor */
defined( 'ABSPATH' ) || exit;
if ( ! \Flavor\Bespoke_Demos::enabled( 'story' ) ) { return; }
$demo  = \Flavor\Bespoke_Demos::demo();
$image = get_theme_mod( 'flavor_landing_story_image', '' ) ?: \Flavor\Bespoke_Demos::asset( 'story.jpg' );
$text  = get_theme_mod( 'flavor_about', $demo['about'] ?? '' );
?>
<section class="fd-section fd-story" id="story" aria-labelledby="fd-story-title">
	<div class="flavor-container fd-story__grid">
		<figure class="fd-story__media"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( \Flavor\Bespoke_Demos::value( 'story_alt', __( 'فضای برند و مواد اولیه تازه', 'flavor' ) ) ); ?>" loading="lazy" decoding="async" width="1000" height="800" /><figcaption><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'story_caption' ) ); ?></figcaption></figure>
		<div class="fd-story__copy"><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'story_eyebrow' ) ); ?></span><h2 id="fd-story-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'story_title' ) ); ?></h2><div class="fd-prose"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
			<ul class="fd-story__values"><?php foreach ( $demo['landing']['story_values'] ?? array() as $value ) : ?><li><?php \Flavor\Bespoke_Demos::icon( 'check', 18 ); ?><span><?php echo esc_html( $value ); ?></span></li><?php endforeach; ?></ul>
			<a class="fd-text-link" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( $demo['landing']['story_action'] ?? 'menu' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'story_link', __( 'کشف منو', 'flavor' ) ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 20 ); ?></a>
		</div>
	</div>
</section>
