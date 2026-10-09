<?php
/**
 * Footer template.
 *
 * Columns and their content come from `Chrome_Builder`, the same renderer the
 * Customizer footer partial uses.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( \Flavor\Bespoke_Demos::active() ) {
	get_template_part( 'template-parts/demos/footer' );
	return;
}
?>
</main><!-- #main -->

<?php \Flavor\Chrome_Builder::render_footer(); ?>

<!-- Mobile Bottom Navigation Bar -->
<?php flavor_mobile_bottom_bar(); ?>

<?php wp_footer(); ?>
</body>
</html>
