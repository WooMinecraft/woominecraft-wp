<?php
/*
Plugin Name: Minecraft WooCommerce
Plugin URI: http://woominecraft.com
Description: To be used in conjunction with the WooMinecraft Bukkit plugin.  If you do not have it you can get it on the repository at <a href="https://github.com/JayWood/WooMinecraft">Github</a>.  Please be sure and fork the repository and make pull requests.
Author: Jerry Wood
Version: 2.0
License: GPLv2
Text Domain: woominecraft
Domain Path: /languages
Author URI: http://plugish.com
*/

function wmc_autoload_classes( $class_name ) {
	if ( 0 !== strpos( $class_name, 'WCM_' ) ) {
		return false;
	}

	$filename = strtolower( str_ireplace(
		array( 'WCM_', '_' ),
		array( '', '-' ),
		$class_name
	) );

	Woo_Minecraft::include_file( $filename );

	return true;
}

spl_autoload_register( 'wmc_autoload_classes' );

/**
 * Class Woo_Minecraft
 *
 * @todo   : Create some way of handling orphaned orders. See Below -
 * If an order is created which had commands tied to a specific server, and that server is later deleted, those commands cannot be re-sent at any time.
 *
 * @author JayWood
 */
class Woo_Minecraft {

	/**
	 * Current version
	 *
	 * @var  string
	 * @since  0.1.0
	 */
	const VERSION = '2.0';

	/**
	 * URL of plugin directory
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $path = '';

	/**
	 * Plugin basename
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $basename = '';

	/**
	 * Singleton instance of plugin
	 *
	 * @var Woo_Minecraft
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	/**
	 * Instance of the WCM_Admin class
	 *
	 * @var WCM_Admin
	 * @since 0.1.0
	 */
	public $admin = null;

	/**
	 * The transient key
	 *
	 * @var string
	 */
	private $command_transient = 'wmc-transient-command-feed';

	/**
	 * Sets up our plugin
	 *
	 * @since  0.1.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );

		$this->plugin_classes();
	}

	/**
	 * Loads all child classes for the plugin.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	private function plugin_classes() {
		$this->admin = new WCM_Admin( $this );
	}

	/**
	 * Contains all the necessary hooks for the main plugin file.
	 *
	 * @author JayWood
	 * @return void
	 */
	public function hooks() {
		add_action( 'woocommerce_checkout_process', array( $this, 'check_player' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_commands_to_order' ) );
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'additional_checkout_field' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'thanks' ) );

		add_action( 'template_redirect', array( $this, 'json_feed' ) );

		add_action( 'save_post', array( $this, 'bust_command_cache' ) );

		add_action( 'rest_api_init', array( $this, 'rest_setup_routes' ) );

		$this->admin->hooks();
	}

	/**
	 * Sets up REST routes for the WP-API
	 *
	 * @author JayWood
	 * @return void
	 */
	public function rest_setup_routes() {
		register_rest_route( 'woominecraft/v1', '/server/(?P<server_key>[a-zA-Z0-9\@\#\!]+)', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_server_commands' ),
			'args'     => array(
				'server_key' => array(
					'sanitize_callback' => 'esc_attr',
				),
			),
		) );
	}

	/**
	 * Retrieves server specific commands for the key provided.
	 *
	 * @param WP_Rest_Request $request The rest request object.
	 *
	 * @return mixed
	 *
	 * @author JayWood
	 * @since 2.0.0
	 */
	public function get_server_commands( WP_Rest_Request $request ) {
		$server_key = $request->get_param( 'server_key' );

		return rest_ensure_response( new WP_Error( 'testing',' This is a test' ) );
	}

	/**
	 * Produces the JSON Feed for Orders Pending Delivery
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function json_feed() {

		if ( ! isset( $_REQUEST['wmc_key'] ) ) {
			// Bail if no key
			return;
		}

		$servers = get_option( 'wm_servers', array() );
		if ( empty( $servers ) ) {
			wp_send_json_error( array( 'msg' => 'No servers setup, check WordPress config.' ) );
		}
		$keys = wp_list_pluck( $servers, 'key' );
		if ( empty( $keys ) ) {
			wp_send_json_error( array( 'msg' => 'WordPress keys are not set.' ) );
		}

		if ( false === array_search( $_GET['wmc_key'], $keys ) ) {
			wp_send_json_error( array( 'msg' => 'Invalid key supplied to WordPress, compare your keys.' ) );
		}

		$key = esc_attr( $_GET['wmc_key'] );

		if ( isset( $_REQUEST['processedOrders'] ) ) {

			$this->process_completed_commands( $key );
		}

		if ( false === ( $output = get_transient( $this->command_transient ) ) || isset( $_GET['delete-trans'] ) ) {

			$delivered = '_wmc_delivered_' . $key;
			$meta_key  = '_wmc_commands_' . $key;

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

			$output = array();

			if ( ! empty( $orders ) ) {
				foreach ( $orders as $wc_order ) {
					if ( ! isset( $wc_order->ID ) ) {
						continue;
					}

					$player_id   = get_post_meta( $wc_order->ID, 'player_id', true );
					$order_array = $this->generate_order_json( $wc_order, $key );

					if ( ! empty( $order_array ) ) {
						if ( ! isset( $output[ $player_id ] ) ) {
							$output[ $player_id ] = array();
						}
						$output[ $player_id ][ $wc_order->ID ] = $order_array;
					}
				}
			}

			set_transient( $this->command_transient, $output, 60 * 60 ); // Stores the feed in a transient for 1 hour.
		}

		wp_send_json_success( $output );

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
	private function generate_order_json( $order_post, $key ) {

		if ( ! isset( $order_post->ID ) ) {
			return array();
		}

		$general_commands = get_post_meta( $order_post->ID, '_wmc_commands_' . $key, true );

		return $general_commands;
	}


	/**
	 * Setup Localization
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function i18n() {
		load_plugin_textdomain( 'woominecraft', false, dirname( $this->basename ) . '/languages/' );
	}

	/**
	 * Take special care to sanitize the incoming post data.
	 *
	 * While not as simple as running esc_attr, this is still necessary since the JSON
	 * from the Java code comes in escaped, so we need some custom sensitization.
	 *
	 * @param string $post_data JSON data passed to the server for order processing.
	 *
	 * @return array
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	private function sanitized_orders_post( $post_data ) {
		$decoded = json_decode( stripslashes( urldecode( $post_data ) ) );
		if ( empty( $decoded ) ) {
			return array();
		}

		return array_map( 'intval', $decoded );
	}

	/**
	 * Helper method for transient busting
	 *
	 * @param integer $post_id The post ID.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function bust_command_cache( $post_id = 0 ) {

		if ( ! empty( $post_id ) && 'shop_order' !== get_post_type( $post_id ) ) {
			return;
		}

		delete_transient( $this->command_transient );
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
		$order_ids = (array) $this->sanitized_orders_post( $_POST['processedOrders'] );

		if ( empty( $order_ids ) ) {
			wp_send_json_error( array( 'msg' => __( 'Commands was empty', 'woominecraft' ) ) );
		}

		// Set the orders to delivered
		foreach ( $order_ids as $order_id ) {
			update_post_meta( $order_id, $delivered, true );
		}

		$this->bust_command_cache();
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
		if ( ! wmc_items_have_commands( $items ) || ! function_exists( 'woocommerce_form_field' ) ) {
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
		$this->bust_command_cache( $order_id );

		return true;
	}

	/**
	 * Caches the results of the Mojang API request, based on player ID
	 *
	 * Object is as follows:
	 * <pre>
	 * {
	 *    "id": "0d252b7218b648bfb86c2ae476954d32",
	 *    "name": "CasESensatIveUserName",
	 *    "legacy": true,
	 *    "demo": true
	 * }
	 * </pre>
	 *
	 * @param string $player_id Minecraft Username.
	 *
	 * @return boolean|object False on failure, Object on success.
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function mojang_player_cache( $player_id ) {

		$key     = md5( 'minecraft_player_' . $player_id );
		$mc_json = wp_cache_get( $key, 'woominecraft' );

		if ( false == $mc_json ) {

			$post_config = apply_filters( 'mojang_profile_api_post_args', array(
				'body'    => json_encode( array( rawurlencode( $player_id ) ) ),
				'method'  => 'POST',
				'headers' => array( 'content-type' => 'application/json' ),
			) );

			$minecraft_account = wp_remote_post( 'https://api.mojang.com/profiles/minecraft', $post_config );

			if ( 200 != wp_remote_retrieve_response_code( $minecraft_account ) ) {
				return false;
			}

			$mc_json = json_decode( wp_remote_retrieve_body( $minecraft_account ) );
			if ( ! isset( $mc_json[0] ) ) {
				return false;
			} else {
				$mc_json = $mc_json[0];
			}

			wp_cache_set( $key, $mc_json, 'wcm', 1 * HOUR_IN_SECONDS );
		}

		return $mc_json;
	}

	/**
	 * Checks if Minecraft Username is valid
	 *
	 * @return void
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

		if ( ! wmc_items_have_commands( $items ) ) {
			return;
		}

		if ( ! $player_id ) {
			wc_add_notice( __( 'You MUST provide a Minecraft username.', 'woominecraft' ), 'error' );

			return;
		}

		// Grab JSON data
		$mc_json = $this->mojang_player_cache( $player_id );
		if ( ! $mc_json ) {
			wc_add_notice( __( 'We cannot retrieve your account from the Mojang API. Try again later, or contact an administrator.', 'woominecraft' ), 'error' );
		}

		if ( isset( $mc_json->demo ) ) {
			wc_add_notice( __( 'We do not allow unpaid-accounts to make donations, sorry!', 'woominecraft' ), 'error' );

			return;
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

		// @TODO Use wc_get_order instead, so one can check if it's a valid ID, instead of assuming we can get_items()
		$order_data = new WC_Order( $order_id );
		$items      = $order_data->get_items();
		$tmp_array  = array();

		// @TODO an empty check is sufficient
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
			for ( $n = 0; $n < absint( $item['qty'] ); $n ++ ) {
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
			<div class="woo_minecraft"><h4><?php _e( 'Minecraft Details', 'woominecraft' ); ?></h4>

			<p><strong><?php _e( 'Username:', 'woominecraft' ); ?></strong><?php echo $player_name ?></p></div><?php
		}
	}

	/**
	 * Include a file from the includes directory
	 *
	 * @param  string $filename Name of the file to be included.
	 *
	 * @return boolean Result of include call.
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public static function include_file( $filename ) {
		$file = self::dir( 'includes/' . $filename . '.php' );
		if ( file_exists( $file ) ) {
			/** @noinspection PhpIncludeInspection */
			return include_once( $file );
		}

		return false;
	}

	/**
	 * Returns the plugin's path with an optional appended path.
	 *
	 * @param  string $path (optional) appended path.
	 *
	 * @return string       Directory and path
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public static function dir( $path = '' ) {
		static $dir;
		$dir = $dir ? $dir : trailingslashit( dirname( __FILE__ ) );

		return $dir . $path;
	}

	/**
	 * This plugin's url
	 *
	 * @since  0.1.0
	 *
	 * @param  string $path The (optional) appended path.
	 *
	 * @return string URL and path.
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );

		return $url . $path;
	}

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @return Woo_Minecraft A single instance of this class.
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.1.0
	 *
	 * @param string $field The property being accessed.
	 *
	 * @return mixed
	 *
	 * @throws Exception Throws an exception if the field is invalid.
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'basename':
			case 'url':
			case 'path':
				return $this->$field;
			default:
				throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
		}
	}
}

/**
 * The main plugin function, can be used to load an instance of the plugin.
 *
 * @return Woo_Minecraft
 *
 * @author JayWood
 * @since  1.0.0
 */
function woo_minecraft() {
	return Woo_Minecraft::get_instance();
}

add_action( 'plugins_loaded', array( woo_minecraft(), 'hooks' ) );
add_action( 'plugins_loaded', array( woo_minecraft(), 'i18n' ) );

/**
 * Determines if an item has commands.
 *
 * @param array $item_data An array of item data from WooCommerce.
 *
 * @TODO: Move this to helper file
 *
 * @return boolean
 *
 * @author JayWood
 * @since  1.0.0
 */
function wmc_items_have_commands( $item_data ) {
	if ( is_array( $item_data ) ) {
		foreach ( $item_data as $item ) {
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
	}

	return false;
}
