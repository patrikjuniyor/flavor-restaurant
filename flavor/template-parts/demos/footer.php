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
		<?php
		/* The builder's bottom-bar switches apply here too; the columns keep the
		   demo's own composition, while its colour, spacing and column-count
		   controls are read from the same tokens (see assets/css/chrome.css). */
		$show_copy  = \Flavor\Chrome_Builder::show( 'copy', \Flavor\Chrome_Builder::FOOTER_SHOW_PREFIX, true );
		$show_power = \Flavor\Chrome_Builder::show( 'powered', \Flavor\Chrome_Builder::FOOTER_SHOW_PREFIX, true );
		$copy       = (string) get_theme_mod( 'flavor_footer_copy', '' );
		?>
		<?php if ( $show_copy || $show_power ) : ?>
		<div class="fd-footer__bottom">
			<?php if ( $show_copy ) : ?>
				<span><?php echo esc_html( '' !== trim( $copy ) ? $copy : '© ' . \Flavor\Bespoke_Demos::digits( wp_date( 'Y' ) ) . ' ' . get_bloginfo( 'name' ) . '؛ تمامی حقوق محفوظ است.' ); ?></span>
			<?php endif; ?>
			<?php if ( $show_power ) : ?>
				<span lang="en" dir="ltr"><?php echo esc_html( $demo['landing']['wordmark'] ?? 'FLAVOR' ); ?> · <?php esc_html_e( 'ساخته‌شده با', 'flavor' ); ?> Flavor</span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
</footer>
<?php get_template_part( 'template-parts/ui/mobile-nav' ); ?>
<?php wp_footer(); ?>
</body>
</html>
