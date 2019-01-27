<?php

require_once WMC_INCLUDES . 'admin.php';

/**
 * Class Woo_Minecraft
 *
 * @todo: Create some way of handling orphaned orders. See Below -
 * If an order is created which had commands tied to a specific server, and that server is later deleted, those commands cannot be re-sent at any time.
 *
 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
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
	const VERSION = '1.2';

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
	 * Creates a transient based on the wmc_key variable
	 *
	 * @since 1.2
	 *
	 * @return string|false The key on success, false if no GET param can be found.
	 */
	private function get_transient_key() {
		$key = sanitize_text_field( $_GET['wmc_key'] ); // @codingStandardsIgnoreLine we don't care, just escape the data.
		if ( ! $key ) {
			return false;
		}

		return $this->command_transient . '_' . $key;
	}

	/**
	 * Produces the JSON Feed for Orders Pending Delivery
	 */
	public function json_feed() {

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

		$output = get_transient( $this->get_transient_key() );

		if ( false === $output || isset( $_GET['delete-trans'] ) ) { // @codingStandardsIgnoreLine Not verifying because we don't need to, just checking if isset.

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

			set_transient( $this->get_transient_key(), $output, 60 * 60 ); // Stores the feed in a transient for 1 hour.
		}

		wp_send_json_success( $output );

	}

	/**
	 * Generates the order JSON data for a single order.
	 *
	 * @param WP_Post $order_post
	 * @param string $key Server key to check against
	 *
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
	 * @return void
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
		global $wpdb;

		if ( ! empty( $post_id ) && 'shop_order' !== get_post_type( $post_id ) ) {
			return;
		}

		$keys = $wpdb->get_col( $wpdb->prepare( "select distinct option_name from {$wpdb->options} where option_name like '%s'", '%' . $this->command_transient . '%' ) ); // @codingStandardsIgnoreLine Have to use this.
		if ( ! $keys ) {
			return;
		}

		foreach ( $keys as $key ) {
			$key = str_replace( '_transient_', '', $key );
			delete_transient( $key );
		}
	}

	/**
	 * Processes all completed commands.
	 *
	 * @author JayWood
	 *
	 * @param string $key
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
		if ( ! wmc_items_have_commands( $items ) || ! function_exists( 'woocommerce_form_field' ) ) {
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
	 * Resets an order from being delivered.
	 *
	 * @param int $order_id
	 * @param string $server_key
	 *
	 * @author JayWood
	 * @return bool
	 */
	public function reset_order( $order_id, $server_key ) {
		delete_post_meta( $order_id, '_wmc_delivered_' . $server_key );
		$this->bust_command_cache( $order_id );

		return true;
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

		if ( false == $mc_json ) { // @codingStandardsIgnoreLine Lose compare is fine here.

			$post_config = apply_filters( 'mojang_profile_api_post_args', array(
				'body'    => json_encode( array( rawurlencode( $player_id ) ) ), // @codingStandardsIgnoreLine Nope, need this.
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
	 * Checks if Minecraft Username is valid
	 *
	 * @return void
	 */
	public function check_player() {
		global $woocommerce;

		if ( ! $woocommerce instanceof WooCommerce ) {
			return;
		}

		$player_id = isset( $_POST['player_id'] ) ? sanitize_text_field( $_POST['player_id'] ) : false; // @codingStandardsIgnoreLine No nonce needed.
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
	 * @param $order_id
	 *
	 * @author JayWood
	 */
	public function save_commands_to_order( $order_id ) {

		$order_data = new WC_Order( $order_id );
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

	public function thanks( $id ) {
		$player_name = get_post_meta( $id, 'player_id', true );
		if ( ! empty( $player_name ) ) {
			?>
			<div class="woo_minecraft"><h4><?php esc_html_e( 'Minecraft Details', 'woominecraft' ); ?></h4>

			<p><strong><?php esc_html_e( 'Username:', 'woominecraft' ); ?></strong><?php echo esc_html( $player_name ); ?></p></div><?php
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

	    // Mark the get_instance call as deprecated.
	    _deprecated_function( __METHOD__, '1.3.0' );

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

/**
 * Load the instanced class.
 *
 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
 *
 * @return Woo_Minecraft
 */
function woo_minecraft() {
    _deprecated_function( __FUNCTION__, '1.3.0' );

	return Woo_Minecraft::get_instance();
}

add_action( 'plugins_loaded', array( woo_minecraft(), 'hooks' ) );
add_action( 'plugins_loaded', array( woo_minecraft(), 'i18n' ) );

/**
 * Determines if any item in the cart has WMC commands attached.
 *
 * @param array $items Cart contents from WooCommerce
 *
 * @return bool
 */
function wmc_items_have_commands( array $items ) {
    // Assume $data is cart contents
    foreach ( $items as $item ) {
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

	return false;
}
