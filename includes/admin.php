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

		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_player_name_in_order_meta' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_group_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_commands' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_commands' ), 10 );

		add_action( 'wp_ajax_wmc_resend_donations', array( $this, 'ajax_handler' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_filter( 'woocommerce_get_settings_general', array( $this, 'wmc_settings' ) );
		add_action( 'woocommerce_admin_field_wmc_servers', array( $this, 'render_servers_section' ) );
		add_action( 'woocommerce_settings_save_general', array( $this, 'save_servers' ) );
	}

	/**
	 * Saves server keys
	 *
	 * @author JayWood
	 */
	public function save_servers() {
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

		$default_set = array(
			array(
				'name' => __( 'Main', 'woominecraft' ),
				'key' => '',
			)
		);

		$servers = get_option( $this->option_key, array() );
		if ( empty( $servers ) || ! is_array( $servers ) ) {
			return $default_set;
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

	/**
	 * Re-sends orders to players based on player ID and order ID
	 *
	 * @todo: update this for multi-server support
	 *
	 * @author JayWood
	 */
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

		$commands = get_post_meta( $post->ID, 'wmc_commands', true );
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

		$commands = get_post_meta( $post->ID, 'wmc_commands', true );
		include 'views/commands.php';
	}

	/**
	 * Adds the players ID to the order information screen.
	 * @param WC_Order $order
	 */
	public function display_player_name_in_order_meta( $order ) {

		$player_id   = get_post_meta( $order->id, 'player_id', true );
		$servers     = get_option( $this->option_key );
		$post_custom = get_post_custom( $order->ID );

		if ( empty( $player_id ) || empty( $post_custom ) ) {
			// Just show nothing if there's no player ID
			return;
		}

		$option_set = array();
		foreach( $post_custom as $key => $data ) {
			if ( 0 === stripos( $key, '_wmc_commands_' ) ) {
				$server_key = substr( $key, 14, strlen( $key ) );
				$option_set[ $key ] = __( 'Deleted', 'woominecraft' ) . ' ( ' . $server_key . ' )';
				foreach ( $servers as $server ) {
					if ( $server_key == $server['key'] ) {
						$option_set[ $key ] = $server['name'];
						break;
					}
				}
			}
		}

		?>
		<div class="woominecraft order-meta">
			<?php wp_nonce_field( 'woominecraft', 'woo_minecraft_nonce' ); ?>
			<h3><?php _e( 'WooMinecraft', 'woominecraft' ); ?></h3>
			<p>
				<strong><?php _e( 'Player Nanme:', 'woominecraft' ); ?></strong>
				<?php echo $player_id; ?>
			</p>
			<p>
				<select name="" class="woominecraft server-select">
					<option value=""><?php _e( 'Select a Server', 'woominecraft' ); ?></option>
					<?php foreach( $option_set as $k => $v ) : ?>
						<option value="<?php echo $k; ?>"><?php echo $v; ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<input type="button" class="button button-primary" id="resendDonations" value="<?php _e( 'Resend Donations', 'woominecraft' ); ?>" data-id="<?php echo $player_id; ?>" data-orderid="<?php echo $order->ID; ?>"/>
			</p>
		</div>
		<?php
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
		wp_register_script( 'woo_minecraft_js', $this->plugin->url( "assets/js/jquery.woo{$min}.js" ), array( 'jquery' ), '1.1', true );
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
	 * Updates all OLD commands to the new structure.
	 *
	 * @deprecated
	 * @author JayWood
	 */
	private function update_product_commands( $old_key ) {

		$posts = get_posts( array(
			'post_type'   => array( 'product', 'product_variation' ),
			'post_status' => 'any',
			'meta_query'  => array(
				array(
					'key'     => 'minecraft_woo',
					'compare' => 'EXISTS',
				)
			)
		) );

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $product ) {
			$meta = get_post_meta( $product->ID, 'minecraft_woo', true );
			$new_array = array();
			$new_array[ $old_key ] = $meta;
			update_post_meta( $product->ID, 'wmc_commands', $new_array );
			delete_post_meta( $product->ID, 'minecraft_woo' );
		}

	}

	/**
	 * Updates all orders to the new order command structure.
	 *
	 * @deprecated
	 * @param string $old_key
	 *
	 * @author JayWood
	 */
	private function update_order_commands( $old_key ) {
		$posts = get_posts( array(
			'post_type' => 'shop_order',
			'post_status' => 'any',
			'meta_query' => array(
				array(
					'key'     => 'wmc_commands',
					'compare' => 'EXISTS',
				)
			),
		) );

		foreach ( $posts as $post_obj ) {
			$meta = get_post_meta( $post_obj->ID, 'wmc_commands' );
			update_post_meta( $post_obj->ID, '_wmc_commands_' . $old_key, $meta );
			delete_post_meta( $post_obj->ID, 'wmc_commands' );
		}
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

		$is_old_version = get_option( 'wm_db_version', false );
		if ( $is_old_version ) {
			global $wpdb;
			$results = $wpdb->get_results( "SELECT orderid,delivered FROM {$wpdb->prefix}woo_minecraft" );
			if ( empty( $results ) ) {
				delete_option( 'wm_db_version' );
			} else {
				foreach ( $results as $command_object ) {
					$order_id     = $command_object->orderid;
					$is_delivered = (bool) $command_object->delivered;
					if ( get_post_meta( $order_id, 'wmc_commands' ) ) {
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
		}

		// Migrate old options to new array set
		if ( $old_key = get_option( 'wm_key' ) ) {

			$old_key = esc_attr( $old_key );

			$new_options = array(
				array(
					'name' => __( 'Main', 'woominecraft' ),
					'key'  => $old_key,
				),
			);

			$this->update_product_commands( $old_key );
			$this->update_order_commands( $old_key );

			update_option( $this->option_key, $new_options );
			delete_option( 'wm_key' );
		}
	}

	/**
	 * Saves the general commands to post meta data.
	 *
	 * @param int $post_id
	 */
	public function save_product_commands( $post_id = 0 ) {

		if ( ! isset( $_POST['wmc_commands'] ) || empty( $post_id ) ) {
			return;
		}

		$variations = $_POST['wmc_commands'];
		$meta = array();
		foreach ( $variations as $id => $commands ) {
			// Key commands by key.
			$key     = $commands['server'];
			$command = esc_attr( $commands['command'] );
			if ( ! isset( $meta[ $key ] ) ) {
				$meta[ $key ] = array();
			}

			$meta[ $key ][] = $command;
		}

		if ( ! empty( $meta ) ) {
			update_post_meta( $post_id, 'wmc_commands', $meta );
		}
	}
}
