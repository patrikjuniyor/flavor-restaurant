<?php
/**
 * Default page.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="flavor-container">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<article <?php post_class( 'flavor-article' ); ?>>
			<h1 class="flavor-article__title"><?php if ( function_exists( 'is_account_page' ) && is_account_page() ) { echo is_user_logged_in() ? esc_html__( 'حساب من', 'flavor' ) : esc_html__( 'ورود به حساب', 'flavor' ); } else { the_title(); } ?></h1>
			<div class="flavor-article__content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
