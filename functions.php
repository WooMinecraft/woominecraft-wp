<?php
/*
Plugin Name: Minecraft WooCommerce
Plugin URI: http://plugish.com/plugins/minecraft_woo
Description: To be used in conjunction with the minecraft_woo plugin.  If you do not have it you can get it on the repository at <a href="https://github.com/JayWood/WooMinecraft">Github</a>.  Please be sure and fork the repository and make pull requests.
Author: Jerry Wood
Version: 0.1.0
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
		array( 'WDSCPN_', '_' ),
		array( '', '-' ),
		$class_name
	) );

	Woo_Minecraft::include_file( $filename );

	return true;
}

class Woo_Minecraft {

	/**
	 * Current version
	 *
	 * @var  string
	 * @since  0.1.0
	 */
	const VERSION = '0.1.0';

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
	 * @var WDS_Client_Plugin_Name
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	/**
	 * Sets up our plugin
	 *
	 * @since  0.1.0
	 */
	public function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );

		$this->plugin_classes();
		$this->hooks();
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
		add_action( 'woocommerce_order_status_completed', array( $this, 'finalize_order' ) );
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'anotes' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'thanks' ) );
		add_action( 'plugins_loaded', array( $this, 'checkJSON' ) );
	}


	/**
	 * Adds a field to the checkout form, requiring the user to enter their Minecraft Name
	 * @param object $c WooCommerce Cart Object
	 *
	 * @return bool  False on failure, true otherwise.
	 */
	public function anotes( $c ) {
		global $woocommerce;

		$items = $woocommerce->cart->cart_contents;
		if ( ! has_commands( $items ) || ! function_exists( 'woocommerce_form_field' ) ) {
			return false;
		}

		?>
		<div id="woo_minecraft"><?php
		woocommerce_form_field( 'player_id', array(
			'type'        => 'text',
			'class'       => array(),
			'label'       => __( 'Player ID:', 'wmc' ),
			'placeholder' => __( 'Required Field', 'wmc' ),
		), $c->get_value( 'player_id' ) );
		?></div><?php

		return true;
	}

	/**
	 * Sends JSON API data to the MC Java application
	 */
	public function checkJSON() {

		$method = isset( $_REQUEST['woo_minecraft'] ) ? $_REQUEST['woo_minecraft'] : false;
		$key    = isset( $_REQUEST['key'] ) ? $_REQUEST['key'] : false;
		if ( empty( $key ) ) {
			wp_send_json_error( array(
				'msg'    => __( "Malformed key", 'wmc' ),
				'code'   => 1,
			) );
		}

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
				'web'  => $key,
				'db'   => $key_db,
				'code' => 3,
			) );
		}

		global $wpdb;

		if ( $method == "update" ) {
			$ids = $_REQUEST['players'];

			if ( empty( $ids ) ) {
				wp_send_json_error( array(
					'msg'  => __( 'No IDs for update request.', 'wmc' ),
					'code' => 4,
				) );
			}

			// Sets the item as delivered
			$query = $wpdb->prepare( "UPDATE {$wpdb->prefix}woo_minecraft SET delivered = %d WHERE id IN(%s)", 1, $ids );
			$rs    = $wpdb->query( $query );
			if ( false === $rs ) {
				// Error
				wp_send_json_error( array(
					'msg'  => sprintf( __( 'Error in DB query, received: "%s"', 'wcm' ), $wpdb->last_error ),
					'code' => 5,
				) );
			} elseif ( 1 > $rs ) {
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
			$namesArr = explode( ',', $_REQUEST['names'] );
			if ( empty( $namesArr ) ) {
				$json['status'] = "false";
			} else {
				foreach ( $namesArr as $k => $v ) {
					$namesArr[ $k ] = '"' . strtolower( $v ) . '"';
				}
				$namesArr = implode( ',', $namesArr );
				// Select only un-delivered items.
				$prepared = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woo_minecraft WHERE delivered = %d AND player_name IN (%s)", 0, $namesArr );
				$results  = $wpdb->get_results( $prepared );
				if ( empty( $results ) ) {
					wp_send_json_error( array(
						'msg'    => sprintf( __( "No results for the following players: %s", 'wcm' ), $namesArr ),
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
				'msg'  => __( "Method or Names parameter was not set.", 'wcm' ),
				'code' => 7,
			) );
		}
	}

	public function check_player() {
		global $woocommerce;

		if ( ! $woocommerce instanceof WooCommerce ) {
			return false;
		}

		$playerID = stripslashes_deep( $_POST['player_id'] );

		$items = $woocommerce->cart->cart_contents;
		if ( ! has_commands( $items ) ) {
			return false;
		}

		if ( empty( $_POST['player_id'] ) ) {
			wc_add_notice( __( 'Player ID must not be left empty.', 'wcm' ), 'error' );
		} else {
			$minecraft_account = wp_remote_get( 'http://www.minecraft.net/haspaid.jsp?user=' . rawurlencode( $playerID ), array( 'timeout' => 5 ) );
			$response = wp_remote_retrieve_body( $minecraft_account );
			if ( $response != 'true' ) {
				if ( $response == 'false' ) {
					wc_add_notice( __( 'Invalid Minecraft Account', 'wcm' ), 'error' );
				} else {
					wc_add_notice( __( 'Cannot communicate with Minecraft.net  Servers may be down.', 'wcm' ), 'error' );
				}
			}
		}
	}

	public function finalize_order( $order_id ) {
		global $wpdb;

		$orderData = new WC_Order( $order_id );
		$items     = $orderData->get_items();
//			wp_die(print_r($items, true));
		$tmpArray   = array();
		$player_name = get_post_meta( $order_id, 'player_id', true );
		foreach ( $items as $item ) {
			// Insert into database table
			$metag = get_post_meta( $item['product_id'], 'minecraft_woo_g', true );
			$metav = get_post_meta( $item['variation_id'], 'minecraft_woo_v', true );
			if ( ! empty( $metag ) ) {
				for ( $n = 0; $n < $item['qty']; $n ++ ) {
					foreach ( $metag as $command ) {
						$x = array(
							'postid'      => $item['product_id'],
							'command'     => $command,
							'orderid'     => $order_id,
							'player_name' => $player_name
						);
						array_push( $tmpArray, $x );
					}
				}
			}

			if ( ! empty( $metav ) ) {
				for ( $n = 0; $n < $item['qty']; $n ++ ) {
					foreach ( $metav as $command ) {
						$x1 = array(
							'postid'      => $item['variation_id'],
							'command'     => $command,
							'orderid'     => $order_id,
							'player_name' => $player_name
						);
						array_push( $tmpArray, $x1 );
					}
				}
			}
		}

		if ( ! empty( $tmpArray ) ) {
			foreach ( $tmpArray as $row ) {
				$wpdb->insert( $wpdb->prefix . "woo_minecraft", $row, array( '%d', '%s', '%d', '%s' ) );
			}
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

$GLOBALS['Woo_Minecraft'] = new Woo_Minecraft();
$GLOBALS['Woo_Minecraft']->hooks();

/**
 * Has Commands
 *
 * @param $data
 *
 * @TODO: Move this to helper file
 * @return bool
 */
function has_commands( $data ) {
	if ( is_array( $data ) ) {
		// Assume $data is cart contents
		foreach ( $data as $item ) {
			$metag = get_post_meta( $item['product_id'], 'minecraft_woo_g', true );
			$metav = get_post_meta( $item['variation_id'], 'minecraft_woo_v', true );
			if ( empty( $metag ) && empty( $metav ) ) {
				continue;
			} else {
				return true;
			}
		}
	}

	return false;
}

//new Woo_Minecraft_Admin;
//register_activation_hook( __FILE__, array( 'Woo_Minecraft_Admin', 'install' ) );
//register_uninstall_hook( __FILE__, array( 'Woo_Minecraft_Admin', 'uninstall' ) );
//new Woo_Minecraft;