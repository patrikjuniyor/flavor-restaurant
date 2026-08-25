<?php
/**
 * Top-level admin menu and settings page.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Admin;

use FlavorCore\Delivery\ZoneAdmin;
use FlavorCore\Loyalty\LoyaltyAdmin;
use FlavorCore\Menu\AvailabilityAdmin;
use FlavorCore\Menu\ScheduleAdmin;
use FlavorCore\Order\PhoneOrderAdmin;
use FlavorCore\Reservation\ReservationAdmin;
use FlavorCore\SMS\SmsManager;
use FlavorCore\Support\Settings;
use FlavorCore\Table\TableAdmin;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminMenus
 */
class AdminMenus {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Pages.
	 */
	public function menu(): void {
		add_menu_page(
			__( 'رستوران مستقیم', 'flavor-core' ),
			__( 'رستوران مستقیم', 'flavor-core' ),
			'flavor_manage_branch',
			'flavor-core',
			array( $this, 'render_dashboard' ),
			'dashicons-food',
			56
		);

		add_submenu_page(
			'flavor-core',
			__( 'پیشخوان', 'flavor-core' ),
			__( 'پیشخوان', 'flavor-core' ),
			'flavor_manage_branch',
			'flavor-core',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'تنظیمات', 'flavor-core' ),
			__( 'تنظیمات', 'flavor-core' ),
			'flavor_manage_settings',
			'flavor-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'میزها و QR', 'flavor-core' ),
			__( 'میزها و QR', 'flavor-core' ),
			'flavor_manage_branch',
			'flavor-tables',
			array( TableAdmin::class, 'render' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'مناطق ارسال', 'flavor-core' ),
			__( 'مناطق ارسال', 'flavor-core' ),
			'flavor_manage_branch',
			'flavor-zones',
			array( ZoneAdmin::class, 'render' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'آشپزخانه', 'flavor-core' ),
			__( 'آشپزخانه', 'flavor-core' ),
			'flavor_manage_kitchen',
			'flavor-kitchen',
			array( $this, 'render_kitchen_admin' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'رزرو میز', 'flavor-core' ),
			__( 'رزرو میز', 'flavor-core' ),
			'flavor_manage_reservations',
			'flavor-reservations',
			array( ReservationAdmin::class, 'render' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'سفارش تلفنی', 'flavor-core' ),
			__( 'سفارش تلفنی', 'flavor-core' ),
			'flavor_create_phone_order',
			'flavor-phone',
			array( PhoneOrderAdmin::class, 'render' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'زمان‌بندی منو', 'flavor-core' ),
			__( 'زمان‌بندی منو', 'flavor-core' ),
			'flavor_manage_branch',
			'flavor-schedules',
			array( ScheduleAdmin::class, 'render' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'موجودی لحظه‌ای', 'flavor-core' ),
			__( 'موجودی لحظه‌ای', 'flavor-core' ),
			'flavor_manage_kitchen',
			'flavor-availability',
			array( AvailabilityAdmin::class, 'render' )
		);

		add_submenu_page(
			'flavor-core',
			__( 'باشگاه مشتریان', 'flavor-core' ),
			__( 'باشگاه مشتریان', 'flavor-core' ),
			'flavor_manage_loyalty',
			'flavor-loyalty',
			array( LoyaltyAdmin::class, 'render' )
		);
	}

	/**
	 * Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'flavor_core',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'show_in_rest'      => false,
			)
		);

		register_setting(
			'flavor_core',
			'flavor_core_remove_data',
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return 'yes' === $value ? 'yes' : 'no';
				},
				'default'           => 'no',
			)
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param mixed $input Raw.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array {
		$current = Settings::all();
		if ( ! is_array( $input ) ) {
			return $current;
		}

		$current['currency_storage']     = in_array( $input['currency_storage'] ?? '', array( 'irr', 'irt' ), true ) ? $input['currency_storage'] : 'irr';
		$current['currency_display']     = in_array( $input['currency_display'] ?? '', array( 'irr', 'irt' ), true ) ? $input['currency_display'] : 'irt';
		$current['digits']               = in_array( $input['digits'] ?? '', array( 'persian', 'latin' ), true ) ? $input['digits'] : 'persian';
		$current['otp_length']           = min( 6, max( 4, absint( $input['otp_length'] ?? 5 ) ) );
		$current['otp_ttl_minutes']      = min( 10, max( 1, absint( $input['otp_ttl_minutes'] ?? 2 ) ) );
		$current['otp_max_per_10min']    = min( 10, max( 1, absint( $input['otp_max_per_10min'] ?? 3 ) ) );
		$current['reservation_buffer']   = min( 120, max( 0, absint( $input['reservation_buffer'] ?? 30 ) ) );
		$current['kitchen_poll_seconds'] = min( 60, max( 5, absint( $input['kitchen_poll_seconds'] ?? 15 ) ) );
		$current['pay_at_counter']       = ! empty( $input['pay_at_counter'] ) ? 'yes' : 'no';
		$current['cash_on_delivery']     = ! empty( $input['cash_on_delivery'] ) ? 'yes' : 'no';
		$current['card_on_delivery']     = ! empty( $input['card_on_delivery'] ) ? 'yes' : 'no';
		$current['guest_checkout']       = ! empty( $input['guest_checkout'] ) ? 'yes' : 'no';
		$current['kitchen_sound']        = ! empty( $input['kitchen_sound'] ) ? 'yes' : 'no';
		$allowed_sms                     = array( 'dev', 'melipayamak', 'faraz', 'kavenegar' );
		$current['sms_provider']         = in_array( $input['sms_provider'] ?? '', $allowed_sms, true ) ? $input['sms_provider'] : 'dev';

		return $current;
	}

	/**
	 * Admin CSS.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'flavor' ) ) {
			return;
		}
		wp_enqueue_style(
			'flavor-core-admin',
			FLAVOR_CORE_URL . 'assets/admin/css/admin.css',
			array(),
			FLAVOR_CORE_VERSION
		);
	}

	/**
	 * Home dashboard.
	 */
	public function render_dashboard(): void {
		$sample = Currency::format( 2500000 );
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'رستوران مستقیم', 'flavor-core' ); ?></h1>
			<p><?php esc_html_e( 'فاز ۳ — رزرو شمسی، زمان‌بندی منو، تخفیف، سفارش تلفنی و باشگاه مشتریان.', 'flavor-core' ); ?></p>
			<ul class="flavor-admin__checklist">
				<li><?php esc_html_e( 'صفحه با قالب «رزرو میز» بسازید.', 'flavor-core' ); ?></li>
				<li><?php esc_html_e( 'از منوی رزرو، ورود حضوری را تست کنید.', 'flavor-core' ); ?></li>
				<li><?php esc_html_e( 'کد تخفیف ووکامرس بسازید و انقضای شمسی را پر کنید.', 'flavor-core' ); ?></li>
				<li><?php echo esc_html( sprintf( /* translators: formatted money */ __( 'نمونه نمایش ارز: %s', 'flavor-core' ), $sample ) ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Settings form.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'flavor_manage_settings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		$s = Settings::all();
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'تنظیمات رستوران مستقیم', 'flavor-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'flavor_core' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'واحد ذخیره', 'flavor-core' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( Settings::OPTION ); ?>[currency_storage]">
								<option value="irr" <?php selected( $s['currency_storage'], 'irr' ); ?>><?php esc_html_e( 'ریال', 'flavor-core' ); ?></option>
								<option value="irt" <?php selected( $s['currency_storage'], 'irt' ); ?>><?php esc_html_e( 'تومان', 'flavor-core' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'پیش‌فرض ریال است تا با درگاه‌های ایرانی هماهنگ بماند.', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'واحد نمایش', 'flavor-core' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( Settings::OPTION ); ?>[currency_display]">
								<option value="irt" <?php selected( $s['currency_display'], 'irt' ); ?>><?php esc_html_e( 'تومان', 'flavor-core' ); ?></option>
								<option value="irr" <?php selected( $s['currency_display'], 'irr' ); ?>><?php esc_html_e( 'ریال', 'flavor-core' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ارقام', 'flavor-core' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( Settings::OPTION ); ?>[digits]">
								<option value="persian" <?php selected( $s['digits'], 'persian' ); ?>><?php esc_html_e( 'فارسی (۲۵٬۰۰۰)', 'flavor-core' ); ?></option>
								<option value="latin" <?php selected( $s['digits'], 'latin' ); ?>><?php esc_html_e( 'لاتین (25,000)', 'flavor-core' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'پرداخت پای صندوق', 'flavor-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[pay_at_counter]" value="1" <?php checked( $s['pay_at_counter'], 'yes' ); ?> /> <?php esc_html_e( 'فعال', 'flavor-core' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'پرداخت هنگام تحویل', 'flavor-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[cash_on_delivery]" value="1" <?php checked( $s['cash_on_delivery'] ?? 'yes', 'yes' ); ?> /> <?php esc_html_e( 'فعال', 'flavor-core' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'کارت‌خوان دم در', 'flavor-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[card_on_delivery]" value="1" <?php checked( $s['card_on_delivery'] ?? 'yes', 'yes' ); ?> /> <?php esc_html_e( 'فعال', 'flavor-core' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'صدای سفارش جدید', 'flavor-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[kitchen_sound]" value="1" <?php checked( $s['kitchen_sound'] ?? 'yes', 'yes' ); ?> /> <?php esc_html_e( 'فعال', 'flavor-core' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'ارائه‌دهنده پیامک', 'flavor-core' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( Settings::OPTION ); ?>[sms_provider]">
								<?php foreach ( SmsManager::providers() as $p ) : ?>
									<option value="<?php echo esc_attr( $p->slug() ); ?>" <?php selected( $s['sms_provider'] ?? 'dev', $p->slug() ); ?>>
										<?php echo esc_html( $p->label() . ( $p->is_available() ? '' : ' — ' . __( 'نصب نشده', 'flavor-core' ) ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'اگر درگاهی نصب نباشد، کد OTP در حالت توسعه فقط لاگ می‌شود.', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'بافر رزرو (دقیقه)', 'flavor-core' ); ?></th>
						<td><input type="number" min="0" max="120" name="<?php echo esc_attr( Settings::OPTION ); ?>[reservation_buffer]" value="<?php echo esc_attr( (string) ( $s['reservation_buffer'] ?? 30 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'مدت رزرو (دقیقه)', 'flavor-core' ); ?></th>
						<td><input type="number" min="30" max="240" name="<?php echo esc_attr( Settings::OPTION ); ?>[reservation_duration]" value="<?php echo esc_attr( (string) ( $s['reservation_duration'] ?? 90 ) ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'پنهان کردن آیتم خارج از وعده', 'flavor-core' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[hide_offschedule]" value="1" <?php checked( $s['hide_offschedule'] ?? 'yes', 'yes' ); ?> /> <?php esc_html_e( 'فعال', 'flavor-core' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'بازه نظرسنجی آشپزخانه (ثانیه)', 'flavor-core' ); ?></th>
						<td><input type="number" min="5" max="60" name="<?php echo esc_attr( Settings::OPTION ); ?>[kitchen_poll_seconds]" value="<?php echo esc_attr( (string) $s['kitchen_poll_seconds'] ); ?>" /></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'حذف داده هنگام Uninstall', 'flavor-core' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="flavor_core_remove_data" value="yes" <?php checked( get_option( 'flavor_core_remove_data' ), 'yes' ); ?> />
								<?php esc_html_e( 'اگر افزونه پاک شود، جدول‌ها و CPT شعبه حذف شوند. پیش‌فرض خاموش است.', 'flavor-core' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Admin-side kitchen (same view as the kiosk).
	 */
	public function render_kitchen_admin(): void {
		$view = FLAVOR_CORE_PATH . 'admin/views/kitchen-dashboard.php';
		if ( is_readable( $view ) ) {
			$flavor_admin_wrap = true;
			include $view;
		}
	}
}
