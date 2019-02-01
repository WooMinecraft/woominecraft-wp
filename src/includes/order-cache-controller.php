<?php

namespace WooMinecraft\Orders\Cache;

/**
 * Sets up all the things related to Order cache handling.
 */
function setup() {
	$n = function( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};

	add_action( 'save_post', $n( 'bust_command_cache' ) );
}

/**
 * Helper method for transient busting
 *
 * @param int $post_id
 */
function bust_command_cache( $post_id = 0 ) {
	global $wpdb;

	if ( ! empty( $post_id ) && 'shop_order' !== get_post_type( $post_id ) ) {
		return;
	}

	$keys = $wpdb->get_col( $wpdb->prepare( "select distinct option_name from {$wpdb->options} where option_name like '%s'", '%' . get_command_transient() . '%' ) ); // @codingStandardsIgnoreLine Have to use this.
	if ( ! $keys ) {
		return;
	}

	foreach ( $keys as $key ) {
		$key = str_replace( '_transient_', '', $key );
		delete_transient( $key );
	}
}

/**
 * Creates a transient based on the wmc_key variable
 *
 * @param string $server_key
 *
 * @since 1.3.0 Rest API implementation.
 *
 * @return string|false The key on success, false if no GET param can be found.
 */
function get_transient_key( $server_key = '' ) {
	if ( empty( $server_key ) ) {
		$server_key = isset( $_GET['wmc_key'] ) ? $_GET['wmc_key'] : '';
	}

	$server_key = sanitize_text_field( $server_key );
	if ( empty( $server_key ) ) {
		return false;
	}

	return get_command_transient() . '_' . $server_key;
}

/**
 * The command transient base key.
 *
 * @since 1.3.0
 *
 * @return string
 */
function get_command_transient() {
	return 'wmc-transient-command-feed';
}
