<?php
/**
 * Plugin Name: Flavor GitHub Updater
 * Description: دکمهٔ آپدیت مستقیم پوسته Flavor از مخزن گیت‌هاب — فقط برای محیط توسعه.
 * Version:     1.0.0
 * Author:      Flavor Dev
 * License:     GPL-2.0-or-later
 * Network:     false
 *
 * نصب: این فایل را در wp-content/mu-plugins/ قرار دهید.
 * پوشهٔ mu-plugins اگر وجود ندارد بسازیدش.
 *
 * ⚠️  فقط در محیط توسعه / staging استفاده کنید، نه سایت زندهٔ مشتری.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

/**
 * تنظیمات مخزن گیت‌هاب.
 * این مقادیر را مطابق مخزن خودتان تغییر دهید.
 */
final class Flavor_GitHub_Updater {

	/** نام کاربری مالک مخزن */
	const REPO_OWNER = 'patrikjuniyor';

	/** نام مخزن */
	const REPO_NAME  = 'flavor-restaurant';

	/** شاخهٔ پیش‌فرض */
	const BRANCH     = 'main';

	/** نام فولدر پوسته داخل زیپ گیت‌هاب */
	const THEME_SLUG = 'flavor';

	/** توکن دسترسی (شخصی — این مقدار جایگزین می‌شود) */
	private static $token = '';

	/* ------------------------------------------------------------------ */

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init(): void {
		// صفحهٔ مدیریت آپدیت
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		// اجرای آپدیت وقتی دکمه زده شد
		add_action( 'admin_init', array( __CLASS__, 'handle_update_request' ) );
		// نوتیف آپدیت در نوار بالای وردپرس
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_item' ), 999 );
		// بررسی خودکار هر ۱۲ ساعت
		add_action( 'flavor_github_check', array( __CLASS__, 'check_for_updates' ) );
		if ( ! wp_next_scheduled( 'flavor_github_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'flavor_github_check' );
		}
	}

	/* ------------------------------------------------------------------ */
	/*  صفحهٔ مدیریت                                                     */
	/* ------------------------------------------------------------------ */

	public static function add_menu_page(): void {
		add_theme_page(
			'آپدیت از گیت‌هاب',
			'🔄 آپدیت Flavor',
			'update_themes',
			'flavor-github-updater',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		$last_check = get_option( 'flavor_github_last_check', 0 );
		$latest_sha = get_option( 'flavor_github_latest_sha', '' );
		$local_sha  = self::get_local_sha();
		$update_available = $latest_sha && $local_sha !== $latest_sha;
		$last_error = get_option( 'flavor_github_last_error', '' );

		// اگر توکن در wp-config تعریف شده باشد
		$has_token = defined( 'FLAVOR_GITHUB_TOKEN' ) && FLAVOR_GITHUB_TOKEN;

		echo '<div class="wrap" style="max-width:720px;direction:rtl;text-align:right;font-family:Tahoma,Vazirmatn,sans-serif;">';
		echo '<h1>🔄 آپدیت پوسته Flavor از گیت‌هاب</h1>';

		// باکس وضعیت
		echo '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:24px;margin:20px 0;box-shadow:0 1px 3px rgba(0,0,0,.04);">';

		echo '<table class="form-table" style="margin:0;">';
		echo '<tr><th style="width:180px;">مخزن:</th><td><code>' . esc_html( self::REPO_OWNER . '/' . self::REPO_NAME ) . '</code> → شاخهٔ <code>' . esc_html( self::BRANCH ) . '</code></td></tr>';
		echo '<tr><th>SHA محلی:</th><td><code>' . esc_html( $local_sha ?: 'نامشخص' ) . '</code></td></tr>';
		echo '<tr><th>SHA آخرین کامیت:</th><td><code>' . esc_html( $latest_sha ?: 'هنوز بررسی نشده' ) . '</code></td></tr>';
		echo '<tr><th>آخرین بررسی:</th><td>' . ( $last_check ? esc_html( self::jalali_date( $last_check ) ) . ' — ' . esc_html( human_time_diff( $last_check ) . ' پیش' ) : 'هرگز' ) . '</td></tr>';
		echo '<tr><th>وضعیت:</th><td>';

		if ( $update_available ) {
			echo '<span style="color:#d63638;font-weight:bold;font-size:15px;">⚠️ نسخهٔ جدید موجود است!</span>';
		} else {
			echo '<span style="color:#00a32a;font-weight:bold;">✅ پوسته به‌روز است.</span>';
		}

		echo '</td></tr>';

		if ( ! $has_token ) {
			echo '<tr><th>توکن:</th><td><span style="color:#d63638;">⚠️ توکن تعریف نشده. خط زیر را به <code>wp-config.php</code> اضافه کنید:</span><br><code style="display:block;margin-top:8px;padding:12px;background:#f0f0f1;border-radius:4px;direction:ltr;text-align:left;">define( \'FLAVOR_GITHUB_TOKEN\', \'ghp_xxxxxxxxxxxx\' );</code></td></tr>';
		} else {
			echo '<tr><th>توکن:</th><td><span style="color:#00a32a;">✅ تعریف شده</span></td></tr>';
		}

		echo '</table>';
		echo '</div>';

		// دکمه‌ها
		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0;">';

		// دکمه بررسی
		echo '<form method="post" style="display:inline;">';
		wp_nonce_field( 'flavor_github_check' );
		echo '<input type="hidden" name="flavor_action" value="check">';
		submit_button( '🔍 بررسی نسخهٔ جدید', 'secondary', 'flavor_check', false );
		echo '</form>';

		// دکمه آپدیت
		if ( $update_available ) {
			echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'آیا مطمئنید؟ فایل‌های فعلی پوسته با نسخهٔ جدید جایگزین می‌شوند.\');">';
			wp_nonce_field( 'flavor_github_update' );
			echo '<input type="hidden" name="flavor_action" value="update">';
			submit_button( '⬇️ آپدیت اکنون', 'primary', 'flavor_update', false );
			echo '</form>';
		}

		echo '</div>';

		// پیام خطا
		if ( $last_error ) {
			echo '<div style="background:#fcf0f1;border:1px solid #d63638;border-radius:6px;padding:16px;margin:16px 0;">';
			echo '<strong>خطای آخرین عملیات:</strong> ' . esc_html( $last_error );
			echo '</div>';
		}

		// راهنما
		echo '<div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:20px;margin:20px 0;">';
		echo '<h3>📖 راهنما</h3>';
		echo '<ol style="line-height:2.2;">';
		echo '<li>توکن شخصی گیت‌هاب را در <code>wp-config.php</code> تعریف کنید.</li>';
		echo '<li>هر وقت کد جدید به گیت‌هاب پوش کردید، به این صفحه بیایید.</li>';
		echo '<li>«بررسی نسخهٔ جدید» بزنید تا SHA آخرین کامیت را ببینید.</li>';
		echo '<li>«آپدیت اکنون» بزنید — فایل‌های پوسته مستقیم از گیت‌هاب دانلود و جایگزین می‌شوند.</li>';
		echo '<li>کش وردپرس را در صورت نیاز پاک کنید.</li>';
		echo '</ol>';
		echo '<p style="color:#666;">⚠️ <strong>این ابزار فقط برای توسعه است.</strong> در سایت زندهٔ مشتری از بستهٔ رسمی نصب استفاده کنید.</p>';
		echo '</div>';

		echo '</div>';
	}

	/* ------------------------------------------------------------------ */
	/*  اجرای عملیات                                                     */
	/* ------------------------------------------------------------------ */

	public static function handle_update_request(): void {
		// بررسی نسخه جدید
		if ( isset( $_POST['flavor_action'] ) && $_POST['flavor_action'] === 'check' ) {
			if ( ! check_admin_referer( 'flavor_github_check' ) ) return;
			self::check_for_updates();
			delete_option( 'flavor_github_last_error' );
			wp_redirect( add_query_arg( 'flavor_checked', '1', menu_page_url( 'flavor-github-updater', false ) ) );
			exit;
		}

		// آپدیت
		if ( isset( $_POST['flavor_action'] ) && $_POST['flavor_action'] === 'update' ) {
			if ( ! check_admin_referer( 'flavor_github_update' ) ) return;
			$result = self::do_update();
			if ( is_wp_error( $result ) ) {
				update_option( 'flavor_github_last_error', $result->get_error_message() );
			} else {
				delete_option( 'flavor_github_last_error' );
			}
			wp_redirect( add_query_arg( 'flavor_updated', is_wp_error( $result ) ? '0' : '1', menu_page_url( 'flavor-github-updater', false ) ) );
			exit;
		}
	}

	/* ------------------------------------------------------------------ */
	/*  بررسی گیت‌هاب                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * SHA آخرین کامیت شاخه را از GitHub API می‌گیرد.
	 */
	public static function check_for_updates(): string {
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/commits/%s',
			self::REPO_OWNER,
			self::REPO_NAME,
			self::BRANCH
		);

		$response = self::github_get( $url );
		if ( is_wp_error( $response ) ) {
			update_option( 'flavor_github_last_error', $response->get_error_message() );
			update_option( 'flavor_github_last_check', time() );
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['sha'] ) ) {
			update_option( 'flavor_github_last_error', 'پاسخ API معتبر نبود.' );
			update_option( 'flavor_github_last_check', time() );
			return '';
		}

		$sha = substr( $body['sha'], 0, 7 );
		update_option( 'flavor_github_latest_sha', $sha );
		update_option( 'flavor_github_last_check', time() );
		delete_option( 'flavor_github_last_error' );
		return $sha;
	}

	/* ------------------------------------------------------------------ */
	/*  آپدیت                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * دانلود زیپ از گیت‌هاب و جایگزینی فایل‌های پوسته.
	 */
	private static function do_update() {
		if ( ! current_user_can( 'update_themes' ) ) {
			return new \WP_Error( 'forbidden', 'شما اجازهٔ آپدیت ندارید.' );
		}

		// ۱. دانلود زیپ شاخه
		$zip_url = sprintf(
			'https://api.github.com/repos/%s/%s/zipball/%s',
			self::REPO_OWNER,
			self::REPO_NAME,
			self::BRANCH
		);

		$response = self::github_get( $zip_url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$zip_body = wp_remote_retrieve_body( $response );
		if ( empty( $zip_body ) ) {
			return new \WP_Error( 'empty', 'فایل زیپ خالی بود.' );
		}

		// ۲. ذخیهٔ موقت
		$tmp_dir  = get_temp_dir();
		$zip_file = $tmp_dir . 'flavor-github-' . time() . '.zip';

		if ( ! file_put_contents( $zip_file, $zip_body ) ) {
			return new \WP_Error( 'write', 'نوشتن فایل زیپ موقت ممکن نشد.' );
		}

		// ۳. باز کردن زیپ
		$zip = new \ZipArchive();
		if ( $zip->open( $zip_file ) !== true ) {
			@unlink( $zip_file );
			return new \WP_Error( 'zip', 'باز کردن فایل زیپ ممکن نشد.' );
		}

		// پیدا کردن نام فولدر داخل زیپ (گیت‌هاب فولدری مثل flavor-restaurant-abc1234 می‌سازد)
		$top_folder = $zip->getNameIndex( 0 );
		$top_folder = trailingslashit( $top_folder );

		// ۴. استخراج موقت
		$extract_dir = $tmp_dir . 'flavor-extract-' . time();
		mkdir( $extract_dir, 0755, true );

		if ( ! $zip->extractTo( $extract_dir ) ) {
			$zip->close();
			@unlink( $zip_file );
			self::rmdir_recursive( $extract_dir );
			return new \WP_Error( 'extract', 'استخراج زیپ ممکن نشد.' );
		}
		$zip->close();
		@unlink( $zip_file );

		// ۵. مسیر پوسته داخل زیپ استخراج‌شده
		$source_theme = $extract_dir . '/' . $top_folder . self::THEME_SLUG;
		if ( ! is_dir( $source_theme ) ) {
			self::rmdir_recursive( $extract_dir );
			return new \WP_Error( 'not_found', 'فولدر پوسته (' . self::THEME_SLUG . ') داخل زیپ پیدا نشد.' );
		}

		// ۶. مسیر فعلی پوسته
		$theme_dir = get_theme_root() . '/' . self::THEME_SLUG;

		// بکاپ سریع از SHA فعلی
		$current_sha = self::get_local_sha();

		// ۷. حذف پوستهٔ فعلی (فایل‌ها — پوسته نباید فعال باشد اگر فولدر حذف شود)
		//    به جای حذف کامل، فقط فایل‌ها و فولدرهای قدیمی رو جایگزین می‌کنیم
		//    تا اگر خطایی رخ داد پوسته از کار نیفتد.
		$result = self::copy_recursive( $source_theme, $theme_dir );
		self::rmdir_recursive( $extract_dir );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// ۸. ذخیرهٔ SHA جدید
		$new_sha = self::read_sha_from_filesystem( $theme_dir );
		update_option( 'flavor_github_local_sha', $new_sha ?: self::check_for_updates() );

		// ۹. پاک کردن کش پوسته
		wp_clean_themes_cache();
		delete_option( 'flavor_github_last_error' );

		return true;
	}

	/* ------------------------------------------------------------------ */
	/*  کمکی                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * درخواست GET به گیت‌هاب با توکن.
	 */
	private static function github_get( string $url ) {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'Flavor-GitHub-Updater/1.0',
		);

		$token = self::get_token();
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $url, array(
			'headers' => $headers,
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return new \WP_Error( 'http', 'خطای HTTP ' . $code . ' از گیت‌هاب.' );
		}

		return $response;
	}

	/**
	 * توکن دسترسی — اولویت: wp-config → آپشن.
	 */
	private static function get_token(): string {
		if ( defined( 'FLAVOR_GITHUB_TOKEN' ) && FLAVOR_GITHUB_TOKEN ) {
			return FLAVOR_GITHUB_TOKEN;
		}
		return get_option( 'flavor_github_token', '' );
	}

	/**
	 * SHA محلی ذخیره‌شده.
	 */
	public static function get_local_sha(): string {
		return get_option( 'flavor_github_local_sha', '' );
	}

	/**
	 * خواندن SHA از فایل .git یا فایل متنی.
	 */
	private static function read_sha_from_filesystem( string $dir ): string {
		// اگر فایل .flavor-sha ذخیره شده
		$sha_file = $dir . '/.flavor-sha';
		if ( file_exists( $sha_file ) ) {
			return trim( file_get_contents( $sha_file ) );
		}
		return '';
	}

	/**
	 * کپی بازگشتی فایل‌ها — جایگزینی بدون حذف اول.
	 */
	private static function copy_recursive( string $source, string $dest ): bool {
		if ( ! is_dir( $source ) ) {
			return new \WP_Error( 'src', 'مسیر مبدأ وجود ندارد.' );
		}

		if ( ! is_dir( $dest ) ) {
			mkdir( $dest, 0755, true );
		}

		$items = scandir( $source );
		if ( $items === false ) {
			return new \WP_Error( 'scan', 'خواندن مسیر مبدأ ممکن نشد.' );
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) continue;

			$s = $source . '/' . $item;
			$d = $dest   . '/' . $item;

			if ( is_dir( $s ) ) {
				$result = self::copy_recursive( $s, $d );
				if ( is_wp_error( $result ) ) return $result;
			} else {
				if ( ! @copy( $s, $d ) ) {
					// تلاش با حذف و کپی مجدد
					@unlink( $d );
					if ( ! @copy( $s, $d ) ) {
						return new \WP_Error( 'copy', 'کپی فایل ' . $item . ' ممکن نشد.' );
					}
				}
			}
		}

		return true;
	}

	/**
	 * حذف بازگشتی فولدر موقت.
	 */
	private static function rmdir_recursive( string $dir ): void {
		if ( ! is_dir( $dir ) ) return;
		$items = scandir( $dir );
		if ( $items === false ) return;
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) continue;
			$path = $dir . '/' . $item;
			is_dir( $path ) ? self::rmdir_recursive( $path ) : @unlink( $path );
		}
		@rmdir( $dir );
	}

	/**
	 * آیتم نوار مدیریت.
	 */
	public static function admin_bar_item( $wp_admin_bar ): void {
		$latest = get_option( 'flavor_github_latest_sha', '' );
		$local  = self::get_local_sha();
		if ( ! $latest || $local === $latest ) return;

		$wp_admin_bar->add_node( array(
			'id'    => 'flavor-update-alert',
			'title' => '🔄 آپدیت Flavor موجود است!',
			'href'  => menu_page_url( 'flavor-github-updater', false ),
		) );
	}

	/**
	 * تاریخ جلالی ساده.
	 */
	private static function jalali_date( int $timestamp ): string {
		$g = getdate( $timestamp );
		$gy = $g['year']; $gm = $g['mon']; $gd = $g['mday'];
		// تبدیل میلادی به شمسی (الگوریتم ساده)
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days = 355666 + ( 365 * $gy ) + intval( ( $gy2 + 3 ) / 4 ) - intval( ( $gy2 + 99 ) / 100 )
		        + intval( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy = -1595 + ( 33 * intval( $days / 12053 ) );
		$days %= 12053;
		$jy += 4 * intval( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy += intval( ( $days - 1 ) / 365 );
			$days = ( $days - 1 ) % 365;
		}
		$jm = ( $days < 186 ) ? 1 + intval( $days / 31 ) : 7 + intval( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days < 186 ) ? ( $days % 31 ) : ( ( $days - 186 ) % 30 ) );
		return $jy . '/' . str_pad( $jm, 2, '0', STR_PAD_LEFT ) . '/' . str_pad( $jd, 2, '0', STR_PAD_LEFT )
		       . ' ' . $g['hours'] . ':' . str_pad( $g['minutes'], 2, '0', STR_PAD_LEFT );
	}
}

Flavor_GitHub_Updater::init();