<?php
/**
 * REST API: Settings & App Bootstrap controller for Mobile & Web clients.
 * Aggregates restaurant config, design tokens, currency, branches, and app flags.
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Iran;
use FlavorCore\Support\Jalali;
use FlavorCore\Support\Settings;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsController
 */
class SettingsController extends BaseApiController {

	/**
	 * Register settings routes.
	 *
	 * @param string|null $namespace Namespace override (defaults to V1).
	 */
	public function register( ?string $namespace = null ): void {
		$ns = $namespace ?: FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/settings/app-bootstrap',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_bootstrap' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /settings/app-bootstrap
	 *
	 * @return \WP_REST_Response
	 */
	public function get_bootstrap(): \WP_REST_Response {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		$logo_url       = $custom_logo_id ? wp_get_attachment_image_url( (int) $custom_logo_id, 'full' ) : '';

		// Get active design preset tokens
		$active_preset = get_theme_mod( 'flavor_design_preset', 'modern_restaurant' );

		// Branches summary
		$branch_ids = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$branches = array();
		foreach ( $branch_ids as $bid ) {
			$b = BranchPostType::to_array( (int) $bid );
			if ( $b ) {
				$branches[] = $b;
			}
		}

		$today_g = current_time( 'Y-m-d' );

		$data = array(
			'app' => array(
				'name'             => get_bloginfo( 'name' ),
				'description'      => get_bloginfo( 'description' ),
				'logo'             => $logo_url ?: '',
				'phone'            => (string) get_theme_mod( 'flavor_footer_phone', '021-12345678' ),
				'instagram'        => (string) get_theme_mod( 'flavor_footer_instagram', '' ),
				'maintenance_mode' => false,
				'min_app_version'  => '1.0.0',
				'current_version'  => FLAVOR_CORE_VERSION,
			),
			'design' => array(
				'preset'        => $active_preset,
				'primary_color' => get_theme_mod( 'flavor_color_primary', '#c8102e' ),
				'accent_color'  => get_theme_mod( 'flavor_color_accent', '#d97706' ),
				'font_family'   => get_theme_mod( 'flavor_font_family', 'iransans' ),
				'border_radius' => get_theme_mod( 'flavor_border_radius', '0.75rem' ),
			),
			'currency' => array(
				'storage_unit'  => Currency::storage_unit(),
				'display_unit'  => Currency::display_unit(),
				'label'         => Currency::display_label(),
				'symbol'        => Currency::display_label(),
				'decimals'      => 0,
			),
			'ordering' => array(
				'modes' => array(
					array( 'id' => 'dine_in', 'label' => __( 'سفارش سر میز (سالن)', 'flavor-core' ), 'icon' => 'utensils' ),
					array( 'id' => 'takeaway', 'label' => __( 'بیرون‌بر / تحویل حضوری', 'flavor-core' ), 'icon' => 'shopping-bag' ),
					array( 'id' => 'delivery', 'label' => __( 'ارسال با پیک اختصاصی', 'flavor-core' ), 'icon' => 'motorcycle' ),
				),
				'guest_checkout' => 'yes' === Settings::get( 'guest_checkout', 'yes' ),
			),
			'auth' => array(
				'otp_length'     => (int) Settings::get( 'otp_length', 5 ),
				'otp_resend_sec' => (int) ( (int) Settings::get( 'otp_ttl_minutes', 2 ) * 60 ),
			),
			'branches'           => $branches,
			'default_branch_id'  => BranchPostType::default_id(),
			'calendar'           => array(
				'gregorian_today' => $today_g,
				'jalali_today'    => Jalali::parse_gregorian( $today_g ),
				'jalali_label'    => Jalali::format( $today_g, true ),
			),
		);

		return $this->respond_success(
			$data,
			array(),
			200,
			array( 'Cache-Control' => 'public, max-age=300' )
		);
	}
}
