<?php

namespace WooMinecraft\Orders\Manager;

/**
 * Sets up all the things related to Order handling.
 */
function setup() {
	$n = function( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};

	add_action( 'woocommerce_checkout_update_order_meta', $n( 'save_commands_to_order' ) );
}

/**
 * Updates an order's meta data with the commands hash.
 *
 * @param int $order_id A WooCommerce order ID.
 */
public function save_commands_to_order( $order_id ) {

	$order_data = new \WC_Order( $order_id );
	$items      = $order_data->get_items();
	$tmp_array  = array();

	if ( ! isset( $_POST['player_id'] ) || empty( $_POST['player_id'] ) ) { // @codingStandardsIgnoreLine No nonce needed.
		return;
	}

	$player_name = sanitize_text_field( $_POST['player_id'] ); // @codingStandardsIgnoreLine No nonce needed.
	update_post_meta( $order_id, 'player_id', $player_name );

	foreach ( $items as $item ) {

		// If this is a variable product, use that meta, otherwise check for the product data and use it.
		$product_id = isset( $item['variation_id'] ) && ! empty( $item['variation_id'] ) ? absint( $item['variation_id'] ) : absint( $item['product_id'] );
		if ( empty( $product_id ) ) {
			continue;
		}

		$item_commands = get_post_meta( $product_id, 'wmc_commands', true );
		if ( empty( $item_commands ) ) {
			continue;
		}

		// Loop over the command set for every 1 qty of the item.
		$qty = absint( $item['qty'] );
		for ( $n = 0; $n < $qty; $n ++ ) {
			foreach ( $item_commands as $server_key => $command ) {
				if ( ! isset( $tmp_array[ $server_key ] ) ) {
					$tmp_array[ $server_key ] = array();
				}

				if ( is_array( $command ) ) {
					foreach ( $command as $c ) {
						$tmp_array[ $server_key ][] = sprintf( $c, $player_name );
					}
				} else {
					$tmp_array[ $server_key ][] = sprintf( $command, $player_name );
				}
			}
		}
	}

	if ( ! empty( $tmp_array ) ) {
		foreach ( $tmp_array as $server_key => $commands ) {
			update_post_meta( $order_id, '_wmc_commands_' . $server_key, $commands );
		}
	}
}