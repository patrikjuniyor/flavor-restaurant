<?php
/**
 * Custom roles and capabilities.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Roles
 */
class Roles {

	public const BRANCH_MANAGER = 'flavor_branch_manager';
	public const KITCHEN        = 'flavor_kitchen';
	public const CASHIER        = 'flavor_cashier';

	/**
	 * Capabilities introduced by the plugin.
	 *
	 * @return string[]
	 */
	public static function caps(): array {
		return array(
			'flavor_manage_settings',
			'flavor_manage_all_branches',
			'flavor_manage_branch',
			'flavor_manage_kitchen',
			'flavor_manage_reservations',
			'flavor_create_phone_order',
			'flavor_confirm_payment',
			'flavor_view_reports',
			'flavor_manage_loyalty',
			'flavor_manage_sms',
		);
	}

	/**
	 * Register roles and grant caps to administrators.
	 */
	public static function register(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::caps() as $cap ) {
				$admin->add_cap( $cap );
			}
			$admin->add_cap( 'edit_flavor_branches' );
			$admin->add_cap( 'edit_others_flavor_branches' );
			$admin->add_cap( 'publish_flavor_branches' );
			$admin->add_cap( 'read_private_flavor_branches' );
			$admin->add_cap( 'delete_flavor_branches' );
		}

		add_role(
			self::BRANCH_MANAGER,
			__( 'مدیر شعبه', 'flavor-core' ),
			array(
				'read'                        => true,
				'upload_files'                => true,
				'flavor_manage_branch'        => true,
				'flavor_manage_kitchen'       => true,
				'flavor_manage_reservations'  => true,
				'flavor_create_phone_order'   => true,
				'flavor_confirm_payment'      => true,
				'flavor_view_reports'         => true,
				'flavor_manage_loyalty'       => true,
				'edit_flavor_branches'        => true,
				'publish_flavor_branches'     => true,
			)
		);

		add_role(
			self::KITCHEN,
			__( 'پرسنل آشپزخانه', 'flavor-core' ),
			array(
				'read'                  => true,
				'flavor_manage_kitchen' => true,
			)
		);

		add_role(
			self::CASHIER,
			__( 'صندوق‌دار', 'flavor-core' ),
			array(
				'read'                      => true,
				'flavor_manage_kitchen'     => true,
				'flavor_create_phone_order' => true,
				'flavor_confirm_payment'    => true,
				'flavor_manage_reservations'=> true,
			)
		);

		$shop_manager = get_role( 'shop_manager' );
		if ( $shop_manager ) {
			foreach ( self::caps() as $cap ) {
				$shop_manager->add_cap( $cap );
			}
		}
	}

	/**
	 * Branch IDs a user may operate on. Empty array means "none".
	 * Administrators / users with flavor_manage_all_branches get every published branch.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public static function managed_branch_ids( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		if ( user_can( $user, 'flavor_manage_all_branches' ) || user_can( $user, 'manage_options' ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'flavor_branch',
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			return array_map( 'intval', $ids );
		}

		$assigned = get_user_meta( $user_id, '_flavor_managed_branches', true );
		if ( ! is_array( $assigned ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'intval', $assigned ) ) );
	}

	/**
	 * Whether the user may touch a given branch.
	 */
	public static function can_access_branch( int $user_id, int $branch_id ): bool {
		return in_array( $branch_id, self::managed_branch_ids( $user_id ), true );
	}
}
