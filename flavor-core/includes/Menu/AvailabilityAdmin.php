<?php
/**
 * Per-branch sold-out toggles.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Menu;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class AvailabilityAdmin
 */
class AvailabilityAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_toggle_avail', array( $this, 'handle' ) );
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_manage_kitchen' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		$branch_id = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : BranchPostType::default_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$branches  = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'posts_per_page' => 50,
				'post_status'    => array( 'publish', 'draft' ),
			)
		);
		$products = function_exists( 'wc_get_products' ) ? wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 100,
				'type'   => array( 'simple' ),
			)
		) : array();
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'موجودی لحظه‌ای', 'flavor-core' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="flavor-availability" />
				<label><?php esc_html_e( 'شعبه', 'flavor-core' ); ?>
					<select name="branch_id" onchange="this.form.submit()">
						<?php foreach ( $branches as $b ) : ?>
							<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( $branch_id, $b->ID ); ?>><?php echo esc_html( get_the_title( $b ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'آیتم', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'flavor-core' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $products as $p ) : ?>
						<?php
						$ok = AvailabilityManager::is_available( $branch_id, $p->get_id() );
						?>
						<tr>
							<td><?php echo esc_html( $p->get_name() ); ?></td>
							<td><?php echo $ok ? esc_html__( 'موجود', 'flavor-core' ) : esc_html__( 'ناموجود', 'flavor-core' ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'flavor_toggle_avail' ); ?>
									<input type="hidden" name="action" value="flavor_toggle_avail" />
									<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
									<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $p->get_id() ); ?>" />
									<input type="hidden" name="available" value="<?php echo $ok ? '0' : '1'; ?>" />
									<button class="button"><?php echo $ok ? esc_html__( 'ناموجود کن', 'flavor-core' ) : esc_html__( 'موجود کن', 'flavor-core' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Toggle.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'flavor_manage_kitchen' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		check_admin_referer( 'flavor_toggle_avail' );
		$branch_id = isset( $_POST['branch_id'] ) ? absint( wp_unslash( $_POST['branch_id'] ) ) : 0;
		$product   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$ok        = ! empty( $_POST['available'] );
		if ( $branch_id && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'این شعبه در دسترس شما نیست.', 'flavor-core' ) );
		}
		if ( $branch_id && $product ) {
			AvailabilityManager::set( $branch_id, $product, $ok, null, get_current_user_id() );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=flavor-availability&branch_id=' . $branch_id ) );
		exit;
	}
}
