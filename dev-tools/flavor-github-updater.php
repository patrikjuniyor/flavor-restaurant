<?php
/**
 * Plugin Name: Flavor GitHub Updater
 * Description: آپدیت مستقیم پوسته Flavor و افزونهٔ Flavor Core از مخزن گیت‌هاب — فقط برای محیط توسعه.
 * Version:     1.1.0
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
 * به‌روزرسانی مستقیم از گیت‌هاب برای دو هدف: پوسته و افزونه.
 *
 * هر دو از یک مخزن و یک commit خوانده می‌شوند، پس یک بررسی برای هر دو کافی است.
 * SHA محلی هر هدف جداگانه ذخیره می‌شود تا بدانیم کدام یک عقب است.
 */
final class Flavor_GitHub_Updater {

	/** نام کاربری مالک مخزن */
	const REPO_OWNER = 'patrikjuniyor';

	/** نام مخزن */
	const REPO_NAME = 'flavor-restaurant';

	/** شاخهٔ پیش‌فرض */
	const BRANCH = 'main';

	/** نام فولدر پوسته، هم داخل مخزن و هم داخل themes */
	const THEME_SLUG = 'flavor';

	/** نام فولدر افزونه، هم داخل مخزن و هم داخل plugins */
	const PLUGIN_SLUG = 'flavor-core';

	const OPT_LATEST  = 'flavor_github_latest_sha';
	const OPT_CHECKED = 'flavor_github_last_check';
	const OPT_ERROR   = 'flavor_github_last_error';

	/* ------------------------------------------------------------------ */

	/**
	 * راه‌اندازی هوک‌ها.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_update_request' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_item' ), 999 );
		add_action( 'flavor_github_check', array( __CLASS__, 'check_for_updates' ) );
		if ( ! wp_next_scheduled( 'flavor_github_check' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'flavor_github_check' );
		}
	}

	/**
	 * اهداف قابل آپدیت.
	 *
	 * @return array<string, array{label:string, subdir:string, option:string, dir:string, cap:string}>
	 */
	public static function targets(): array {
		return array(
			'theme'  => array(
				'label'  => 'پوسته Flavor',
				'subdir' => self::THEME_SLUG,
				'option' => 'flavor_github_local_sha',
				'dir'    => get_theme_root() . '/' . self::THEME_SLUG,
				'cap'    => 'update_themes',
			),
			'plugin' => array(
				'label'  => 'افزونه Flavor Core',
				'subdir' => self::PLUGIN_SLUG,
				'option' => 'flavor_github_plugin_local_sha',
				'dir'    => WP_PLUGIN_DIR . '/' . self::PLUGIN_SLUG,
				'cap'    => 'update_plugins',
			),
		);
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
		$last_check = (int) get_option( self::OPT_CHECKED, 0 );
		$latest_sha = get_option( self::OPT_LATEST, '' );
		$last_error = get_option( self::OPT_ERROR, '' );
		$has_token  = '' !== self::get_token();
		$targets    = self::targets();

		echo '<div class="wrap" style="max-width:720px;direction:rtl;text-align:right;font-family:Tahoma,Vazirmatn,sans-serif;">';
		echo '<h1>🔄 آپدیت Flavor از گیت‌هاب</h1>';

		echo '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:24px;margin:20px 0;box-shadow:0 1px 3px rgba(0,0,0,.04);">';
		echo '<table class="form-table" style="margin:0;">';
		echo '<tr><th style="width:180px;">مخزن:</th><td><code>' . esc_html( self::REPO_OWNER . '/' . self::REPO_NAME ) . '</code> → شاخهٔ <code>' . esc_html( self::BRANCH ) . '</code></td></tr>';
		echo '<tr><th>SHA آخرین کامیت:</th><td><code>' . esc_html( $latest_sha ?: 'هنوز بررسی نشده' ) . '</code></td></tr>';
		echo '<tr><th>آخرین بررسی:</th><td>' . ( $last_check ? esc_html( self::jalali_date( $last_check ) ) . ' — ' . esc_html( human_time_diff( $last_check ) . ' پیش' ) : 'هرگز' ) . '</td></tr>';

		foreach ( $targets as $key => $t ) {
			$installed = is_dir( $t['dir'] );
			$local     = get_option( $t['option'], '' );
			echo '<tr><th>' . esc_html( $t['label'] ) . ':</th><td>';
			if ( ! $installed ) {
				echo '<span style="color:#d63638;">⚠️ در ' . esc_html( $t['dir'] ) . ' پیدا نشد.</span>';
			} else {
				echo 'SHA محلی: <code>' . esc_html( $local ?: 'نامشخص' ) . '</code> — ';
				if ( self::update_available( $key ) ) {
					echo '<span style="color:#d63638;font-weight:bold;">⚠️ نسخهٔ جدید موجود است</span>';
				} else {
					echo '<span style="color:#00a32a;font-weight:bold;">✅ به‌روز است</span>';
				}
			}
			echo '</td></tr>';
		}

		if ( ! $has_token ) {
			echo '<tr><th>توکن:</th><td><span style="color:#d63638;">⚠️ توکن تعریف نشده. خط زیر را به <code>wp-config.php</code> اضافه کنید:</span><br><code style="display:block;margin-top:8px;padding:12px;background:#f0f0f1;border-radius:4px;direction:ltr;text-align:left;">define( \'FLAVOR_GITHUB_TOKEN\', \'ghp_xxxxxxxxxxxx\' );</code></td></tr>';
		} else {
			echo '<tr><th>توکن:</th><td><span style="color:#00a32a;">✅ تعریف شده</span></td></tr>';
		}

		echo '</table>';
		echo '</div>';

		// دکمه‌ها
		echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0;">';

		echo '<form method="post" style="display:inline;">';
		wp_nonce_field( 'flavor_github_check' );
		echo '<input type="hidden" name="flavor_action" value="check">';
		submit_button( '🔍 بررسی نسخهٔ جدید', 'secondary', 'flavor_check', false );
		echo '</form>';

		$available = array();
		foreach ( $targets as $key => $t ) {
			if ( self::update_available( $key ) ) {
				$available[] = $key;
			}
		}

		if ( $available ) {
			$buttons = array();
			foreach ( $available as $key ) {
				$buttons[ $key ] = '⬇️ آپدیت ' . $targets[ $key ]['label'];
			}
			if ( count( $available ) > 1 ) {
				$buttons['all'] = '⬇️ آپدیت هر دو';
			}
			foreach ( $buttons as $value => $label ) {
				echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'فایل‌های فعلی با نسخهٔ جدید جایگزین می‌شوند. ادامه می‌دهید؟\');">';
				wp_nonce_field( 'flavor_github_update' );
				echo '<input type="hidden" name="flavor_action" value="update">';
				echo '<input type="hidden" name="flavor_target" value="' . esc_attr( $value ) . '">';
				submit_button( $label, 'primary', 'flavor_update_' . $value, false );
				echo '</form>';
			}
		}

		echo '</div>';

		if ( $last_error ) {
			echo '<div style="background:#fcf0f1;border:1px solid #d63638;border-radius:6px;padding:16px;margin:16px 0;">';
			echo '<strong>خطای آخرین عملیات:</strong> ' . esc_html( $last_error );
			echo '</div>';
		}

		echo '<div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:20px;margin:20px 0;">';
		echo '<h3>📖 راهنما</h3>';
		echo '<ol style="line-height:2.2;">';
		echo '<li>توکن شخصی گیت‌هاب را در <code>wp-config.php</code> تعریف کنید.</li>';
		echo '<li>هر وقت کد جدید به گیت‌هاب پوش کردید، به این صفحه بیایید.</li>';
		echo '<li>«بررسی نسخهٔ جدید» بزنید تا SHA آخرین کامیت را ببینید.</li>';
		echo '<li>«آپدیت» را بزنید. پوسته و افزونه به‌صورت جداگانه از همان کامیت دانلود و جایگزین می‌شوند. پیش از جایگزینی، نسخهٔ فعلی هر هدف موقتاً پشتیبان گرفته می‌شود.</li>';
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
		if ( ! isset( $_POST['flavor_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['flavor_action'] ) );
		$page   = menu_page_url( 'flavor-github-updater', false );

		if ( 'check' === $action ) {
			check_admin_referer( 'flavor_github_check' );
			// check_for_updates() خطا را خودش ثبت می‌کند و در صورت موفقیت، خطای قبلی را پاک می‌کند.
			self::check_for_updates();
			wp_safe_redirect( add_query_arg( 'flavor_checked', '1', $page ) );
			exit;
		}

		if ( 'update' === $action ) {
			check_admin_referer( 'flavor_github_update' );
			$choice  = isset( $_POST['flavor_target'] ) ? sanitize_key( wp_unslash( $_POST['flavor_target'] ) ) : '';
			$targets = self::targets();
			if ( 'all' === $choice ) {
				$keys = array_keys( $targets );
			} elseif ( isset( $targets[ $choice ] ) ) {
				$keys = array( $choice );
			} else {
				$keys = array();
			}

			$result = empty( $keys ) ? array( 'updated' => array(), 'failed' => array( 'هدف نامعتبر است.' ) ) : self::do_update( $keys );

			if ( $result['failed'] ) {
				update_option( self::OPT_ERROR, implode( ' | ', $result['failed'] ) );
			} else {
				delete_option( self::OPT_ERROR );
			}

			wp_safe_redirect( add_query_arg( 'flavor_updated', $result['failed'] ? '0' : '1', $page ) );
			exit;
		}
	}

	/* ------------------------------------------------------------------ */
	/*  بررسی گیت‌هاب                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * SHA کوتاه آخرین کامیت را می‌گیرد و ذخیره می‌کند.
	 *
	 * @return string SHA کوتاه، یا '' در صورت خطا.
	 */
	public static function check_for_updates(): string {
		$sha = self::fetch_head_sha();
		update_option( self::OPT_CHECKED, time() );

		if ( is_wp_error( $sha ) ) {
			update_option( self::OPT_ERROR, $sha->get_error_message() );
			return '';
		}

		$short = substr( $sha, 0, 7 );
		update_option( self::OPT_LATEST, $short );
		delete_option( self::OPT_ERROR );
		return $short;
	}

	/**
	 * SHA کامل آخرین کامیت شاخه.
	 *
	 * @return string|\WP_Error
	 */
	private static function fetch_head_sha() {
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/commits/%s',
			self::REPO_OWNER,
			self::REPO_NAME,
			self::BRANCH
		);

		$response = self::github_get( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['sha'] ) || ! preg_match( '/^[0-9a-f]{40}$/', $body['sha'] ) ) {
			return new \WP_Error( 'api', 'پاسخ API معتبر نبود.' );
		}

		return $body['sha'];
	}

	/**
	 * آیا نسخهٔ جدیدی برای این هدف هست؟ فقط وقتی نصب باشد.
	 *
	 * @param string $key Target key.
	 */
	public static function update_available( string $key ): bool {
		$targets = self::targets();
		if ( ! isset( $targets[ $key ] ) || ! is_dir( $targets[ $key ]['dir'] ) ) {
			return false;
		}
		$latest = get_option( self::OPT_LATEST, '' );
		return '' !== $latest && get_option( $targets[ $key ]['option'], '' ) !== $latest;
	}

	/* ------------------------------------------------------------------ */
	/*  آپدیت                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * یک زیپ از همان commit دانلود می‌کند و هدف‌های درخواستی را جایگزین می‌کند.
	 *
	 * هر هدف فقط وقتی جایگزین می‌شود که از قبل نصب باشد؛ افزونه‌ای که وجود ندارد،
	 * بی‌صدا نصب نمی‌شود. پیش از جایگزینی، نسخهٔ فعلی پشتیبان موقت می‌گیرد و اگر
	 * کپی شکست بخورد، برمی‌گرداند.
	 *
	 * @param string[] $keys Target keys.
	 * @return array{updated: string[], failed: string[]}
	 */
	private static function do_update( array $keys ): array {
		$result  = array( 'updated' => array(), 'failed' => array() );
		$targets = self::targets();

		// ۱. دسترسی هر هدف را جداگانه بررسی کنید.
		$allowed = array();
		foreach ( $keys as $key ) {
			if ( current_user_can( $targets[ $key ]['cap'] ) ) {
				$allowed[] = $key;
			} else {
				$result['failed'][] = $targets[ $key ]['label'] . ': دسترسی ندارید.';
			}
		}
		if ( empty( $allowed ) ) {
			return $result;
		}

		// ۲. دقیقاً همان commit را دانلود کنیم که SHA آن را گرفته‌ایم.
		$sha = self::fetch_head_sha();
		if ( is_wp_error( $sha ) ) {
			foreach ( $allowed as $key ) {
				$result['failed'][] = $targets[ $key ]['label'] . ': ' . $sha->get_error_message();
			}
			return $result;
		}
		$short = substr( $sha, 0, 7 );

		if ( ! class_exists( '\ZipArchive' ) ) {
			foreach ( $allowed as $key ) {
				$result['failed'][] = $targets[ $key ]['label'] . ': افزونهٔ ZipArchive روی سرور فعال نیست.';
			}
			return $result;
		}

		$tmp_dir = get_temp_dir();
		$stamp   = time() . '-' . wp_rand( 1000, 9999 );

		// ۳. دانلود زیپ.
		$zip_file = self::download_zip( $sha, $tmp_dir . 'flavor-github-' . $stamp . '.zip' );
		if ( is_wp_error( $zip_file ) ) {
			foreach ( $allowed as $key ) {
				$result['failed'][] = $targets[ $key ]['label'] . ': ' . $zip_file->get_error_message();
			}
			return $result;
		}

		// ۴. باز کردن و استخراج.
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_file ) ) {
			@unlink( $zip_file );
			foreach ( $allowed as $key ) {
				$result['failed'][] = $targets[ $key ]['label'] . ': باز کردن فایل زیپ ممکن نشد.';
			}
			return $result;
		}

		// گیت‌هاب یک فولدر بالایی مثل owner-repo-abc1234/ می‌سازد.
		$top_folder  = trailingslashit( $zip->getNameIndex( 0 ) );
		$extract_dir = $tmp_dir . 'flavor-extract-' . $stamp;
		wp_mkdir_p( $extract_dir );

		$extracted = $zip->extractTo( $extract_dir );
		$zip->close();
		@unlink( $zip_file );

		if ( ! $extracted ) {
			self::rmdir_recursive( $extract_dir );
			foreach ( $allowed as $key ) {
				$result['failed'][] = $targets[ $key ]['label'] . ': استخراج زیپ ممکن نشد.';
			}
			return $result;
		}

		// ۵. جایگزینی هر هدف.
		foreach ( $allowed as $key ) {
			$t      = $targets[ $key ];
			$source = $extract_dir . '/' . $top_folder . $t['subdir'];
			$dest   = $t['dir'];

			if ( ! is_dir( $source ) ) {
				$result['failed'][] = $t['label'] . ': فولدر ' . $t['subdir'] . ' داخل زیپ پیدا نشد.';
				continue;
			}
			if ( ! is_dir( $dest ) ) {
				$result['failed'][] = $t['label'] . ': در ' . $dest . ' نصب نیست؛ این ابزار نصب نمی‌کند.';
				continue;
			}

			$backup = $tmp_dir . 'flavor-backup-' . $key . '-' . $stamp;
			$copy   = self::copy_recursive( $dest, $backup );
			if ( is_wp_error( $copy ) ) {
				self::rmdir_recursive( $backup );
				$result['failed'][] = $t['label'] . ': پشتیبان موقت ساخته نشد؛ جایگزینی انجام نشد (' . $copy->get_error_message() . ').';
				continue;
			}

			$copy = self::copy_recursive( $source, $dest );
			if ( is_wp_error( $copy ) ) {
				// برگرداندن از پشتیبان. فایل‌های جدیدی که کپی شده‌اند باقی می‌مانند.
				self::copy_recursive( $backup, $dest );
				$result['failed'][] = $t['label'] . ': ' . $copy->get_error_message() . ' (نسخهٔ قبلی برگردانده شد).';
				self::rmdir_recursive( $backup );
				continue;
			}

			self::rmdir_recursive( $backup );
			update_option( $t['option'], $short );
			$result['updated'][] = $t['label'];
		}

		self::rmdir_recursive( $extract_dir );
		wp_clean_themes_cache();

		return $result;
	}

	/**
	 * زیپ commit را در $file ذخیره می‌کند.
	 *
	 * @return string|\WP_Error مسیر فایل یا خطا.
	 */
	private static function download_zip( string $sha, string $file ) {
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/zipball/%s',
			self::REPO_OWNER,
			self::REPO_NAME,
			$sha
		);

		$response = self::github_get( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return new \WP_Error( 'empty', 'فایل زیپ خالی بود.' );
		}
		if ( false === file_put_contents( $file, $body ) ) {
			return new \WP_Error( 'write', 'نوشتن فایل زیپ موقت ممکن نشد.' );
		}
		return $file;
	}

	/* ------------------------------------------------------------------ */
	/*  کمکی                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * درخواست GET به گیت‌هاب. با توکن اگر تعریف شده باشد.
	 *
	 * @return array|\WP_Error
	 */
	private static function github_get( string $url ) {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'Flavor-GitHub-Updater/1.1',
		);

		$token = self::get_token();
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $headers,
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new \WP_Error( 'http', 'خطای HTTP ' . $code . ' از گیت‌هاب.' );
		}

		return $response;
	}

	/**
	 * توکن دسترسی — اولویت: wp-config، سپس گزینه.
	 */
	private static function get_token(): string {
		if ( defined( 'FLAVOR_GITHUB_TOKEN' ) && FLAVOR_GITHUB_TOKEN ) {
			return (string) FLAVOR_GITHUB_TOKEN;
		}
		return (string) get_option( 'flavor_github_token', '' );
	}

	/**
	 * کپی بازگشتی. فایل‌های مقصد که در مبدأ نیستند حذف نمی‌شوند.
	 *
	 * @return true|\WP_Error
	 */
	private static function copy_recursive( string $source, string $dest ) {
		if ( ! is_dir( $source ) ) {
			return new \WP_Error( 'src', 'مسیر مبدأ وجود ندارد.' );
		}

		if ( ! is_dir( $dest ) && ! wp_mkdir_p( $dest ) ) {
			return new \WP_Error( 'mkdir', 'ساخت مسیر ' . basename( $dest ) . ' ممکن نشد.' );
		}

		$items = scandir( $source );
		if ( false === $items ) {
			return new \WP_Error( 'scan', 'خواندن مسیر مبدأ ممکن نشد.' );
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$s = $source . '/' . $item;
			$d = $dest . '/' . $item;

			if ( is_dir( $s ) ) {
				$result = self::copy_recursive( $s, $d );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			} elseif ( ! @copy( $s, $d ) ) {
				// یک بار دیگر، پس از حذف مقصد (مثلاً فایل قفل‌شده یا فقط‌خواندنی).
				@unlink( $d );
				if ( ! @copy( $s, $d ) ) {
					return new \WP_Error( 'copy', 'کپی فایل ' . $item . ' ممکن نشد.' );
				}
			}
		}

		return true;
	}

	/**
	 * حذف بازگشتی فولدر موقت.
	 */
	private static function rmdir_recursive( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::rmdir_recursive( $path );
			} else {
				@unlink( $path );
			}
		}
		@rmdir( $dir );
	}

	/**
	 * آیتم نوار مدیریت وقتی حداقل یک هدف عقب است.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar.
	 */
	public static function admin_bar_item( $wp_admin_bar ): void {
		$any = false;
		foreach ( array_keys( self::targets() ) as $key ) {
			if ( self::update_available( $key ) ) {
				$any = true;
				break;
			}
		}
		if ( ! $any ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'flavor-update-alert',
				'title' => '🔄 آپدیت Flavor موجود است!',
				'href'  => menu_page_url( 'flavor-github-updater', false ),
			)
		);
	}

	/**
	 * تاریخ جلالی ساده.
	 */
	private static function jalali_date( int $timestamp ): string {
		$g  = getdate( $timestamp );
		$gy = $g['year'];
		$gm = $g['mon'];
		$gd = $g['mday'];
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
		$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
		$days  = 355666 + ( 365 * $gy ) + intval( ( $gy2 + 3 ) / 4 ) - intval( ( $gy2 + 99 ) / 100 )
			+ intval( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
		$jy    = -1595 + ( 33 * intval( $days / 12053 ) );
		$days %= 12053;
		$jy   += 4 * intval( $days / 1461 );
		$days %= 1461;
		if ( $days > 365 ) {
			$jy   += intval( ( $days - 1 ) / 365 );
			$days  = ( $days - 1 ) % 365;
		}
		$jm = ( $days < 186 ) ? 1 + intval( $days / 31 ) : 7 + intval( ( $days - 186 ) / 30 );
		$jd = 1 + ( ( $days < 186 ) ? ( $days % 31 ) : ( ( $days - 186 ) % 30 ) );
		return $jy . '/' . str_pad( (string) $jm, 2, '0', STR_PAD_LEFT ) . '/' . str_pad( (string) $jd, 2, '0', STR_PAD_LEFT )
			. ' ' . $g['hours'] . ':' . str_pad( (string) $g['minutes'], 2, '0', STR_PAD_LEFT );
	}
}

Flavor_GitHub_Updater::init();
