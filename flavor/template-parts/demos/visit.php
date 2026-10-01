<?php
/** Configurable contact card, with no fabricated open/closed status. @package Flavor */
defined( 'ABSPATH' ) || exit;
if ( 'no' === get_theme_mod( 'flavor_hours_enable', 'yes' ) ) { return; }
$demo    = \Flavor\Bespoke_Demos::demo();
$address = (string) get_theme_mod( 'flavor_address', $demo['address'] ?? '' );
$phone   = (string) get_theme_mod( 'flavor_phone', $demo['phone'] ?? '' );
$hours   = (string) get_theme_mod( 'flavor_hours', $demo['landing']['hours'] ?? '' );
?>
<section class="fd-section fd-visit" id="visit" aria-labelledby="fd-visit-title">
	<div class="flavor-container"><div class="fd-visit__card">
		<div class="fd-visit__head"><div><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'visit_eyebrow', __( 'خوشحال می‌شویم ببینیمت', 'flavor' ) ) ); ?></span><h2 id="fd-visit-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'visit_title' ) ); ?></h2></div><a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( $demo['landing']['primary_action'] ?? 'menu' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'primary_label' ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 20 ); ?></a></div>
		<div class="fd-visit__details"><div><?php \Flavor\Bespoke_Demos::icon( 'pin', 24 ); ?><div><h3><?php esc_html_e( 'نشانی', 'flavor' ); ?></h3><p><?php echo esc_html( $address ); ?></p></div></div><div><?php \Flavor\Bespoke_Demos::icon( 'clock', 24 ); ?><div><h3><?php esc_html_e( 'ساعات فعالیت', 'flavor' ); ?></h3><p><?php echo esc_html( $hours ); ?></p></div></div><div><?php \Flavor\Bespoke_Demos::icon( 'phone', 24 ); ?><div><h3><?php esc_html_e( 'با ما تماس بگیر', 'flavor' ); ?></h3><a href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>"><bdi><?php echo esc_html( \Flavor\Bespoke_Demos::digits( $phone ) ); ?></bdi></a></div></div></div>
	</div></div>
</section>
