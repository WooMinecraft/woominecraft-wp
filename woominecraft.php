<?php
/*
Plugin Name: Minecraft WooCommerce
Plugin URI: http://plugish.com/plugins/minecraft_woo
Description: To be used in conjunction with the minecraft_woo plugin.  If you do not have it you can get it on the repository at <a href="https://github.com/JayWood/WooMinecraft">Github</a>.  Please be sure and fork the repository and make pull requests.
Author: Jerry Wood
Version: 1.0.7
License: GPLv2
Text Domain: woominecraft
Domain Path: /languages
Author URI: http://plugish.com
*/

function wmc_autoload_classes( $class_name ) {
	if ( 0 != strpos( $class_name, 'WCM_' ) ) {
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

class Woo_Minecraft {

	/**
	 * Current version
	 *
	 * @var  string
	 * @since  0.1.0
	 */
	const VERSION = '1.0.3';

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
	 * @var string The db table name
	 */
	private $table = 'woo_minecraft';

	/**
	 * The transient key
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
	 * Plugin Hooks
	 *
	 * Contains all WP hooks for the plugin
	 *
	 * @since 0.1.0
	 */
	public function hooks() {
		add_action( 'woocommerce_checkout_process', array( $this, 'check_player' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_commands_to_order' ) );
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'additional_checkout_field' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'thanks' ) );

		add_action( 'template_redirect', array( $this, 'json_feed' ) );

		add_action( 'save_post', array( $this, 'bust_command_cache' ) );

		$this->admin->hooks();
	}

	/**
	 * Produces the JSON Feed for Orders Pending Delivery
	 */
	public function json_feed() {

		if ( ! isset( $_REQUEST['key'] ) ) {
			// Bail if no key
			return;
		}

		$servers = get_option( 'wm_servers', array() );
		if ( empty( $servers ) ) {
			wp_send_json_error( array( 'msg' => "No servers setup, check WordPress config." ) );
		}
		$keys = wp_list_pluck( $servers, 'key' );
		if ( empty( $keys ) ) {
			wp_send_json_error( array( 'msg' => "WordPress keys are not set." ) );
		}

		if ( false === array_search( $_GET['key'], $keys ) ) {
			wp_send_json_error( array( 'msg' => "Invalid key supplied to WordPress, compare your keys." ) );
		}

		$key = esc_attr( $_GET['key'] );

		if ( isset( $_REQUEST['processedOrders'] ) ) {
			$this->process_completed_commands( $key );
		}

		if ( false === ( $output = get_transient( $this->command_transient ) ) || isset( $_GET['delete-trans'] ) ) {

			$delivered = '_wmc_delivered_' . $key;
			$meta_key = '_wmc_commands_' . $key;

			$order_query = apply_filters( 'woo_minecraft_json_orders_args', array(
				'posts_per_page' => '-1',
				'post_status'    => 'wc-completed',
				'post_type'      => 'shop_order',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key' => $meta_key,
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
	 * @param WP_Post $order_post
	 * @param string $key Server key to check against
	 * @author JayWood
	 * @return array|mixed
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
	 * @since  0.1.0
	 * @return null
	 */
	public function i18n() {
		load_plugin_textdomain( 'woominecraft', false, dirname( $this->basename ) . '/languages/' );
	}

	/**
	 * Take special care to sanitize the incoming post data.
	 *
	 * While not as simple as running esc_attr, this is still necessary since the JSON
	 * from the Java code comes in escaped, so we need some custom sanitization.
	 *
	 * @param $post_data
	 *
	 * @author JayWood
	 * @return array
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
	 * @param int $post_id
	 *
	 * @author JayWood
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
	 * @author JayWood
	 * @param string $key
	 */
	private function process_completed_commands( $key = '' ) {
		$delivered = '_wmc_delivered_' . $key;
		$order_ids = (array) $this->sanitized_orders_post( $_POST['processedOrders'] );

		if (  empty( $order_ids ) ) {
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
	 * @param object $cart WooCommerce Cart Object
	 *
	 * @return bool  False on failure, true otherwise.
	 */
	public function additional_checkout_field( $cart ) {
		global $woocommerce;

		$items = $woocommerce->cart->cart_contents;
		if ( ! wmc_has_commands( $items ) || ! function_exists( 'woocommerce_form_field' ) ) {
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
	 * @param $order_id
	 *
	 * @author JayWood
	 * @return bool
	 */
	public function reset_order( $order_id ) {
		return delete_post_meta( $order_id, 'wmc_delivered' );
	}

	/**
	 * Caches the results of the mojang API based on player ID
	 *
	 * @param String $player_id Minecraft Username
	 *
	 * Object is as follows
	 * {
	 *    "id": "0d252b7218b648bfb86c2ae476954d32",
	 *    "name": "CasESensatIveUserName",
	 *    "legacy": true,
	 *    "demo": true
	 * }
	 *
	 * @return bool|object False on failure, Object on success
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
	 */
	public function check_player() {
		global $woocommerce;

		if ( ! $woocommerce instanceof WooCommerce ) {
			return;
		}

		$player_id = isset( $_POST['player_id'] ) ? esc_attr( $_POST['player_id'] ) : false;
		$items    = $woocommerce->cart->cart_contents;

		if ( ! wmc_has_commands( $items ) ) {
			return;
		}

		if ( ! $player_id ) {
			wc_add_notice( __( 'You MUST provide a Minecraft username.', 'woominecraft' ), 'error' );

			return;
		}

		// Grab JSON data
		$mc_json = $this->mojang_player_cache( $player_id );
		if ( ! $mc_json ) {
			wc_add_notice( __( 'There was an error with the Mojang API, please try again later.', 'woominecraft' ) );
		}

		if ( isset( $mc_json->demo ) ) {
			wc_add_notice( __( 'We do not allow unpaid-accounts to make donations, sorry!', 'woominecraft' ) );

			return;
		}
	}

	/**
	 * Updates an order's meta data with the commands hash.
	 *
	 * @param $order_id
	 *
	 * @author JayWood
	 */
	public function save_commands_to_order( $order_id ) {

		$order_data   = new WC_Order( $order_id );
		$items       = $order_data->get_items();
		$tmp_array   = array();

		if ( ! isset( $_POST['player_id'] ) || empty( $_POST['player_id'] ) ) {
			return;
		}

		$player_name = esc_attr( $_POST['player_id'] );
		update_post_meta( $order_id, 'player_id', $player_name );

		foreach ( $items as $item ) {
			// Insert into database table
			$general_commands = get_post_meta( $item['product_id'], 'wmc_commands', true );
			if ( ! empty( $general_commands ) ) {
				for ( $n = 0; $n < $item['qty']; $n++ ) {
					foreach ( $general_commands as $server_key => $command ) {
						if ( ! isset( $tmp_array[ $server_key ] ) ) {
							$tmp_array[ $server_key ] = array();
						}
						if ( is_array( $command ) ) {
							foreach( $command as $c ) {
								$tmp_array[ $server_key ][] = sprintf( $c, $player_name );
							}
						} else {
							$tmp_array[ $server_key ][] = sprintf( $command, $player_name );
						}
					}
				}
			}

			if ( isset( $item['variation_id'] ) ) {
				$variation_commands = get_post_meta( $item['variation_id'], 'wmc_commands', true );
				if ( ! empty( $variation_commands ) ) {
					for ( $n = 0; $n < $item['qty']; $n++ ) {
						foreach ( $variation_commands as $server_key => $command ) {
							if ( ! isset( $tmp_array[ $server_key ] ) ) {
								$tmp_array[ $server_key ] = array();
							}

							if ( is_array( $command ) ) {
								foreach( $command as $c ) {
									$tmp_array[ $server_key ][] = sprintf( $c, $player_name );
								}
							} else {
								$tmp_array[ $server_key ][] = sprintf( $command, $player_name );
							}
						}
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

	public function thanks( $id ) {
		$player_name = get_post_meta( $id, 'player_id', true );
		if ( ! empty( $player_name ) ) {
			?>
			<div class="woo_minecraft"><h4><?php _e( 'Minecraft Details', 'woominecraft' ); ?></h4>

			<p><strong><?php _e( 'Username:', 'woominecraft' ); ?></strong><?php echo $player_name ?></p></div><?php
		}
	}

	/**
	 * Plugin classes
	 *
	 * @since 0.1.0
	 */
	public function plugin_classes() {
		$this->admin = new WCM_Admin( $this );
	}

	/**
	 * Include a file from the includes directory
	 *
	 * @since  0.1.0
	 *
	 * @param  string $filename Name of the file to be included
	 *
	 * @return bool    Result of include call.
	 */
	public static function include_file( $filename ) {
		$file = self::dir( 'includes/' . $filename . '.php' );
		if ( file_exists( $file ) ) {
			return include_once( $file );
		}

		return false;
	}

	/**
	 * This plugin's directory
	 *
	 * @since  0.1.0
	 *
	 * @param  string $path (optional) appended path
	 *
	 * @return string       Directory and path
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
	 * @param  string $path (optional) appended path
	 *
	 * @return string       URL and path
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );

		return $url . $path;
	}

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since  0.1.0
	 * @return Woo_Minecraft A single instance of this class.
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
	 * @param string $field
	 *
	 * @throws Exception Throws an exception if the field is invalid.
	 * @return mixed
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

function woo_minecraft() {
	return Woo_Minecraft::get_instance();
}

add_action( 'plugins_loaded', array( woo_minecraft(), 'hooks' ) );
add_action( 'plugins_loaded', array( woo_minecraft(), 'i18n' ) );

/**
 * Has Commands
 *
 * @param $data
 *
 * @TODO: Move this to helper file
 * @return bool
 */
function wmc_has_commands( $data ) {
	if ( is_array( $data ) ) {
		// Assume $data is cart contents
		foreach ( $data as $item ) {
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
