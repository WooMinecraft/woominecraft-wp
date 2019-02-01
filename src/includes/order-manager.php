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
	add_action( 'woocommerce_before_checkout_billing_form', $n( 'additional_checkout_field' ) );
	add_action( 'woocommerce_thankyou', $n( 'thanks' ) );
}

/**
 * Updates an order's meta data with the commands hash.
 *
 * @param int $order_id A WooCommerce order ID.
 */
function save_commands_to_order( $order_id ) {

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

/**
 * Adds a field to the checkout form, requiring the user to enter their Minecraft Name
 *
 * @param object $cart WooCommerce Cart Object
 *
 * @return bool  False on failure, true otherwise.
 */
function additional_checkout_field( $cart ) {
	global $woocommerce;

	$items = $woocommerce->cart->cart_contents;
	if ( ! \WooMinecraft\Helpers\wmc_items_have_commands( $items ) || ! function_exists( 'woocommerce_form_field' ) ) {
		return false;
	}

	?>
	<div id="woo_minecraft">
		<?php
		woocommerce_form_field( 'player_id', array(
			'type'        => 'text',
			'class'       => array(),
			'label'       => __( 'Player ID ( Minecraft Username ):', 'woominecraft' ),
			'placeholder' => __( 'Required Field', 'woominecraft' ),
		), $cart->get_value( 'player_id' ) );
		?>
	</div>
	<?php

	return true;
}

/**
 * Adds the minecraft order details to the thank you page.
 *
 * @param int $id The order ID.
 */
function thanks( $id ) {
	$player_name = get_post_meta( $id, 'player_id', true );
	if ( ! empty( $player_name ) ) {
		?>
		<div class="woo_minecraft"><h4><?php esc_html_e( 'Minecraft Details', 'woominecraft' ); ?></h4>

		<p><strong><?php esc_html_e( 'Username:', 'woominecraft' ); ?></strong><?php echo esc_html( $player_name ); ?></p></div><?php
	}
}

/**
 * Resets an order from being delivered.
 *
 * @param int $order_id
 * @param string $server_key
 *
 * @return bool
 */
function reset_order( $order_id, $server_key ) {
	delete_post_meta( $order_id, '_wmc_delivered_' . $server_key );
	$this->bust_command_cache( $order_id );

	return true;
}
