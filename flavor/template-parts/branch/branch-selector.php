<?php
/**
 * Branch list from CPT.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

$branches = get_posts(
	array(
		'post_type'      => 'flavor_branch',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
	)
);
?>
<div class="flavor-container">
	<h1><?php esc_html_e( 'شعبه‌ها', 'flavor' ); ?></h1>
	<?php if ( empty( $branches ) ) : ?>
		<p><?php esc_html_e( 'هنوز شعبه‌ای منتشر نشده است.', 'flavor' ); ?></p>
	<?php else : ?>
		<ul class="flavor-branch-list">
			<?php foreach ( $branches as $branch ) : ?>
				<li>
					<a href="<?php echo esc_url( get_permalink( $branch ) ); ?>">
						<?php echo esc_html( get_the_title( $branch ) ); ?>
					</a>
					<span><?php echo esc_html( (string) get_post_meta( $branch->ID, '_flavor_city', true ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
