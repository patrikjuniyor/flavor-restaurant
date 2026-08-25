<?php
/**
 * Pretty URLs owned by the plugin (not the theme).
 *
 *  /branch/{slug}/table/{number}/   → dine-in session
 *  /kitchen-dashboard/              → kitchen kiosk
 *
 * @package FlavorCore
 */

namespace FlavorCore\Support;

use FlavorCore\Order\OrderModes;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Table\TableRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Rewrites
 */
class Rewrites {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'dispatch' ) );
	}

	/**
	 * Rewrite rules.
	 */
	public function rules(): void {
		add_rewrite_rule(
			'^kitchen-dashboard/?$',
			'index.php?flavor_kitchen=1',
			'top'
		);
		add_rewrite_rule(
			'^branch/([^/]+)/table/([^/]+)/?$',
			'index.php?flavor_branch_slug=$matches[1]&flavor_table_number=$matches[2]',
			'top'
		);
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'flavor_kitchen';
		$vars[] = 'flavor_branch_slug';
		$vars[] = 'flavor_table_number';
		return $vars;
	}

	/**
	 * Front-controller for plugin routes.
	 */
	public function dispatch(): void {
		if ( get_query_var( 'flavor_kitchen' ) ) {
			$this->kitchen();
			return;
		}

		$slug  = (string) get_query_var( 'flavor_branch_slug' );
		$table = (string) get_query_var( 'flavor_table_number' );
		if ( $slug && $table ) {
			$this->bind_table( $slug, $table );
		}
	}

	/**
	 * Bind QR landing to the session and send the guest to the menu.
	 */
	private function bind_table( string $slug, string $table_number ): void {
		$branch = get_page_by_path( $slug, OBJECT, BranchPostType::POST_TYPE );
		if ( ! $branch ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$match = null;
		foreach ( TableRepository::for_branch( (int) $branch->ID ) as $row ) {
			if ( (string) $row['table_number'] === $table_number && (int) $row['is_active'] ) {
				$match = $row;
				break;
			}
		}

		$ctx = OrderModes::get();
		$ctx['branch_id']    = (int) $branch->ID;
		$ctx['order_mode']   = 'dine_in';
		$ctx['table_number'] = $table_number;
		if ( $match ) {
			$ctx['table_id']    = (int) $match['id'];
			$ctx['table_token'] = (string) $match['qr_token'];
		}
		OrderModes::set( $ctx );

		$menu = get_page_by_path( 'menu' );
		$dest = $menu ? get_permalink( $menu ) : home_url( '/' );
		wp_safe_redirect( $dest );
		exit;
	}

	/**
	 * Kitchen kiosk. Capability-gated. Theme may skin via flavor_kitchen_dashboard hook.
	 */
	private function kitchen(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'flavor_manage_kitchen' ) ) {
			auth_redirect();
			exit;
		}

		status_header( 200 );
		nocache_headers();

		$plugin_view = FLAVOR_CORE_PATH . 'admin/views/kitchen-dashboard.php';

		/**
		 * Allow the theme to replace the kitchen markup (skin only — still plugin data).
		 *
		 * @param string $plugin_view Default view path.
		 */
		$view = apply_filters( 'flavor_core_kitchen_view', $plugin_view );
		if ( is_readable( $view ) ) {
			include $view;
			exit;
		}

		wp_die( esc_html__( 'نمای داشبورد آشپزخانه پیدا نشد.', 'flavor-core' ) );
	}
}
