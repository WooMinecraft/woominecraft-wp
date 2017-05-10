<?php
/**
 * Plugin Name: Minecraft WooCommerce
 * Plugin URI: http://woominecraft.com
 * Description: To be used in conjunction with the WooMinecraft Bukkit plugin.  If you do not have it you can get it on the repository at <a href="https://github.com/JayWood/WooMinecraft">Github</a>.  Please be sure and fork the repository and make pull requests.
 * Author: Jerry Wood
 * Version: 2.0
 * License: GPLv2
 * Text Domain: woominecraft
 * Domain Path: /languages
 * Author URI: http://plugish.com
 *
 * @package WooMinecraft
 * @version 2.0
 */

/**
 * Copyright (c) 2017 JayWood (email : jjwood2004@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

namespace WooMinecraft;
use Exception;
use WooMinecraft\Admin\WCM_Admin;
use WooMinecraft\WooCommerce\WCM_WooCommerce;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Automatically loads class files when needed.
 *
 * @param string $class_name The class attempting to be loaded.
 *
 * @return void
 *
 * @author JayWood
 * @since  1.0.0
 */
function wmc_autoload_classes( $class_name ) {

	if ( false === strpos( $class_name, 'WooMinecraft' ) ) {
		return;
	}

	// Break everything into parts.
	$class_array = explode( '\\', $class_name );

	// Build the filename from the last item in the array.
	$filename = strtolower( str_ireplace(
		array( 'WCM_', '_' ),
		array( '', '-' ),
		end( $class_array )
	) );

	// Cut off the first, and last item from the array
	$new_dir = array_slice( $class_array, 1, count( $class_array ) - 2 );

	// Glue the pieces back together.
	$new_dir = implode( '/', array_map( 'strtolower', $new_dir ) );

	// Build the directory.
	$new_dir = trailingslashit( $new_dir ) . $filename;

	WooMinecraft::include_file( $new_dir );
}
spl_autoload_register( '\WooMinecraft\wmc_autoload_classes' );

/**
 * Class WooMinecraft
 *
 * @TODO Create some way of handling orphaned orders. If an order is created which had commands tied to a specific server, and that server is later deleted, those commands cannot be re-sent at any time.
 *
 * @author JayWood
 *
 * @since 1.0.0
 */
class WooMinecraft {

	/**
	 * Current version
	 *
	 * @var  string
	 * @since  0.1.0
	 */
	private $version = '2.0';

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
	 * @var WooMinecraft
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	/**
	 * Instance of the WCM_Admin class
	 *
	 * @var Admin\WCM_Admin
	 * @since 0.1.0
	 */
	public $admin = null;

	/**
	 * Instance of the WCM_WooCommerce class
	 *
	 * @var WooCommerce\WCM_WooCommerce
	 * @since NEXT
	 */
	public $woocommerce = null;

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
		$this->admin       = new WCM_Admin( $this );
		$this->woocommerce = new WCM_WooCommerce( $this );
	}

	/**
	 * Gets the current version number of the plugin.
	 *
	 * @return string The version string
	 *
	 * @author JayWood
	 * @since  2.0.0
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Contains all the necessary hooks for the main plugin file.
	 *
	 * @author JayWood
	 * @return void
	 */
	public function hooks() {
		add_action( 'save_post', array( $this, 'bust_command_cache' ) );
		add_action( 'rest_api_init', array( $this, 'rest_setup_routes' ) );
	}

	/**
	 * A
	 *
	 * @return bool|WP_Error
	 *
	 * @author JayWood
	 * @since  NEXT
	 */
	public function get_server_settings_Permission_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'Only administrators can view server keys.', 'woominecraft' ) );
		}

		return true;
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
			'callback'            => array( $this->admin, 'get_servers' ),
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
	 * Processes all order updates
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since  NEXT
	 */
	public function process_order_updates( WP_REST_Request $request ) {

	}

	/**
	 * Validates the server key provided against a list of stored keys.
	 *
	 * @param string $server_key The server key provided.
	 *
	 * @return bool|WP_Error True on success, WP_Error otherwise.
	 *
	 * @author JayWood
	 * @since  NEXT
	 */
	private function validate_key( $server_key ) {

		if ( ! $server_key ) {
			return new WP_Error( 'invalid_key', esc_html__( 'Invalid Key specified, or no key provided', 'woominecraft' ) );
		}

		$servers = get_option( 'wm_servers', array() );
		if ( empty( $servers ) ) {
			return new WP_Error( 'no_servers', esc_html__( 'No servers have been setup for this resource.', 'woominecraft' ) );
		}

		$keys = wp_list_pluck( $servers, 'key' );
		if ( ! $keys ) {
			return new WP_Error( 'no_keys', esc_html__( 'Unknown error, no keys are available.', 'woominecraft' ) );
		}

		if ( false === array_search( $server_key, $keys, true ) ) {
			return new WP_Error( 'invalid_key', esc_html__( 'The key provided is invalid.', 'woominecraft' ) );
		}

		return true;
	}

	/**
	 * Retrieves server specific commands for the key provided.
	 *
	 * @param WP_Rest_Request $request The rest request object.
	 * @todo Support paging, and include that in the output.
	 *
	 * @return mixed
	 *
	 * @author JayWood
	 * @since NEXT
	 */
	public function get_server_commands( WP_Rest_Request $request ) {
		$server_key = $request->get_param( 'server_key' );

		$is_valid_key = $this->validate_key( $server_key );
		if ( is_wp_error( $is_valid_key ) ) {
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
				$order_array = $this->woocommerce->generate_order_json( $wc_order, $server_key );

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
	public function sanitized_orders_post( $post_data ) {
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

		if ( false === $mc_json ) {

			$post_config = apply_filters( 'mojang_profile_api_post_args', array(
				'body'    => wp_json_encode( array( rawurlencode( $player_id ) ) ),
				'method'  => 'POST',
				'headers' => array( 'content-type' => 'application/json' ),
			) );

			$minecraft_account = wp_remote_post( 'https://api.mojang.com/profiles/minecraft', $post_config );

			if ( 200 !== wp_remote_retrieve_response_code( $minecraft_account ) ) {
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
	 * @param  string $path The path to append to the main plugin directory.
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
	 * @return WooMinecraft A single instance of this class.
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
	 * @throws \Exception Throws an exception if the field is invalid.
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public function __get( $field ) {
		switch ( $field ) {
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
 * @return WooMinecraft
 *
 * @author JayWood
 * @since  1.0.0
 */
function woo_minecraft() {
	return WooMinecraft::get_instance();
}

add_action( 'plugins_loaded', array( woo_minecraft(), 'hooks' ) );
add_action( 'plugins_loaded', array( woo_minecraft(), 'i18n' ) );

/**
 * Determines if an item has commands.
 *
 * @param array $item_data An array of item data from WooCommerce.
 *
 * @TODO Move this function either to a class or helper file.
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
