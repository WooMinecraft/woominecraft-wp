<?php

class WCM_Admin {

	/**
	 * @var Woo_Minecraft null
	 */
	private $plugin = null;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_player_id_to_order' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_player_name_in_order_meta' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_group_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_commands' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_product_commands' ), 10 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'delete_sql_data' ), 10, 3 );

		add_action( 'wp_ajax_wmc_resend_donations', array( $this, 'ajax_handler' ) );

		// TODO: Add per-item resend capability.
		//add_action( 'woocommerce_order_item_line_item_html', array( $this, 'line_item'), 10, 2);

		add_action( 'admin_menu', array( $this, 'setup_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function ajax_handler() {
		error_log( print_r( $_POST, 1 ) );
		wp_send_json_success();
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
	 * Fires when the order status changes.
	 *
	 * @param int    $order_id
	 * @param string $current_status
	 * @param string $new_status
	 */
	public function delete_sql_data( $order_id, $current_status, $new_status ) {
		if ( 'completed' !== $current_status ) {
			return;
		}
		global $wpdb;

		//$orderData  = new WC_Order( $order_id );
		//$items      = $orderData->get_items();
		//$tmpArray   = array();
		//$playername = get_post_meta( $order_id, 'player_id', true );
		$result     = $wpdb->delete( $wpdb->prefix . 'woo_minecraft', array( 'orderid' => $order_id ), array( '%d' ) );
		if ( false === $result ) {
			wp_die( $wpdb->last_error );
		}

	}

	/**
	 * Adds the players ID to the order information screen.
	 * @param WP_Post $order
	 */
	public function display_player_name_in_order_meta( $order ) {
		$playerID = get_post_meta( $order->id, 'player_id', true ) or 'N/A';
		wp_nonce_field( 'woominecraft', 'woo_minecraft_nonce' );
		?><p><strong><?php _e( 'Player Name:', 'wmc' ); ?></strong> <?php echo $playerID; ?></p>
		<?php if ( 'N/A' != $playerID ) : ?>
			<?php global $post; ?>
			<p><input type="button" class="button button-primary" id="resendDonations" value="<?php _e( 'Resend Donations', 'wmc' ); ?>" data-id="<?php echo $playerID; ?>" data-orderid="<?php echo $post->ID; ?>"/></p>
		<?php endif;
	}

	/**
	 * Runs the database installation process
	 */
	public function install() {
		global $wp_version, $wpdb;
		$plugin_ver  = get_option( 'wm_db_version', false );
		$current_ver = $this->plugin->get_version();

		if ( $plugin_ver == $current_ver ) {
			return;
		}

		if ( version_compare( $wp_version, '3.0', '<' ) ) {
			$i18n_error_msg = sprintf( '<div class="error"><strong>%1$s</strong> %2$s</div>', __( 'ERROR:', 'wmc' ), __( 'Plugin requires WordPress v3.1 or higher.', 'wmc' ) );
			die( $i18n_error_msg );
		}

		$tName = $wpdb->prefix . 'woo_minecraft';
		$table = "CREATE TABLE IF NOT EXISTS $tName (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			orderid mediumint(9) NOT NULL,
			postid mediumint(9) NOT NULL,
			delivered TINYINT(1) NOT NULL DEFAULT 0,
			player_name VARCHAR(64) NOT NULL,
			command VARCHAR(128) NOT NULL,
			PRIMARY KEY  (id)
		);";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $table );

		update_option( 'wm_db_version', $current_ver );
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
			'script_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? true : false,
			'confirm' => __( 'This will delete ALL commands, are you sure? This cannot be undone.', 'wmc' ),
			'donations_resent' => __( 'All donations for this order have been resent', 'wmc' ),
		);

		if ( 'post.php' == $hook ) {
			global $post;
			if ( isset( $post->ID ) ) {
				$script_data['order_id'] = $post->ID;
				$script_data['player_id'] = get_post_meta( $post->ID, 'player_id', true );
			}
		}

		error_log( print_r( $script_data, 1 ) );

		wp_localize_script( 'woo_minecraft_js', 'woominecraft', $script_data );

		wp_enqueue_script( 'woo_minecraft_js' );
		wp_enqueue_style( 'woo_minecraft_css' );
	}

	/**
	 * Generates the HTML for the settings page.
	 */
	public function setup_admin_page() {
		include_once 'views/admin-settings.php';
	}

	/**
	 * Adds the menu to the Admin menu.
	 */
	public function setup_admin_menu() {
		add_options_page( 'Woo Minecraft', 'Woo Minecraft', 'manage_options', 'woominecraft', array( $this, 'setup_admin_page' ) );
	}

	/**
	 * Registers the setting key, and installs the database.
	 */
	public function admin_init() {
		register_setting( 'woo_minecraft', 'wm_key' );
		$this->install();
	}

	/**
	 * Drops the database if a user uninstalls the entire plugin.
	 */
	public function uninstall() {
		global $wpdb;
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
			update_post_meta( $id, 'minecraft_woo', array_filter( $commands ) );
		}
	}
}
