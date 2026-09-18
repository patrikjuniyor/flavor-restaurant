<?php
/**
 * Admin handler: Mobile Application White-label Dashboard.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Admin;

use FlavorCore\Mobile\BuildManager;
use FlavorCore\Mobile\MobileConfigManager;

defined( 'ABSPATH' ) || exit;

/**
 * Class MobileAppAdmin
 */
class MobileAppAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register submenu under Flavor Core.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'flavor-core',
			__( 'اپلیکیشن موبایل', 'flavor-core' ),
			__( 'اپلیکیشن موبایل', 'flavor-core' ),
			'manage_options',
			'flavor-mobile-app',
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue WP media and color picker on this page.
	 *
	 * @param string $hook Admin hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'flavor-mobile-app' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Handle admin post actions.
	 */
	public function handle_form_submissions(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( (string) ( $_POST['flavor_action'] ?? '' ) );

		if ( 'save_mobile_config' === $action ) {
			check_admin_referer( 'flavor_mobile_save_config', 'flavor_mobile_config_nonce' );

			$fields = array(
				'app_name',
				'restaurant_id',
				'package_name',
				'bundle_id',
				'app_icon_url',
				'splash_image_url',
				'primary_color',
				'secondary_color',
				'accent_color',
				'default_branch_id',
				'support_phone',
				'support_email',
				'privacy_policy_url',
				'terms_url',
			);

			$data = array();
			foreach ( $fields as $f ) {
				if ( isset( $_POST[ $f ] ) ) {
					$data[ $f ] = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
				}
			}

			MobileConfigManager::update( $data );
			add_settings_error( 'flavor_mobile_messages', 'config_saved', __( 'تنظیمات برندینگ اپلیکیشن با موفقیت ذخیره شد.', 'flavor-core' ), 'success' );
		}

		if ( 'trigger_mobile_build' === $action ) {
			check_admin_referer( 'flavor_mobile_trigger_build', 'flavor_mobile_build_nonce' );

			$platform = sanitize_key( (string) ( $_POST['build_platform'] ?? 'all' ) );
			$env      = sanitize_key( (string) ( $_POST['build_environment'] ?? 'prod' ) );

			if ( ! empty( $_POST['version_name'] ) || ! empty( $_POST['version_code'] ) ) {
				$v_data = array();
				if ( ! empty( $_POST['version_name'] ) ) {
					$v_data['version_name'] = sanitize_text_field( wp_unslash( $_POST['version_name'] ) );
				}
				if ( ! empty( $_POST['version_code'] ) ) {
					$v_data['version_code'] = absint( $_POST['version_code'] );
				}
				MobileConfigManager::update( $v_data );
			}

			$res = BuildManager::trigger_build( $platform, $env, get_current_user_id() );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'flavor_mobile_messages', 'build_failed', $res->get_error_message(), 'error' );
			} else {
				add_settings_error( 'flavor_mobile_messages', 'build_triggered', __( 'درخواست بیلد با موفقیت ایجاد گردید و به خط لوله CI ارسال شد.', 'flavor-core' ), 'success' );
			}
		}
	}

	/**
	 * Render the view.
	 */
	public function render(): void {
		settings_errors( 'flavor_mobile_messages' );
		$view = FLAVOR_CORE_PATH . 'admin/views/mobile-app-dashboard.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}
}
