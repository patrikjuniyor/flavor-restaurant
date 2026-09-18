<?php
/**
 * Mobile White-Label Configuration Manager.
 * Handles restaurant branding options, asset URLs, and readiness checklists.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Mobile;

use FlavorCore\PostTypes\BranchPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Class MobileConfigManager
 */
class MobileConfigManager {

	public const OPTION_NAME = 'flavor_mobile_config';

	/**
	 * Default white-label mobile configuration.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'app_name'           => get_bloginfo( 'name' ) ?: 'رستوران طعم',
			'app_identifier'     => 'com.flavor.restaurant',
			'tenant_id'          => sanitize_title( get_bloginfo( 'name' ) ) ?: 'flavor_restaurant',
			'version_name'       => '1.1.0',
			'version_code'       => 101,
			'logo_id'            => 0,
			'icon_id'            => 0,
			'splash_id'          => 0,
			'primary_color'      => '#c8102e',
			'secondary_color'    => '#d97706',
			'font_family'        => 'Vazirmatn',
			'border_radius'      => '12',
			'support_phone'      => (string) get_option( 'admin_email' ),
			'support_email'      => (string) get_option( 'admin_email' ),
			'website_url'        => home_url(),
			'privacy_policy_url' => home_url( '/privacy-policy/' ),
			'terms_url'          => home_url( '/terms/' ),
			'default_branch_id'  => BranchPostType::default_id(),
			'fcm_project_id'     => '',
			'fcm_service_key'    => '',
			'ci_webhook_url'     => '',
			'ci_webhook_secret'  => wp_generate_password( 32, false ),
			'github_repo'        => '',
			'github_token'       => '',
		);
	}

	/**
	 * Get all mobile configuration options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Update mobile configuration.
	 *
	 * @param array<string, mixed> $input Input options.
	 * @return bool
	 */
	public static function update( array $input ): bool {
		$current = self::get_all();
		$clean   = array();

		if ( isset( $input['app_name'] ) ) {
			$clean['app_name'] = sanitize_text_field( $input['app_name'] );
		}
		if ( isset( $input['app_identifier'] ) ) {
			$clean['app_identifier'] = sanitize_text_field( $input['app_identifier'] );
		}
		if ( isset( $input['tenant_id'] ) ) {
			$clean['tenant_id'] = sanitize_title( $input['tenant_id'] );
		}
		if ( isset( $input['version_name'] ) ) {
			$clean['version_name'] = sanitize_text_field( $input['version_name'] );
		}
		if ( isset( $input['version_code'] ) ) {
			$clean['version_code'] = max( 1, absint( $input['version_code'] ) );
		}
		if ( isset( $input['logo_id'] ) ) {
			$clean['logo_id'] = absint( $input['logo_id'] );
		}
		if ( isset( $input['icon_id'] ) ) {
			$clean['icon_id'] = absint( $input['icon_id'] );
		}
		if ( isset( $input['splash_id'] ) ) {
			$clean['splash_id'] = absint( $input['splash_id'] );
		}
		if ( isset( $input['primary_color'] ) ) {
			$clean['primary_color'] = sanitize_hex_color( $input['primary_color'] ) ?: '#c8102e';
		}
		if ( isset( $input['secondary_color'] ) ) {
			$clean['secondary_color'] = sanitize_hex_color( $input['secondary_color'] ) ?: '#d97706';
		}
		if ( isset( $input['font_family'] ) ) {
			$clean['font_family'] = sanitize_text_field( $input['font_family'] );
		}
		if ( isset( $input['border_radius'] ) ) {
			$clean['border_radius'] = sanitize_text_field( $input['border_radius'] );
		}
		if ( isset( $input['support_phone'] ) ) {
			$clean['support_phone'] = sanitize_text_field( $input['support_phone'] );
		}
		if ( isset( $input['support_email'] ) ) {
			$clean['support_email'] = sanitize_email( $input['support_email'] );
		}
		if ( isset( $input['website_url'] ) ) {
			$clean['website_url'] = esc_url_raw( $input['website_url'] );
		}
		if ( isset( $input['privacy_policy_url'] ) ) {
			$clean['privacy_policy_url'] = esc_url_raw( $input['privacy_policy_url'] );
		}
		if ( isset( $input['terms_url'] ) ) {
			$clean['terms_url'] = esc_url_raw( $input['terms_url'] );
		}
		if ( isset( $input['default_branch_id'] ) ) {
			$clean['default_branch_id'] = absint( $input['default_branch_id'] );
		}
		if ( isset( $input['fcm_project_id'] ) ) {
			$clean['fcm_project_id'] = sanitize_text_field( $input['fcm_project_id'] );
		}
		if ( isset( $input['fcm_service_key'] ) ) {
			$clean['fcm_service_key'] = sanitize_textarea_field( $input['fcm_service_key'] );
		}
		if ( isset( $input['ci_webhook_url'] ) ) {
			$clean['ci_webhook_url'] = esc_url_raw( $input['ci_webhook_url'] );
		}
		if ( isset( $input['ci_webhook_secret'] ) && ! empty( $input['ci_webhook_secret'] ) ) {
			$clean['ci_webhook_secret'] = sanitize_text_field( $input['ci_webhook_secret'] );
		}
		if ( isset( $input['github_repo'] ) ) {
			$clean['github_repo'] = sanitize_text_field( $input['github_repo'] );
		}
		if ( isset( $input['github_token'] ) && ! empty( $input['github_token'] ) ) {
			$clean['github_token'] = sanitize_text_field( $input['github_token'] );
		}

		$merged = array_merge( $current, $clean );
		return update_option( self::OPTION_NAME, $merged );
	}

	/**
	 * Build payload consumed by CI (GitHub Actions / Fastlane) to provision the branded mobile build.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_ci_provision_payload(): array {
		$cfg = self::get_all();

		$logo_url   = $cfg['logo_id'] ? wp_get_attachment_image_url( (int) $cfg['logo_id'], 'full' ) : '';
		$icon_url   = $cfg['icon_id'] ? wp_get_attachment_image_url( (int) $cfg['icon_id'], 'full' ) : '';
		$splash_url = $cfg['splash_id'] ? wp_get_attachment_image_url( (int) $cfg['splash_id'], 'full' ) : '';

		return array(
			'app_name'           => $cfg['app_name'],
			'app_identifier'     => $cfg['app_identifier'],
			'tenant_id'          => $cfg['tenant_id'],
			'version_name'       => $cfg['version_name'],
			'version_code'       => (int) $cfg['version_code'],
			'api_base_url'       => rest_url( 'flavor/v1' ),
			'assets'             => array(
				'logo_url'   => $logo_url ?: '',
				'icon_url'   => $icon_url ?: '',
				'splash_url' => $splash_url ?: '',
			),
			'branding'           => array(
				'primary_color'   => $cfg['primary_color'],
				'secondary_color' => $cfg['secondary_color'],
				'font_family'     => $cfg['font_family'],
				'border_radius'   => (float) $cfg['border_radius'],
			),
			'contact'            => array(
				'phone'   => $cfg['support_phone'],
				'email'   => $cfg['support_email'],
				'website' => $cfg['website_url'],
			),
			'legal'              => array(
				'privacy_policy' => $cfg['privacy_policy_url'],
				'terms'          => $cfg['terms_url'],
			),
			'default_branch_id'  => (int) $cfg['default_branch_id'],
			'has_fcm'            => ! empty( $cfg['fcm_project_id'] ),
			'server_timestamp'   => time(),
		);
	}

	/**
	 * Configuration readiness checklist.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_readiness_status(): array {
		$cfg = self::get_all();

		$checks = array(
			'app_name'   => ! empty( $cfg['app_name'] ),
			'app_icon'   => ! empty( $cfg['icon_id'] ),
			'colors'     => ! empty( $cfg['primary_color'] ) && ! empty( $cfg['secondary_color'] ),
			'branches'   => BranchPostType::default_id() > 0,
			'contact'    => ! empty( $cfg['support_phone'] ),
			'ci_webhook' => ! empty( $cfg['github_repo'] ) || ! empty( $cfg['ci_webhook_url'] ),
		);

		$passed = count( array_filter( $checks ) );
		$total  = count( $checks );
		$pct    = (int) round( ( $passed / $total ) * 100 );

		return array(
			'percentage' => $pct,
			'is_ready'   => $pct >= 80,
			'checks'     => $checks,
		);
	}
}
