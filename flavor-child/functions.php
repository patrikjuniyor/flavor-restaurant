<?php
/**
 * Flavor Child bootstrap.
 *
 * Deliberately tiny: the parent theme already enqueues its own styles and the
 * premium layer. The only job here is to load this child's stylesheet after all
 * of them, so an override never loses a specificity race.
 *
 * @package FlavorChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the child stylesheet last.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'flavor-child',
			get_stylesheet_uri(),
			array( 'flavor-premium' ),
			(string) wp_get_theme()->get( 'Version' )
		);
	},
	50
);

/*
 * نمونه: تغییر متن دکمهٔ سفارش بدون دست‌زدن به قالب اصلی.
 *
 * add_filter( 'flavor_mega_locations', function ( $locations ) {
 *     $locations[] = 'footer';
 *     return $locations;
 * } );
 *
 * افزودن یک بلوک بعد از هر نوار پیشرفت ارسال رایگان:
 *
 * add_action( 'flavor_newsletter_signup', function ( $email, $source ) {
 *     // ارسال به سرویس ایمیل مارکتینگ شما.
 * }, 10, 2 );
 */
