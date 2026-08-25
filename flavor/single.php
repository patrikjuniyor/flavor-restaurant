<?php
/**
 * Single post / fallback for CPTs.
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
			<h1 class="flavor-article__title"><?php the_title(); ?></h1>
			<div class="flavor-article__content">
				<?php the_content(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</div>
<?php
get_footer();
