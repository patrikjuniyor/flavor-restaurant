<?php
/**
 * Static front page — marketing first. Menu stays on its own template.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

get_header();

$skin = Flavor\Design::current_skin();
$hero = FLAVOR_URI . '/demos/' . $skin . '/hero.jpg';
if ( has_post_thumbnail() ) {
	$hero = get_the_post_thumbnail_url( get_the_ID(), 'flavor-hero' ) ?: $hero;
}

get_template_part(
	'template-parts/marketing/hero',
	null,
	array(
		'image' => $hero,
	)
);

get_template_part( 'template-parts/marketing/about' );

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		if ( get_the_content() ) {
			echo '<div class="flavor-container flavor-article__content">';
			the_content();
			echo '</div>';
		}
	}
}

get_footer();
