<?php
/**
 * About section.
 *
 * @package Flavor
 *
 * @var array<string, string> $args title, text.
 */

defined( 'ABSPATH' ) || exit;

$title = $args['title'] ?? __( 'داستان ما', 'flavor' );
$text  = $args['text'] ?? get_theme_mod( 'flavor_about', '' );
if ( ! $text ) {
	return;
}
?>
<section class="flavor-about flavor-container">
	<h2><?php echo esc_html( $title ); ?></h2>
	<div class="flavor-about__text"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
</section>
