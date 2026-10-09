<?php
/**
 * One-click demo importer (pages, products, branch, tables, customizer).
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Demo_Importer
 */
class Demo_Importer {

	/**
	 * Import state and archive storage.
	 */
	private const STATE_OPTION = 'flavor_demo_import_state';
	private const ARCHIVES_OPTION = 'flavor_demo_archives';
	private const ARCHIVE_PREFIX = 'flavor_demo_archive_';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_flavor_import_demo', array( self::class, 'handle' ) );
		add_action( 'admin_post_flavor_demo_rollback', array( self::class, 'rollback' ) );
	}

	/**
	 * Appearance submenu.
	 */
	public static function menu(): void {
		add_theme_page(
			__( 'درون‌ریزی دموی Flavor', 'flavor' ),
			__( 'دموهای Flavor', 'flavor' ),
			'edit_theme_options',
			'flavor-demos',
			array( self::class, 'render' )
		);
	}

	/**
	 * UI.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}
		require_once FLAVOR_DIR . '/inc/demo-catalog.php';
		$catalog      = flavor_demo_catalog();
		$notice       = isset( $_GET['imported'] ) ? sanitize_key( wp_unslash( $_GET['imported'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview_slug = isset( $_GET['preview'] ) ? sanitize_key( wp_unslash( $_GET['preview'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state        = self::state();
		$archives     = self::archives();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'دموهای نصب یک‌کلیکی Flavor', 'flavor' ); ?></h1>
			<?php if ( $notice && isset( $catalog[ $notice ] ) ) : ?>
				<div class="notice notice-success"><p>
					<?php echo esc_html( sprintf( /* translators: demo */ __( 'دمو «%s» درون‌ریزی شد. صفحه نخست را بررسی کنید. آرشیو بازگشت هم ساخته شد.', 'flavor' ), $catalog[ $notice ]['title'] ) ); ?>
				</p></div>
			<?php elseif ( isset( $_GET['rolled_back'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'وضعیت پیش از آخرین درون‌ریزی بازگردانی شد.', 'flavor' ); ?></p></div>
			<?php elseif ( isset( $_GET['error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'عملیات کامل نشد. آرشیو قبلی را بررسی کنید و در صورت نیاز بازگردانی را اجرا کنید.', 'flavor' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! defined( 'FLAVOR_CORE_VERSION' ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Flavor Core فعال نیست؛ صفحات و رنگ‌ها وارد می‌شوند اما محصول و شعبه ساخته نمی‌شود.', 'flavor' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $state['status'] ) && 'running' === $state['status'] ) : ?>
				<div class="notice notice-info"><p><?php echo esc_html( sprintf( __( 'درون‌ریزی «%s» در مرحلهٔ «%s» است: %d٪', 'flavor' ), $state['slug'] ?? '', $state['message'] ?? '', (int) ( $state['percent'] ?? 0 ) ) ); ?></p></div>
			<?php elseif ( ! empty( $state['status'] ) && 'failed' === $state['status'] ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'درون‌ریزی کامل نشد. محتوای پیشین از آرشیو محافظت می‌شود؛ از بخش بازگشت استفاده کنید.', 'flavor' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'پیش از تغییر، تنظیمات، پوسته و محتوای دموی قبلی در یک آرشیو قابل بازگشت ذخیره می‌شود. محتوای خارج از مالکیت دموی Flavor دست‌نخورده می‌ماند.', 'flavor' ); ?></p>
			<?php if ( $preview_slug && isset( $catalog[ $preview_slug ] ) ) : ?>
				<?php $preview = self::preview( $catalog[ $preview_slug ] ); ?>
				<div class="notice notice-info" style="padding:12px 16px;">
					<h2 style="margin-top:0;"><?php echo esc_html( sprintf( __( 'پیش‌نمایش بستهٔ «%s»', 'flavor' ), $catalog[ $preview_slug ]['title'] ) ); ?></h2>
					<p><?php echo esc_html( $catalog[ $preview_slug ]['tagline'] ); ?></p>
					<ul>
						<li><?php echo esc_html( sprintf( __( '%d صفحه و %d آیتم منو ایجاد می‌شود.', 'flavor' ), $preview['pages'], $preview['items'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'تصاویر بسته: %d فایل؛ شعبه و %d میز نمونه.', 'flavor' ), $preview['assets'], $preview['tables'] ) ); ?></li>
						<li><?php echo esc_html( sprintf( __( 'تغییرات احتمالی: %d مسیر صفحهٔ موجود با پسوند امن ساخته می‌شود؛ محتوای اصلی حذف نمی‌شود.', 'flavor' ), $preview['conflicts'] ) ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $archives ) ) : ?>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;margin:16px 0;">
					<strong><?php esc_html_e( 'آرشیوهای قابل بازگشت', 'flavor' ); ?></strong>
					<?php foreach ( array_slice( $archives, 0, 5 ) as $archive ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:8px 0 0 12px;">
							<?php wp_nonce_field( 'flavor_demo_rollback_' . $archive['id'] ); ?>
							<input type="hidden" name="action" value="flavor_demo_rollback" />
							<input type="hidden" name="archive_id" value="<?php echo esc_attr( $archive['id'] ); ?>" />
							<button class="button"><?php echo esc_html( sprintf( __( 'بازگشت به %s (%s)', 'flavor' ), $archive['demo'] ?? __( 'وضعیت قبلی', 'flavor' ), $archive['created_at'] ?? '' ) ); ?></button>
						</form>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="flavor-demo-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
				<?php
				$active_skin = (string) get_theme_mod( 'flavor_skin', 'modern-restaurant' );
				foreach ( $catalog as $slug => $demo ) :
					$is_active    = $active_skin === $slug;
					$hero         = is_readable( FLAVOR_DIR . '/demos/' . $slug . '/hero.jpg' )
						? FLAVOR_URI . '/demos/' . $slug . '/hero.jpg'
						: \Flavor\Bespoke_Demos::placeholder();
					$customizer_url = admin_url( 'customize.php?autofocus[control]=flavor_skin&flavor_try_demo=' . rawurlencode( $slug ) . '&return=' . rawurlencode( admin_url( 'themes.php?page=flavor-demos' ) ) );
				?>
					<article style="border:1px solid <?php echo $is_active ? '#a74e2c' : '#ddd'; ?>;border-radius:12px;overflow:hidden;background:#fff;position:relative;box-shadow:<?php echo $is_active ? '0 8px 24px rgba(167,78,44,0.15)' : 'none'; ?>;">
						<div style="position:relative;">
							<img src="<?php echo esc_url( $hero ); ?>" alt="" style="width:100%;height:140px;object-fit:cover;" />
							<?php if ( $is_active ) : ?>
								<span style="position:absolute;inset-inline-start:10px;inset-block-start:10px;padding:4px 10px;border-radius:999px;background:#a74e2c;color:#fff;font-size:11px;font-weight:600;"><?php esc_html_e( 'دموی فعال', 'flavor' ); ?></span>
							<?php endif; ?>
						</div>
						<div style="padding:12px 14px 16px;">
							<h2 style="margin:0 0 6px;font-size:1.1rem;"><?php echo esc_html( $demo['title'] ); ?></h2>
							<p style="color:#555;min-height:3em;"><?php echo esc_html( $demo['tagline'] ); ?></p>
							<p><?php echo esc_html( sprintf( /* translators: count */ __( '%d آیتم منو', 'flavor' ), count( $demo['items'] ) ) ); ?></p>
							<div style="display:flex;flex-wrap:wrap;gap:6px;">
								<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( $customizer_url ); ?>"><?php esc_html_e( 'پیش‌نمایش در سفارشی‌ساز (بدون ذخیره)', 'flavor' ); ?></a>
								<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'flavor-demos', 'preview' => $slug ), admin_url( 'themes.php' ) ) ); ?>"><?php esc_html_e( 'بررسی تغییرات درون‌ریزی', 'flavor' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
									<?php wp_nonce_field( 'flavor_import_demo' ); ?>
									<input type="hidden" name="action" value="flavor_import_demo" />
									<input type="hidden" name="demo" value="<?php echo esc_attr( $slug ); ?>" />
									<button class="button button-primary" onclick="return confirm('<?php echo esc_js( sprintf( __( 'درون‌ریزی «%s» محتوای فعلی این دموی Flavor را با محتوای جدید جایگزین می‌کند (سایر محتواها دست‌نخورده باقی می‌مانند) و یک آرشیو پشتیبان پیش از اعمال ساخته می‌شود. ادامه می‌دهید؟', 'flavor' ), $demo['title'] ) ); ?>')"><?php esc_html_e( 'درون‌ریزی با پشتیبان', 'flavor' ); ?></button>
								</form>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Run import.
	 */
	public static function handle(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}
		check_admin_referer( 'flavor_import_demo' );
		$slug = isset( $_POST['demo'] ) ? sanitize_key( wp_unslash( $_POST['demo'] ) ) : '';
		require_once FLAVOR_DIR . '/inc/demo-catalog.php';
		$catalog = flavor_demo_catalog();
		if ( ! isset( $catalog[ $slug ] ) ) {
			wp_die( esc_html__( 'دمو پیدا نشد.', 'flavor' ) );
		}

		$archive_id = '';
		try {
			$archive_id = self::create_archive( $slug );
			self::set_state( array( 'status' => 'running', 'slug' => $slug, 'archive_id' => $archive_id, 'percent' => 3, 'message' => __( 'ساخت آرشیو پشتیبان', 'flavor' ) ) );
			self::import( $catalog[ $slug ], $archive_id );
			wp_safe_redirect( admin_url( 'themes.php?page=flavor-demos&imported=' . rawurlencode( $slug ) ) );
		} catch ( \Throwable $error ) {
			if ( $archive_id ) {
				try {
					self::rollback_archive( $archive_id, false );
				} catch ( \Throwable $rollback_error ) {
					// Keep the archive available for a manual rollback even if the automatic recovery fails.
				}
			}
			self::set_state( array( 'status' => 'failed', 'slug' => $slug, 'archive_id' => $archive_id, 'percent' => 100, 'message' => __( 'بازگردانی خودکار پس از خطا', 'flavor' ), 'error' => sanitize_text_field( $error->getMessage() ) ) );
			wp_safe_redirect( admin_url( 'themes.php?page=flavor-demos&error=import' ) );
		}
		exit;
	}

	/**
	 * Restore a selected pre-import archive.
	 */
	public static function rollback(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}
		$archive_id = isset( $_POST['archive_id'] ) ? sanitize_key( wp_unslash( $_POST['archive_id'] ) ) : '';
		if ( ! $archive_id ) {
			wp_die( esc_html__( 'آرشیو معتبر نیست.', 'flavor' ) );
		}
		check_admin_referer( 'flavor_demo_rollback_' . $archive_id );
		if ( ! self::get_archive( $archive_id ) ) {
			wp_die( esc_html__( 'آرشیو پیدا نشد.', 'flavor' ) );
		}
		self::set_state( array( 'status' => 'running', 'archive_id' => $archive_id, 'percent' => 10, 'message' => __( 'بازگردانی آرشیو', 'flavor' ) ) );
		try {
			self::rollback_archive( $archive_id, true );
			self::set_state( array( 'status' => 'complete', 'archive_id' => $archive_id, 'percent' => 100, 'message' => __( 'بازگشت کامل شد', 'flavor' ) ) );
			wp_safe_redirect( admin_url( 'themes.php?page=flavor-demos&rolled_back=1' ) );
		} catch ( \Throwable $error ) {
			self::set_state( array( 'status' => 'failed', 'archive_id' => $archive_id, 'percent' => 100, 'message' => __( 'بازگشت کامل نشد', 'flavor' ), 'error' => sanitize_text_field( $error->getMessage() ) ) );
			wp_safe_redirect( admin_url( 'themes.php?page=flavor-demos&error=rollback' ) );
		}
		exit;
	}

	/**
	 * Import one pack.
	 *
	 * @param array<string, mixed> $demo Demo.
	 */
	public static function import( array $demo, string $archive_id = '' ): bool {
		$archive_id = $archive_id ? sanitize_key( $archive_id ) : self::create_archive( (string) ( $demo['slug'] ?? 'demo' ) );
		self::set_state( array( 'status' => 'running', 'slug' => (string) ( $demo['slug'] ?? '' ), 'archive_id' => $archive_id, 'percent' => 10, 'message' => __( 'حذف فقط محتوای دموی مالکیت‌شده', 'flavor' ) ) );
		self::cleanup();
		self::set_state( array( 'percent' => 20, 'message' => __( 'اعمال پوسته و تنظیمات ظاهری', 'flavor' ) ) );

		// Reset only demo-owned overrides when selecting a new pack. This code
		// never runs on a theme update or a normal page request.
		$previous_keys = (array) get_option( 'flavor_demo_theme_mod_keys', array() );
		if ( ! $previous_keys && function_exists( 'flavor_demo_catalog' ) ) {
			$previous = flavor_demo_catalog()[ get_option( 'flavor_active_demo', '' ) ] ?? array();
			$previous_keys = array_keys( $previous['theme_mods'] ?? array() );
		}
		foreach ( $previous_keys as $key ) {
			if ( 0 === strpos( (string) $key, 'flavor_' ) ) { remove_theme_mod( $key ); }
		}
		foreach ( (array) get_theme_mods() as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'flavor_landing_' ) ) { remove_theme_mod( $key ); }
		}
		remove_theme_mod( 'flavor_hero_image' );
		if ( ! empty( $demo['landing'] ) ) {
			set_theme_mod( 'flavor_featured_enable', 'yes' );
			set_theme_mod( 'flavor_hours_enable', 'yes' );
		}

		$tokens = Design::tokens( $demo['slug'] );
		set_theme_mod( 'flavor_skin', $demo['slug'] );
		$owned_mod_keys = array_merge( $previous_keys, array( 'flavor_skin', 'flavor_hero_title', 'flavor_hero_text', 'flavor_hero_cta', 'flavor_about', 'flavor_hero_image' ) );
		foreach ( $tokens as $k => $v ) {
			set_theme_mod( 'flavor_' . $k, $v );
			$owned_mod_keys[] = 'flavor_' . $k;
		}
		// Demo-specific art direction (copy, header behavior and section labels).
		// Keeping this data in the catalog makes future bespoke demos additive.
		foreach ( $demo['theme_mods'] ?? array() as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'flavor_' ) ) {
				$key = sanitize_key( (string) $key );
				set_theme_mod( $key, $value );
				$owned_mod_keys[] = $key;
			}
		}
		$owned_mod_keys[] = 'flavor_featured_enable';
		$owned_mod_keys[] = 'flavor_hours_enable';
		set_theme_mod( 'flavor_hero_title', $demo['hero_title'] );
		set_theme_mod( 'flavor_hero_text', $demo['hero_text'] );
		set_theme_mod( 'flavor_hero_cta', $demo['theme_mods']['flavor_hero_cta'] ?? __( 'مشاهده منو', 'flavor' ) );
		set_theme_mod( 'flavor_about', $demo['about'] );
		update_option( 'blogname', $demo['site_title'] );
		update_option( 'blogdescription', $demo['tagline'] );

		$hero_id = self::sideload_hero( $demo['slug'] );
		if ( $hero_id && ! empty( $demo['landing'] ) ) {
			// Keep the square art direction: the legacy 16:9 hero size crops it.
			set_theme_mod( 'flavor_hero_image', wp_get_attachment_image_url( $hero_id, 'full' ) );
		}
		update_option( 'flavor_demo_theme_mod_keys', array_values( array_unique( $owned_mod_keys ) ), false );

		$pages = array(
			'home'        => array( 'title' => $demo['site_title'], 'template' => '' ),
			'menu'        => array( 'title' => __( 'منو', 'flavor' ), 'template' => 'page-templates/template-menu.php' ),
			'reservation' => array( 'title' => __( 'رزرو', 'flavor' ), 'template' => 'page-templates/template-reservation.php' ),
			'branches'    => array( 'title' => __( 'شعبه‌ها', 'flavor' ), 'template' => 'page-templates/template-branches.php' ),
			'about'       => array( 'title' => __( 'درباره ما', 'flavor' ), 'template' => '' ),
			'contact'     => array( 'title' => __( 'تماس', 'flavor' ), 'template' => '' ),
		);

		$ids = array();
		foreach ( $pages as $key => $cfg ) {
			$content = '';
			if ( 'home' === $key ) {
				$content = self::home_blocks( $demo, $hero_id );
			} elseif ( 'about' === $key ) {
				$content = '<p>' . esc_html( $demo['about'] ) . '</p>';
			} elseif ( 'contact' === $key ) {
				$content = '<p>' . esc_html( $demo['address'] ) . '</p><p dir="ltr">' . esc_html( $demo['phone'] ) . '</p>';
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $cfg['title'],
					'post_name'    => $key,
					'post_content' => $content,
					'meta_input'   => array( '_flavor_demo' => $demo['slug'] ),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				if ( $cfg['template'] ) {
					update_post_meta( $id, '_wp_page_template', $cfg['template'] );
				}
				if ( 'home' === $key && $hero_id ) {
					set_post_thumbnail( $id, $hero_id );
				}
				$ids[ $key ] = (int) $id;
			}
		}

			if ( ! empty( $ids['home'] ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $ids['home'] );
			}

			self::set_state( array( 'percent' => 50, 'message' => __( 'ساخت صفحات و منوی اصلی', 'flavor' ) ) );
			self::build_menu( $ids, $demo['navigation'] ?? array() );

			if ( defined( 'FLAVOR_CORE_VERSION' ) && function_exists( 'wc_get_product' ) ) {
				self::set_state( array( 'percent' => 65, 'message' => __( 'ساخت محصولات، شعبه و ساعات کاری', 'flavor' ) ) );
				self::import_commerce( $demo, $hero_id );
			}

			// Shared tracking page survives demo swaps; never overwrite existing pages/builders.
			Onboarding::ensure_pages();
			update_option( 'flavor_active_demo', $demo['slug'], false );
			update_option( 'flavor_demo_last_archive', $archive_id, false );
			self::set_state( array( 'status' => 'complete', 'percent' => 100, 'message' => __( 'درون‌ریزی کامل شد', 'flavor' ), 'finished_at' => current_time( 'mysql' ) ) );
			return true;
		}

	/**
	 * Last progress state for the admin UI and support diagnostics.
	 *
	 * @return array<string, mixed>
	 */
	private static function state(): array {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Merge a progress update without exposing exception details to visitors.
	 *
	 * @param array<string, mixed> $changes State changes.
	 */
	private static function set_state( array $changes ): void {
		update_option( self::STATE_OPTION, array_merge( self::state(), $changes ), false );
	}

	/**
	 * List archive summaries, newest first.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function archives(): array {
		$archives = get_option( self::ARCHIVES_OPTION, array() );
		return is_array( $archives ) ? array_values( array_filter( $archives, 'is_array' ) ) : array();
	}

	/**
	 * Return one archive only after validating its identifier.
	 *
	 * @param string $archive_id Archive identifier.
	 * @return array<string, mixed>|null
	 */
	private static function get_archive( string $archive_id ): ?array {
		$archive_id = sanitize_key( $archive_id );
		if ( '' === $archive_id ) {
			return null;
		}
		$archive = get_option( self::ARCHIVE_PREFIX . $archive_id, array() );
		return is_array( $archive ) && ! empty( $archive['id'] ) ? $archive : null;
	}

	/**
	 * Preview the operations without changing WordPress state.
	 *
	 * @param array<string, mixed> $demo Demo package.
	 * @return array<string, int>
	 */
	private static function preview( array $demo ): array {
		$slugs = array( 'home', 'menu', 'reservation', 'branches', 'about', 'contact' );
		$conflicts = 0;
		foreach ( $slugs as $slug ) {
			if ( get_page_by_path( $slug ) ) {
				$conflicts++;
			}
		}
		$assets = 1 + count( (array) ( $demo['category_images'] ?? array() ) );
		foreach ( (array) ( $demo['items'] ?? array() ) as $item ) {
			if ( ! empty( $item['image'] ) ) {
				$assets++;
			}
		}
		return array(
			'pages'    => count( $slugs ),
			'items'    => count( (array) ( $demo['items'] ?? array() ) ),
			'assets'   => $assets,
			'tables'   => max( 0, (int) ( $demo['tables'] ?? 0 ) ),
			'conflicts'=> $conflicts,
		);
	}

	/**
	 * Create an archive before any demo-owned data or settings are changed.
	 */
	private static function create_archive( string $slug ): string {
		$archive_id = sanitize_key( gmdate( 'YmdHis' ) . '-' . wp_generate_password( 8, false, false ) );
		$snapshot = self::snapshot( $archive_id, $slug );
		update_option( self::ARCHIVE_PREFIX . $archive_id, $snapshot, false );
		$summaries = self::archives();
		array_unshift(
			$summaries,
			array(
				'id'         => $archive_id,
				'demo'       => $slug,
				'created_at' => $snapshot['created_at'],
				'posts'      => count( $snapshot['posts'] ),
			)
		);
		update_option( self::ARCHIVES_OPTION, array_slice( $summaries, 0, 10 ), false );
		return $archive_id;
	}

	/**
	 * Capture demo-owned posts and the settings changed by the importer.
	 *
	 * @return array<string, mixed>
	 */
	private static function snapshot( string $archive_id, string $slug ): array {
		$ids = get_posts(
			array(
				'post_type'      => array( 'page', 'product', 'flavor_branch', 'attachment' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_flavor_demo',
			)
		);
		$posts = array();
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$posts[] = self::snapshot_post( $post, $archive_id );
		}
		return array(
			'id'          => $archive_id,
			'demo'        => $slug,
			'created_at'  => current_time( 'mysql' ),
			'user_id'     => get_current_user_id(),
			'theme_mods'  => (array) get_theme_mods(),
			'options'     => array(
				'blogname'             => get_option( 'blogname' ),
				'blogdescription'      => get_option( 'blogdescription' ),
				'show_on_front'        => get_option( 'show_on_front' ),
				'page_on_front'        => get_option( 'page_on_front' ),
				'flavor_active_demo'   => get_option( 'flavor_active_demo' ),
				'flavor_demo_theme_mod_keys' => get_option( 'flavor_demo_theme_mod_keys' ),
			),
			'posts'       => $posts,
			'menu_items'  => self::snapshot_menu(),
		);
	}

	/**
	 * Capture the current primary menu items so a rollback restores navigation.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function snapshot_menu(): array {
		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		$menu_id = absint( $locations['primary'] ?? 0 );
		if ( ! $menu_id ) {
			return array();
		}
		$items = wp_get_nav_menu_items( $menu_id );
		$snapshot = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$post = get_post( $item->ID );
			if ( $post ) {
				$snapshot[] = array( 'post' => (array) $post, 'meta' => get_post_meta( $post->ID ) );
			}
		}
		return $snapshot;
	}

	/**
	 * Serialize a post and copy its uploads files into the archive directory.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private static function snapshot_post( \WP_Post $post, string $archive_id ): array {
		$fields = array( 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count' );
		$data = array( 'ID' => (int) $post->ID );
		foreach ( $fields as $field ) {
			$data[ $field ] = $post->{$field};
		}
		$taxonomies = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$taxonomies[ $taxonomy ] = array_map(
				static function ( $term ): array {
					return array( 'slug' => $term->slug, 'name' => $term->name );
				},
				$terms
			);
		}
		return array(
			'post'          => $data,
			'meta'          => get_post_meta( $post->ID ),
			'terms'         => $taxonomies,
			'thumbnail_id'  => get_post_thumbnail_id( $post->ID ),
			'files'         => self::archive_attachment_files( $post, $archive_id ),
		);
	}

	/**
	 * Copy all files belonging to an attachment, including generated sizes.
	 *
	 * @param \WP_Post $post Attachment.
	 * @return array<string, string>
	 */
	private static function archive_attachment_files( \WP_Post $post, string $archive_id ): array {
		if ( 'attachment' !== $post->post_type ) {
			return array();
		}
		$original = get_attached_file( $post->ID );
		$uploads  = wp_upload_dir();
		$base     = realpath( $uploads['basedir'] );
		$source   = $original ? realpath( $original ) : false;
		$base_prefix = $base ? trailingslashit( $base ) : '';
		if ( ! $base || ! $source || '' === $base_prefix || 0 !== strpos( $source, $base_prefix ) ) {
			return array();
		}
		$archive_dir = trailingslashit( $uploads['basedir'] ) . 'flavor-archives/' . $archive_id . '/' . (int) $post->ID;
		wp_mkdir_p( $archive_dir );
		$files = array();
		foreach ( (array) glob( trailingslashit( dirname( $source ) ) . '*' ) as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}
			$backup = trailingslashit( $archive_dir ) . basename( $file );
			if ( copy( $file, $backup ) ) {
				$files[ $file ] = $backup;
			}
		}
		return $files;
	}

	/**
	 * Restore the archive. Cleanup only targets posts explicitly owned by a demo.
	 */
	private static function rollback_archive( string $archive_id, bool $set_state = true ): void {
		$archive = self::get_archive( $archive_id );
		if ( ! $archive ) {
			throw new \RuntimeException( 'Archive not found.' );
		}
		self::cleanup();
		if ( $set_state ) {
			self::set_state( array( 'percent' => 35, 'message' => __( 'بازگردانی محتوا و رسانه', 'flavor' ) ) );
		}
		$map = array();
		$posts = (array) $archive['posts'];
		usort( $posts, static function ( $left, $right ): int {
			return ( 'attachment' === ( $left['post']['post_type'] ?? '' ) ? 1 : 0 ) <=> ( 'attachment' === ( $right['post']['post_type'] ?? '' ) ? 1 : 0 );
		} );
		foreach ( $posts as $entry ) {
			$post_data = (array) ( $entry['post'] ?? array() );
			$old_id = absint( $post_data['ID'] ?? 0 );
			if ( ! $old_id ) {
				continue;
			}
			$post_id = get_post( $old_id ) ? wp_update_post( $post_data, true ) : wp_insert_post( $post_data, true );
			if ( is_wp_error( $post_id ) ) {
				unset( $post_data['ID'] );
				$post_id = wp_insert_post( $post_data, true );
			}
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			$map[ $old_id ] = (int) $post_id;
			foreach ( (array) get_post_meta( (int) $post_id ) as $meta_key => $values ) {
				delete_post_meta( (int) $post_id, $meta_key );
			}
			foreach ( (array) ( $entry['meta'] ?? array() ) as $meta_key => $values ) {
				foreach ( (array) $values as $value ) {
					update_post_meta( (int) $post_id, $meta_key, maybe_unserialize( $value ) );
				}
			}
			foreach ( (array) ( $entry['terms'] ?? array() ) as $taxonomy => $terms ) {
				wp_set_object_terms( (int) $post_id, wp_list_pluck( $terms, 'slug' ), $taxonomy, false );
			}
			foreach ( (array) ( $entry['files'] ?? array() ) as $original => $backup ) {
				if ( is_readable( $backup ) ) {
					wp_mkdir_p( dirname( $original ) );
					copy( $backup, $original );
				}
			}
		}
		foreach ( $posts as $entry ) {
			$old_thumb = absint( $entry['thumbnail_id'] ?? 0 );
			$old_id = absint( $entry['post']['ID'] ?? 0 );
			if ( $old_thumb && isset( $map[ $old_id ] ) && isset( $map[ $old_thumb ] ) ) {
				set_post_thumbnail( $map[ $old_id ], $map[ $old_thumb ] );
			}
		}
		$current_locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		foreach ( (array) get_theme_mods() as $key => $value ) {
			remove_theme_mod( $key );
		}
		foreach ( (array) ( $archive['theme_mods'] ?? array() ) as $key => $value ) {
			set_theme_mod( $key, $value );
		}
		$locations = (array) ( ( $archive['theme_mods']['nav_menu_locations'] ?? array() ) );
		$menu_id = absint( $locations['primary'] ?? 0 );
		$current_menu_ids = array_filter( array_unique( array( absint( $current_locations['primary'] ?? 0 ), $menu_id ) ) );
		foreach ( $current_menu_ids as $current_menu_id ) {
			foreach ( wp_get_nav_menu_items( $current_menu_id ) ?: array() as $item ) {
				if ( '1' === (string) get_post_meta( (int) $item->ID, '_flavor_demo_menu', true ) ) {
					wp_delete_post( (int) $item->ID, true );
				}
			}
		}
		if ( $menu_id ) {
			foreach ( (array) ( $archive['menu_items'] ?? array() ) as $entry ) {
				$raw = (array) ( $entry['post'] ?? array() );
				$old_item_id = absint( $raw['ID'] ?? 0 );
				if ( ! $old_item_id || get_post( $old_item_id ) ) {
					continue;
				}
				$fields = array();
				foreach ( array( 'ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count' ) as $field ) {
					if ( array_key_exists( $field, $raw ) ) {
						$fields[ $field ] = $raw[ $field ];
					}
				}
				$restored_item = wp_insert_post( $fields, true );
				if ( is_wp_error( $restored_item ) ) {
					unset( $fields['ID'] );
					$restored_item = wp_insert_post( $fields, true );
				}
				if ( is_wp_error( $restored_item ) ) {
					continue;
				}
				foreach ( (array) ( $entry['meta'] ?? array() ) as $meta_key => $values ) {
					foreach ( (array) $values as $value ) {
						update_post_meta( (int) $restored_item, $meta_key, maybe_unserialize( $value ) );
					}
				}
			}
		}
		foreach ( (array) ( $archive['options'] ?? array() ) as $key => $value ) {
			if ( false === $value ) {
				delete_option( $key );
			} else {
				update_option( $key, $value );
			}
		}
		if ( $set_state ) {
			self::set_state( array( 'percent' => 90, 'message' => __( 'بازگردانی تنظیمات اصلی', 'flavor' ) ) );
		}
	}

	/**
	 * Delete previous Flavor demo content.
	 */
	private static function cleanup(): void {
		$q = new \WP_Query(
			array(
				'post_type'      => array( 'page', 'product', 'flavor_branch' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_flavor_demo',
			)
		);
		foreach ( $q->posts as $id ) {
			if ( 'flavor_branch' === get_post_type( $id ) && class_exists( \FlavorCore\Database\Schema::class ) ) {
				global $wpdb;
				$wpdb->delete( \FlavorCore\Database\Schema::table( 'flavor_branch_hours' ), array( 'branch_id' => (int) $id ), array( '%d' ) );
				if ( class_exists( \FlavorCore\Delivery\ZoneRepository::class ) ) {
					foreach ( \FlavorCore\Delivery\ZoneRepository::for_branch( (int) $id ) as $zone ) { \FlavorCore\Delivery\ZoneRepository::delete( (int) $zone['id'] ); }
				}
				if ( class_exists( \FlavorCore\Table\TableRepository::class ) ) {
					foreach ( \FlavorCore\Table\TableRepository::for_branch( (int) $id ) as $table ) { \FlavorCore\Table\TableRepository::delete( (int) $table['id'] ); }
				}
			}
			wp_delete_post( (int) $id, true );
		}

		// Attachments use the `inherit` status and are not included by
		// WP_Query's `any`; remove old bundled demo media explicitly so repeated
		// one-click imports do not bloat the uploads directory.
		$attachment_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_flavor_demo',
			)
		);
		foreach ( $attachment_ids as $attachment_id ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}

	/**
	 * Sideload bundled hero.
	 */
	private static function sideload_hero( string $slug ): int {
		return self::sideload_demo_asset( $slug, 'hero.jpg' );
	}

	/**
	 * Import a bundled image from a demo pack into the media library.
	 *
	 * @param string $slug     Demo slug.
	 * @param string $filename Basename inside the demo directory.
	 */
	private static function sideload_demo_asset( string $slug, string $filename ): int {
		$slug     = sanitize_key( $slug );
		$filename = sanitize_file_name( basename( $filename ) );
		if ( ! preg_match( '/\.(?:jpe?g|png|webp)$/i', $filename ) ) {
			return 0;
		}

		$path = FLAVOR_DIR . '/demos/' . $slug . '/' . $filename;
		if ( ! is_readable( $path ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $slug . '-' . $filename );
		if ( ! $tmp ) {
			return 0;
		}
		if ( ! copy( $path, $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return 0;
		}
		$file = array(
			'name'     => $slug . '-' . $filename,
			'tmp_name' => $tmp,
		);
		$id = media_handle_sideload( $file, 0, $slug );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return 0;
		}
		update_post_meta( $id, '_flavor_demo', $slug );
		return (int) $id;
	}

	/**
	 * Front page: Gutenberg marketing blocks.
	 *
	 * @param array<string, mixed> $demo Demo.
	 */
	private static function home_blocks( array $demo, int $hero_id ): string {
		// These packs are rendered once by front-page.php. Leave the editor
		// empty so bespoke sections are not followed by a second block hero.
		if ( ! empty( $demo['landing'] ) ) { return ''; }
		$url = $hero_id ? wp_get_attachment_image_url( $hero_id, 'flavor-hero' ) : '';
		$quotes = '';
		foreach ( $demo['testimonials'] as $t ) {
			$quotes .= '<!-- wp:flavor/testimonial {"name":"' . esc_attr( $t['name'] ) . '"} --><p>' . esc_html( $t['text'] ) . '</p><!-- /wp:flavor/testimonial -->';
		}
		return '<!-- wp:flavor/hero {"title":"' . esc_attr( $demo['hero_title'] ) . '","cta":"' . esc_attr__( 'مشاهده منو', 'flavor' ) . '","image":"' . esc_url( $url ) . '"} --><p>' . esc_html( $demo['hero_text'] ) . '</p><!-- /wp:flavor/hero -->'
			. '<!-- wp:flavor/about {"title":"' . esc_attr__( 'داستان ما', 'flavor' ) . '"} --><p>' . esc_html( $demo['about'] ) . '</p><!-- /wp:flavor/about -->'
			. $quotes;
	}

	/**
	 * Primary menu.
	 *
	 * @param array<string, int> $ids Page ids.
	 */
	private static function build_menu( array $ids, array $navigation = array() ): void {
		$name = 'Flavor Primary';
		$mid  = wp_create_nav_menu( $name );
		if ( is_wp_error( $mid ) ) {
			$menus = wp_get_nav_menus();
			foreach ( $menus as $m ) {
				if ( $m->name === $name ) {
					$mid = (int) $m->term_id;
				}
			}
		}
		if ( ! $mid || is_wp_error( $mid ) ) {
			return;
		}
		// Custom anchor items do not disappear when old demo pages are deleted.
		// Rebuild only the importer's own named menu, never the site's other menus.
		foreach ( wp_get_nav_menu_items( (int) $mid ) ?: array() as $old_item ) {
			$owned = '1' === (string) get_post_meta( (int) $old_item->ID, '_flavor_demo_menu', true );
			if ( ! $owned && 'post_type' === $old_item->type && 'page' === $old_item->object && get_post_meta( (int) $old_item->object_id, '_flavor_demo', true ) ) {
				$owned = true;
			}
			if ( $owned ) {
				wp_delete_post( (int) $old_item->ID, true );
			}
		}
		foreach ( $navigation as $link ) {
			if ( ! preg_match( '/^#[a-z][a-z0-9-]*$/', $link['anchor'] ?? '' ) ) { continue; }
			$item_id = wp_update_nav_menu_item( (int) $mid, 0, array(
				'menu-item-title' => sanitize_text_field( $link['label'] ),
				'menu-item-type' => 'custom',
				'menu-item-url' => home_url( '/' ) . $link['anchor'],
				'menu-item-status' => 'publish',
			) );
			if ( $item_id && ! is_wp_error( $item_id ) ) {
				update_post_meta( (int) $item_id, '_flavor_demo_menu', '1' );
			}
		}
		foreach ( $navigation ? array() : array( 'home', 'menu', 'reservation', 'about', 'contact' ) as $key ) {
			if ( empty( $ids[ $key ] ) ) {
				continue;
			}
			$item_id = wp_update_nav_menu_item(
				(int) $mid,
				0,
				array(
					'menu-item-title'     => get_the_title( $ids[ $key ] ),
					'menu-item-object'    => 'page',
					'menu-item-object-id' => $ids[ $key ],
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
			if ( $item_id && ! is_wp_error( $item_id ) ) {
				update_post_meta( (int) $item_id, '_flavor_demo_menu', '1' );
			}
		}
		$locations            = (array) get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = (int) $mid;
		$locations['footer']  = (int) $mid;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Products + branch + tables.
	 *
	 * @param array<string, mixed> $demo Demo.
	 */
	private static function import_commerce( array $demo, int $hero_id ): void {
		$term_ids           = array();
		$category_image_ids = array();
		$asset_image_ids    = array();
		foreach ( $demo['categories'] as $label => $slug ) {
			$term_created = false;
			$term = term_exists( $slug, 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( $label, 'product_cat', array( 'slug' => $slug ) );
				$term_created = ! is_wp_error( $term );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id           = (int) ( $term['term_id'] ?? $term );
				$term_ids[ $slug ] = $term_id;

				$image_file = $demo['category_images'][ $slug ] ?? '';
				$owned_term = $term_created || (string) get_term_meta( $term_id, '_flavor_demo_category', true ) === (string) $demo['slug'];
				if ( $owned_term && is_string( $image_file ) && '' !== $image_file ) {
					$image_id = self::sideload_demo_asset( (string) $demo['slug'], $image_file );
					if ( $image_id ) {
						$category_image_ids[ $slug ] = $image_id;
						$asset_image_ids[ $image_file ] = $image_id;
						update_term_meta( $term_id, 'thumbnail_id', $image_id );
						update_term_meta( $term_id, '_flavor_demo_category', $demo['slug'] );
					}
				}
			}
		}

		foreach ( $demo['items'] as $index => $row ) {
			// Pack prices are toman; products and modifiers must use the same
			// storage unit as the Core menu/cart API. No existing product is
			// migrated: this applies only during an explicit demo import.
			$stored_price = \FlavorCore\WooCommerce\Currency::to_storage( (int) $row['price'], 'irt' );
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'product',
					'post_status'  => 'publish',
					'post_title'   => $row['name'],
					'menu_order'   => (int) $index + 1,
					'post_excerpt' => $row['description'],
					'post_content' => $row['description'],
					'meta_input'   => array( '_flavor_demo' => $demo['slug'] ),
				)
			);
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}
			wp_set_object_terms( $post_id, 'simple', 'product_type' );
			if ( ! empty( $row['category'] ) && ! empty( $term_ids[ $row['category'] ] ) ) {
				wp_set_object_terms( $post_id, array( $term_ids[ $row['category'] ] ), 'product_cat' );
			}
			update_post_meta( $post_id, '_regular_price', (string) $stored_price );
			update_post_meta( $post_id, '_price', (string) $stored_price );
			update_post_meta( $post_id, '_virtual', 'yes' );
			update_post_meta( $post_id, '_flavor_prep_time', (int) ( $row['prep'] ?? 15 ) );
			update_post_meta( $post_id, '_flavor_dietary', $row['dietary'] ?? array() );
			if ( isset( $row['allergens'] ) ) { update_post_meta( $post_id, '_flavor_allergens', array_map( 'sanitize_key', (array) $row['allergens'] ) ); }
			if ( isset( $row['calories'] ) ) { update_post_meta( $post_id, '_flavor_calories', absint( $row['calories'] ) ); }
			update_post_meta( $post_id, '_flavor_schedule', $row['schedule'] ?? array() );
			$mods = array();
			foreach ( $row['modifiers'] ?? array() as $i => $m ) {
				$mods[] = array(
					'id'         => sanitize_title( ( $m['type'] ?? 'topping' ) . '-' . $m['name'] ),
					'type'       => $m['type'] ?? 'topping',
					'name'       => $m['name'],
					'price'      => \FlavorCore\WooCommerce\Currency::to_storage( (int) ( $m['price'] ?? 0 ), 'irt' ),
					'is_default' => ! empty( $m['is_default'] ) ? 1 : 0,
				);
			}
			update_post_meta( $post_id, '_flavor_modifiers', $mods );
			$product_image_id = $category_image_ids[ $row['category'] ?? '' ] ?? $hero_id;
			if ( ! empty( $row['image'] ) && is_string( $row['image'] ) ) {
				if ( ! isset( $asset_image_ids[ $row['image'] ] ) ) {
					$asset_image_ids[ $row['image'] ] = self::sideload_demo_asset( (string) $demo['slug'], $row['image'] );
				}
				$product_image_id = $asset_image_ids[ $row['image'] ] ?: $product_image_id;
			}
			if ( $product_image_id ) {
				set_post_thumbnail( $post_id, $product_image_id );
			}
			// Sync WooCommerce's lookup tables through its CRUD data store.
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$product->set_regular_price( (string) $stored_price );
				$product->set_price( (string) $stored_price );
				$product->set_stock_status( 'instock' );
				$product->set_virtual( true );
				$product->save();
			}
		}

		$branch_id = wp_insert_post(
			array(
				'post_type'    => 'flavor_branch',
				'post_status'  => 'publish',
				'post_title'   => $demo['branch_name'],
				'post_name'    => sanitize_title( $demo['slug'] . '-branch' ),
				'post_content' => $demo['about'],
				'meta_input'   => array(
					'_flavor_demo'        => $demo['slug'],
					'_flavor_city'        => $demo['city'],
					'_flavor_province'    => $demo['city'],
					'_flavor_phone'       => $demo['phone'],
					'_flavor_address'     => $demo['address'],
					'_flavor_timezone'    => 'Asia/Tehran',
					'_flavor_is_default'  => '1',
					'_flavor_order_modes' => $demo['order_modes'] ?? array( 'dine_in', 'takeaway', 'delivery' ),
				),
			)
		);

		if ( $branch_id && ! is_wp_error( $branch_id ) && ! empty( $demo['delivery_zones'] ) && class_exists( \FlavorCore\Delivery\ZoneRepository::class ) ) {
			foreach ( $demo['delivery_zones'] as $zone ) {
				$zone['branch_id'] = (int) $branch_id;
				$zone['delivery_fee'] = \FlavorCore\WooCommerce\Currency::to_storage( (int) ( $zone['delivery_fee'] ?? 0 ), 'irt' );
				$zone['min_order'] = \FlavorCore\WooCommerce\Currency::to_storage( (int) ( $zone['min_order'] ?? 0 ), 'irt' );
				\FlavorCore\Delivery\ZoneRepository::create( $zone );
			}
		}

		if ( $branch_id && ! is_wp_error( $branch_id ) && ! empty( $demo['opening_hours'] ) && class_exists( \FlavorCore\Database\Schema::class ) ) {
			global $wpdb;
			for ( $day = 0; $day < 7; $day++ ) {
				$wpdb->insert( \FlavorCore\Database\Schema::table( 'flavor_branch_hours' ), array(
					'branch_id' => (int) $branch_id, 'day_of_week' => $day, 'mode' => 'all',
					'open_time' => $demo['opening_hours']['open'], 'close_time' => $demo['opening_hours']['close'], 'is_closed' => 0,
				), array( '%d', '%d', '%s', '%s', '%s', '%d' ) );
			}
		}

		if ( $branch_id && ! is_wp_error( $branch_id ) && class_exists( '\\FlavorCore\\Table\\TableRepository' ) ) {
			$table_count = max( 0, min( 100, (int) ( $demo['tables'] ?? 8 ) ) );
			if ( $table_count > 0 ) {
				\FlavorCore\Table\TableRepository::bulk_create( (int) $branch_id, 1, $table_count, 4 );
			}
			if ( class_exists( '\\FlavorCore\\Support\\Settings' ) ) {
				\FlavorCore\Support\Settings::update( array( 'default_branch_id' => (int) $branch_id ) );
			}
		}
	}
}
