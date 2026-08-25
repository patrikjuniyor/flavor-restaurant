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
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_flavor_import_demo', array( self::class, 'handle' ) );
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
		$catalog = flavor_demo_catalog();
		$notice  = isset( $_GET['imported'] ) ? sanitize_key( wp_unslash( $_GET['imported'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'هشت دموی رستوران مستقیم', 'flavor' ); ?></h1>
			<?php if ( $notice && isset( $catalog[ $notice ] ) ) : ?>
				<div class="notice notice-success"><p>
					<?php echo esc_html( sprintf( /* translators: demo */ __( 'دمو «%s» درون‌ریزی شد. صفحه نخست را بررسی کنید.', 'flavor' ), $catalog[ $notice ]['title'] ) ); ?>
				</p></div>
			<?php endif; ?>
			<?php if ( ! defined( 'FLAVOR_CORE_VERSION' ) ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Flavor Core فعال نیست؛ صفحات و رنگ‌ها وارد می‌شوند اما محصول و شعبه ساخته نمی‌شود.', 'flavor' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'هر دمو حداقل ۲۰ آیتم منو، شعبه نمونه، میز و صفحات منو/رزرو می‌سازد. دموی قبلی با برچسب همین قالب پاک می‌شود.', 'flavor' ); ?></p>
			<div class="flavor-demo-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;">
				<?php foreach ( $catalog as $slug => $demo ) : ?>
					<?php $hero = FLAVOR_URI . '/demos/' . $slug . '/hero.jpg'; ?>
					<article style="border:1px solid #ddd;border-radius:12px;overflow:hidden;background:#fff;">
						<img src="<?php echo esc_url( $hero ); ?>" alt="" style="width:100%;height:140px;object-fit:cover;" />
						<div style="padding:12px 14px 16px;">
							<h2 style="margin:0 0 6px;font-size:1.1rem;"><?php echo esc_html( $demo['title'] ); ?></h2>
							<p style="color:#555;min-height:3em;"><?php echo esc_html( $demo['tagline'] ); ?></p>
							<p><?php echo esc_html( sprintf( /* translators: count */ __( '%d آیتم منو', 'flavor' ), count( $demo['items'] ) ) ); ?></p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'flavor_import_demo' ); ?>
								<input type="hidden" name="action" value="flavor_import_demo" />
								<input type="hidden" name="demo" value="<?php echo esc_attr( $slug ); ?>" />
								<button class="button button-primary"><?php esc_html_e( 'درون‌ریزی یک‌کلیکی', 'flavor' ); ?></button>
							</form>
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

		self::import( $catalog[ $slug ] );

		wp_safe_redirect( admin_url( 'themes.php?page=flavor-demos&imported=' . rawurlencode( $slug ) ) );
		exit;
	}

	/**
	 * Import one pack.
	 *
	 * @param array<string, mixed> $demo Demo.
	 */
	public static function import( array $demo ): void {
		self::cleanup();

		$tokens = Design::tokens( $demo['slug'] );
		set_theme_mod( 'flavor_skin', $demo['slug'] );
		foreach ( $tokens as $k => $v ) {
			set_theme_mod( 'flavor_' . $k, $v );
		}
		set_theme_mod( 'flavor_hero_title', $demo['hero_title'] );
		set_theme_mod( 'flavor_hero_text', $demo['hero_text'] );
		set_theme_mod( 'flavor_hero_cta', __( 'مشاهده منو', 'flavor' ) );
		set_theme_mod( 'flavor_about', $demo['about'] );
		update_option( 'blogname', $demo['site_title'] );
		update_option( 'blogdescription', $demo['tagline'] );

		$hero_id = self::sideload_hero( $demo['slug'] );

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

		self::build_menu( $ids );

		if ( defined( 'FLAVOR_CORE_VERSION' ) && function_exists( 'wc_get_product' ) ) {
			self::import_commerce( $demo, $hero_id );
		}

		update_option( 'flavor_active_demo', $demo['slug'], false );
	}

	/**
	 * Delete previous Flavor demo content.
	 */
	private static function cleanup(): void {
		$q = new \WP_Query(
			array(
				'post_type'      => array( 'page', 'product', 'flavor_branch' ),
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => '_flavor_demo',
			)
		);
		foreach ( $q->posts as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * Sideload bundled hero.
	 */
	private static function sideload_hero( string $slug ): int {
		$path = FLAVOR_DIR . '/demos/' . $slug . '/hero.jpg';
		if ( ! is_readable( $path ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $slug . '-hero.jpg' );
		copy( $path, $tmp );
		$file = array(
			'name'     => $slug . '-hero.jpg',
			'tmp_name' => $tmp,
		);
		$id   = media_handle_sideload( $file, 0, $slug );
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
	private static function build_menu( array $ids ): void {
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
		foreach ( array( 'home', 'menu', 'reservation', 'about', 'contact' ) as $key ) {
			if ( empty( $ids[ $key ] ) ) {
				continue;
			}
			wp_update_nav_menu_item(
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
		}
		$locations            = get_theme_mod( 'nav_menu_locations', array() );
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
		$term_ids = array();
		foreach ( $demo['categories'] as $label => $slug ) {
			$term = term_exists( $slug, 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( $label, 'product_cat', array( 'slug' => $slug ) );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_ids[ $slug ] = (int) ( $term['term_id'] ?? $term );
			}
		}

		foreach ( $demo['items'] as $row ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'product',
					'post_status'  => 'publish',
					'post_title'   => $row['name'],
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
			update_post_meta( $post_id, '_regular_price', (string) $row['price'] );
			update_post_meta( $post_id, '_price', (string) $row['price'] );
			update_post_meta( $post_id, '_virtual', 'yes' );
			update_post_meta( $post_id, '_flavor_prep_time', (int) ( $row['prep'] ?? 15 ) );
			update_post_meta( $post_id, '_flavor_dietary', $row['dietary'] ?? array() );
			update_post_meta( $post_id, '_flavor_schedule', $row['schedule'] ?? array() );
			$mods = array();
			foreach ( $row['modifiers'] ?? array() as $i => $m ) {
				$mods[] = array(
					'id'         => sanitize_title( ( $m['type'] ?? 'topping' ) . '-' . $m['name'] ),
					'type'       => $m['type'] ?? 'topping',
					'name'       => $m['name'],
					'price'      => isset( $m['price'] ) ? (int) $m['price'] * 10 : 0, // toman → rial storage default.
					'is_default' => ! empty( $m['is_default'] ) ? 1 : 0,
				);
			}
			update_post_meta( $post_id, '_flavor_modifiers', $mods );
			if ( $hero_id ) {
				set_post_thumbnail( $post_id, $hero_id );
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
					'_flavor_order_modes' => array( 'dine_in', 'takeaway', 'delivery' ),
				),
			)
		);

		if ( $branch_id && ! is_wp_error( $branch_id ) && class_exists( '\\FlavorCore\\Table\\TableRepository' ) ) {
			\FlavorCore\Table\TableRepository::bulk_create( (int) $branch_id, 1, 8, 4 );
			if ( class_exists( '\\FlavorCore\\Support\\Settings' ) ) {
				\FlavorCore\Support\Settings::update( array( 'default_branch_id' => (int) $branch_id ) );
			}
		}
	}
}
