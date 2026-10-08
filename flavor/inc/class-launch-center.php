<?php
/**
 * Guided launch checks for the restaurant site.
 *
 * The paid-theme experience does not stop at importing a demo: it tells the
 * owner what is ready, what is missing and where to fix it. This class keeps
 * those checks read-only and actionable; it never changes orders or prices.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Launch_Center
 */
class Launch_Center {

	public const PAGE = 'flavor-launch';

	/**
	 * Register the admin page.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 6 );
	}

	/**
	 * Add a page next to the setup wizard.
	 */
	public static function menu(): void {
		add_theme_page(
			__( 'مرکز آمادگی انتشار', 'flavor' ),
			__( '✅ آمادگی انتشار', 'flavor' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Return all launch checks.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function checks(): array {
		$checks = array();
		$checks[] = self::check(
			'theme',
			__( 'پوستهٔ Flavor فعال است', 'flavor' ),
			true,
			__( 'قالب فعال، تنظیمات ظاهری و صفحهٔ اصلی را در اختیار دارد.', 'flavor' ),
			__( 'مشاهدهٔ پوسته', 'flavor' ),
			admin_url( 'themes.php' )
		);

		$core_active = defined( 'FLAVOR_CORE_VERSION' ) || class_exists( '\\FlavorCore\\Plugin' );
		$checks[] = self::check(
			'core',
			__( 'Flavor Core فعال است', 'flavor' ),
			$core_active,
			$core_active ? __( 'سفارش، شعبه، رزرو، QR و آشپزخانه قابل استفاده‌اند.', 'flavor' ) : __( 'بدون هسته، سایت فقط حالت معرفی دارد و سفارش‌گیری فعال نیست.', 'flavor' ),
			__( 'افزونه‌ها', 'flavor' ),
			admin_url( 'plugins.php' )
		);

		$woo_active = function_exists( 'wc_get_product' ) && class_exists( 'WooCommerce' );
		$checks[] = self::check(
			'woocommerce',
			__( 'WooCommerce فعال است', 'flavor' ),
			$woo_active,
			$woo_active ? __( 'محصول و سبد خرید در دسترس است.', 'flavor' ) : __( 'برای ساخت منو و سفارش آنلاین، WooCommerce را فعال کنید.', 'flavor' ),
			__( 'افزونه‌ها', 'flavor' ),
			admin_url( 'plugins.php' )
		);

		$permalinks = (bool) get_option( 'permalink_structure', '' );
		$checks[] = self::check(
			'permalinks',
			__( 'پیوندهای یکتا تنظیم شده‌اند', 'flavor' ),
			$permalinks,
			$permalinks ? __( 'مسیرهای منو، شعبه و API از ساختار خوانا استفاده می‌کنند.', 'flavor' ) : __( 'ساختار ساده کار می‌کند، اما برای QR و تجربهٔ حرفه‌ای پیوندهای یکتا را ذخیره کنید.', 'flavor' ),
			__( 'تنظیمات پیوند یکتا', 'flavor' ),
			admin_url( 'options-permalink.php' ),
			false
		);

		$front_id = absint( get_option( 'page_on_front', 0 ) );
		$front_ok = 'page' === get_option( 'show_on_front' ) && $front_id && 'publish' === get_post_status( $front_id );
		$checks[] = self::check(
			'front_page',
			__( 'صفحهٔ نخست منتشر شده است', 'flavor' ),
			(bool) $front_ok,
			$front_ok ? __( 'صفحهٔ اصلی برای بازدیدکننده قابل مشاهده است.', 'flavor' ) : __( 'یک دمو درون‌ریزی کنید یا صفحهٔ خانه را در تنظیمات خواندن انتخاب کنید.', 'flavor' ),
			__( 'درون‌ریزی دموها', 'flavor' ),
			admin_url( 'themes.php?page=flavor-demos' )
		);

		$menu_page = get_page_by_path( 'menu' );
		$menu_ok   = $menu_page && 'publish' === $menu_page->post_status;
		$checks[]  = self::check(
			'menu_page',
			__( 'صفحهٔ منو وجود دارد', 'flavor' ),
			(bool) $menu_ok,
			$menu_ok ? __( 'مهمان می‌تواند منوی سفارش را باز کند.', 'flavor' ) : __( 'صفحهٔ منو با قالب «منوی غذا» ساخته نشده است.', 'flavor' ),
			__( 'راه‌اندازی سریع', 'flavor' ),
			admin_url( 'themes.php?page=flavor-setup' )
		);

		$branch_id = self::branch_id();
		$branch_ok = $branch_id && 'publish' === get_post_status( $branch_id ) && get_post_meta( $branch_id, '_flavor_city', true ) && get_post_meta( $branch_id, '_flavor_address', true );
		$checks[]  = self::check(
			'branch',
			__( 'شعبهٔ اصلی با نشانی و شهر فعال است', 'flavor' ),
			(bool) $branch_ok,
			$branch_ok ? sprintf( __( 'شعبهٔ پیش‌فرض: %s', 'flavor' ), get_the_title( $branch_id ) ) : __( 'برای QR، ساعت کاری، رزرو و محدودهٔ ارسال یک شعبه با نشانی کامل بسازید.', 'flavor' ),
			__( 'شعبه‌ها', 'flavor' ),
			admin_url( 'admin.php?page=flavor-core' )
		);

		$modes = $branch_id ? get_post_meta( $branch_id, '_flavor_order_modes', true ) : array();
		$modes_ok = is_array( $modes ) && ! empty( array_intersect( $modes, array( 'dine_in', 'takeaway', 'delivery' ) ) );
		$checks[] = self::check(
			'order_modes',
			__( 'حالت‌های سفارش‌گیری شعبه ثبت شده‌اند', 'flavor' ),
			$modes_ok,
			$modes_ok ? implode( '، ', array_intersect( $modes, array( 'dine_in', 'takeaway', 'delivery' ) ) ) : __( 'حداقل یک حالت سالن، بیرون‌بر یا ارسال را برای شعبه انتخاب کنید.', 'flavor' ),
			__( 'ویرایش شعبه', 'flavor' ),
			admin_url( 'admin.php?page=flavor-core' )
		);

		$hours_ok = false;
		if ( $branch_id && class_exists( '\\FlavorCore\\Database\\Schema' ) ) {
			global $wpdb;
			$hours_table = \FlavorCore\Database\Schema::table( 'flavor_branch_hours' );
			$table_exists = $hours_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $hours_table ) ) );
			if ( $table_exists ) {
				$hours_ok = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$hours_table} WHERE branch_id = %d AND is_closed = 0", $branch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}
		$checks[] = self::check(
			'opening_hours',
			__( 'ساعات کاری شعبه ذخیره شده‌اند', 'flavor' ),
			$hours_ok,
			$hours_ok ? __( 'ساعت‌های فعال برای رزرو و نمایش شعبه در دسترس‌اند.', 'flavor' ) : __( 'ساعت کاری را در ویزارد ذخیره کنید یا در تنظیمات شعبه وارد کنید.', 'flavor' ),
			__( 'راه‌اندازی سریع', 'flavor' ),
			admin_url( 'themes.php?page=flavor-setup' )
		);

		$product_count = 0;
		if ( post_type_exists( 'product' ) ) {
			$counts       = wp_count_posts( 'product' );
			$product_count = isset( $counts->publish ) ? (int) $counts->publish : 0;
		}
		$checks[] = self::check(
			'menu_items',
			__( 'حداقل یک آیتم منوی منتشر وجود دارد', 'flavor' ),
			$product_count > 0,
			$product_count > 0 ? sprintf( __( '%d محصول منتشر شده است.', 'flavor' ), $product_count ) : __( 'یک دمو درون‌ریزی کنید یا اولین آیتم غذا را در ویزارد بسازید.', 'flavor' ),
			__( 'افزودن محصول', 'flavor' ),
			admin_url( 'post-new.php?post_type=product' )
		);

		$currency_ok = false;
		if ( class_exists( '\\FlavorCore\\Support\\Settings' ) ) {
			$currency_ok = 'irt' === (string) \FlavorCore\Support\Settings::get( 'currency_display', 'irt' );
		}
		$checks[] = self::check(
			'currency',
			__( 'واحد نمایش روی تومان است', 'flavor' ),
			$currency_ok,
			$currency_ok ? __( 'قیمت‌ها برای مشتری ایرانی با واحد تومان نمایش داده می‌شوند.', 'flavor' ) : __( 'واحد نمایش را در تنظیمات رستوران روی تومان بگذارید.', 'flavor' ),
			__( 'تنظیمات رستوران', 'flavor' ),
			admin_url( 'admin.php?page=flavor-settings' ),
			false
		);

		$gateway_ok = false;
		if ( class_exists( 'WC_Payment_Gateways' ) ) {
			$gateways   = \WC_Payment_Gateways::instance()->get_available_payment_gateways();
			$gateway_ok = is_array( $gateways ) && ! empty( $gateways );
		}
		$checks[] = self::check(
			'gateway',
			__( 'حداقل یک روش پرداخت فعال است', 'flavor' ),
			$gateway_ok,
			$gateway_ok ? __( 'WooCommerce حداقل یک درگاه/روش پرداخت فعال دارد.', 'flavor' ) : __( 'درگاه بانکی یا روش پرداخت محلی را در WooCommerce فعال کنید.', 'flavor' ),
			__( 'روش‌های پرداخت', 'flavor' ),
			admin_url( 'admin.php?page=wc-settings&tab=checkout' )
		);

		$email_ok = is_email( (string) get_option( 'admin_email', '' ) );
		$checks[] = self::check(
			'email',
			__( 'ایمیل مدیر معتبر است', 'flavor' ),
			$email_ok,
			$email_ok ? __( 'برای اعلان‌های رزرو و سفارش یک آدرس معتبر ثبت شده است.', 'flavor' ) : __( 'ایمیل مدیر را در تنظیمات عمومی اصلاح کنید.', 'flavor' ),
			__( 'تنظیمات عمومی', 'flavor' ),
			admin_url( 'options-general.php' ),
			false
		);

		$sms_ok = true;
		$sms_description = __( 'حالت توسعه فعال است؛ OTP در لاگ محلی ثبت می‌شود و پیامک واقعی ارسال نمی‌شود.', 'flavor' );
		if ( class_exists( '\\FlavorCore\\Support\\Settings' ) ) {
			$provider = (string) \FlavorCore\Support\Settings::get( 'sms_provider', 'dev' );
			$sms_ok   = 'dev' !== $provider;
			$sms_description = $sms_ok ? sprintf( __( 'ارائه‌دهندهٔ پیامک «%s» انتخاب شده است.', 'flavor' ), $provider ) : $sms_description;
		}
		$checks[] = self::check(
			'sms',
			__( 'پیامک واقعی پیکربندی شده است', 'flavor' ),
			$sms_ok,
			$sms_description,
			__( 'تنظیمات رستوران', 'flavor' ),
			admin_url( 'admin.php?page=flavor-settings' ),
			false
		);

		$checks[] = self::check(
			'ssl',
			__( 'اتصال امن HTTPS است', 'flavor' ),
			is_ssl(),
			is_ssl() ? __( 'فرم ورود، OTP و پرداخت روی اتصال امن اجرا می‌شوند.', 'flavor' ) : __( 'برای سایت واقعی SSL را فعال کنید؛ محیط localhost می‌تواند این مورد را نادیده بگیرد.', 'flavor' ),
			__( 'تنظیمات عمومی', 'flavor' ),
			admin_url( 'options-general.php' ),
			false
		);

		$checks[] = self::check(
			'test_order',
			__( 'سفارش آزمایشی ثبت شده است', 'flavor' ),
			false,
			__( 'این check عمداً خودکار مبلغ‌گذاری یا سفارش واقعی نمی‌سازد؛ یک سفارش staging را دستی از QR تا آشپزخانه بررسی کنید.', 'flavor' ),
			__( 'باز کردن منو', 'flavor' ),
			$menu_ok ? get_permalink( $menu_page ) : home_url( '/menu/' ),
			false,
			true
		);

		return $checks;
	}

	/**
	 * Render the launch page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}
		$checks = self::checks();
		$required = array_filter( $checks, static function ( $check ) { return ! empty( $check['required'] ); } );
		$passed   = count( array_filter( $required, static function ( $check ) { return 'pass' === $check['status']; } ) );
		$total    = count( $required );
		$percent  = $total ? (int) round( ( $passed / $total ) * 100 ) : 0;
		?>
		<div class="wrap flavor-launch-center" dir="rtl">
			<style>
				.flavor-launch-center{max-width:1060px}.flavor-launch-hero{display:flex;justify-content:space-between;gap:24px;align-items:center;margin:24px 0;padding:28px;border-radius:18px;background:linear-gradient(135deg,#253e55,#8a5a2b);color:#fff}.flavor-launch-hero h1{margin:0 0 8px;color:#fff}.flavor-launch-hero p{margin:0;opacity:.88}.flavor-launch-score{min-width:120px;text-align:center}.flavor-launch-score strong{display:block;font-size:38px;line-height:1}.flavor-launch-actions{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}.flavor-launch-actions a{background:#fff!important;color:#253e55!important;border-color:#fff!important}.flavor-launch-table{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden}.flavor-launch-table td,.flavor-launch-table th{padding:14px 16px;vertical-align:top}.flavor-launch-table tr+tr td{border-top:1px solid #edf0f4}.flavor-launch-status{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;white-space:nowrap}.flavor-launch-status--pass{background:#dcfce7;color:#166534}.flavor-launch-status--warning{background:#fef3c7;color:#92400e}.flavor-launch-status--fail{background:#fee2e2;color:#991b1b}.flavor-launch-status--info{background:#e0f2fe;color:#075985}.flavor-launch-description{color:#5f6b78}.flavor-launch-action{white-space:nowrap}.flavor-launch-bar{height:8px;background:rgba(255,255,255,.22);border-radius:999px;margin-top:16px;overflow:hidden}.flavor-launch-bar span{display:block;height:100%;background:#fff;border-radius:inherit;width:<?php echo esc_attr( (string) $percent ); ?>%}@media(max-width:760px){.flavor-launch-hero{display:block}.flavor-launch-score{text-align:right;margin-top:20px}.flavor-launch-table th:nth-child(2),.flavor-launch-table td:nth-child(2){display:none}}
			</style>
			<div class="flavor-launch-hero">
				<div>
					<h1><?php esc_html_e( 'مرکز آمادگی انتشار Flavor', 'flavor' ); ?></h1>
					<p><?php esc_html_e( 'قبل از تبلیغ سایت، وابستگی‌ها، منو، شعبه، پرداخت و مسیر سفارش را بررسی کنید.', 'flavor' ); ?></p>
					<div class="flavor-launch-bar" aria-label="<?php esc_attr_e( 'درصد آمادگی', 'flavor' ); ?>"><span></span></div>
				</div>
				<div class="flavor-launch-score"><strong><?php echo esc_html( (string) $percent ); ?>٪</strong><span><?php echo esc_html( sprintf( __( '%d از %d مورد اصلی', 'flavor' ), $passed, $total ) ); ?></span></div>
			</div>
			<div class="flavor-launch-actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'themes.php?page=flavor-setup' ) ); ?>"><?php esc_html_e( 'راه‌اندازی سریع', 'flavor' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=flavor-demos' ) ); ?>"><?php esc_html_e( 'درون‌ریزی دمو', 'flavor' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'سفارشی‌سازی ظاهر', 'flavor' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=flavor-transfer' ) ); ?>"><?php esc_html_e( 'پشتیبان تنظیمات', 'flavor' ); ?></a>
			</div>
			<table class="widefat flavor-launch-table">
				<thead><tr><th><?php esc_html_e( 'بررسی', 'flavor' ); ?></th><th><?php esc_html_e( 'وضعیت', 'flavor' ); ?></th><th><?php esc_html_e( 'توضیح', 'flavor' ); ?></th><th><?php esc_html_e( 'اقدام', 'flavor' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $checks as $check ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $check['label'] ); ?></strong><?php if ( ! empty( $check['required'] ) ) : ?><br /><small><?php esc_html_e( 'مورد اصلی انتشار', 'flavor' ); ?></small><?php endif; ?></td>
						<td><span class="flavor-launch-status flavor-launch-status--<?php echo esc_attr( $check['status'] ); ?>"><?php echo esc_html( $check['status_label'] ); ?></span></td>
						<td class="flavor-launch-description"><?php echo esc_html( $check['description'] ); ?></td>
						<td class="flavor-launch-action"><a href="<?php echo esc_url( $check['url'] ); ?>"><?php echo esc_html( $check['action'] ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Resolve the current default branch without requiring the core class.
	 */
	private static function branch_id(): int {
		if ( class_exists( '\\FlavorCore\\PostTypes\\BranchPostType' ) ) {
			$default_id = (int) \FlavorCore\PostTypes\BranchPostType::default_id();
			if ( $default_id ) {
				return $default_id;
			}
		}
		$ids = get_posts( array( 'post_type' => 'flavor_branch', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return empty( $ids ) ? 0 : (int) $ids[0];
	}

	/**
	 * Build a normalized check item.
	 */
	private static function check( string $id, string $label, bool $pass, string $description, string $action, string $url, bool $required = true, bool $info = false ): array {
		$status = $info ? 'info' : ( $pass ? 'pass' : ( $required ? 'fail' : 'warning' ) );
		$labels = array( 'pass' => __( 'آماده', 'flavor' ), 'fail' => __( 'نیازمند اقدام', 'flavor' ), 'warning' => __( 'هشدار', 'flavor' ), 'info' => __( 'بررسی دستی', 'flavor' ) );
		return array( 'id' => $id, 'label' => $label, 'status' => $status, 'status_label' => $labels[ $status ], 'description' => $description, 'action' => $action, 'url' => $url, 'required' => $required );
	}
}
