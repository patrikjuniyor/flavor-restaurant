<?php
/**
 * REST + admin-ajax endpoints for the smart menu search.
 *
 * Routes (namespace flavor/v1):
 *   GET /search            → ranked results, facets, did-you-mean
 *   GET /search/suggest    → autocomplete terms
 *   GET /search/popular    → most searched terms
 *
 * Legacy AJAX (for cached pages / no-REST hosts):
 *   admin-ajax.php?action=flavor_search
 *
 * @package FlavorCore
 */

namespace FlavorCore\API;

use FlavorCore\Search\SearchIndex;
use FlavorCore\Search\SmartSearch;
use FlavorCore\Support\RateLimit;

defined( 'ABSPATH' ) || exit;

/**
 * Class RestSearch
 */
class RestSearch {

	/**
	 * Wire up REST + admin-ajax.
	 */
	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
		add_action( 'wp_ajax_flavor_search', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_flavor_search', array( $this, 'ajax' ) );
	}

	/**
	 * Register routes.
	 */
	public function register(): void {
		$ns = FLAVOR_CORE_REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => $this->args(),
			)
		);

		register_rest_route(
			$ns,
			'/search/suggest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'suggest' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'         => array(
						'type'    => 'string',
						'default' => '',
					),
					'branch_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/search/popular',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'popular' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Shared argument schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function args(): array {
		return array(
			'q'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'branch_id' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'limit'     => array(
				'type'    => 'integer',
				'default' => 10,
			),
			'category'  => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'dietary'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'min_price' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'max_price' => array(
				'type'    => 'integer',
				'default' => 0,
			),
			'available' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * GET /search
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function search( \WP_REST_Request $request ) {
		$guard = RateLimit::guard( 'search', 90, MINUTE_IN_SECONDS );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$payload = $this->run( $this->params_from_request( $request ) );

		$response = rest_ensure_response( $payload );
		$response->header( 'Cache-Control', 'public, max-age=30' );

		return $response;
	}

	/**
	 * GET /search/suggest
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function suggest( \WP_REST_Request $request ) {
		$guard = RateLimit::guard( 'search_suggest', 120, MINUTE_IN_SECONDS );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$terms = SmartSearch::suggest(
			sanitize_text_field( (string) $request->get_param( 'q' ) ),
			(int) $request->get_param( 'branch_id' )
		);

		return rest_ensure_response( array( 'terms' => $terms ) );
	}

	/**
	 * GET /search/popular
	 *
	 * @return \WP_REST_Response
	 */
	public function popular() {
		return rest_ensure_response( array( 'terms' => SmartSearch::popular( 8 ) ) );
	}

	/**
	 * admin-ajax fallback.
	 */
	public function ajax(): void {
		if ( ! RateLimit::allow( 'search_ajax', 90, MINUTE_IN_SECONDS ) ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست بیش از حد است.', 'flavor-core' ) ), 429 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public read-only search.
		$params = array(
			'q'         => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'branch_id' => isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : 0,
			'limit'     => isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 10,
			'category'  => isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0,
			'dietary'   => isset( $_GET['dietary'] ) ? sanitize_text_field( wp_unslash( $_GET['dietary'] ) ) : '',
			'min_price' => isset( $_GET['min_price'] ) ? absint( $_GET['min_price'] ) : 0,
			'max_price' => isset( $_GET['max_price'] ) ? absint( $_GET['max_price'] ) : 0,
			'available' => ! empty( $_GET['available'] ),
		);
		// phpcs:enable

		wp_send_json_success( $this->run( $params ) );
	}

	/**
	 * Normalise a REST request into the internal parameter array.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function params_from_request( \WP_REST_Request $request ): array {
		return array(
			'q'         => sanitize_text_field( (string) $request->get_param( 'q' ) ),
			'branch_id' => (int) $request->get_param( 'branch_id' ),
			'limit'     => (int) $request->get_param( 'limit' ),
			'category'  => (int) $request->get_param( 'category' ),
			'dietary'   => sanitize_text_field( (string) $request->get_param( 'dietary' ) ),
			'min_price' => (int) $request->get_param( 'min_price' ),
			'max_price' => (int) $request->get_param( 'max_price' ),
			'available' => (bool) $request->get_param( 'available' ),
		);
	}

	/**
	 * Execute a search and log the term.
	 *
	 * @param array<string, mixed> $params Parameters.
	 * @return array<string, mixed>
	 */
	private function run( array $params ): array {
		$dietary = array_filter( array_map( 'sanitize_key', explode( ',', (string) $params['dietary'] ) ) );

		$payload = SmartSearch::search(
			(string) $params['q'],
			array(
				'branch_id'      => SearchIndex::resolve_branch( (int) $params['branch_id'] ),
				'limit'          => (int) $params['limit'],
				'category'       => (int) $params['category'],
				'dietary'        => $dietary,
				'min_price'      => (int) $params['min_price'],
				'max_price'      => (int) $params['max_price'],
				'only_available' => (bool) $params['available'],
			)
		);

		SmartSearch::log( (string) $params['q'], (int) $payload['total'] );

		return $payload;
	}
}
