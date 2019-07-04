<?php

require_once WMC_INCLUDES . 'class-wcm-admin.php';

/**
 * Class Woo_Minecraft
 * @todo: Create some way of handling orphaned orders. See Below -
 * If an order is created which had commands tied to a specific server, and that server is later deleted, those commands cannot be re-sent at any time.
 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
 * @author JayWood
 */
class Woo_Minecraft {

	/**
	 * Current version
	 * @var  string
	 * @since  0.1.0
	 */
	const VERSION = '1.2';

	/**
	 * URL of plugin directory
	 * @var string
	 * @since  0.1.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory
	 * @var string
	 * @since  0.1.0
	 */
	protected $path = '';

	/**
	 * Plugin basename
	 * @var string
	 * @since  0.1.0
	 */
	protected $basename = '';

	/**
	 * Singleton instance of plugin
	 * @var Woo_Minecraft
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	/**
	 * Instance of the WCM_Admin class
	 * @var WCM_Admin
	 * @since 0.1.0
	 */
	public $admin = null;

	/**
	 * The transient key
	 * @var string
	 */
	private $command_transient = 'wmc-transient-command-feed';

	/**
	 * Sets up our plugin
	 * @since  0.1.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );

		$this->plugin_classes();
	}

	/**
	 * Creates or returns an instance of this class.
	 * @return Woo_Minecraft A single instance of this class.
	 * @since  0.1.0
	 */
	public static function get_instance() {

		// Mark the get_instance call as deprecated.
		// _deprecated_function( __METHOD__, '1.3.0' );

		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Plugin Hooks
	 * Contains all WP hooks for the plugin
	 * @since 0.1.0
	 */
	public function hooks() {

		add_action( 'template_redirect', array( $this, 'json_feed' ) );

		$this->admin->hooks();
	}

	/**
	 * Creates a transient based on the wmc_key variable
	 * @since 1.2
	 */
	private function get_transient_key() {
		_deprecated_function( __METHOD__, '1.3.0', '\WooMinecraft\Orders\Cache\get_transient_key' );
	}

	/**
	 * Produces the JSON Feed for Orders Pending Delivery
	 */
	public function json_feed() {
		if ( empty( $_GET['wmc_key'] ) ) {
			return;
		}

		$key = sanitize_text_field( $_GET['wmc_key'] ); // @codingStandardsIgnoreLine Just sanitize, no nonce needed.

		if ( ! $key ) { // @codingStandardsIgnoreLine No nonce validation needed.
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

		if ( false === array_search( $key, $keys ) ) { // @codingStandardsIgnoreLine I really hate this standard of nonce validation in this context...
			wp_send_json_error( array( 'msg' => 'Invalid key supplied to WordPress, compare your keys.' ) );
		}

		if ( isset( $_REQUEST['processedOrders'] ) ) { // @codingStandardsIgnoreLine No need for nonce here.
			$this->process_completed_commands( $key );
		}

		$output = get_transient( \WooMinecraft\Orders\Cache\get_transient_key() );

		if ( false === $output || isset( $_GET['delete-trans'] ) ) { // @codingStandardsIgnoreLine Not verifying because we don't need to, just checking if isset.

			$orders = get_posts( \WooMinecraft\Helpers\get_order_query_params( $key ) );

			$output = array();

			if ( ! empty( $orders ) ) {
				foreach ( $orders as $wc_order ) {
					if ( ! isset( $wc_order->ID ) ) {
						continue;
					}

					$player_id   = \WooMinecraft\Orders\Manager\get_player_id_for_order( $wc_order );
					$order_array = $this->generate_order_json( $wc_order, $key );

					if ( ! empty( $order_array ) ) {
						if ( ! isset( $output[ $player_id ] ) ) {
							$output[ $player_id ] = array();
						}
						$output[ $player_id ][ $wc_order->ID ] = $order_array;
					}
				}
			}

			set_transient( \WooMinecraft\Orders\Cache\get_transient_key(), $output, 60 * 60 ); // Stores the feed in a transient for 1 hour.
		}

		wp_send_json_success( $output );

	}

	/**
	 * Generates the order JSON data for a single order.
	 *
	 * @param WP_Post $order_post
	 * @param string $key Server key to check against
	 *
	 * @return array|mixed
	 * @author JayWood
	 */
	private function generate_order_json( $order_post, $key ) {

		if ( ! isset( $order_post->ID ) ) {
			return array();
		}

		return get_post_meta( $order_post->ID, '_wmc_commands_' . $key, true );
	}


	/**
	 * Setup Localization
	 * @return void
	 * @since  0.1.0
	 */
	public function i18n() {
		load_plugin_textdomain( 'woominecraft', false, dirname( $this->basename ) . '/languages/' );
	}

	/**
	 * Take special care to sanitize the incoming post data.
	 * While not as simple as running esc_attr, this is still necessary since the JSON
	 * from the Java code comes in escaped, so we need some custom sanitization.
	 *
	 * @param $post_data
	 *
	 * @return array
	 * @author JayWood
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
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function bust_command_cache( $post_id = 0 ) {
		_deprecated_function( __METHOD__, '', '\WooMinecraft\Orders\Cache\bust_command_cache' );
	}

	/**
	 * Processes all completed commands.
	 *
	 * @param string $key
	 *
	 * @author JayWood
	 */
	private function process_completed_commands( $key = '' ) {
		$delivered = '_wmc_delivered_' . $key;
		$order_ids = (array) $this->sanitized_orders_post( $_POST['processedOrders'] ); // @codingStandardsIgnoreLine No need for a nonce.

		if ( empty( $order_ids ) ) {
			wp_send_json_error( array( 'msg' => __( 'Commands was empty', 'woominecraft' ) ) );
		}

		// Set the orders to delivered
		foreach ( $order_ids as $order_id ) {
			update_post_meta( $order_id, $delivered, true );
		}

		\WooMinecraft\Orders\Cache\bust_command_cache();
	}


	/**
	 * Adds a field to the checkout form, requiring the user to enter their Minecraft Name
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function additional_checkout_field( $cart ) {
		_deprecated_function( __METHOD__, '1.3.0', '\WooMinecraft\Orders\Manager\additional_checkout_field' );
	}

	/**
	 * Resets an order from being delivered.
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function reset_order( $order_id, $server_key ) {
		_deprecated_function( __METHOD__, '1.3.0', '\WooMinecraft\Orders\Manager\reset_order' );
	}

	/**
	 * Caches the results of the mojang API based on player ID
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function mojang_player_cache() {
		_deprecated_function( __METHOD__, '1.3.0', '\WooMinecraft\Mojang\get_player_from_cache' );
	}

	/**
	 * Checks if Minecraft Username is valid
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function check_player() {
		_deprecated_function( __METHOD__, '1.3.0', '\WooMinecraft\Mojang\validate_is_paid_player' );
	}

	/**
	 * Updates an order's meta data with the commands hash.
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function save_commands_to_order( $order_id ) {
		_deprecated_function( __METHOD__, '1.3.0', 'WooMinecraft\Orders\Manager\save_commands_to_order()' );
	}

	/**
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function thanks( $id ) {
		_deprecated_function( __METHOD__, '1.3.0', '\WooMinecraft\Orders\Manager\thanks' );
	}

	/**
	 * Plugin classes
	 * @since 0.1.0
	 */
	public function plugin_classes() {
		$this->admin = new WCM_Admin( $this );
	}

	/**
	 * Include a file from the includes directory
	 *
	 * @param string $filename Name of the file to be included
	 *
	 * @return bool    Result of include call.
	 * @since  0.1.0
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
	 * @param string $path (optional) appended path
	 *
	 * @return string       Directory and path
	 * @since  0.1.0
	 */
	public static function dir( $path = '' ) {
		static $dir;
		$dir = $dir ? $dir : trailingslashit( dirname( __FILE__ ) );

		return $dir . $path;
	}

	/**
	 * This plugin's url
	 *
	 * @param string $path (optional) appended path
	 *
	 * @return string       URL and path
	 * @since  0.1.0
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );

		return $url . $path;
	}

	/**
	 * Magic getter for our object.
	 *
	 * @param string $field
	 *
	 * @return mixed
	 * @throws Exception Throws an exception if the field is invalid.
	 * @since  0.1.0
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
 * Load the instanced class.
 * @return Woo_Minecraft
 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
 */
function woo_minecraft() {
	// _deprecated_function( __FUNCTION__, '1.3.0' );

	return Woo_Minecraft::get_instance();
}

add_action( 'plugins_loaded', array( woo_minecraft(), 'hooks' ) );
add_action( 'plugins_loaded', array( woo_minecraft(), 'i18n' ) );

/**
 * Determines if any item in the cart has WMC commands attached.
 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
 */
function wmc_items_have_commands( array $items ) {
	_deprecated_function( __FUNCTION__, '1.3.0', '\WooMinecraft\Helpers\wmc_items_have_commands' );
}
