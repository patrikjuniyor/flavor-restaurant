<?php
/**
 * Dynamic Restaurant Homepage — Assembles 15 Modular Enterprise Sections.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

get_header();

// 1. Hero Section
get_template_part( 'template-parts/marketing/hero' );

// 2. Intro & Highlights
get_template_part( 'template-parts/marketing/intro' );

// 3. Featured Chef's Specials
get_template_part( 'template-parts/marketing/featured' );

// 4. Categories Showcase Grid
get_template_part( 'template-parts/marketing/categories' );

// 5. Special Offers & Promo Banners
get_template_part( 'template-parts/marketing/special-offers' );

// 6. Table Reservation CTA Banner
get_template_part( 'template-parts/marketing/reservation-cta' );

// 7. About & Culinary Story
get_template_part( 'template-parts/marketing/about' );

// 8. Photo Gallery
get_template_part( 'template-parts/marketing/gallery' );

// 9. Guest Testimonials & Reviews
get_template_part( 'template-parts/marketing/testimonials' );

// 10. Working Hours & Location Details
get_template_part( 'template-parts/marketing/hours' );

// 11. Page Content (for Elementor / Gutenberg Custom Blocks)
if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		if ( get_the_content() ) {
			echo '<div class="flavor-container flavor-custom-page-content" style="padding-block: 40px;">';
			the_content();
			echo '</div>';
		}
	}
}

get_footer();
