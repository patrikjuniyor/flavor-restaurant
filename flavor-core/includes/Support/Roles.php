<?php
/**
 * Restaurant Staff Roles and Granular Capability Matrix.
 * Supports Multi-branch permission isolation across Owner, General Manager,
 * Branch Manager, Cashier, Kitchen Chef, and Waiter.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class Roles
 */
class Roles {

	public const OWNER          = 'flavor_owner';
	public const GENERAL_MANAGER= 'flavor_manager';
	public const BRANCH_MANAGER = 'flavor_branch_manager';
	public const CASHIER        = 'flavor_cashier';
	public const KITCHEN        = 'flavor_kitchen';
	public const WAITER         = 'flavor_waiter';

	/**
	 * Granular capabilities introduced by Flavor Platform.
	 *
	 * @return string[]
	 */
	public static function caps(): array {
		return array(
			'flavor_manage_platform',
			'flavor_manage_settings',
			'flavor_manage_all_branches',
			'flavor_manage_branch',
			'flavor_manage_kitchen',
			'flavor_manage_reservations',
			'flavor_create_phone_order',
			'flavor_take_table_order',
			'flavor_confirm_payment',
			'flavor_view_reports',
			'flavor_view_analytics',
			'flavor_manage_loyalty',
			'flavor_manage_sms',
			'flavor_manage_webhooks',
			'flavor_manage_staff',
		);
	}

	/**
	 * Register restaurant roles and grant capability sets.
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

		// 1. Restaurant Owner (مالک رستوران)
		add_role(
			self::OWNER,
			__( 'مالک رستوران (Owner)', 'flavor-core' ),
			array_merge(
				array(
					'read'         => true,
					'upload_files' => true,
					'edit_posts'   => true,
				),
				array_fill_keys( self::caps(), true )
			)
		);

		// 2. General Operations Manager (مدیر کل عملیاتی)
		add_role(
			self::GENERAL_MANAGER,
			__( 'مدیر کل رستوران (General Manager)', 'flavor-core' ),
			array(
				'read'                       => true,
				'upload_files'               => true,
				'flavor_manage_all_branches' => true,
				'flavor_manage_branch'       => true,
				'flavor_manage_kitchen'      => true,
				'flavor_manage_reservations' => true,
				'flavor_create_phone_order'  => true,
				'flavor_take_table_order'    => true,
				'flavor_confirm_payment'     => true,
				'flavor_view_reports'        => true,
				'flavor_view_analytics'      => true,
				'flavor_manage_loyalty'      => true,
				'flavor_manage_sms'          => true,
				'flavor_manage_staff'        => true,
			)
		);

		// 3. Branch Manager (مدیر شعبه)
		add_role(
			self::BRANCH_MANAGER,
			__( 'مدیر شعبه (Branch Manager)', 'flavor-core' ),
			array(
				'read'                        => true,
				'upload_files'                => true,
				'flavor_manage_branch'        => true,
				'flavor_manage_kitchen'       => true,
				'flavor_manage_reservations'  => true,
				'flavor_create_phone_order'   => true,
				'flavor_take_table_order'     => true,
				'flavor_confirm_payment'      => true,
				'flavor_view_reports'         => true,
				'flavor_view_analytics'       => true,
				'flavor_manage_loyalty'       => true,
				'edit_flavor_branches'        => true,
				'publish_flavor_branches'     => true,
			)
		);

		// 4. Cashier (صندوق‌دار)
		add_role(
			self::CASHIER,
			__( 'صندوق‌دار (Cashier)', 'flavor-core' ),
			array(
				'read'                       => true,
				'flavor_manage_kitchen'      => true,
				'flavor_create_phone_order'  => true,
				'flavor_confirm_payment'     => true,
				'flavor_manage_reservations' => true,
				'flavor_take_table_order'    => true,
			)
		);

		// 5. Kitchen Chef (سرآشپز و پرسنل آشپزخانه)
		add_role(
			self::KITCHEN,
			__( 'پرسنل آشپزخانه (Kitchen Staff)', 'flavor-core' ),
			array(
				'read'                  => true,
				'flavor_manage_kitchen' => true,
			)
		);

		// 6. Waiter / Floor Staff (گارسون و سالن‌دار)
		add_role(
			self::WAITER,
			__( 'گارسون و سالن‌دار (Waiter / Floor Staff)', 'flavor-core' ),
			array(
				'read'                    => true,
				'flavor_take_table_order' => true,
				'flavor_manage_kitchen'   => true,
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
	 * Administrators / Owners / Users with flavor_manage_all_branches get every branch.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public static function managed_branch_ids( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		if ( user_can( $user, 'flavor_manage_all_branches' ) || user_can( $user, 'manage_options' ) || in_array( self::OWNER, (array) $user->roles, true ) ) {
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
	 * Assign branches to a staff member.
	 *
	 * @param int   $user_id User ID.
	 * @param int[] $branch_ids Branch IDs.
	 */
	public static function set_user_branches( int $user_id, array $branch_ids ): void {
		$clean = array_values( array_unique( array_filter( array_map( 'absint', $branch_ids ) ) ) );
		update_user_meta( $user_id, '_flavor_managed_branches', $clean );
	}

	/**
	 * Whether the user may touch a given branch.
	 */
	public static function can_access_branch( int $user_id, int $branch_id ): bool {
		if ( ! $branch_id ) {
			return true;
		}
		return in_array( $branch_id, self::managed_branch_ids( $user_id ), true );
	}
}
