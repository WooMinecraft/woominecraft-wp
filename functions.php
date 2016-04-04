<?php
/*
Plugin Name: Minecraft WooCommerce
Plugin URI: http://plugish.com/plugins/minecraft_woo
Description: To be used in conjunction with the minecraft_woo plugin.  If you do not have it you can get it on the repository at <a href="https://github.com/JayWood/WooMinecraft">Github</a>.  Please be sure and fork the repository and make pull requests.
Author: Jerry Wood
Version: 1.0.4
License: GPLv2
Text Domain: wmc
Author URI: http://plugish.com
*/

//include 'inc/admin.class.php';
//include 'inc/main.class.php';


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
		add_action( 'init', array( $this, 'init' ) );

		$this->admin->hooks();
	}

	/**
	 * Produces the JSON Feed for Orders Pending Delivery
	 */
	public function json_feed() {
		$db_key = get_option( 'wm_key' );
		if ( ! isset( $_REQUEST['key'] ) || $db_key !== $_REQUEST['key'] ) {
			return;
		}

		$order_query = apply_filters( 'woo_minecraft_json_orders_args', array(
			'posts_per_page' => '-1',
			'post_status'    => 'wc-completed',
			'post_type'      => 'shop_order',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'player_id',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'wm_delivered',
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

				$player_id = get_post_meta( $wc_order->ID, 'player_id', true );
				$order_array = $this->generate_order_json( $wc_order );

				if ( ! empty( $order_array ) ) {
					if ( ! isset( $output[ $player_id ] ) ) {
						$output[ $player_id ] = array();
					}
					$output[ $player_id ][ $wc_order->ID ] = $order_array;
				}
			}
		}

		wp_send_json_success( $output );

	}

	private function generate_order_json( $order_post ) {

		if ( ! isset( $order_post->ID ) ) {
			return array();
		}

		$general_commands = get_post_meta( $order_post->ID, 'wmc_commands', true );
		return $general_commands;
	}


	/**
	 * Init hooks
	 *
	 * @since  0.1.0
	 * @return null
	 */
	public function init() {
		load_plugin_textdomain( 'wcm', false, dirname( $this->basename ) . '/languages/' );
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
			'label'       => __( 'Player ID ( Minecraft Username ):', 'wmc' ),
			'placeholder' => __( 'Required Field', 'wmc' ),
		), $cart->get_value( 'player_id' ) );
		?></div><?php

		return true;
	}

	/**
	 * Sends JSON API data to the MC Java application
	 */
	public function check_json() {

		if ( ! isset( $_REQUEST['woo_minecraft'] ) ) {
			return;
		}


		$key = isset( $_REQUEST['key'] ) ? esc_attr( $_REQUEST['key'] ) : false;
		if ( empty( $key ) ) {
			wp_send_json_error( array(
				'msg'  => __( 'Cannot communicate with database, key not provided.', 'wmc' ),
				'code' => 1,
			) );
		}

		$method = isset( $_REQUEST['woo_minecraft'] ) ? esc_attr( $_REQUEST['woo_minecraft'] ) : false;
		$key_db = get_option( 'wm_key' );
		if ( empty( $key_db ) ) {
			wp_send_json_error( array(
				'msg'  => __( 'Website key unavailable', 'wmc' ),
				'code' => 2,
			) );
		}

		if ( $key_db != $key ) {
			wp_send_json_error( array(
				'msg'  => __( 'Keys do not match', 'wmc' ),
				'code' => 3,
			) );
		}

		global $wpdb;

		if ( 'update' == $method ) {

			$ids = array_map( 'intval', explode( ',', $_REQUEST['players'] ) );

			if ( empty( $ids ) ) {
				wp_send_json_error( array(
					'msg'  => __( 'No IDs for update request.', 'wmc' ),
					'code' => 4,
				) );
			}
			$results = $this->update_deliveries_for_players( $ids );
			if ( false === $results ) {
				// Error
				wp_send_json_error( array(
					'msg'  => sprintf( __( 'Error in DB query, received: "%s"', 'wcm' ), $wpdb->last_error ),
					'code' => 5,
				) );
			} elseif ( 1 > $results ) {
				// No results
				wp_send_json_error( array(
					'msg'  => __( 'Player does not exist or may not be registered.', 'wcm' ),
					'code' => 6,
				) );
			} else {
				wp_send_json_success( array(
					'msg' => __( 'Item delivered', 'wcm' ),
				) );
			}
		} else if ( false !== $method && isset( $_REQUEST['names'] ) ) {
			$namesArr = array_map( 'esc_attr', explode( ',', $_REQUEST['names'] ) );
			if ( empty( $namesArr ) ) {
				$json['status'] = 'false';
			} else {
				$results = $this->get_non_delivered( $namesArr );
				if ( empty( $results ) ) {
					wp_send_json_error( array(
						'msg'    => sprintf( __( 'No results for the following players: %s', 'wcm' ), implode( ',', $namesArr ) ),
						'status' => 'empty',
						'code'   => 6,
					) );
				} else {
					wp_send_json_success( array(
						'results' => $results,
					) );
				}
			}
		} else {
			// Bandaid for debugging the java side of things
			wp_send_json_error( array(
				'msg'  => __( 'Method or Names parameter was not set.', 'wcm' ),
				'request_data' => $_REQUEST,
				'code' => 7,
			) );
		}
	}

	/**
	 * Sets the items for a specific player to non-delivered.
	 *
	 * @param string $player_id
	 *
	 * @return false|int
	 */
	public function set_non_delivered_for_player( $player_id, $order_id = 0 ) {
		global $wpdb;
		$sql_query = $wpdb->prepare( "UPDATE {$wpdb->prefix}{$this->table} SET delivered = %d WHERE player_name = %s", 0, $player_id );

		if ( ! empty( $order_id ) ) {
			$sql_query .= $wpdb->prepare( ' AND orderid = %d', $order_id );
		}
		return $wpdb->query( $sql_query );
	}

	/**
	 * Sets orders to delivered in the database.
	 *
	 * @param array $row_ids
	 *
	 * @return false|int
	 */
	public function update_deliveries_for_players( $row_ids ) {
		global $wpdb;
		// Sets the item as delivered
		$sql_query = $wpdb->prepare( "UPDATE {$wpdb->prefix}{$this->table} SET delivered = %d WHERE id IN ([IN])", 1 );
		$sql_query = $this->prepare_in( $sql_query, $row_ids, true );
		return $wpdb->query( $sql_query );
	}

	/**
	 * Gets all un-delivered orders based on player names
	 *
	 * @param array $player_names
	 *
	 * @return array|null|object
	 */
	public function get_non_delivered( $player_names ) {
		global $wpdb;
		// Select only un-delivered items.
		$prepared = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$this->table} WHERE delivered = %d AND player_name IN ([IN])", 0 );
		$prepared = $this->prepare_in( $prepared, $player_names );
		return $wpdb->get_results( $prepared );
	}

	function prepare_in( $sql, $vals, $int = false ) {
		global $wpdb;
		$not_in_count = substr_count( $sql, '[IN]' );
		$replacement = $int ? '%d' : '%s';

		if ( ! is_array( $vals ) ) {
			$vals = array( $vals );
		}

		if ( 0 < $not_in_count ) {
			$args = array( str_replace( '[IN]', implode( ', ', array_fill( 0, count( $vals ), $replacement ) ), str_replace( '%', '%%', $sql ) ) );
			// This will populate ALL the [IN]'s with the $vals, assuming you have more than one [IN] in the sql
			$vals = array_map( 'trim', $vals );
			for ( $i = 0; $i < substr_count( $sql, '[IN]' ); $i ++ ) {
				$args = array_merge( $args, $vals );
			}
			$sql = call_user_func_array( array( $wpdb, 'prepare' ), $args );
		}

		return $sql;
	}

	/**
	 * Caches the results of the mojang API based on player ID
	 *
	 * @param String $playerID Minecraft Username
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
	public function mojang_player_cache( $playerID ) {

		$key     = md5( 'minecraft_player_' . $playerID );
		$mc_json = wp_cache_get( $key, 'wcm' );

		if ( false == $mc_json ) {

			$post_config = apply_filters( 'mojang_profile_api_post_args', array(
				'body'    => json_encode( array( rawurlencode( $playerID ) ) ),
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

		$playerID = isset( $_POST['player_id'] ) ? esc_attr( $_POST['player_id'] ) : false;
		$items    = $woocommerce->cart->cart_contents;

		if ( ! wmc_has_commands( $items ) ) {
			return;
		}

		if ( ! $playerID ) {
			wc_add_notice( __( 'You MUST provide a Minecraft username.', 'ucm' ), 'error' );

			return;
		}

		// Grab JSON data
		$mc_json = $this->mojang_player_cache( $playerID );
		if ( ! $mc_json ) {
			wc_add_notice( __( 'There was an error with the Mojang API, please try again later.', 'wcm' ) );
		}

		if ( isset( $mc_json->demo ) ) {
			wc_add_notice( __( 'We do not allow unpaid-accounts to make donations, sorry!', 'wcm' ) );

			return;
		}
	}

	public function save_commands_to_order( $order_id ) {
		global $wpdb;

		$orderData   = new WC_Order( $order_id );
		$items       = $orderData->get_items();
		$tmpArray    = array();
		$player_name = get_post_meta( $order_id, 'player_id', true );
		foreach ( $items as $item ) {
			// Insert into database table
			$product = get_post_meta( $item['product_id'], 'minecraft_woo', true );
			if ( ! empty( $product ) ) {
				for ( $n = 0; $n < $item['qty']; $n ++ ) {
					foreach ( $product as $command ) {
						$tmpArray[] = ( false === strpos( '%s', $command ) ) ? $command : sprintf( $command, $player_name );
					}
				}
			}


			$product_variation = get_post_meta( $item['variation_id'], 'minecraft_woo', true );
			if ( ! empty( $product_variation ) ) {
				for ( $n = 0; $n < $item['qty']; $n ++ ) {
					foreach ( $product_variation as $command ) {
						$tmpArray[] = ( false === strpos( '%s', $command ) ) ? $command : sprintf( $command, $player_name );
					}
				}
			}
		}

		if ( ! empty( $tmpArray ) ) {
			update_post_meta( $order_id, 'wmc_commands', $tmpArray );
		}
	}

	public function thanks( $id ) {
		$player_name = get_post_meta( $id, 'player_id', true );
		if ( ! empty( $player_name ) ) {
			?>
			<div class="woo_minecraft"><h4><?php _e( 'Minecraft Details', 'wcm' ); ?></h4>

			<p><strong><?php _e( 'Username:', 'wcm' ); ?></strong><?php echo $player_name ?></p></div><?php
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
	 * @return WDS_Client_Plugin_Name A single instance of this class.
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

function Woo_Minecraft() {
	return Woo_Minecraft::get_instance();
}

add_action( 'plugins_loaded', array( Woo_Minecraft(), 'hooks' ) );

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

			$has_command = get_post_meta( $post_id, 'minecraft_woo', true );
			if ( empty( $has_command ) ) {
				continue;
			} else {
				return true;
			}
		}
	}

	return false;
}
