<?php
/**
 * Footer.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<footer class="flavor-footer">
	<div class="flavor-footer__inner">
		<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
			<div class="flavor-footer__widgets">
				<?php dynamic_sidebar( 'footer-1' ); ?>
			</div>
		<?php endif; ?>
		<p class="flavor-footer__copy">
			&copy; <?php echo esc_html( (string) wp_date( 'Y' ) ); ?>
			<?php flavor_site_name(); ?>
		</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
