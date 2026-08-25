<?php
/**
 * Branch → tables admin screen (list + bulk create).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Table;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class TableAdmin
 */
class TableAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_save_tables', array( $this, 'handle' ) );
	}

	/**
	 * Render the tables page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_manage_branch' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}

		$branch_id = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : BranchPostType::default_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$branches  = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
			)
		);
		$tables = $branch_id ? TableRepository::for_branch( $branch_id ) : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'میزها و QR', 'flavor-core' ); ?></h1>
			<form method="get" style="margin: 1rem 0;">
				<input type="hidden" name="page" value="flavor-tables" />
				<label>
					<?php esc_html_e( 'شعبه', 'flavor-core' ); ?>
					<select name="branch_id" onchange="this.form.submit()">
						<?php foreach ( $branches as $branch ) : ?>
							<option value="<?php echo esc_attr( (string) $branch->ID ); ?>" <?php selected( $branch_id, $branch->ID ); ?>>
								<?php echo esc_html( get_the_title( $branch ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>

			<?php if ( $branch_id ) : ?>
				<h2><?php esc_html_e( 'ساخت گروهی', 'flavor-core' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'flavor_save_tables' ); ?>
					<input type="hidden" name="action" value="flavor_save_tables" />
					<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
					<input type="hidden" name="flavor_op" value="bulk" />
					<label><?php esc_html_e( 'از شماره', 'flavor-core' ); ?> <input type="number" name="from" value="1" min="1" /></label>
					<label><?php esc_html_e( 'تا شماره', 'flavor-core' ); ?> <input type="number" name="to" value="10" min="1" /></label>
					<label><?php esc_html_e( 'ظرفیت', 'flavor-core' ); ?> <input type="number" name="capacity" value="4" min="1" max="20" /></label>
					<button class="button button-primary"><?php esc_html_e( 'ساخت میزها', 'flavor-core' ); ?></button>
				</form>

				<h2><?php esc_html_e( 'لیست میزها', 'flavor-core' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'شماره', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'برچسب', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'ظرفیت', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'بخش', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'لینک QR (فاز ۲: تصویر)', 'flavor-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $tables ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'میزی ثبت نشده.', 'flavor-core' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $tables as $table ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $table['table_number'] ); ?></td>
								<td><?php echo esc_html( (string) $table['label'] ); ?></td>
								<td><?php echo esc_html( (string) $table['capacity'] ); ?></td>
								<td><?php echo esc_html( (string) $table['section'] ); ?></td>
								<td dir="ltr"><code><?php echo esc_html( TableRepository::public_url( $table ) ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * admin-post handler.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'flavor_manage_branch' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		check_admin_referer( 'flavor_save_tables' );

		$branch_id = isset( $_POST['branch_id'] ) ? absint( wp_unslash( $_POST['branch_id'] ) ) : 0;
		$op        = isset( $_POST['flavor_op'] ) ? sanitize_key( wp_unslash( $_POST['flavor_op'] ) ) : '';

		if ( $branch_id && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'این شعبه در دسترس شما نیست.', 'flavor-core' ) );
		}

		if ( 'bulk' === $op && $branch_id ) {
			$from     = isset( $_POST['from'] ) ? absint( wp_unslash( $_POST['from'] ) ) : 1;
			$to       = isset( $_POST['to'] ) ? absint( wp_unslash( $_POST['to'] ) ) : 1;
			$capacity = isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 4;
			TableRepository::bulk_create( $branch_id, $from, $to, $capacity );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=flavor-tables&branch_id=' . $branch_id ) );
		exit;
	}
}
