<?php

namespace WooMinecraft\REST;

use function WooMinecraft\Helpers\get_meta_key_delivered;
use function WooMinecraft\Orders\Cache\bust_command_cache;
use function WooMinecraft\Orders\Manager\get_orders_for_server;

/**
 * Holds the REST API endpoint information.
 */

/**
 * Sets up all the things related to REST API.
 */
function setup() {
	$n = function ( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};

	add_action( 'rest_api_init', $n( 'register_endpoints' ) );
}

function get_rest_namespace() {
	return 'wmc/v1';
}

function register_endpoints() {
	register_rest_route(
		get_rest_namespace(),
		'/server/(?P<server>[\S]+)',
		[
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_pending_orders',
			'permission_callback' => '__return_true',
		]
	);

	register_rest_route(
		get_rest_namespace(),
		'/server/(?P<server>[\S]+)',
		[
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => __NAMESPACE__ . '\\process_orders',
			'permission_callback' => '__return_true',
		]
	);
}

/**
 * Gets orders which have not been delivered yet.
 *
 * @param \WP_REST_Request $request The request object.
 *
 * @return \WP_Error|array Error on failure, orders on success.
 */
function get_pending_orders( $request ) {
	$server_key = esc_attr( $request->get_param( 'server' ) );
	$servers    = get_option( 'wm_servers', [] );
	if ( empty( $servers ) ) {
		return new \WP_Error( 'no_servers', 'No servers setup, check WordPress config.', [ 'status' => 200 ] );
	}

	// Check key, send 401 unauthorized if necessary.
	$keys = wp_list_pluck( $servers, 'key' );
	if ( false === array_search( $server_key, $keys, true ) ) {
		return new \WP_Error( 'invalid_key', 'Key provided in request is invalid.', [ 'status' => 200 ] );
	}

	$pending_orders = wp_cache_get( $server_key, 'wmc_commands' );
	if ( false === $pending_orders ) {
		$pending_orders = get_orders_for_server( $server_key );
		if ( is_wp_error( $pending_orders ) ) {
			return $pending_orders;
		}
		wp_cache_set( $server_key, $pending_orders, 'wmc_commands', 1 * HOUR_IN_SECONDS );
	}

	return [ 'orders' => $pending_orders ];
}

/**
 * Take special care to sanitize the incoming post data.
 *
 * While not as simple as running esc_attr, this is still necessary since the JSON
 * from the Java code comes in escaped, so we need some custom sanitization.
 *
 * @param string|array $post_data The post data.
 * @return int[] An array of order IDs or an empty array.
 */
function sanitized_orders_post( $post_data ) {
	if ( is_string( $post_data ) ) {
		$post_data = json_decode( stripslashes( urldecode( $post_data ) ) );
		if ( empty( $post_data ) ) {
			return array();
		}
	}

	return array_map( 'intval', $post_data );
}

/**
 * Processes orders sent to the endpoint.
 *
 * @param \WP_REST_Request $request
 *
 * @return \WP_Error|array OK status on success, error if needed.
 */
function process_orders( $request ) {
	// Get and validate the server key.
	$server_key = esc_attr( $request->get_param( 'server' ) );
	$servers    = get_option( 'wm_servers', [] );
	if ( empty( $servers ) ) {
		return new \WP_Error( 'no_servers', 'No servers setup, check WordPress config.', [ 'status' => 200 ] );
	}

	// Check key, send 401 unauthorized if necessary.
	$keys = wp_list_pluck( $servers, 'key' );
	if ( false === array_search( $server_key, $keys, true ) ) {
		return new \WP_Error( 'invalid_key', 'Key provided in request is invalid.', [ 'status' => 200 ] );
	}

	$body_params = $request->get_json_params();
	$bad_request = new \WP_Error( 'bad_request', 'Empty post data.', [ 'status' => 200 ] );
	if ( empty( $body_params['processedOrders'] ) ) {
		return $bad_request;
	}

	// Get order data.
	$orders = (array) sanitized_orders_post( $body_params['processedOrders'] );
	if ( empty( $orders ) ) {
		return $bad_request;
	}

	foreach ( $orders as $order_id ) {
		update_post_meta( $order_id, get_meta_key_delivered( $server_key ), true );
	}

	bust_command_cache( $server_key );

	return [ 'status' => 'ok' ];
}

