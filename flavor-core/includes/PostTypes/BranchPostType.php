<?php
/**
 * Branch custom post type and meta.
 *
 * @package FlavorCore
 */

namespace FlavorCore\PostTypes;

use FlavorCore\Support\Roles;
use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class BranchPostType
 */
class BranchPostType {

	public const POST_TYPE = 'flavor_branch';

	/**
	 * Meta keys stored on the branch post.
	 */
	public const META_KEYS = array(
		'_flavor_phone',
		'_flavor_address',
		'_flavor_province',
		'_flavor_city',
		'_flavor_neighborhood',
		'_flavor_lat',
		'_flavor_lng',
		'_flavor_timezone',
		'_flavor_order_modes',
		'_flavor_payment_methods',
		'_flavor_is_default',
		'_flavor_staff_ids',
	);

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'metaboxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_fields' ) );
	}

	/**
	 * Register the CPT.
	 */
	public function register(): void {
		$labels = array(
			'name'               => __( 'شعبه‌ها', 'flavor-core' ),
			'singular_name'      => __( 'شعبه', 'flavor-core' ),
			'add_new'            => __( 'افزودن شعبه', 'flavor-core' ),
			'add_new_item'       => __( 'افزودن شعبه جدید', 'flavor-core' ),
			'edit_item'          => __( 'ویرایش شعبه', 'flavor-core' ),
			'new_item'           => __( 'شعبه جدید', 'flavor-core' ),
			'view_item'          => __( 'مشاهده شعبه', 'flavor-core' ),
			'search_items'       => __( 'جستجوی شعبه', 'flavor-core' ),
			'not_found'          => __( 'شعبه‌ای پیدا نشد.', 'flavor-core' ),
			'not_found_in_trash' => __( 'در زباله‌دان شعبه‌ای نیست.', 'flavor-core' ),
			'all_items'          => __( 'همه شعبه‌ها', 'flavor-core' ),
			'menu_name'          => __( 'شعبه‌ها', 'flavor-core' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'has_archive'         => true,
				'rewrite'             => array(
					'slug'       => 'branch',
					'with_front' => false,
				),
				'show_in_rest'        => true,
				'rest_base'           => 'flavor-branches',
				'menu_icon'           => 'dashicons-store',
				'menu_position'       => 56,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'slug' ),
				'show_in_menu'        => 'flavor-core',
				'capability_type'     => array( 'flavor_branch', 'flavor_branches' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Meta boxes.
	 */
	public function metaboxes(): void {
		add_meta_box(
			'flavor-branch-details',
			__( 'اطلاعات شعبه', 'flavor-core' ),
			array( $this, 'render_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Metabox markup.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'flavor_save_branch', 'flavor_branch_nonce' );

		$phone        = (string) get_post_meta( $post->ID, '_flavor_phone', true );
		$address      = (string) get_post_meta( $post->ID, '_flavor_address', true );
		$province     = (string) get_post_meta( $post->ID, '_flavor_province', true );
		$city         = (string) get_post_meta( $post->ID, '_flavor_city', true );
		$neighborhood = (string) get_post_meta( $post->ID, '_flavor_neighborhood', true );
		$lat          = (string) get_post_meta( $post->ID, '_flavor_lat', true );
		$lng          = (string) get_post_meta( $post->ID, '_flavor_lng', true );
		$timezone     = (string) get_post_meta( $post->ID, '_flavor_timezone', true );
		$is_default   = (string) get_post_meta( $post->ID, '_flavor_is_default', true );
		$modes        = get_post_meta( $post->ID, '_flavor_order_modes', true );
		if ( ! is_array( $modes ) || empty( $modes ) ) {
			$modes = array( 'dine_in', 'takeaway', 'delivery' );
		}

		if ( '' === $timezone ) {
			$timezone = 'Asia/Tehran';
		}

		$mode_labels = array(
			'dine_in'  => __( 'سالن', 'flavor-core' ),
			'takeaway' => __( 'بیرون‌بر', 'flavor-core' ),
			'delivery' => __( 'ارسال', 'flavor-core' ),
		);
		?>
		<style>
			.flavor-branch-grid { display: grid; grid-template-columns: 160px 1fr; gap: 10px 16px; max-width: 720px; }
			.flavor-branch-grid label { font-weight: 600; align-self: center; }
			.flavor-branch-grid input[type="text"],
			.flavor-branch-grid textarea,
			.flavor-branch-grid select { width: 100%; }
		</style>
		<div class="flavor-branch-grid">
			<label for="flavor_phone"><?php esc_html_e( 'تلفن', 'flavor-core' ); ?></label>
			<input type="text" id="flavor_phone" name="flavor_phone" value="<?php echo esc_attr( $phone ); ?>" dir="ltr" />

			<label for="flavor_province"><?php esc_html_e( 'استان', 'flavor-core' ); ?></label>
			<input type="text" id="flavor_province" name="flavor_province" value="<?php echo esc_attr( $province ); ?>" />

			<label for="flavor_city"><?php esc_html_e( 'شهر', 'flavor-core' ); ?></label>
			<input type="text" id="flavor_city" name="flavor_city" value="<?php echo esc_attr( $city ); ?>" />

			<label for="flavor_neighborhood"><?php esc_html_e( 'محله', 'flavor-core' ); ?></label>
			<input type="text" id="flavor_neighborhood" name="flavor_neighborhood" value="<?php echo esc_attr( $neighborhood ); ?>" />

			<label for="flavor_address"><?php esc_html_e( 'نشانی', 'flavor-core' ); ?></label>
			<textarea id="flavor_address" name="flavor_address" rows="3"><?php echo esc_textarea( $address ); ?></textarea>

			<label for="flavor_lat"><?php esc_html_e( 'عرض جغرافیایی', 'flavor-core' ); ?></label>
			<input type="text" id="flavor_lat" name="flavor_lat" value="<?php echo esc_attr( $lat ); ?>" dir="ltr" />

			<label for="flavor_lng"><?php esc_html_e( 'طول جغرافیایی', 'flavor-core' ); ?></label>
			<input type="text" id="flavor_lng" name="flavor_lng" value="<?php echo esc_attr( $lng ); ?>" dir="ltr" />

			<label for="flavor_timezone"><?php esc_html_e( 'منطقه زمانی', 'flavor-core' ); ?></label>
			<input type="text" id="flavor_timezone" name="flavor_timezone" value="<?php echo esc_attr( $timezone ); ?>" dir="ltr" />

			<span><?php esc_html_e( 'حالت‌های سفارش', 'flavor-core' ); ?></span>
			<div>
				<?php foreach ( $mode_labels as $mode => $label ) : ?>
					<label style="display:inline-block;margin-inline-end:12px;font-weight:400;">
						<input type="checkbox" name="flavor_order_modes[]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( in_array( $mode, $modes, true ) ); ?> />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<span><?php esc_html_e( 'شعبه پیش‌فرض', 'flavor-core' ); ?></span>
			<label style="font-weight:400;">
				<input type="checkbox" name="flavor_is_default" value="1" <?php checked( $is_default, '1' ); ?> />
				<?php esc_html_e( 'اگر مشتری شعبه‌ای انتخاب نکرده باشد از این شعبه استفاده شود.', 'flavor-core' ); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Persist meta.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['flavor_branch_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flavor_branch_nonce'] ) ), 'flavor_save_branch' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$phone        = isset( $_POST['flavor_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['flavor_phone'] ) ) : '';
		$address      = isset( $_POST['flavor_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['flavor_address'] ) ) : '';
		$province     = isset( $_POST['flavor_province'] ) ? sanitize_text_field( wp_unslash( $_POST['flavor_province'] ) ) : '';
		$city         = isset( $_POST['flavor_city'] ) ? sanitize_text_field( wp_unslash( $_POST['flavor_city'] ) ) : '';
		$neighborhood = isset( $_POST['flavor_neighborhood'] ) ? sanitize_text_field( wp_unslash( $_POST['flavor_neighborhood'] ) ) : '';
		$lat          = isset( $_POST['flavor_lat'] ) ? sanitize_text_field( wp_unslash( $_POST['flavor_lat'] ) ) : '';
		$lng          = isset( $_POST['flavor_lng'] ) ? sanitize_text_field( wp_unslash( $_POST['flavor_lng'] ) ) : '';
		$timezone     = isset( $_POST['flavor_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['flavor_timezone'] ) ) : 'Asia/Tehran';
		$is_default   = ! empty( $_POST['flavor_is_default'] ) ? '1' : '0';
		$modes        = isset( $_POST['flavor_order_modes'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['flavor_order_modes'] ) ) : array();

		$allowed_modes = array( 'dine_in', 'takeaway', 'delivery' );
		$modes         = array_values( array_intersect( $modes, $allowed_modes ) );
		if ( empty( $modes ) ) {
			$modes = array( 'dine_in', 'takeaway' );
		}

		update_post_meta( $post_id, '_flavor_phone', $phone );
		update_post_meta( $post_id, '_flavor_address', $address );
		update_post_meta( $post_id, '_flavor_province', $province );
		update_post_meta( $post_id, '_flavor_city', $city );
		update_post_meta( $post_id, '_flavor_neighborhood', $neighborhood );
		update_post_meta( $post_id, '_flavor_lat', $lat );
		update_post_meta( $post_id, '_flavor_lng', $lng );
		update_post_meta( $post_id, '_flavor_timezone', $timezone );
		update_post_meta( $post_id, '_flavor_order_modes', $modes );
		update_post_meta( $post_id, '_flavor_is_default', $is_default );

		if ( '1' === $is_default ) {
			$this->clear_other_defaults( $post_id );
			Settings::update( array( 'default_branch_id' => $post_id ) );
		}

		Settings::bump_menu_version( $post_id );

		unset( $post );
	}

	/**
	 * Only one default branch.
	 */
	private function clear_other_defaults( int $keep_id ): void {
		$others = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post__not_in'   => array( $keep_id ),
				'meta_key'       => '_flavor_is_default',
				'meta_value'     => '1',
			)
		);
		foreach ( $others as $id ) {
			update_post_meta( (int) $id, '_flavor_is_default', '0' );
		}
	}

	/**
	 * Admin columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['flavor_city']    = __( 'شهر', 'flavor-core' );
				$new['flavor_phone']   = __( 'تلفن', 'flavor-core' );
				$new['flavor_default'] = __( 'پیش‌فرض', 'flavor-core' );
			}
		}
		return $new;
	}

	/**
	 * Column output.
	 */
	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'flavor_city':
				echo esc_html( (string) get_post_meta( $post_id, '_flavor_city', true ) );
				break;
			case 'flavor_phone':
				echo esc_html( (string) get_post_meta( $post_id, '_flavor_phone', true ) );
				break;
			case 'flavor_default':
				echo '1' === (string) get_post_meta( $post_id, '_flavor_is_default', true ) ? '★' : '—';
				break;
		}
	}

	/**
	 * Extra REST fields on the CPT endpoint.
	 */
	public function register_rest_fields(): void {
		$keys = array(
			'phone'        => '_flavor_phone',
			'address'      => '_flavor_address',
			'province'     => '_flavor_province',
			'city'         => '_flavor_city',
			'neighborhood' => '_flavor_neighborhood',
			'lat'          => '_flavor_lat',
			'lng'          => '_flavor_lng',
			'timezone'     => '_flavor_timezone',
			'order_modes'  => '_flavor_order_modes',
			'is_default'   => '_flavor_is_default',
		);

		foreach ( $keys as $field => $meta ) {
			register_rest_field(
				self::POST_TYPE,
				$field,
				array(
					'get_callback' => static function ( array $object ) use ( $meta ) {
						return get_post_meta( (int) $object['id'], $meta, true );
					},
					'schema'       => array(
						'context' => array( 'view', 'edit' ),
					),
				)
			);
		}
	}

	/**
	 * Default published branch id, or 0.
	 */
	public static function default_id(): int {
		$from_settings = (int) Settings::get( 'default_branch_id', 0 );
		if ( $from_settings && get_post_type( $from_settings ) === self::POST_TYPE ) {
			return $from_settings;
		}

		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_flavor_is_default',
				'meta_value'     => '1',
			)
		);

		if ( ! empty( $found ) ) {
			return (int) $found[0];
		}

		$any = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return ! empty( $any ) ? (int) $any[0] : 0;
	}

	/**
	 * Serialized public payload.
	 *
	 * @param int $branch_id Branch post id.
	 * @return array<string, mixed>|null
	 */
	public static function to_array( int $branch_id ): ?array {
		$post = get_post( $branch_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$modes = get_post_meta( $branch_id, '_flavor_order_modes', true );
		if ( ! is_array( $modes ) ) {
			$modes = array( 'dine_in', 'takeaway', 'delivery' );
		}

		return array(
			'id'           => $branch_id,
			'name'         => get_the_title( $branch_id ),
			'slug'         => $post->post_name,
			'phone'        => (string) get_post_meta( $branch_id, '_flavor_phone', true ),
			'address'      => (string) get_post_meta( $branch_id, '_flavor_address', true ),
			'province'     => (string) get_post_meta( $branch_id, '_flavor_province', true ),
			'city'         => (string) get_post_meta( $branch_id, '_flavor_city', true ),
			'neighborhood' => (string) get_post_meta( $branch_id, '_flavor_neighborhood', true ),
			'lat'          => (string) get_post_meta( $branch_id, '_flavor_lat', true ),
			'lng'          => (string) get_post_meta( $branch_id, '_flavor_lng', true ),
			'timezone'     => (string) get_post_meta( $branch_id, '_flavor_timezone', true ) ?: 'Asia/Tehran',
			'order_modes'  => $modes,
			'is_default'   => '1' === (string) get_post_meta( $branch_id, '_flavor_is_default', true ),
			'menu_version' => Settings::menu_version( $branch_id ),
			'permalink'    => get_permalink( $branch_id ),
		);
	}
}
