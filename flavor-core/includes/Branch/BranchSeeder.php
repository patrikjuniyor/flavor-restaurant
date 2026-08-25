<?php
/**
 * Creates a default branch on first run.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Branch;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class BranchSeeder
 */
class BranchSeeder {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'maybe_seed' ), 30 );
	}

	/**
	 * Insert a published default branch when the activation flag is set.
	 */
	public function maybe_seed(): void {
		if ( 'yes' !== get_option( 'flavor_core_need_default_branch' ) ) {
			return;
		}

		$existing = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			delete_option( 'flavor_core_need_default_branch' );
			Settings::update( array( 'default_branch_id' => (int) $existing[0] ) );
			return;
		}

		$id = wp_insert_post(
			array(
				'post_type'   => BranchPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'شعبه مرکزی', 'flavor-core' ),
				'post_name'   => 'central',
				'post_content'=> __( 'شعبه پیش‌فرض. نشانی و ساعات کاری را ویرایش کنید.', 'flavor-core' ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return;
		}

		update_post_meta( $id, '_flavor_timezone', 'Asia/Tehran' );
		update_post_meta( $id, '_flavor_is_default', '1' );
		update_post_meta( $id, '_flavor_order_modes', array( 'dine_in', 'takeaway', 'delivery' ) );
		update_post_meta( $id, '_flavor_city', __( 'تهران', 'flavor-core' ) );
		update_post_meta( $id, '_flavor_province', __( 'تهران', 'flavor-core' ) );

		Settings::update( array( 'default_branch_id' => (int) $id ) );
		delete_option( 'flavor_core_need_default_branch' );
	}
}
