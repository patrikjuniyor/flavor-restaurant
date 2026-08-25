<?php
/**
 * Delivery zone admin UI.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Delivery;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Roles;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class ZoneAdmin
 */
class ZoneAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_save_zone', array( $this, 'handle' ) );
	}

	/**
	 * Render page.
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
		$zones = $branch_id ? ZoneRepository::for_branch( $branch_id ) : array();
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'مناطق ارسال', 'flavor-core' ); ?></h1>
			<form method="get" style="margin:1rem 0;">
				<input type="hidden" name="page" value="flavor-zones" />
				<label><?php esc_html_e( 'شعبه', 'flavor-core' ); ?>
					<select name="branch_id" onchange="this.form.submit()">
						<?php foreach ( $branches as $branch ) : ?>
							<option value="<?php echo esc_attr( (string) $branch->ID ); ?>" <?php selected( $branch_id, $branch->ID ); ?>><?php echo esc_html( get_the_title( $branch ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>

			<?php if ( $branch_id ) : ?>
				<h2><?php esc_html_e( 'منطقه جدید', 'flavor-core' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flavor-zone-form">
					<?php wp_nonce_field( 'flavor_save_zone' ); ?>
					<input type="hidden" name="action" value="flavor_save_zone" />
					<input type="hidden" name="flavor_op" value="create" />
					<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
					<p>
						<label><?php esc_html_e( 'نام', 'flavor-core' ); ?> <input type="text" name="name" required /></label>
						<label><?php esc_html_e( 'نوع', 'flavor-core' ); ?>
							<select name="zone_type">
								<option value="neighborhoods"><?php esc_html_e( 'محله / شهر', 'flavor-core' ); ?></option>
								<option value="radius"><?php esc_html_e( 'شعاع (کیلومتر)', 'flavor-core' ); ?></option>
							</select>
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'هزینه ارسال (واحد ذخیره)', 'flavor-core' ); ?> <input type="number" name="delivery_fee" value="0" min="0" /></label>
						<label><?php esc_html_e( 'حداقل سفارش', 'flavor-core' ); ?> <input type="number" name="min_order" value="0" min="0" /></label>
						<label><?php esc_html_e( 'زمان تقریبی (دقیقه)', 'flavor-core' ); ?> <input type="number" name="estimated_minutes" value="45" min="5" /></label>
					</p>
					<p>
						<label><?php esc_html_e( 'محله‌ها (هر خط یکی)', 'flavor-core' ); ?><br />
							<textarea name="neighborhoods" rows="3" cols="40" placeholder="ونک&#10;جردن&#10;سعادت‌آباد"></textarea>
						</label>
					</p>
					<p>
						<label><?php esc_html_e( 'عرض مرکز', 'flavor-core' ); ?> <input type="text" name="center_lat" dir="ltr" /></label>
						<label><?php esc_html_e( 'طول مرکز', 'flavor-core' ); ?> <input type="text" name="center_lng" dir="ltr" /></label>
						<label><?php esc_html_e( 'شعاع km', 'flavor-core' ); ?> <input type="number" step="0.1" name="radius_km" /></label>
					</p>
					<button class="button button-primary"><?php esc_html_e( 'افزودن منطقه', 'flavor-core' ); ?></button>
				</form>

				<h2><?php esc_html_e( 'لیست', 'flavor-core' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نام', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'نوع', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'هزینه', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'حداقل', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'محدوده', 'flavor-core' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $zones ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'منطقه‌ای نیست.', 'flavor-core' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $zones as $z ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $z['name'] ); ?></td>
								<td><?php echo esc_html( (string) $z['zone_type'] ); ?></td>
								<td><?php echo esc_html( Currency::format( (int) $z['delivery_fee'] ) ); ?></td>
								<td><?php echo esc_html( Currency::format( (int) $z['min_order'] ) ); ?></td>
								<td>
									<?php
									if ( 'radius' === $z['zone_type'] ) {
										echo esc_html( (string) $z['radius_km'] . ' km' );
									} else {
										echo esc_html( implode( '، ', $z['neighborhoods'] ) );
									}
									?>
								</td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'flavor_save_zone' ); ?>
										<input type="hidden" name="action" value="flavor_save_zone" />
										<input type="hidden" name="flavor_op" value="delete" />
										<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
										<input type="hidden" name="zone_id" value="<?php echo esc_attr( (string) $z['id'] ); ?>" />
										<button class="button-link-delete"><?php esc_html_e( 'حذف', 'flavor-core' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save handler.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'flavor_manage_branch' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		check_admin_referer( 'flavor_save_zone' );
		$branch_id = isset( $_POST['branch_id'] ) ? absint( wp_unslash( $_POST['branch_id'] ) ) : 0;
		$op        = isset( $_POST['flavor_op'] ) ? sanitize_key( wp_unslash( $_POST['flavor_op'] ) ) : '';

		if ( $branch_id && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'این شعبه در دسترس شما نیست.', 'flavor-core' ) );
		}

		if ( 'delete' === $op ) {
			$zid = isset( $_POST['zone_id'] ) ? absint( wp_unslash( $_POST['zone_id'] ) ) : 0;
			if ( $zid ) {
				ZoneRepository::delete( $zid );
			}
		} elseif ( 'create' === $op && $branch_id ) {
			ZoneRepository::create(
				array(
					'branch_id'          => $branch_id,
					'name'               => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
					'zone_type'          => isset( $_POST['zone_type'] ) ? sanitize_key( wp_unslash( $_POST['zone_type'] ) ) : 'neighborhoods',
					'delivery_fee'       => isset( $_POST['delivery_fee'] ) ? absint( wp_unslash( $_POST['delivery_fee'] ) ) : 0,
					'min_order'          => isset( $_POST['min_order'] ) ? absint( wp_unslash( $_POST['min_order'] ) ) : 0,
					'estimated_minutes'  => isset( $_POST['estimated_minutes'] ) ? absint( wp_unslash( $_POST['estimated_minutes'] ) ) : 45,
					'neighborhoods'      => isset( $_POST['neighborhoods'] ) ? sanitize_textarea_field( wp_unslash( $_POST['neighborhoods'] ) ) : '',
					'center_lat'         => isset( $_POST['center_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['center_lat'] ) ) : '',
					'center_lng'         => isset( $_POST['center_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['center_lng'] ) ) : '',
					'radius_km'          => isset( $_POST['radius_km'] ) ? sanitize_text_field( wp_unslash( $_POST['radius_km'] ) ) : '',
					'is_active'          => 1,
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=flavor-zones&branch_id=' . $branch_id ) );
		exit;
	}
}
