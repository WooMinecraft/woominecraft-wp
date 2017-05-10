<?php

namespace WooMinecraft\API;

use WooMinecraft\WooMinecraft;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class WCM_Rest_API
 *
 * This class contains all API related methods and hooks
 *
 * @since   NEXT
 * @author  JayWood
 * @package WooMinecraft\API
 */
class WCM_Rest_API {

	/**
	protected $plugin;

	/**
	 * WCM_Rest constructor.
	 *
	 * @param WooMinecraft $plugin
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->hooks();
	}

	private function hooks() {
		add_action( 'rest_api_init', array( $this, 'rest_setup_routes' ) );
	}

	/**
	 * Sets up REST routes for the WP-API
	 *
	 * @since NEXT
	 *
	 * @author JayWood
	 * @return void
	 */
	public function rest_setup_routes() {

		/*
		 * This registers an endpoint which allows Administrators to view server labels and keys.
		 *
		 * NOTE: Some WordPress installs will need a secondary plugin in order to utilize this endpoint.
		 * @link https://wordpress.org/plugins/oauth2-provider/
		 */
		register_rest_route( 'woominecraft/v1', '/servers', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this->plugin->admin, 'get_servers' ),
			'permission_callback' => array( $this, 'get_server_settings_permission_check' ),
		) );

		register_rest_route( 'woominecraft/v1', '/server/(?P<server_key>[a-zA-Z0-9\@\#\!]+)', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_server_commands' ),
				'args'     => array(
					'server_key' => array(
						'sanitize_callback' => 'esc_attr',
					),
				),
			),
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'process_order_updates' ),
				'args'     => array(
					'server_key' => array(
						'sanitize_callback' => 'esc_attr',
					),
				),
			)
		) );
	}

	/**
	 * Determines rather or not a user can view the list of servers.
	 *
	 * @return boolean|WP_Error True if they can manage_options, WP_Error otherwise.
	 *
	 * @author JayWood
	 * @since  2.0.0
	 */
	public function get_server_settings_permission_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'Only administrators can view server keys.', 'woominecraft' ) );
		}

		return true;
	}

	/**
	 * Processes all order updates
	 *
	 * @param \WP_REST_Request $request The rest request object.
	 *
	 * @return WP_Error|mixed
	 *
	 * @author JayWood
	 * @since  2.0.0
	 */
	public function process_order_updates( WP_REST_Request $request ) {

		$url_params   = $request->get_url_params();
		$server_key   = empty( $url_params['server_key'] ) ? '' : $url_params['server_key'];
		$is_valid_key = $this->plugin->validate_key( $server_key );
		if ( true !== $is_valid_key ) {
			return $is_valid_key;
		}

		$update_data = $request->get_json_params();
		if ( empty( $update_data['order_data'] ) ) {
			$response = rest_ensure_response( esc_html__( 'Order data must be set', 'woominecraft' ) );
			$response->set_status( 400 );
			return $response;
		}

		$order_ids = array_filter( array_map( 'absint', $update_data['order_data'] ) );
		$delivered = '_wmc_delivered_' . $server_key;

		$response = array(
			'msg' => esc_html__( 'Successfully updated orders.', 'woominecraft' ),
		);

		foreach ( $order_ids as $order_id ) {
			if ( 'shop_order' !== get_post_type( $order_id ) ) {
				$response['result']['skipped'][] = $order_id;
				continue;
			}

			$updated = update_post_meta( $order_id, $delivered, true );
			if ( $updated ) {
				$response['result']['processed'][] = $order_id;
			} else {
				$response['result']['skipped'][] = $order_id;
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Retrieves server specific commands for the key provided.
	 *
	 * @param WP_REST_Request $request The rest request object.
	 *
	 * @todo Support paging, and include that in the output.
	 *
	 * @return mixed
	 *
	 * @author JayWood
	 * @since NEXT
	 */
	public function get_server_commands( WP_REST_Request $request ) {

		$url_params   = $request->get_url_params();
		$server_key   = empty( $url_params['server_key'] ) ? '' : $url_params['server_key'];
		$is_valid_key = $this->plugin->validate_key( $server_key );
		if ( true !== $is_valid_key ) {
			return $is_valid_key;
		}

		$delivered = '_wmc_delivered_' . $server_key;
		$meta_key  = '_wmc_commands_' . $server_key;

		$order_query = apply_filters( 'woo_minecraft_json_orders_args', array(
			'posts_per_page' => '-1',
			'post_status'    => 'wc-completed',
			'post_type'      => 'shop_order',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => $delivered,
					'compare' => 'NOT EXISTS',
				),
			),
		) );

		$orders = get_posts( $order_query );

		$order_data = array();

		if ( ! empty( $orders ) ) {
			foreach ( $orders as $wc_order ) {
				if ( ! isset( $wc_order->ID ) ) {
					continue;
				}

				$player_id   = get_post_meta( $wc_order->ID, 'player_id', true );
				$order_array = $this->plugin->woocommerce->generate_order_json( $wc_order, $server_key );

				if ( ! empty( $order_array ) ) {
					if ( ! isset( $order_data[ $player_id ] ) ) {
						$order_data[ $player_id ] = array();
					}
					$order_data[ $player_id ][ $wc_order->ID ] = $order_array;
				}
			}
		}

		return rest_ensure_response( compact( 'server_key', 'order_data' ) );
	}
}