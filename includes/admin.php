<?php

namespace WooMinecraft\Admin;
use WooMinecraft\WooMinecraft;

/**
 * Class WCM_Admin
 *
 * @author JayWood
 * @since 1.0
 * @package WooMinecraft
 * @sub-package: Admin
 */
class WCM_Admin {

	/**
	 * @var \WooMinecraft\WooMinecraft null
	 */
	private $plugin;

	/**
	 * The servers key to store in the database
	 * @var string
	 */
	private $option_key = 'wm_servers';

	/**
	 * WCM_Admin constructor.
	 *
	 * @param WooMinecraft $plugin Instance of the main class.
	 *
	 * @since 1.0.0
	 * @author JayWood
	 */
	public function __construct( WooMinecraft $plugin ) {
		$this->plugin = $plugin;

		$this->hooks();
	}

	/**
	 * Contains all required hooks for this class.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_player_name_in_order_meta' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_group_field' ) );
		add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_simple_commands' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_field' ), 10, 3 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'save_variable_commands' ), 10 );

		add_action( 'wp_ajax_wmc_resend_donations', array( $this, 'ajax_handler' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_filter( 'woocommerce_get_settings_general', array( $this, 'wmc_settings' ) );
		add_action( 'woocommerce_admin_field_wmc_servers', array( $this, 'render_servers_section' ) );
		add_action( 'woocommerce_settings_save_general', array( $this, 'save_servers' ) );

		add_filter( 'manage_shop_order_posts_columns', array( $this, 'add_user_and_deliveries_header' ), 999 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_users_and_deliveries' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_sortable_columns', array( $this, 'make_player_sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_posts_by_player_name' ) );
	}

	/**
	 * Sorts by player name, if query arg is present.
	 *
	 * @param \WP_Query $wp_query The WP_Query object.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @author JayWood
	 */
	public function sort_posts_by_player_name( $wp_query ) {
		if ( ! is_admin() ) {
			return;
		}

		$orderby = $wp_query->get( 'orderby' );
		if ( 'wmc-player' === $orderby ) {
			$wp_query->set( 'meta_key', 'player_id' );
			$wp_query->set( 'orderby', 'meta_value' );
		}

		$player_name = isset( $_GET['wmc-player-name'] ) ? esc_attr( $_GET['wmc-player-name'] ) : false; // @codingStandardsIgnoreLine
		if ( ! empty( $player_name ) ) {
			$wp_query->set( 'meta_key', 'player_id' );
			$wp_query->set( 'meta_value', $player_name );
		}
	}

	/**
	 * Adds the player name to be sorted.
	 *
	 * @param array $columns The array of columns from the post list.
	 *
	 * @return array
	 *
	 * @since 1.0.0
	 * @author JayWood
	 */
	public function make_player_sortable( $columns ) {
		$columns['wmc-player'] = 'wmc-player';

		return $columns;
	}

	/**
	 * Adds column headers to posts for Delivered and Player data.
	 *
	 * @param array $columns An array of columns from the post list.
	 *
	 * @return array
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function add_user_and_deliveries_header( $columns ) {

		$out = [];

		foreach ( $columns as $key => $value ) {
			$out[ $key ] = $value;
			if ( 'order_status' === $key ) {
				$out['wmc-delivered'] = esc_attr__( 'Delivered', 'woominecraft-wp' ) . wc_help_tip( esc_attr__( 'How many servers delivered versus how many still to be delivered.', 'woominecraft-wp' ) );
				$out['wmc-player'] = esc_attr__( 'Player', 'woominecraft-wp' );
			}
		}

		return $out;
	}

	/**
	 * Adds corresponding column data to current row if available.
	 *
	 * @param string  $column  The column name.
	 * @param integer $post_id The post id.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function add_users_and_deliveries( $column, $post_id ) {
		switch ( $column ) {
			case 'wmc-delivered':
				printf( '<span class="wmc-orders-delivered">%s</span>', $this->get_delivered_col_output( $post_id ) ); // @codingStandardsIgnoreLine
				break;
			case 'wmc-player':
				$player_id = get_post_meta( $post_id, 'player_id', true );
				$href      = add_query_arg( 'wmc-player-name', $player_id );
				/** @noinspection HtmlUnknownTarget */
				printf( '<a class="wmc-player-name" href="%2$s">%1$s</a>', esc_attr( $player_id ) ?: ' - ', esc_url( $href ) );
				break;
		}
	}

	/**
	 * Returns a HTML string representing delivered items vs pending items.
	 *
	 * Example: 1/5 - means 1 delivered, 5 pending.
	 *
	 * @param integer $post_id The post ID to check.
	 *
	 * @return string
	 *
	 * @author JayWood
	 * @since  1.0.0
	 */
	private function get_delivered_col_output( $post_id ) {
		$delivered = $this->get_count( $post_id, 'delivered' );
		$pending   = $this->get_count( $post_id );

		return sprintf( "<span class='completed'>%d</span> / <span class='pending'>%d</span>", count( $delivered ), count( $pending ) );
	}

	/**
	 * A wrapper for doing multiple get_post_meta() calls.
	 *
	 * @param integer $order_id The post ID.
	 * @param string  $type     The type to check, either delivered or otherwise.
	 *
	 * @return array|null|object
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	private function get_count( $order_id, $type = '' ) {
		global $wpdb;

		if ( 'delivered' === $type ) {
			$wmc_status = '%' . $wpdb->esc_like( '_wmc_delivered' ) . '%';
		} else {
			$wmc_status = '%' . $wpdb->esc_like( '_wmc_commands' ) . '%';
		}

		return $wpdb->get_results( $wpdb->prepare( "select * from {$wpdb->postmeta} where meta_key like %s and post_id = %d", $wmc_status, $order_id ) );
	}

	/**
	 * Saves server settings.
	 *
	 * @return void
	 *
	 * @author JayWood
	 * @since 1.0.0
	 */
	public function save_servers() {
		if ( ! isset( $_POST['wmc_servers'] ) ) { // @codingStandardsIgnoreLine
			return;
		}

		$servers = (array) $_POST['wmc_servers'];  // @codingStandardsIgnoreLine
		$output  = [];
		foreach ( $servers as $server ) {

			$name = ! empty( $server['name'] ) ? esc_attr( $server['name'] ) : false;
			$key  = ! empty( $server['key'] ) ? esc_attr( $server['key'] ) : false;

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
				'key'  => '',
			);
		}

		update_option( $this->option_key, $output );
	}

	/**
	 * Renders the server section of the settings page.
	 *
	 * Example Array:
	 * <pre>
	 * array(
	 * 		array(
	 * 			'name' => 'Server Name',
	 * 			'key'  => 'somerandomserverpassword',
	 * 		),
	 * )
	 * </pre>
	 *
	 * @param array $values An array of server settings to parse through.
	 *
	 * @return void
	 *
	 * @since  1.0.7
	 * @author JayWood
	 */
	public function render_servers_section( $values ) {
		require_once 'views/server-section.php';
	}

	/**
	 * Add settings section to woocommerce general settings page
	 *
	 * @param array $settings An array of WooCommerce settings.
	 *
	 * @return array
	 *
	 * @since  1.0.7
	 * @author JayWood
	 */
	public function wmc_settings( $settings ) {

		$settings[] = array(
			'title' => __( 'WooMinecraft Options', 'woominecraft' ),
			'id'    => 'wmc_options',
			'type'  => 'title',
		);

		$settings[] = array(
			'type' => 'wmc_servers',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'wmc_options',
		);

		return $settings;
	}

	/**
	 * Gets all servers and sanitizes their output.
	 *
	 * @return array
	 *
	 * @since  1.7.0
	 * @author JayWood
	 */
	public function get_servers() {

		$default_set = array(
			array(
				'name' => __( 'Main', 'woominecraft' ),
				'key'  => '',
			),
		);

		$servers = get_option( $this->option_key, $default_set );
		if ( empty( $servers ) || ! is_array( $servers ) ) {
			return $default_set;
		}

		$output = array();

		foreach ( $servers as $server ) {
			$name = isset( $server['name'] ) ? esc_attr( $server['name'] ) : false;
			$key  = isset( $server['key'] ) ? esc_attr( $server['key'] ) : false;

			if ( ! $name ) {
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
	 * @return void
	 *
	 * @since  1.7.0
	 * @author JayWood
	 */
	public function ajax_handler() {

		$player_id = isset( $_POST['player_id'] ) ? esc_attr( $_POST['player_id'] ) : false; // @codingStandardsIgnoreLine
		$order_id  = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : false; // @codingStandardsIgnoreLine
		$server    = isset( $_POST['server'] ) ? esc_attr( $_POST['server'] ) : false; // @codingStandardsIgnoreLine

		if ( $player_id && $order_id && $server ) {
			$result = $this->plugin->reset_order( $order_id, $server );
			if ( $result > 0 ) {
				wp_send_json_success();
			}
		}

		wp_send_json_error( array( 'msg' => __( 'Cannot reset deliveries for order.', 'woominecraft' ) ) );
	}

	/**
	 * Adds WooMinecraft commands field to the general product meta-box.
	 *
	 * @return void
	 *
	 * @since  1.7.0
	 * @author JayWood
	 */
	public function add_group_field() {
		global $post;

		if ( ! isset( $post->ID ) || ! $post instanceof \WP_Post ) {
			return;
		}

		$commands    = get_post_meta( $post->ID, 'wmc_commands', true );
		$command_key = 'simple';
		$post_id     = $post->ID;

		include_once 'views/commands.php';
	}

	/**
	 * Fires for each variation section, in-turn this creates a set of 'command rows' for each variation.
	 *
	 * @param integer  $loop           The loop for variation data.
	 * @param array    $variation_data The variation data from WooCommerce.
	 * @param \WP_Post $post           The post data from the variation.
	 *
	 * @return void
	 *
	 * @since  1.7.0
	 * @author JayWood
	 */
	public function add_variation_field( $loop, $variation_data, $post ) {

		if ( ! $post instanceof \WP_Post || ! isset( $post->ID ) ) {
			return;
		}

		$commands = get_post_meta( $post->ID, 'wmc_commands', true );
		$command_key = 'variable';
		$post_id = $post->ID;
		include 'views/commands.php';
	}

	/**
	 * Adds the players ID to the order information screen.
	 *
	 * @param \WC_Order $order An instance of the current order being displayed.
	 *
	 * @return void
	 *
	 * @since  1.7.0
	 * @author JayWood
	 */
	public function display_player_name_in_order_meta( $order ) {
		$player_id   = get_post_meta( $order->get_id(), 'player_id', true );
		$servers     = get_option( $this->option_key );
		$post_custom = get_post_custom( $order->get_id() );

		if ( empty( $player_id ) || empty( $post_custom ) ) {
			// Just show nothing if there's no player ID
			return;
		}

		$option_set = array();
		foreach ( $post_custom as $key => $data ) {
			if ( 0 === stripos( $key, '_wmc_commands_' ) ) {
				$server_key                = substr( $key, 14, strlen( $key ) );
				$option_set[ $server_key ] = esc_attr__( 'Deleted', 'woominecraft' ) . ' ( ' . $server_key . ' )';
				foreach ( $servers as $server ) {
					if ( $server_key === $server['key'] ) {
						$option_set[ $server_key ] = $server['name'];
						break;
					}
				}
			}
		}

		?>
		<div class="woominecraft order-meta">
			<?php wp_nonce_field( 'woominecraft', 'woo_minecraft_nonce' ); ?>
			<h3><?php esc_attr_e( 'WooMinecraft', 'woominecraft' ); ?></h3>
			<p>
				<strong><?php esc_attr_e( 'Player Name:', 'woominecraft' ); ?></strong>
				<?php echo esc_attr( $player_id ); ?>
			</p>
			<p>
				<select name="" class="woominecraft wmc-server-select">
					<option value=""><?php esc_attr_e( 'Select a Server', 'woominecraft' ); ?></option>
					<?php foreach ( $option_set as $k => $v ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_attr( $v ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<input type="button" class="button button-primary" id="resendDonations" value="<?php esc_attr_e( 'Resend Donations', 'woominecraft' ); ?>" data-id="<?php echo esc_attr( $player_id ); ?>" data-orderid="<?php echo absint( $order->get_id() ); ?>"/>
			</p>
		</div>
		<?php
	}

	/**
	 * Sets up scripts for the administrator pages.
	 *
	 * @param string $hook The page we're currently hooking into.
	 *
	 * @return void
	 *
	 * @since  1.7.0
	 * @author JayWood
	 */
	public function scripts( $hook = '' ) {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script( 'woo_minecraft_js', $this->plugin->url( "assets/js/jquery.woo{$min}.js" ), array( 'jquery' ), $this->plugin->get_version(), true );
		wp_register_style( 'woo_minecraft_css', plugins_url( 'style.css', dirname( __FILE__ ) ), array( 'woocommerce_admin_styles' ), $this->plugin->get_version() );

		$script_data = array(
			'script_debug'     => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? true : false,
			'confirm'          => __( 'This will delete ALL commands, are you sure? This cannot be undone.', 'woominecraft' ),
			'donations_resent' => __( 'All donations for this order have been resent', 'woominecraft' ),
			'resend'           => __( 'Resend Donations', 'woominecraft' ),
			'must_have_single' => __( 'You must have at least one entry.', 'woominecraft' ),
			'please_wait'      => __( 'Please wait...', 'woominecraft' ),
		);

		if ( 'post.php' === $hook ) {
			global $post;
			if ( isset( $post->ID ) ) {
				$script_data['order_id']  = $post->ID;
				$script_data['player_id'] = get_post_meta( $post->ID, 'player_id', true );
			}
		}

		wp_localize_script( 'woo_minecraft_js', 'woominecraft', $script_data );

		wp_enqueue_script( 'woo_minecraft_js' );
		wp_enqueue_style( 'woo_minecraft_css' );
	}

	/**
	 * Registers the setting key, and installs the database.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 * @author JayWood
	 */
	public function admin_init() {
		register_setting( 'woo_minecraft', $this->option_key );
	}

	/**
	 * Saves Simple commands
	 *
	 * @param integer $post_id The post ID.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @author JayWood
	 */
	public function save_simple_commands( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			return;
		}

		$this->_save_commands( $post_id, 'simple' );
	}

	/**
	 * Saves variable-based commands
	 *
	 * @param integer $post_id The post id.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @author JayWood
	 */
	public function save_variable_commands( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			return;
		}

		$this->_save_commands( $post_id, 'variable' );
	}

	/**
	 * Saves the general commands to post meta data.
	 *
	 * @param integer $post_id     The post ID to save commands against.
	 * @param array   $command_set A linear array of commands to be saved.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 * @author JayWood
	 */
	private function _save_product_commands( $post_id = 0, $command_set = array() ) {

		if ( empty( $command_set ) || empty( $post_id ) ) {
			return;
		}

		$meta       = array();
		foreach ( $command_set as $commands ) {
			// Key commands by key.
			$key     = $commands['server'];
			$command = esc_attr( $commands['command'] );

			// Skip empty command sets.
			if ( empty( $command ) ) {
				continue;
			}

			if ( ! isset( $meta[ $key ] ) ) {
				$meta[ $key ] = array();
			}

			$meta[ $key ][] = $command;
		}

		if ( ! empty( $meta ) ) {
			update_post_meta( $post_id, 'wmc_commands', $meta );
		}
	}

	/**
	 * Saves the commands on a per-product basis.
	 *
	 * @param integer $post_id The post id.
	 * @param string  $type    The type of command to save.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 * @author JayWood
	 */
	private function _save_commands( $post_id, $type ) {
		if ( ! isset( $_POST['wmc_commands'] ) || ! isset( $_POST['wmc_commands'][ $type ] ) ) {  // @codingStandardsIgnoreLine
			return;
		}

		$variable_commands = $_POST['wmc_commands'][ $type ];  // @codingStandardsIgnoreLine
		if ( ! isset( $variable_commands[ 'post_' . $post_id ] ) ) {
			return;
		}

		$this->_save_product_commands( $post_id, $variable_commands[ 'post_' . $post_id ] );
	}
}
