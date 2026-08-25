<?php
/**
 * Reservation calendar + walk-in.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reservation;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReservationAdmin
 */
class ReservationAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_save_reservation', array( $this, 'handle' ) );
	}

	/**
	 * Render page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_manage_reservations' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		$branch_id = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : BranchPostType::default_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date      = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : current_time( 'Y-m-d' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( preg_match( '/^14\d{2}/', $date ) ) {
			$date = Jalali::jalali_iso_to_gregorian( $date );
		}
		$branches = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 50,
			)
		);
		$rows   = $branch_id ? ReservationRepository::for_date( $branch_id, $date, false ) : array();
		$jalali = Jalali::format( $date, true );
		$prev   = gmdate( 'Y-m-d', strtotime( $date . ' -1 day' ) );
		$next   = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'رزرو میز', 'flavor-core' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="flavor-reservations" />
				<label><?php esc_html_e( 'شعبه', 'flavor-core' ); ?>
					<select name="branch_id" onchange="this.form.submit()">
						<?php foreach ( $branches as $b ) : ?>
							<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( $branch_id, $b->ID ); ?>><?php echo esc_html( get_the_title( $b ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'تاریخ میلادی', 'flavor-core' ); ?>
					<input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()" />
				</label>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=flavor-reservations&branch_id=' . $branch_id . '&date=' . $prev ) ); ?>">‹</a>
				<strong><?php echo esc_html( $jalali ); ?></strong>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=flavor-reservations&branch_id=' . $branch_id . '&date=' . $next ) ); ?>">›</a>
			</form>

			<h2><?php esc_html_e( 'ورود حضوری / تلفنی', 'flavor-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'flavor_save_reservation' ); ?>
				<input type="hidden" name="action" value="flavor_save_reservation" />
				<input type="hidden" name="flavor_op" value="create" />
				<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
				<input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>" />
				<input type="text" name="name" placeholder="<?php esc_attr_e( 'نام', 'flavor-core' ); ?>" required />
				<input type="text" name="mobile" placeholder="09xxxxxxxxx" dir="ltr" required />
				<input type="time" name="time" required />
				<input type="number" name="party_size" value="2" min="1" max="20" />
				<select name="section">
					<option value=""><?php esc_html_e( 'هر بخش', 'flavor-core' ); ?></option>
					<option value="indoor"><?php esc_html_e( 'سالن', 'flavor-core' ); ?></option>
					<option value="outdoor"><?php esc_html_e( 'فضای باز', 'flavor-core' ); ?></option>
					<option value="window"><?php esc_html_e( 'پنجره', 'flavor-core' ); ?></option>
					<option value="bar"><?php esc_html_e( 'بار', 'flavor-core' ); ?></option>
				</select>
				<select name="source">
					<option value="walk_in"><?php esc_html_e( 'حضوری', 'flavor-core' ); ?></option>
					<option value="phone"><?php esc_html_e( 'تلفن', 'flavor-core' ); ?></option>
				</select>
				<button class="button button-primary"><?php esc_html_e( 'ثبت رزرو', 'flavor-core' ); ?></button>
			</form>

			<table class="widefat striped" style="margin-top:1rem;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ساعت', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'نام', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'موبایل', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'نفر', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'flavor-core' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'رزروی در این روز نیست.', 'flavor-core' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( substr( (string) $r['reservation_time'], 0, 5 ) ); ?></td>
							<td><?php echo esc_html( (string) $r['customer_name'] ); ?></td>
							<td dir="ltr"><?php echo esc_html( (string) $r['customer_mobile'] ); ?></td>
							<td><?php echo esc_html( (string) $r['party_size'] ); ?></td>
							<td><?php echo esc_html( (string) $r['status'] ); ?></td>
							<td>
								<?php foreach ( array( 'confirmed' => 'تایید', 'seated' => 'نشست', 'completed' => 'تمام', 'cancelled' => 'لغو', 'no_show' => 'نیامد' ) as $st => $lab ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'flavor_save_reservation' ); ?>
										<input type="hidden" name="action" value="flavor_save_reservation" />
										<input type="hidden" name="flavor_op" value="status" />
										<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
										<input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>" />
										<input type="hidden" name="res_id" value="<?php echo esc_attr( (string) $r['id'] ); ?>" />
										<input type="hidden" name="status" value="<?php echo esc_attr( $st ); ?>" />
										<button class="button-link"><?php echo esc_html( $lab ); ?></button>
									</form>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Save handler.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'flavor_manage_reservations' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		check_admin_referer( 'flavor_save_reservation' );
		$branch_id = isset( $_POST['branch_id'] ) ? absint( wp_unslash( $_POST['branch_id'] ) ) : 0;
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : current_time( 'Y-m-d' );
		$op        = isset( $_POST['flavor_op'] ) ? sanitize_key( wp_unslash( $_POST['flavor_op'] ) ) : '';

		if ( $branch_id && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'این شعبه در دسترس شما نیست.', 'flavor-core' ) );
		}

		if ( 'status' === $op ) {
			$id = isset( $_POST['res_id'] ) ? absint( wp_unslash( $_POST['res_id'] ) ) : 0;
			$st = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
			if ( $id && $st ) {
				$old = ReservationRepository::find( $id );
				ReservationRepository::set_status( $id, $st );
				if ( $old && 'confirmed' === $st && 'confirmed' !== $old['status'] ) {
					$fresh = ReservationRepository::find( $id );
					if ( $fresh ) {
						ReservationService::sms_confirm( $fresh );
					}
				}
			}
		} elseif ( 'create' === $op ) {
			ReservationService::book(
				array(
					'branch_id'  => $branch_id,
					'date'       => $date,
					'time'       => isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '',
					'party_size' => isset( $_POST['party_size'] ) ? absint( wp_unslash( $_POST['party_size'] ) ) : 2,
					'section'    => isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '',
					'name'       => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
					'mobile'     => isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '',
					'source'     => isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'walk_in',
					'force'      => true,
				)
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=flavor-reservations&branch_id=' . $branch_id . '&date=' . rawurlencode( $date ) ) );
		exit;
	}
}
