<?php
/**
 * GDPR-style export / erase for Flavor customer data.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Class Privacy
 */
class Privacy {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	/**
	 * @param array<string, mixed> $exporters Exporters.
	 * @return array<string, mixed>
	 */
	public function exporters( array $exporters ): array {
		$exporters['flavor-core'] = array(
			'exporter_friendly_name' => __( 'رستوران مستقیم', 'flavor-core' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * @param array<string, mixed> $erasers Erasers.
	 * @return array<string, mixed>
	 */
	public function erasers( array $erasers ): array {
		$erasers['flavor-core'] = array(
			'eraser_friendly_name' => __( 'رستوران مستقیم', 'flavor-core' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export by email (WP user).
	 *
	 * @param string $email Email.
	 * @return array<string, mixed>
	 */
	public function export( string $email ): array {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user ) {
			$mobile = (string) get_user_meta( $user->ID, OtpAuth::META_MOBILE, true );
			$data[] = array(
				'name'  => __( 'موبایل', 'flavor-core' ),
				'value' => $mobile,
			);
			$data[] = array(
				'name'  => __( 'امتیاز وفاداری', 'flavor-core' ),
				'value' => (string) \FlavorCore\Loyalty\PointsManager::balance( $user->ID ),
			);
		}
		return array(
			'data' => array(
				array(
					'group_id'    => 'flavor-core',
					'group_label' => __( 'رستوران مستقیم', 'flavor-core' ),
					'item_id'     => 'user',
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	/**
	 * Anonymize Flavor meta; keep financial WC orders.
	 *
	 * @param string $email Email.
	 * @return array<string, mixed>
	 */
	public function erase( string $email ): array {
		$user    = get_user_by( 'email', $email );
		$removed = false;
		if ( $user ) {
			delete_user_meta( $user->ID, OtpAuth::META_MOBILE );
			delete_user_meta( $user->ID, '_flavor_birthday' );
			delete_user_meta( $user->ID, '_flavor_free_item' );
			delete_user_meta( $user->ID, '_flavor_just_created' );
			global $wpdb;
			$wpdb->delete( Schema::table( 'flavor_otp_codes' ), array( 'mobile' => $user->user_login ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$removed = true;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
