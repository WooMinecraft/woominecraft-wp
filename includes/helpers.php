<?php

namespace WooMinecraft\Helpers;

const WM_SERVERS = 'wm_servers';

/**
 * Sets up all the things related to Order handling.
 */
function setup() {
	$n = function( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};

	add_action( 'template_redirect', $n( 'deprecate_json_feed' ) );
	add_filter( 'woocommerce_get_wp_query_args', $n( 'filter_query' ), 10, 2 );
}

/**
 * Adds meta query capability to the WooCommerce order method.
 * @param $wp_query_args
 * @param $query_vars
 *
 * @return mixed
 */
function filter_query( $wp_query_args, $query_vars ) {
	if ( isset( $query_vars['meta_query'] ) ) {
		$meta_query                  = isset( $wp_query_args['meta_query'] ) ? $wp_query_args['meta_query'] : [];
		$wp_query_args['meta_query'] = array_merge( $meta_query, $query_vars['meta_query'] );
	}
	return $wp_query_args;
}

/**
 * Sends an error to the user.
 *
 * The error lets the user know that the MC version of the plugin is out of date.
 */
function deprecate_json_feed() {
	if ( ! isset( $_GET['wmc_key'] ) ) {
		return;
	}
	wp_send_json_error( [ 'msg' => esc_html__( 'You are using an older version, please update your Minecraft plugin.', 'woominecraft' ) ] );
}

/**
 * Determines if any item in the cart has WMC commands attached.
 *
 * @param array $items Cart contents from WooCommerce
 *
 * @return bool
 */
function wmc_items_have_commands( array $items ) {
	foreach ( $items as $item ) {
		$post_id = $item['product_id'];

		if ( ! empty( $item['variation_id'] ) ) {
			$post_id = $item['variation_id'];
		}

		if ( empty( get_post_meta( $post_id, 'wmc_commands', true ) ) ) {
			continue;
		} else {
			return true;
		}
	}

	return false;
}

/**
 * Gets the delivered key for orders.
 * @param string $server
 *
 * @return string
 */
function get_meta_key_delivered( $server ) {
	return '_wmc_delivered_' . $server;
}

/**
 * Gets the pending meta key for orders.
 * @param string $server
 *
 * @return string
 */
function get_meta_key_pending( $server ) {
	return '_wmc_commands_' . $server;
}

/**
 * Gets the query parameters to grab order data.
 *
 * @param string $server The server.
 *
 * @return array
 */
function get_order_query_params( $server ) {
	return apply_filters(
		'woo_minecraft_json_orders_args',
		array(
			'limit'      => '-1',
			'status'     => 'completed',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => get_meta_key_pending( $server ),
					'compare' => 'EXISTS',
				),
				array(
					'key'     => get_meta_key_delivered( $server ),
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
}
