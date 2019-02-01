<?php

namespace WooMinecraft\REST;

/**
 * Holds the REST API endpoint information.
 */

/**
 * Sets up all the things related to REST API.
 */
function setup() {
	$n = function( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};

	add_action( 'rest_api_init', $n( 'register_endpoints' ) );
}

function get_rest_namespace() {
	return 'wmc/v1';
}

function register_endpoints() {
	register_rest_route( get_rest_namespace(), '/server/(?P<server>[\S]+)', [
		'methods'  => \WP_REST_Server::READABLE,
		'callback' => __NAMESPACE__ . '\\get_pending_orders',
	] );
}

/**
 * Gets orders which have not been delivered yet.
 *
 * @param \WP_REST_Request $request The request object.
 *
 * @return \WP_Error|array Error on failure, orders on success.
 */
function get_pending_orders( $request ) {

	// Get and validate the server key.
	$server_key = esc_attr( $request->get_param( 'server' ) );
	$servers    = get_option( 'wm_servers', [] );
	if ( empty( $servers ) ) {
		return new \WP_Error( 'no_servers', 'No servers setup, check WordPress config.', [ 'status' => 500 ] );
	}

	// Check key, send 401 unauthorized if necessary.
	$keys = wp_list_pluck( $servers, 'key' );
	if ( false === array_search( $server_key, $keys, true ) ) {
		return new \WP_Error( 'invalid_key', 'Key provided in request is invalid.', [ 'status' => 401 ] );
	}

	return $request->get_params();
}

