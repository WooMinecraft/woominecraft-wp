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
	// Assume $data is cart contents
	foreach ( $items as $item ) {
		$post_id = $item['product_id'];

		if ( ! empty( $item['variation_id'] ) ) {
			$post_id = $item['variation_id'];
		}

		$has_command = get_post_meta( $post_id, 'wmc_commands', true );
		if ( empty( $has_command ) ) {
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
 * @return string
 */
function get_order_query_params( $server ) {
	return apply_filters(
		'woo_minecraft_json_orders_args',
		array(
			'posts_per_page' => '-1',
			'post_status'    => 'wc-completed',
			'post_type'      => 'shop_order',
			'meta_query'     => array(
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

/**
 * Conditional to determine enabling debugging within the code.
 */
function is_debug() {
	return ( defined( 'WMC_DEBUG' ) && WMC_DEBUG );
}
