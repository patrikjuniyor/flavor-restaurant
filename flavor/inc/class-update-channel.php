<?php
/**
 * WordPress wiring for the signed update channel.
 *
 * Off by default. The channel needs three things the owner must provide:
 * an https manifest URL, a public key, and the "enabled" switch. Until all
 * three are set the site makes no update request at all, which matches the
 * project's rule against third-party calls by default.
 *
 * Flow when enabled:
 *  1. check() fetches manifest.json and manifest.json.sig, verifies the
 *     signature, and caches a validated offer for 12 hours.
 *  2. WordPress shows the offer on the Themes screen through the normal
 *     update mechanism, reading only the cache.
 *  3. Before WordPress downloads the package, pre_download() fetches it
 *     itself and checks size, sha256 and archive paths. Anything else is refused.
 *  4. Before WordPress replaces the theme, pre_install() copies the current
 *     theme to a backup. If the backup fails the update does not start.
 *  5. If the new version misbehaves, the owner restores a backup from the
 *     page this class registers.
 *
 * @package Flavor
 */

namespace Flavor;

use Flavor\Updates\Backup;
use Flavor\Updates\Manifest;
use Flavor\Updates\Package;
use Flavor\Updates\Signature;
use Flavor\Updates\Update_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Update_Channel
 */
final class Update_Channel {

	public const PAGE            = 'flavor-updates';
	public const THEME           = 'flavor';
	public const OPTION_ENABLED  = 'flavor_updates_enabled';
	public const OPTION_URL      = 'flavor_updates_url';
	public const OPTION_KEY      = 'flavor_updates_public_key';
	public const OPTION_CHECKED  = 'flavor_updates_last_check';
	public const OPTION_ERROR    = 'flavor_updates_last_error';
	public const OPTION_BACKUP   = 'flavor_updates_last_backup';
	public const TRANSIENT_OFFER = 'flavor_update_offer';
	public const CRON_HOOK       = 'flavor_updates_check';

	/** How long a verified offer is trusted before it must be re-checked. */
	public const OFFER_TTL = 43200;

	/** Largest manifest or signature file accepted, in bytes. */
	private const MAX_TEXT_BYTES = 65536;

	/**
	 * Register hooks. Nothing here makes a network request.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_flavor_updates_save', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_flavor_updates_check', array( self::class, 'handle_check' ) );
		add_action( 'admin_post_flavor_updates_rollback', array( self::class, 'handle_rollback' ) );
		add_action( self::CRON_HOOK, array( self::class, 'check' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( self::class, 'inject_offer' ) );
		add_filter( 'upgrader_pre_download', array( self::class, 'pre_download' ), 10, 4 );
		add_filter( 'upgrader_pre_install', array( self::class, 'pre_install' ), 10, 2 );
		self::sync_schedule();
	}

	/**
	 * Whether the channel is fully configured and allowed to run.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return self::disabled_reason() === '';
	}

	/**
	 * Human reason the channel is off, or '' when it is on.
	 *
	 * @return string
	 */
	public static function disabled_reason(): string {
		if ( 'yes' !== get_option( self::OPTION_ENABLED, 'no' ) ) {
			return 'کانال به‌روزرسانی خاموش است. برای روشن‌کردن، تیک فعال‌سازی را بزنید.';
		}
		if ( ! Signature::available() ) {
			return 'افزونهٔ sodium روی این سرور فعال نیست؛ امضای بسته‌ها بررسی نمی‌شود.';
		}
		if ( ! self::valid_url( self::manifest_url() ) ) {
			return 'نشانی مانیفست باید یک آدرس https باشد.';
		}
		if ( '' === self::public_key() ) {
			return 'کلید عمومی امضا تنظیم نشده است.';
		}
		return '';
	}

	/**
	 * Public key the owner trusts. A wp-config constant wins over the database.
	 *
	 * @return string Base64 public key, or '' when unset.
	 */
	public static function public_key(): string {
		if ( defined( 'FLAVOR_UPDATE_PUBLIC_KEY' ) ) {
			return (string) constant( 'FLAVOR_UPDATE_PUBLIC_KEY' );
		}
		return (string) get_option( self::OPTION_KEY, '' );
	}

	/**
	 * Configured manifest URL.
	 *
	 * @return string
	 */
	public static function manifest_url(): string {
		return (string) get_option( self::OPTION_URL, '' );
	}

	/**
	 * Fetch the manifest and its signature, verify, and cache the offer.
	 *
	 * Called by the daily schedule and the manual button, never on page render.
	 *
	 * @return array|null The verified manifest when an update is offered, otherwise null.
	 */
	public static function check(): ?array {
		if ( ! self::enabled() ) {
			return null;
		}
		$url       = self::manifest_url();
		$json      = self::fetch( $url );
		$signature = self::fetch( $url . '.sig' );
		if ( false === $json || false === $signature ) {
			return self::fail( 'مانیفست یا امضای آن دریافت نشد؛ آدرس و دسترسی سرور را بررسی کنید.' );
		}

		try {
			$manifest = Manifest::verified( $json, $signature, self::public_key(), time() );
		} catch ( Update_Error $e ) {
			delete_transient( self::TRANSIENT_OFFER );
			return self::fail( $e->getMessage() );
		}

		update_option( self::OPTION_CHECKED, time(), false );
		delete_option( self::OPTION_ERROR );

		if ( ! Manifest::offers_update( $manifest, FLAVOR_VERSION ) ) {
			delete_transient( self::TRANSIENT_OFFER );
			return null;
		}
		if ( '' !== $manifest['requires_php'] && version_compare( PHP_VERSION, $manifest['requires_php'], '<' ) ) {
			delete_transient( self::TRANSIENT_OFFER );
			return self::fail( 'نسخهٔ جدید به PHP ' . $manifest['requires_php'] . ' یا بالاتر نیاز دارد.' );
		}

		set_transient( self::TRANSIENT_OFFER, $manifest, self::OFFER_TTL );
		return $manifest;
	}

	/**
	 * Show a verified offer on the Themes update list. Reads cache only.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function inject_offer( $transient ) {
		if ( ! is_object( $transient ) || ! self::enabled() ) {
			return $transient;
		}
		$offer = get_transient( self::TRANSIENT_OFFER );
		if ( ! is_array( $offer ) || ! Manifest::offers_update( $offer, FLAVOR_VERSION ) ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ self::THEME ] = array(
			'theme'        => self::THEME,
			'new_version'  => $offer['version'],
			'url'          => '',
			'package'      => $offer['package']['url'],
			'requires_php' => $offer['requires_php'],
		);
		return $transient;
	}

	/**
	 * Download and verify the package ourselves, then hand WordPress a local file.
	 *
	 * @param mixed  $reply     Value from earlier filters; false means "not handled".
	 * @param string $package   Package URL WordPress wants to fetch.
	 * @param mixed  $upgrader  Upgrader instance (unused).
	 * @param mixed  $hook_extra Context; theme updates carry a 'theme' key.
	 * @return mixed A local file path, a WP_Error, or the earlier $reply.
	 */
	public static function pre_download( $reply, $package, $upgrader, $hook_extra ) {
		unset( $upgrader );
		if ( false !== $reply || ! is_array( $hook_extra ) || ( $hook_extra['theme'] ?? '' ) !== self::THEME ) {
			return $reply;
		}
		if ( ! self::enabled() ) {
			return new \WP_Error( 'flavor_channel_off', self::disabled_reason() );
		}
		$offer = get_transient( self::TRANSIENT_OFFER );
		if ( ! is_array( $offer ) || $offer['package']['url'] !== $package ) {
			return new \WP_Error( 'flavor_unknown_package', 'این بسته از کانال تأییدشدهٔ Flavor نیست.' );
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = download_url( $package, 120 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		try {
			Package::verify_file( $tmp, $offer['package']['sha256'], $offer['package']['size'] );
			Package::check_zip( $tmp, self::THEME );
		} catch ( Update_Error $e ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'flavor_verify_' . $e->code_name(), $e->getMessage() );
		}
		return $tmp;
	}

	/**
	 * Back up the installed theme before WordPress overwrites it.
	 *
	 * @param mixed $response   Value from earlier filters.
	 * @param mixed $hook_extra Context.
	 * @return mixed $response, or a WP_Error that stops the update.
	 */
	public static function pre_install( $response, $hook_extra ) {
		if ( is_wp_error( $response ) || ! is_array( $hook_extra ) || ( $hook_extra['theme'] ?? '' ) !== self::THEME ) {
			return $response;
		}
		try {
			$backup = self::backup();
			$path   = $backup->create( get_template_directory(), FLAVOR_VERSION );
			$backup->prune( Backup::KEEP );
			update_option( self::OPTION_BACKUP, basename( $path ), false );
		} catch ( Update_Error $e ) {
			return new \WP_Error( 'flavor_backup', $e->getMessage() );
		}
		return $response;
	}

	/**
	 * Backups, newest first.
	 *
	 * @return array
	 */
	public static function backups(): array {
		return self::backup()->list();
	}

	/**
	 * Register the Appearance submenu page.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_theme_page(
			__( 'به‌روزرسانی Flavor', 'flavor' ),
			__( 'به‌روزرسانی', 'flavor' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function handle_save(): void {
		self::guard( 'flavor_updates_save' );
		$enabled = isset( $_POST['enabled'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$url     = isset( $_POST['manifest_url'] ) ? esc_url_raw( trim( wp_unslash( (string) $_POST['manifest_url'] ) ), array( 'https' ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$key     = isset( $_POST['public_key'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST['public_key'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' !== $key && ! self::valid_public_key( $key ) ) {
			self::redirect( 'bad-key' );
		}
		if ( 'yes' === $enabled && ! self::valid_url( $url ) ) {
			self::redirect( 'bad-url' );
		}

		update_option( self::OPTION_ENABLED, $enabled, false );
		update_option( self::OPTION_URL, $url, false );
		update_option( self::OPTION_KEY, $key, false );
		if ( 'no' === $enabled ) {
			delete_transient( self::TRANSIENT_OFFER );
		}
		self::sync_schedule();
		self::redirect( 'saved' );
	}

	/**
	 * Manual check.
	 *
	 * @return void
	 */
	public static function handle_check(): void {
		self::guard( 'flavor_updates_check' );
		if ( ! self::enabled() ) {
			self::redirect( 'off' );
		}
		self::redirect( null !== self::check() ? 'offer' : 'checked' );
	}

	/**
	 * Restore a backup over the installed theme.
	 *
	 * @return void
	 */
	public static function handle_rollback(): void {
		self::guard( 'flavor_updates_rollback' );
		$id = isset( $_POST['backup'] ) ? sanitize_file_name( wp_unslash( (string) $_POST['backup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		try {
			self::backup()->restore( $id, get_template_directory() );
		} catch ( Update_Error $e ) {
			update_option( self::OPTION_ERROR, $e->getMessage(), false );
			self::redirect( 'restore-failed' );
		}
		self::redirect( 'restored' );
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$reason  = self::disabled_reason();
		$offer   = get_transient( self::TRANSIENT_OFFER );
		$checked = (int) get_option( self::OPTION_CHECKED, 0 );
		$error   = (string) get_option( self::OPTION_ERROR, '' );
		$notice  = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$backups = self::backups();
		$texts   = array(
			'saved'          => 'تنظیمات ذخیره شد.',
			'checked'        => 'بررسی انجام شد؛ نسخهٔ جدیدی نیست.',
			'offer'          => 'نسخهٔ جدیدی پیدا شد. نصب را از صفحهٔ به‌روزرسانی‌های وردپرس انجام دهید.',
			'off'            => 'کانال خاموش است؛ ابتدا آن را فعال و پیکربندی کنید.',
			'restored'       => 'نسخهٔ پشتیبان بازگردانده شد.',
			'restore-failed' => 'بازگردانی انجام نشد؛ قالب فعلی دست‌نخورده ماند.',
			'bad-url'        => 'نشانی مانیفست باید یک آدرس https کامل باشد.',
			'bad-key'        => 'کلید عمومی باید یک کلید base64 با ۳۲ بایت باشد.',
		);
		?>
		<div class="wrap" dir="rtl">
			<h1><?php esc_html_e( 'به‌روزرسانی Flavor', 'flavor' ); ?></h1>
			<?php if ( isset( $texts[ $notice ] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $texts[ $notice ] ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'وضعیت', 'flavor' ); ?></h2>
			<p><?php echo esc_html( sprintf( /* translators: %s: installed version */ __( 'نسخهٔ نصب‌شده: %s', 'flavor' ), FLAVOR_VERSION ) ); ?></p>
			<p>
				<?php
				echo '' === $reason
					? esc_html__( 'کانال فعال و پیکربندی شده است.', 'flavor' )
					: esc_html( $reason );
				?>
			</p>
			<?php if ( $checked ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: %s: date and time */ __( 'آخرین بررسی موفق: %s', 'flavor' ), wp_date( 'Y-m-d H:i', $checked ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $error ) : ?>
				<p class="flavor-ui-error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<?php if ( is_array( $offer ) ) : ?>
				<div class="card" style="max-width:720px;padding:12px 18px">
					<h2><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'نسخهٔ %s', 'flavor' ), $offer['version'] ) ); ?></h2>
					<?php if ( '' !== $offer['released'] ) : ?>
						<p><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'تاریخ انتشار: %s', 'flavor' ), $offer['released'] ) ); ?></p>
					<?php endif; ?>
					<ul>
						<?php foreach ( $offer['changelog'] as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'بررسی دستی', 'flavor' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flavor_updates_check" />
				<?php wp_nonce_field( 'flavor_updates_check' ); ?>
				<?php submit_button( __( 'بررسی نسخهٔ جدید', 'flavor' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'بازگردانی نسخهٔ قبلی', 'flavor' ); ?></h2>
			<?php if ( empty( $backups ) ) : ?>
				<p><?php esc_html_e( 'هنوز پشتیبانی ساخته نشده است. پیش از هر به‌روزرسانی خودکار یک نسخه گرفته می‌شود.', 'flavor' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:720px">
					<thead><tr><th><?php esc_html_e( 'نسخه', 'flavor' ); ?></th><th><?php esc_html_e( 'زمان پشتیبان', 'flavor' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $backups as $item ) : ?>
						<tr>
							<td><?php echo esc_html( $item['version'] ); ?></td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $item['time'] ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'قالب فعلی با این نسخه جایگزین شود؟', 'flavor' ) ); ?>');">
									<input type="hidden" name="action" value="flavor_updates_rollback" />
									<input type="hidden" name="backup" value="<?php echo esc_attr( $item['id'] ); ?>" />
									<?php wp_nonce_field( 'flavor_updates_rollback' ); ?>
									<?php submit_button( __( 'بازگردانی', 'flavor' ), 'small', 'submit', false ); ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'تنظیمات کانال', 'flavor' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px">
				<input type="hidden" name="action" value="flavor_updates_save" />
				<?php wp_nonce_field( 'flavor_updates_save' ); ?>
				<p><label><input type="checkbox" name="enabled" value="1" <?php checked( 'yes', get_option( self::OPTION_ENABLED, 'no' ) ); ?> /> <?php esc_html_e( 'فعال‌سازی کانال به‌روزرسانی (بررسی روزانه و درخواست به سرور)', 'flavor' ); ?></label></p>
				<p><label><?php esc_html_e( 'نشانی manifest.json (https)', 'flavor' ); ?><br /><input type="url" class="large-text" name="manifest_url" value="<?php echo esc_attr( self::manifest_url() ); ?>" /></label></p>
				<p><label><?php esc_html_e( 'کلید عمومی امضا (base64)', 'flavor' ); ?><br /><input type="text" class="large-text code" name="public_key" value="<?php echo esc_attr( defined( 'FLAVOR_UPDATE_PUBLIC_KEY' ) ? '' : (string) get_option( self::OPTION_KEY, '' ) ); ?>" <?php echo defined( 'FLAVOR_UPDATE_PUBLIC_KEY' ) ? 'disabled placeholder="' . esc_attr__( 'از wp-config تعیین شده است', 'flavor' ) . '"' : ''; ?> /></label></p>
				<?php submit_button( __( 'ذخیرهٔ تنظیمات', 'flavor' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Build a backup handle rooted in wp-content, with a deny-all guard file.
	 *
	 * @return Backup
	 */
	private static function backup(): Backup {
		$content = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
		$dir     = $content . '/flavor-backups';
		if ( ! is_dir( $dir ) ) {
			@mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( is_dir( $dir ) ) {
			if ( ! file_exists( $dir . '/index.php' ) ) {
				@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			if ( ! file_exists( $dir . '/.htaccess' ) ) {
				@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		return new Backup( $dir );
	}

	/**
	 * Fetch a small text file. Redirects are refused: the URL must answer directly.
	 *
	 * @param string $url Absolute https URL.
	 * @return string|false
	 */
	private static function fetch( string $url ) {
		if ( ! self::valid_url( $url ) ) {
			return false;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => true,
				'user-agent'  => 'Flavor/' . FLAVOR_VERSION,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		return ( is_string( $body ) && '' !== $body && strlen( $body ) <= self::MAX_TEXT_BYTES ) ? $body : false;
	}

	/**
	 * Record a failure for the page and return null.
	 *
	 * @param string $message Persian message.
	 * @return null
	 */
	private static function fail( string $message ): ?array {
		update_option( self::OPTION_ERROR, $message, false );
		return null;
	}

	/**
	 * Ensure the daily check is scheduled only while the channel is on.
	 *
	 * @return void
	 */
	private static function sync_schedule(): void {
		if ( self::enabled() ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
		} elseif ( wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Capability and nonce gate for the admin-post handlers.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( string $action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Return to the settings page with a notice key.
	 *
	 * @param string|null $notice Notice key, or null for none.
	 * @return void
	 */
	private static function redirect( ?string $notice ): void {
		$args = array( 'page' => self::PAGE );
		if ( null !== $notice ) {
			$args['notice'] = $notice;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'themes.php' ) ) );
		exit;
	}

	/**
	 * Whether a URL is absolute https.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function valid_url( string $url ): bool {
		return '' !== $url && 0 === strpos( $url, 'https://' ) && false !== filter_var( $url, FILTER_VALIDATE_URL );
	}

	/**
	 * Whether a base64 string decodes to a 32-byte public key.
	 *
	 * @param string $key Candidate key.
	 * @return bool
	 */
	private static function valid_public_key( string $key ): bool {
		$raw = base64_decode( $key, true );
		return false !== $raw && SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES === strlen( $raw );
	}
}
