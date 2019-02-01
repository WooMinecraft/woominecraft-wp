<?php

namespace WooMinecraft\Helpers;

/**
 * Sets up all the things related to Order handling.
 */
function setup() {
	$n = function( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};
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