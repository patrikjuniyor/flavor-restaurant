<?php
/**
 * Menu schedule editor.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Menu;

use FlavorCore\PostTypes\BranchPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Class ScheduleAdmin
 */
class ScheduleAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_save_schedule', array( $this, 'handle' ) );
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_manage_branch' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		$branch_id = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$branches  = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'posts_per_page' => 50,
				'post_status'    => array( 'publish', 'draft' ),
			)
		);
		$rows = MenuScheduler::schedules( $branch_id );
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'زمان‌بندی منو', 'flavor-core' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="flavor-schedules" />
				<label><?php esc_html_e( 'شعبه (۰ = سراسری)', 'flavor-core' ); ?>
					<select name="branch_id" onchange="this.form.submit()">
						<option value="0"><?php esc_html_e( 'سراسری', 'flavor-core' ); ?></option>
						<?php foreach ( $branches as $b ) : ?>
							<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( $branch_id, $b->ID ); ?>><?php echo esc_html( get_the_title( $b ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>
			<p class="description"><?php esc_html_e( 'اگر برای یک slug در شعبه ردیف بگذارید، جایگزین سراسری می‌شود. آیتم‌هایی که وعده زمانی ندارند همیشه نمایش داده می‌شوند.', 'flavor-core' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نام', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'slug', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'از', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'تا', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'فعال', 'flavor-core' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'flavor_save_schedule' ); ?>
								<input type="hidden" name="action" value="flavor_save_schedule" />
								<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
								<input type="hidden" name="slug" value="<?php echo esc_attr( (string) $r['slug'] ); ?>" />
								<td><input type="text" name="name" value="<?php echo esc_attr( (string) $r['name'] ); ?>" /></td>
								<td><code><?php echo esc_html( (string) $r['slug'] ); ?></code></td>
								<td><input type="time" name="start_time" value="<?php echo esc_attr( substr( (string) $r['start_time'], 0, 5 ) ); ?>" /></td>
								<td><input type="time" name="end_time" value="<?php echo esc_attr( substr( (string) $r['end_time'], 0, 5 ) ); ?>" /></td>
								<td><input type="checkbox" name="is_active" value="1" <?php checked( (int) $r['is_active'], 1 ); ?> /></td>
								<td><button class="button"><?php esc_html_e( 'ذخیره', 'flavor-core' ); ?></button></td>
							</form>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Save.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'flavor_manage_branch' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		check_admin_referer( 'flavor_save_schedule' );
		$branch_id = isset( $_POST['branch_id'] ) ? absint( wp_unslash( $_POST['branch_id'] ) ) : 0;
		MenuScheduler::save(
			array(
				'branch_id'  => $branch_id,
				'slug'       => isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '',
				'name'       => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'start_time' => isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) . ':00' : '07:00:00',
				'end_time'   => isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) . ':00' : '11:00:00',
				'is_active'  => ! empty( $_POST['is_active'] ),
			)
		);
		wp_safe_redirect( admin_url( 'admin.php?page=flavor-schedules&branch_id=' . $branch_id ) );
		exit;
	}
}
