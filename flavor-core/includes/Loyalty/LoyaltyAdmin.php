<?php
/**
 * Loyalty settings + manual point adjust.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Loyalty;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class LoyaltyAdmin
 */
class LoyaltyAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_adjust_points', array( $this, 'handle' ) );
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_manage_loyalty' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		$mobile = isset( $_GET['mobile'] ) ? sanitize_text_field( wp_unslash( $_GET['mobile'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user   = null;
		$sum    = null;
		if ( $mobile ) {
			$norm = Iran::normalize_mobile( $mobile );
			if ( $norm ) {
				$found = get_users(
					array(
						'meta_key'   => OtpAuth::META_MOBILE,
						'meta_value' => $norm,
						'number'     => 1,
					)
				);
				if ( $found ) {
					$user = $found[0];
					$sum  = PointsManager::summary( $user->ID );
				}
			}
		}
		$s = Settings::all();
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'باشگاه مشتریان', 'flavor-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'flavor_core' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'هر چند تومان یک امتیاز', 'flavor-core' ); ?></th>
						<td><input type="number" name="<?php echo esc_attr( Settings::OPTION ); ?>[loyalty_unit_toman]" value="<?php echo esc_attr( (string) ( $s['loyalty_unit_toman'] ?? 10000 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'امتیاز به ازای هر واحد', 'flavor-core' ); ?></th>
						<td><input type="number" name="<?php echo esc_attr( Settings::OPTION ); ?>[loyalty_points_per_unit]" value="<?php echo esc_attr( (string) ( $s['loyalty_points_per_unit'] ?? 1 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ارزش هر امتیاز (تومان)', 'flavor-core' ); ?></th>
						<td><input type="number" name="<?php echo esc_attr( Settings::OPTION ); ?>[loyalty_toman_per_point]" value="<?php echo esc_attr( (string) ( $s['loyalty_toman_per_point'] ?? 1000 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'تعداد مهر برای یک آیتم رایگان', 'flavor-core' ); ?></th>
						<td><input type="number" name="<?php echo esc_attr( Settings::OPTION ); ?>[loyalty_stamp_target]" value="<?php echo esc_attr( (string) ( $s['loyalty_stamp_target'] ?? 10 ) ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'ذخیره تنظیمات وفاداری', 'flavor-core' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'جستجوی مشتری', 'flavor-core' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="flavor-loyalty" />
				<input type="text" name="mobile" value="<?php echo esc_attr( $mobile ); ?>" placeholder="09xxxxxxxxx" dir="ltr" />
				<button class="button"><?php esc_html_e( 'جستجو', 'flavor-core' ); ?></button>
			</form>
			<?php if ( $user && $sum ) : ?>
				<p>
					<?php echo esc_html( $user->display_name ); ?>
					— <?php echo esc_html( sprintf( /* translators: points */ __( 'امتیاز: %d', 'flavor-core' ), $sum['points'] ) ); ?>
					— <?php echo esc_html( sprintf( /* translators: stamps */ __( 'مهر: %1$d / %2$d', 'flavor-core' ), $sum['stamp_cycle'], $sum['stamp_target'] ) ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'flavor_adjust_points' ); ?>
					<input type="hidden" name="action" value="flavor_adjust_points" />
					<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user->ID ); ?>" />
					<input type="hidden" name="mobile" value="<?php echo esc_attr( $mobile ); ?>" />
					<input type="number" name="delta" value="0" />
					<button class="button"><?php esc_html_e( 'اعمال امتیاز (+/−)', 'flavor-core' ); ?></button>
				</form>
			<?php elseif ( $mobile ) : ?>
				<p><?php esc_html_e( 'مشتری پیدا نشد.', 'flavor-core' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Adjust.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'flavor_manage_loyalty' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		check_admin_referer( 'flavor_adjust_points' );
		$uid   = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$delta = isset( $_POST['delta'] ) ? (int) wp_unslash( $_POST['delta'] ) : 0;
		$mob   = isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '';
		if ( $uid && 0 !== $delta ) {
			PointsManager::adjust( $uid, $delta, 'adjust', 0, 'admin' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=flavor-loyalty&mobile=' . rawurlencode( $mob ) ) );
		exit;
	}
}
