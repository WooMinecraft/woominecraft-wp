<?php

namespace WooMinecraft\Orders\Cache;

/**
 * Sets up all the things related to Order cache handling.
 */
function setup() {
	$n = function ( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};

	add_action( 'save_post', $n( 'bust_command_cache' ) );
}

/**
 * Helper method for transient busting
 *
 * @param string $server_key
 */
function bust_command_cache( $server_key ) {
	wp_cache_delete( $server_key, 'wmc_commands' );
}
