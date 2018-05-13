<?php

/**
 * Auto Complete all WooCommerce orders.
 *
 * @link https://docs.woocommerce.com/document/automatically-complete-orders/
 */
add_action( 'woocommerce_thankyou', 'custom_woocommerce_auto_complete_order' );
function custom_woocommerce_auto_complete_order( $order_id ) {
	if ( ! $order_id ) {
		return;
	}

	$order = wc_get_order( $order_id );
	$order->update_status( 'completed' );
}