<?php
/**
 * Safe export/import for Flavor presentation and Core settings.
 *
 * This transfer intentionally excludes orders, customers, products, media files
 * and credentials. It is a settings transport, not a full-site migration.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Transfer
 */
class Transfer {

	private const FORMAT  = 'flavor-settings-export';
	private const VERSION = 1;

	/**
	 * Register menu and handlers.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 7 );
		add_action( 'admin_post_flavor_export_settings', array( self::class, 'export' ) );
		add_action( 'admin_post_flavor_import_settings', array( self::class, 'import' ) );
	}

	/**
	 * Register the transfer page.
	 */
	public static function menu(): void {
		add_theme_page(
			__( 'انتقال امن تنظیمات Flavor', 'flavor' ),
			__( 'پشتیبان تنظیمات', 'flavor' ),
			'manage_options',
			'flavor-transfer',
			array( self::class, 'render' )
		);
	}

	/**
	 * Render export/import controls.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}
		$notice = isset( $_GET['transfer'] ) ? sanitize_key( wp_unslash( $_GET['transfer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap" dir="rtl">
			<h1><?php esc_html_e( 'انتقال امن تنظیمات Flavor', 'flavor' ); ?></h1>
			<?php if ( 'imported' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'تنظیمات با موفقیت وارد شد.', 'flavor' ); ?></p></div>
			<?php elseif ( 'exported' === $notice ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'فایل تنظیمات آماده شد.', 'flavor' ); ?></p></div>
			<?php elseif ( 'error' === $notice ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'فایل تنظیمات معتبر نبود یا عملیات انجام نشد.', 'flavor' ); ?></p></div>
			<?php endif; ?>
			<div style="max-width:860px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:22px;">
				<h2><?php esc_html_e( 'چه چیزی منتقل می‌شود؟', 'flavor' ); ?></h2>
				<p><?php esc_html_e( 'پوسته، رنگ‌ها، تایپوگرافی، چیدمان، تنظیمات هیرو، شبکه‌های اجتماعی و تنظیمات غیرحساس Flavor Core منتقل می‌شوند. سفارش‌ها، مشتریان، محصولات، رسانه‌ها، کاربران و کلیدهای API هرگز داخل این فایل قرار نمی‌گیرند.', 'flavor' ); ?></p>
				<div style="display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 26px;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'flavor_export_settings' ); ?>
						<input type="hidden" name="action" value="flavor_export_settings" />
						<button class="button button-primary"><?php esc_html_e( 'دریافت فایل JSON تنظیمات', 'flavor' ); ?></button>
					</form>
					<a class="button" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>"><?php esc_html_e( 'باز کردن سفارشی‌ساز', 'flavor' ); ?></a>
				</div>
				<hr />
				<h2><?php esc_html_e( 'وارد کردن تنظیمات', 'flavor' ); ?></h2>
				<p class="description"><?php esc_html_e( 'فقط فایل JSON صادرشده از Flavor را انتخاب کنید. وارد کردن، قیمت، سفارش یا محتوای سایت را تغییر نمی‌دهد.', 'flavor' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<?php wp_nonce_field( 'flavor_import_settings' ); ?>
					<input type="hidden" name="action" value="flavor_import_settings" />
					<input type="file" name="flavor_settings_file" accept="application/json,.json" required />
					<button class="button button-secondary"><?php esc_html_e( 'بررسی و وارد کردن JSON', 'flavor' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Download a sanitized export.
	 */
	public static function export(): void {
		self::authorize( 'flavor_export_settings' );
		$payload = self::payload();
		$filename = 'flavor-settings-' . gmdate( 'Ymd-His' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Validate and apply an uploaded export.
	 */
	public static function import(): void {
		self::authorize( 'flavor_import_settings' );
		$file = isset( $_FILES['flavor_settings_file'] ) && is_array( $_FILES['flavor_settings_file'] ) ? $_FILES['flavor_settings_file'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::redirect( 'error' );
		}
		if ( ! empty( $file['size'] ) && (int) $file['size'] > 2 * 1024 * 1024 ) {
			self::redirect( 'error' );
		}
		$raw = file_get_contents( $file['tmp_name'] );
		$data = json_decode( is_string( $raw ) ? $raw : '', true );
		if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) || self::VERSION !== absint( $data['version'] ?? 0 ) ) {
			self::redirect( 'error' );
		}
		self::apply_payload( $data );
		self::redirect( 'imported' );
	}

	/**
	 * Build the portable payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function payload(): array {
		$mods = array();
		foreach ( (array) get_theme_mods() as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( 0 === strpos( $key, 'flavor_' ) && ! self::excluded( $key ) ) {
				$mods[ $key ] = $value;
			}
		}
		$payload = array(
				'format'       => self::FORMAT,
				'version'      => self::VERSION,
				'generated_at' => gmdate( 'c' ),
				'theme'        => get_stylesheet(),
			'theme_mods'  => $mods,
			'core'        => array(),
		);
		if ( class_exists( '\FlavorCore\Support\Settings' ) ) {
			$core = \FlavorCore\Support\Settings::all();
			foreach ( $core as $key => $value ) {
				if ( ! self::sensitive( (string) $key ) ) {
					$payload['core'][ sanitize_key( (string) $key ) ] = $value;
				}
			}
		}
		return $payload;
	}

	/**
	 * Apply only known presentation and non-secret Core settings.
	 *
	 * @param array<string, mixed> $payload Decoded payload.
	 */
	private static function apply_payload( array $payload ): void {
		foreach ( (array) ( $payload['theme_mods'] ?? array() ) as $key => $value ) {
			$key = sanitize_key( (string) $key );
				if ( 0 === strpos( $key, 'flavor_' ) && ! self::excluded( $key ) && ( is_scalar( $value ) || is_array( $value ) ) ) {
				set_theme_mod( $key, self::sanitize_theme_value( $value ) );
			}
		}
		if ( class_exists( '\FlavorCore\Support\Settings' ) ) {
			$allowed = array_keys( \FlavorCore\Support\Settings::all() );
			$core = array();
			foreach ( (array) ( $payload['core'] ?? array() ) as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( in_array( $key, $allowed, true ) && ! self::sensitive( $key ) ) {
					$core[ $key ] = self::sanitize_core_value( $key, $value );
				}
			}
			if ( $core ) {
				\FlavorCore\Support\Settings::update( $core );
			}
		}
	}

	/**
	 * Sanitize scalar/array theme settings while preserving responsive maps.
	 */
	private static function sanitize_theme_value( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $child ) {
				$clean[ is_int( $key ) ? $key : sanitize_key( (string) $key ) ] = self::sanitize_theme_value( $child );
			}
			return $clean;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize the small set of Core choices without importing IDs from another site.
	 */
	private static function sanitize_core_value( string $key, $value ) {
		switch ( $key ) {
			case 'currency_storage':
			case 'currency_display':
				return in_array( $value, array( 'irr', 'irt' ), true ) ? $value : 'irt';
			case 'digits':
				return in_array( $value, array( 'persian', 'latin' ), true ) ? $value : 'persian';
			case 'sms_provider':
				return in_array( $value, array( 'dev', 'melipayamak', 'faraz', 'kavenegar' ), true ) ? $value : 'dev';
			case 'default_branch_id':
				return 0;
			case 'sms_templates':
				return array();
			default:
				if ( is_numeric( $value ) ) {
					return absint( $value );
				}
				return in_array( $value, array( 'yes', 'no' ), true ) ? $value : sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Keep secrets and site-local media references out of a portable settings file.
	 */
	private static function excluded( string $key ): bool {
		return self::sensitive( $key ) || (bool) preg_match( '/(?:image|logo|gallery|thumbnail|attachment)/i', $key );
	}

	/**
	 * Keep secrets out even if a future setting is added.
	 */
	private static function sensitive( string $key ): bool {
		return (bool) preg_match( '/(?:password|passwd|secret|token|api[_-]?key|credential|private[_-]?key)/i', $key );
	}

	/**
	 * Shared authorization and nonce check.
	 */
	private static function authorize( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Redirect to the transfer page.
	 */
	private static function redirect( string $notice ): void {
		wp_safe_redirect( add_query_arg( array( 'page' => 'flavor-transfer', 'transfer' => sanitize_key( $notice ) ), admin_url( 'themes.php' ) ) );
		exit;
	}
}
