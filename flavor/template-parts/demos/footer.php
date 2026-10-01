<?php
/** Honest, service-specific footer: no dummy ratings or live-status labels. @package Flavor */
defined( 'ABSPATH' ) || exit;
$demo    = \Flavor\Bespoke_Demos::demo();
$phone   = (string) get_theme_mod( 'flavor_phone', $demo['phone'] ?? '' );
$address = (string) get_theme_mod( 'flavor_address', $demo['address'] ?? '' );
$hours   = (string) get_theme_mod( 'flavor_hours', $demo['landing']['hours'] ?? '' );
?>
</main>
<footer class="fd-footer">
	<div class="flavor-container">
		<div class="fd-footer__grid">
			<div><a class="fd-footer__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php flavor_site_name(); ?></a><p><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p><?php flavor_social_links(); ?></div>
			<div><h2><?php esc_html_e( 'با ما همراه باش', 'flavor' ); ?></h2><ul><?php foreach ( $demo['navigation'] ?? array() as $link ) : ?><li><a href="<?php echo esc_url( home_url( '/' ) . $link['anchor'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a></li><?php endforeach; ?><li><a href="<?php echo esc_url( \Flavor\Bespoke_Demos::page_url( 'menu' ) ); ?>"><?php esc_html_e( 'منوی کامل', 'flavor' ); ?></a></li></ul></div>
			<div><h2><?php esc_html_e( 'ساعت و نشانی', 'flavor' ); ?></h2><p><?php echo esc_html( $hours ); ?></p><p><?php echo esc_html( $address ); ?></p><a class="fd-footer__phone" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>"><bdi><?php echo esc_html( \Flavor\Bespoke_Demos::digits( $phone ) ); ?></bdi></a></div>
		</div>
		<div class="fd-footer__bottom"><span><?php echo esc_html( (string) get_theme_mod( 'flavor_footer_copy', '© ' . \Flavor\Bespoke_Demos::digits( wp_date( 'Y' ) ) . ' ' . get_bloginfo( 'name' ) . '؛ تمامی حقوق محفوظ است.' ) ); ?></span><span lang="en" dir="ltr"><?php echo esc_html( $demo['landing']['wordmark'] ?? 'FLAVOR' ); ?> · <?php esc_html_e( 'ساخته‌شده با', 'flavor' ); ?> Flavor</span></div>
	</div>
</footer>
<nav class="fd-mobile-bar" aria-label="<?php esc_attr_e( 'دسترسی سریع', 'flavor' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php if ( is_front_page() ) : ?>aria-current="page"<?php endif; ?>><?php \Flavor\Bespoke_Demos::icon( 'home', 21 ); ?><span><?php esc_html_e( 'خانه', 'flavor' ); ?></span></a>
	<a href="<?php echo esc_url( \Flavor\Bespoke_Demos::page_url( 'menu' ) ); ?>" <?php if ( is_page( 'menu' ) ) : ?>aria-current="page"<?php endif; ?>><?php \Flavor\Bespoke_Demos::icon( 'bag', 21 ); ?><span><?php esc_html_e( 'منو', 'flavor' ); ?></span></a>
	<a href="<?php echo esc_url( home_url( '/' ) . '#visit' ); ?>"><?php \Flavor\Bespoke_Demos::icon( 'pin', 21 ); ?><span><?php esc_html_e( 'تماس', 'flavor' ); ?></span></a>
	<a class="fd-mobile-bar__primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( $demo['landing']['primary_action'] ?? 'menu' ) ); ?>"><?php \Flavor\Bespoke_Demos::icon( $demo['landing']['primary_icon'] ?? 'arrow', 21 ); ?><span><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'mobile_label', __( 'سفارش', 'flavor' ) ) ); ?></span></a>
</nav>
<?php wp_footer(); ?>
</body>
</html>
