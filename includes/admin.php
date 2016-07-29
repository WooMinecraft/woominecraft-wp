<?php

class WCM_Admin {

	/**
	 * @var Woo_Minecraft null
	 */
	private $plugin = null;

	/**
	 * The servers key to store in the database
	 * @var string
	 */
	private $option_key = 'wm_servers';

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_player_id_to_order' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_player_name_in_order_meta' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_group_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_commands' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_commands' ), 10 );

		add_action( 'wp_ajax_wmc_resend_donations', array( $this, 'ajax_handler' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_filter( 'woocommerce_get_settings_general', array( $this, 'wmc_settings' ) );
		add_action( 'woocommerce_admin_field_wmc_servers', array( $this, 'render_servers_section' ) );
		add_action( 'woocommerce_settings_save_general', array( $this, 'save' ) );
	}

	public function save() {
		if ( ! isset( $_POST['wmc_servers'] ) ) {
			return;
		}

		$servers = (array) $_POST['wmc_servers'];
		$output = [];
		foreach ( $servers as $server ) {
			$name = array_key_exists( 'name', $server ) && ! empty( $server['name'] ) ? esc_attr( $server['name'] ) : false;
			$key = array_key_exists( 'key', $server ) && ! empty( $server['key'] ) ? esc_attr( $server['key'] ) : false;
			if ( ! $name || ! $key ) {
				continue;
			}
			$output[] = array(
				'name' => $name,
				'key'  => $key,
			);
		}

		if ( empty( $output ) ) {
			$output[] = array(
				'name' => __( 'Main', 'woominecraft' ),
				'key' => '',
			);
		}

		update_option( $this->option_key, $output );
	}

	/**
	 * Renders the server section of the settings page.
	 * @param $values
	 * @since 1.0.7
	 *
	 * @author JayWood
	 */
	public function render_servers_section( $values ) {
		require_once 'views/server-section.php';
	}

	/**
	 * Add settings section to woocommerce general settings page
	 *
	 * @param array $settings
	 *
	 * @since 1.0.7
	 * @author JayWood
	 * @return array
	 */
	public function wmc_settings( $settings ) {

		$settings[] = array(
			'title' => __( 'WooMinecraft Options', 'woominecraft' ),
			'id' => 'wmc_options',
			'type' => 'title',
		);

		$settings[] = array(
			'type' => 'wmc_servers',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id' => 'wmc_options',
		);

		return $settings;
	}

	/**
	 * Gets all servers and sanitizes their output.
	 *
	 * @since 1.7.0
	 * @author JayWood
	 * @return array
	 */
	public function get_servers() {
		$servers = get_option( $this->option_key, array() );
		if ( empty( $servers ) || ! is_array( $servers ) ) {
			return array(
				'name' => __( 'Main', 'woominecraft' ),
				'key' => '',
			);
		}

		$output = array();

		foreach ( $servers as $server ) {
			$name = isset( $server['name'] ) ? esc_attr( $server['name'] ) : false;
			$key  = isset( $server['key'] ) ? esc_attr( $server['key'] ) : false;

			if ( ! $name || ! $key ) {
				continue;
			}

			$output[] = array(
				'name' => $name,
				'key'  => $key,
			);

		}
		return $output;
	}

	public function ajax_handler() {
		$player_id = isset( $_POST['player_id'] ) ? esc_attr( $_POST['player_id'] ) : false;
		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : false;

		if ( $player_id && $order_id ) {
			$result = $this->plugin->reset_order( $order_id );
			if ( $result > 0 ) {
				wp_send_json_success();
			}
		}

		wp_send_json_error( array( 'msg' => __( 'Cannot reset deliveries for order.', 'woominecraft' ) ) );
	}

	/**
	 * Adds WooMinecraft commands field to the general product meta-box.
	 */
	public function add_group_field() {
		global $post;

		if ( ! isset( $post->ID ) || ! $post instanceof WP_Post ) {
			return;
		}

		$meta = get_post_meta( $post->ID, 'minecraft_woo', true );
		include_once 'views/commands.php';
	}

	/**
	 * Fires for each variation section, in-turn this creates a set of 'command rows' for each variation.
	 *
	 * @param int     $loop
	 * @param array   $variation_data
	 * @param WP_Post $post
	 */
	public function add_variation_field( $loop, $variation_data, $post ) {

		if ( ! $post instanceof WP_Post || ! isset( $post->ID ) ) {
			return;
		}

		$meta = get_post_meta( $post->ID, 'minecraft_woo', true );
		include 'views/commands.php';
	}

	/**
	 * Adds the players ID to the order information screen.
	 * @param WC_Order $order
	 */
	public function display_player_name_in_order_meta( $order ) {

		$player_id = get_post_meta( $order->id, 'player_id', true );

		if ( empty( $player_id ) ) {
			$player_id = 'N/A';
		}
		wp_nonce_field( 'woominecraft', 'woo_minecraft_nonce' );

		?><h3><?php _e( 'WooMinecraft', 'woominecraft' ); ?></h3><?php

		?><p><strong><?php _e( 'Player Name:', 'woominecraft' ); ?></strong> <?php echo $player_id; ?></p>

		<?php if ( 'N/A' != $player_id ) : ?>
			<?php global $post; ?>
			<p><input type="button" class="button button-primary" id="resendDonations" value="<?php _e( 'Resend Donations', 'woominecraft' ); ?>" data-id="<?php echo $player_id; ?>" data-orderid="<?php echo $post->ID; ?>"/></p>
		<?php endif;
	}

	/**
	 * Adds a 'resend item' so administrators can resend single items.
	 *
	 * @param int     $item_id
	 * @param WP_Post $item
	 */
	public function line_item( $item_id, $item ) {
		global $post;
		$post_meta = get_post_meta( $item['variation_id'], 'minecraft_woo_v' );
		if ( ! empty( $post_meta ) ) {
			include_once 'views/resend-item.php';
		}
	}

	/**
	 * Sets up scripts for the administrator pages.
	 *
	 * @param $hook
	 */
	public function scripts( $hook = '' ) {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script( 'woo_minecraft_js', $this->plugin->url( "assets/js/jquery.woo{$min}.js" ), array( 'jquery' ), '1.0', true );
		wp_register_style( 'woo_minecraft_css', plugins_url( 'style.css', dirname( __FILE__ ) ), array( 'woocommerce_admin_styles' ), '1.0' );

		$script_data = array(
			'script_debug'     => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? true : false,
			'confirm'          => __( 'This will delete ALL commands, are you sure? This cannot be undone.', 'woominecraft' ),
			'donations_resent' => __( 'All donations for this order have been resent', 'woominecraft' ),
			'resend'           => __( 'Resend Donations', 'woominecraft' ),
			'must_have_single' => __( 'You must have at least one entry.', 'woominecraft' ),
			'please_wait'      => __( 'Please wait...', 'woominecraft' ),
		);

		if ( 'post.php' == $hook ) {
			global $post;
			if ( isset( $post->ID ) ) {
				$script_data['order_id'] = $post->ID;
				$script_data['player_id'] = get_post_meta( $post->ID, 'player_id', true );
			}
		}

		wp_localize_script( 'woo_minecraft_js', 'woominecraft', $script_data );

		wp_enqueue_script( 'woo_minecraft_js' );
		wp_enqueue_style( 'woo_minecraft_css' );
	}

	/**
	 * Registers the setting key, and installs the database.
	 */
	public function admin_init() {
		register_setting( 'woo_minecraft', $this->option_key );
		$this->maybe_update();
	}

	/**
	 * Updates old DB data to the new layout.
	 *
	 * Usable for ONLY 1.0.4 to 1.0.5 update.
	 * Will remove in 1.0.6
	 *
	 * @deprecated This method is used to force update database information
	 * @internal
	 * @author JayWood
	 */
	private function maybe_update() {

		// Migrate old options to new array set
		if ( $old_key = get_option( 'wm_key' ) ) {
			$new_options = array(
				array(
					'name' => __( 'Main', 'woominecraft' ),
					'key'  => $old_key,
				),
			);

			update_option( $this->option_key, $new_options );
			delete_option( 'wm_key' );
		}

		$is_old_version = get_option( 'wm_db_version', false );
		if ( ! $is_old_version ) {
			return false;
		}

		global $wpdb;
		$results = $wpdb->get_results( "SELECT orderid,delivered FROM {$wpdb->prefix}woo_minecraft" );
		if ( empty( $results ) ) {
			return delete_option( 'wm_db_version' );
		}

		foreach ( $results as $command_object ) {
			$order_id = $command_object->orderid;
			$is_delivered = (bool) $command_object->delivered;
			if ( get_post_meta( $order_id, 'wmc_commands' ) ) {
				error_log( __LINE__ );
				continue;
			}

			$this->plugin->save_commands_to_order( $order_id );
			if ( $is_delivered ) {
				update_post_meta( $order_id, 'wmc_delivered', 1 );
			}
		}

		delete_option( 'wm_db_version' );

		// Drop the entire table now.
		$query = 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'woo_minecraft';
		$wpdb->query( $query );
	}

	/**
	 * Saves the player ID to the order, for use later.
	 * @param int $order_id
	 */
	public function save_player_id_to_order( $order_id ) {
		if ( $_POST['player_id'] ) {
			update_post_meta( $order_id, 'player_id', esc_attr( $_POST['player_id'] ) );
		}
	}

	/**
	 * Saves the general commands to post meta data.
	 */
	public function save_product_commands() {

		if ( ! isset( $_POST['minecraft_woo'] ) ) {
			return;
		}

		$variations = $_POST['minecraft_woo'];
		foreach ( $variations as $id => $commands ) {
			$commands = array_map( 'esc_attr', $commands ); // Escape the commands.
			update_post_meta( intval( $id ), 'minecraft_woo', array_filter( $commands ) );
		}
	}
}
