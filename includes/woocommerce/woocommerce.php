<?php

namespace WooMinecraft\WooCommerce;
use WC_Order;
use WooCommerce;
use WP_Post;

/**
 * The Main class for WooCommerce functionality
 *
 * @since 2.0 Rest API integration.
 * @package WooMinecraft-WP
 */

class WCM_WooCommerce {

	/**
	 * @var \WooMinecraft\WooMinecraft
	 */
	protected $plugin;

	/**
	 * WCM_WooCommerce constructor.
	 *
	 * @param \WooMinecraft\WooMinecraft $plugin
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->hooks();
	}

	public function hooks() {
		add_action( 'woocommerce_checkout_process', array( $this, 'check_player' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_commands_to_order' ) );
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'additional_checkout_field' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'thanks' ) );
	}

	/**
	 * Adds the Minecraft username to the thank you page.
	 *
	 * @param integer $id The order ID.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public function thanks( $id ) {
		$player_name = get_post_meta( $id, 'player_id', true );
		if ( ! empty( $player_name ) ) {
			?>
			<div class="woo_minecraft"><h4><?php esc_attr_e( 'Minecraft Details', 'woominecraft' ); ?></h4>

			<p><strong><?php esc_attr_e( 'Username:', 'woominecraft' ); ?></strong><?php echo $player_name ?></p></div><?php
		}
	}

	/**
	 * Updates an order's meta data with the commands hash.
	 *
	 * @param integer $order_id The order to save command data to.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function save_commands_to_order( $order_id ) {

		$order_data = wc_get_order( $order_id );
		if ( ! $order_data ) {
			return;
		}

		$items      = $order_data->get_items();
		$tmp_array  = array();

		if ( ! isset( $_POST['player_id'] ) || empty( $_POST['player_id'] ) ) {
			return;
		}

		$player_name = esc_attr( $_POST['player_id'] );
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
	 * Checks if Minecraft Username is valid
	 *
	 * @return void
	 *
	 * @TODO Use the default WC() function instead of grabbing the global here.
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function check_player() {
		global $woocommerce;

		if ( ! $woocommerce instanceof WooCommerce ) {
			return;
		}

		$player_id = isset( $_POST['player_id'] ) ? esc_attr( $_POST['player_id'] ) : false;
		$items     = $woocommerce->cart->cart_contents;

		if ( ! \WooMinecraft\wmc_items_have_commands( $items ) ) {
			return;
		}

		if ( ! $player_id ) {
			wc_add_notice( __( 'You MUST provide a Minecraft username.', 'woominecraft' ), 'error' );

			return;
		}

		// Grab JSON data
		$mc_json = $this->plugin->mojang_player_cache( $player_id );
		if ( ! $mc_json ) {
			wc_add_notice( __( 'We cannot retrieve your account from the Mojang API. Try again later, or contact an administrator.', 'woominecraft' ), 'error' );
		}

		if ( isset( $mc_json->demo ) ) {
			wc_add_notice( __( 'We do not allow unpaid-accounts to make donations, sorry!', 'woominecraft' ), 'error' );

			return;
		}
	}

	/**
	 * Adds a field to the checkout form, requiring the user to enter their Minecraft Name
	 *
	 * @param object $cart WooCommerce Cart Object.
	 *
	 * @return boolean  False on failure, true otherwise.
	 *
	 * @TODO If $cart is passed into this function, why access the $woocommerce global at all???
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function additional_checkout_field( $cart ) {
		global $woocommerce;

		$items = $woocommerce->cart->cart_contents;
		if ( ! \WooMinecraft\wmc_items_have_commands( $items ) || ! function_exists( 'woocommerce_form_field' ) ) {
			return false;
		}

		?>
		<div id="woo_minecraft"><?php
		woocommerce_form_field( 'player_id', array(
			'type'        => 'text',
			'class'       => array(),
			'label'       => __( 'Player ID ( Minecraft Username ):', 'woominecraft' ),
			'placeholder' => __( 'Required Field', 'woominecraft' ),
		), $cart->get_value( 'player_id' ) );
		?></div><?php

		return true;
	}

	/**
	 * Generates the order JSON data for a single order.
	 *
	 * @param WP_Post $order_post The post to get the commands from.
	 * @param string  $key        The server key to pluck orders with.
	 *
	 * @return array|mixed
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public function generate_order_json( WP_Post $order_post, $key ) {

		if ( ! isset( $order_post->ID ) ) {
			return array();
		}

		$general_commands = get_post_meta( $order_post->ID, '_wmc_commands_' . $key, true );

		return $general_commands;
	}

	/**
	 * Resets an order from being delivered.
	 *
	 * @param integer $order_id   The order ID.
	 * @param string  $server_key The server key.
	 *
	 * @return boolean
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function reset_order( $order_id, $server_key ) {
		delete_post_meta( $order_id, '_wmc_delivered_' . $server_key );
		$this->plugin->bust_command_cache( $order_id );

		return true;
	}

	/**
	 * Processes all completed commands.
	 *
	 * @param string $key The server key to check against.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	private function process_completed_commands( $key = '' ) {
		$delivered = '_wmc_delivered_' . $key;
		$order_ids = (array) $this->plugin->sanitized_orders_post( $_POST['processedOrders'] );

		if ( empty( $order_ids ) ) {
			wp_send_json_error( array( 'msg' => __( 'Commands was empty', 'woominecraft' ) ) );
		}

		// Set the orders to delivered
		foreach ( $order_ids as $order_id ) {
			update_post_meta( $order_id, $delivered, true );
		}

		$this->plugin->bust_command_cache();
	}
}