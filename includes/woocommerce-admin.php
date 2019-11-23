<?php

namespace WooMinecraft\WooCommerce;

use const WooMinecraft\Helpers\WM_SERVERS;
use function WooMinecraft\Orders\Cache\bust_command_cache;

/**
 * Sets things up.
 */
function setup() {
	$n = function ( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'woocommerce_admin_order_data_after_shipping_address', $n( 'do_resend_donations_field' ) );
	add_action( 'admin_enqueue_scripts', $n( 'admin_scripts' ) );
	add_action( 'woocommerce_product_options_general_product_data', $n( 'add_group_field' ) );
	add_action( 'woocommerce_process_product_meta_simple', $n( 'save_simple_commands' ) );

	add_action( 'woocommerce_product_after_variable_attributes', $n( 'add_variation_field' ), 10, 3 );
	add_action( 'woocommerce_update_product_variation', $n( 'save_variable_commands' ), 10 );

	add_action( 'wp_ajax_wmc_resend_donations', $n( 'ajax_handler' ) );

	add_action( 'admin_init', $n( 'admin_init' ) );

	add_filter( 'woocommerce_get_settings_general', $n( 'wmc_settings' ) );
	add_action( 'woocommerce_admin_field_wmc_servers', $n( 'render_servers_section' ) );
	add_action( 'woocommerce_settings_save_general', $n( 'save_servers' ) );

	add_filter( 'manage_shop_order_posts_columns', $n( 'add_user_and_deliveries_header' ), 999 );
	add_action( 'manage_shop_order_posts_custom_column', $n( 'add_users_and_deliveries' ), 10, 2 );
	add_filter( 'manage_edit-shop_order_sortable_columns', $n( 'make_player_sortable' ) );
	add_action( 'pre_get_posts', $n( 'sort_by_player_name' ) );
}

/**
 * Sorts by player name, if query arg is present.
 *
 * @param \WP_Query $wp_query
 *
 */
function sort_by_player_name( $wp_query ) {
	if ( ! is_admin() ) {
		return;
	}

	$orderby = $wp_query->get( 'orderby' );
	if ( 'wmc-player' === $orderby ) {
		$wp_query->set( 'meta_key', 'player_id' );
		$wp_query->set( 'orderby', 'meta_value' );
	}

	$player_name = isset( $_GET['wmc-player-name'] ) ? esc_attr( $_GET['wmc-player-name'] ) : false;
	if ( ! empty( $player_name ) ) {
		$wp_query->set( 'meta_key', 'player_id' );
		$wp_query->set( 'meta_value', $player_name );
	}
}

/**
 * Adds the player name to be sorted.
 *
 * @param array $columns
 *
 * @return mixed
 */
function make_player_sortable( $columns ) {
	$columns['wmc-player'] = 'wmc-player';

	return $columns;
}

/**
 * Adds column headers to posts for Delivered and Player data.
 *
 * @param $columns
 *
 * @return array
 */
function add_user_and_deliveries_header( $columns ) {
	$out = array();
	foreach ( $columns as $key => $value ) {
		$out[ $key ] = $value;
		if ( 'order_status' === $key ) {
			$out['wmc-delivered'] = __( 'Delivered', 'woominecraft-wp' ) . wc_help_tip( __( 'How many servers delivered versus how many still to be delivered.', 'woominecraft-wp' ) );
			$out['wmc-player']    = __( 'Player', 'woominecraft-wp' );
		}
	}

	return $out;
}

/**
 * Adds corresponding column data to current row if available.
 *
 * @param $column
 * @param $post_id
 */
function add_users_and_deliveries( $column, $post_id ) {
	switch ( $column ) {
		case 'wmc-delivered':
			printf( '<span class="wmc-orders-delivered">%s</span>', get_delivered_col_output( $post_id ) );
			break;
		case 'wmc-player':
			$player_id = get_post_meta( $post_id, 'player_id', true );
			$href      = add_query_arg( 'wmc-player-name', $player_id );
			printf( '<a class="wmc-player-name" href="%2$s">%1$s</a>', $player_id ? $player_id : ' - ', $href );
			break;
		// Ni Hijan
	}

	return;
}

function get_delivered_col_output( $post_id ) {
	$delivered = get_count( $post_id, 'delivered' );
	$pending   = get_count( $post_id );

	return sprintf( "<span class='completed'>%d</span> / <span class='pending'>%d</span>", count( $delivered ), count( $pending ) );
}

/**
 * Pretty much a wrapper for doing multiple get_post_meta() calls
 *
 * @param int    $order_id
 * @param string $type
 *
 * @return array|null
 */
function get_count( $order_id, $type = '' ) {
	global $wpdb;

	$statement = "select * from {$wpdb->postmeta}";
	if ( 'delivered' === $type ) {
		$statement .= $wpdb->prepare( ' where meta_key like %s', '%' . $wpdb->esc_like( '_wmc_delivered' ) . '%' );
	} else {
		$statement .= $wpdb->prepare( ' where meta_key like %s', '%' . $wpdb->esc_like( '_wmc_commands' ) . '%' );
	}

	$statement .= $wpdb->prepare( ' and post_id = %d', intval( $order_id ) );

	return $wpdb->get_results( $statement ); // @codingStandardsIgnoreLine Already prepared.
}

/**
 * Saves server ke
 */
function save_servers() {
	if ( ! isset( $_POST['wmc_servers'] ) ) {
		return;
	}

	$servers = (array) $_POST['wmc_servers'];
	$output  = [];
	foreach ( $servers as $server ) {
		$name = array_key_exists( 'name', $server ) && ! empty( $server['name'] ) ? esc_attr( $server['name'] ) : false;
		$key  = array_key_exists( 'key', $server ) && ! empty( $server['key'] ) ? esc_attr( $server['key'] ) : false;
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

	update_option( WM_SERVERS, $output );
}

/**
 * Renders the server section of the settings page.
 *
 * @param $values
 *
 * @since  1.0.7
 */
function render_servers_section( $values ) {
	require_once 'views/server-section.php';
}

/**
 * Add settings section to WooCommerce general settings page
 *
 * @param array $settings
 *
 * @return array
 * @since  1.0.7
 */
function wmc_settings( $settings ) {

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
 * @return array
 * @since  1.7.0
 */
function get_servers() {

	$default_set = array(
		array(
			'name' => __( 'Main', 'woominecraft' ),
			'key'  => '',
		),
	);

	$servers = get_option( WM_SERVERS, $default_set );
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
 * Re-sends orders
 */
function ajax_handler() {

	$player_id = isset( $_POST['player_id'] ) ? esc_attr( $_POST['player_id'] ) : false;
	$order_id  = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : false;
	$server    = isset( $_POST['server'] ) ? esc_attr( $_POST['server'] ) : false;

	if ( $player_id && $order_id && $server ) {
		$result = \WooMinecraft\Orders\Manager\reset_order( $order_id, $server );
		if ( $result > 0 ) {
			bust_command_cache( $server );
			wp_send_json_success();
		}
	}

	wp_send_json_error( array( 'msg' => __( 'Cannot reset deliveries for order.', 'woominecraft' ) ) );
}

/**
 * Fires for each variation section, in-turn this creates a set of 'command rows' for each variation.
 *
 * @param int      $loop
 * @param array    $variation_data
 * @param \WP_Post $post
 */
function add_variation_field( $loop, $variation_data, $post ) {

	if ( ! $post instanceof \WP_Post || ! isset( $post->ID ) ) {
		return;
	}

	$commands    = get_post_meta( $post->ID, 'wmc_commands', true );
	$command_key = 'variable';
	$post_id     = $post->ID;
	include 'views/commands.php';
}

/**
 * Registers the setting key, and installs the database.
 */
function admin_init() {
	register_setting( 'woo_minecraft', WM_SERVERS );
}

/**
 * Updates all OLD commands to the new structure.
 *
 * @param mixed $old_key
 *
 * @deprecated
 */
function update_product_commands( $old_key ) {

	$commands_query = array(
		'post_type'   => array( 'product', 'product_variation' ),
		'post_status' => 'any',
		'meta_query'  => array(
			array(
				'key'     => 'minecraft_woo',
				'compare' => 'EXISTS',
			),
		),
	);

	$posts = get_posts( $commands_query );

	if ( empty( $posts ) ) {
		return;
	}

	foreach ( $posts as $product ) {
		$meta                  = get_post_meta( $product->ID, 'minecraft_woo', true );
		$new_array             = array();
		$new_array[ $old_key ] = $meta;
		update_post_meta( $product->ID, 'wmc_commands', $new_array );
		delete_post_meta( $product->ID, 'minecraft_woo' );
	}

}

/**
 * Updates all orders to the new order command structure.
 *
 * @param string $old_key
 */
function update_order_commands( $old_key ) {
	$order_commands_query = array(
		'post_type'   => 'shop_order',
		'post_status' => 'any',
		'meta_query'  => array(
			array(
				'key'     => 'wmc_commands',
				'compare' => 'EXISTS',
			),
		),
	);

	$posts = get_posts( $order_commands_query );

	foreach ( $posts as $post_obj ) {
		$meta = get_post_meta( $post_obj->ID, 'wmc_commands' );
		update_post_meta( $post_obj->ID, '_wmc_commands_' . $old_key, $meta );
		delete_post_meta( $post_obj->ID, 'wmc_commands' );
	}
}

/**
 * Saves Simple commands
 *
 * @param int $post_id
 *
 */
function save_simple_commands( $post_id = 0 ) {
	if ( empty( $post_id ) ) {
		return;
	}

	_save_commands( $post_id, 'simple' );
}

/**
 * Saves variable-based commands
 *
 * @param int $post_id
 */
function save_variable_commands( $post_id = 0 ) {
	if ( empty( $post_id ) ) {
		return;
	}

	_save_commands( $post_id, 'variable' );
}

/**
 * Saves the general commands to post meta data.
 *
 * @param int   $post_id The post ID to save commands against
 * @param array $command_set A linear array of commands to be saved
 */
function _save_product_commands( $post_id = 0, $command_set = array() ) {

	if ( empty( $command_set ) || empty( $post_id ) ) {
		return;
	}

	$meta = array();
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
 * @param int    $post_id
 * @param string $type
 *
 */
function _save_commands( $post_id, $type ) {
	if ( ! isset( $_POST['wmc_commands'] ) || ! isset( $_POST['wmc_commands'][ $type ] ) ) {
		return;
	}

	$variable_commands = $_POST['wmc_commands'][ $type ];
	if ( ! isset( $variable_commands[ 'post_' . $post_id ] ) ) {
		return;
	}

	_save_product_commands( $post_id, $variable_commands[ 'post_' . $post_id ] );
}

/**
 * Displays the resend donations field.
 *
 * @param \WC_Order $order The Order object.
 */
function do_resend_donations_field( $order ) {

	$player_id   = get_post_meta( $order->get_id(), 'player_id', true );
	$servers     = get_option( WM_SERVERS );
	$post_custom = get_post_custom( $order->get_id() );

	if ( empty( $player_id ) || empty( $post_custom ) ) {
		// Just show nothing if there's no player ID
		return;
	}

	$option_set = array();
	foreach ( $post_custom as $key => $data ) {
		if ( 0 === stripos( $key, '_wmc_commands_' ) ) {
			$server_key                = substr( $key, 14, strlen( $key ) );
			$option_set[ $server_key ] = __( 'Deleted', 'woominecraft' ) . ' ( ' . $server_key . ' )';
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
		<h3><?php _e( 'WooMinecraft', 'woominecraft' ); ?></h3>
		<p>
			<strong><?php _e( 'Player Name:', 'woominecraft' ); ?></strong>
			<?php echo $player_id; ?>
		</p>
		<p>
			<select name="" class="woominecraft wmc-server-select">
				<option value=""><?php _e( 'Select a Server', 'woominecraft' ); ?></option>
				<?php foreach ( $option_set as $k => $v ) : ?>
					<option value="<?php echo $k; ?>"><?php echo $v; ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<input type="button" class="button button-primary" id="resendDonations" value="<?php _e( 'Resend Donations', 'woominecraft' ); ?>" data-id="<?php echo $player_id; ?>" data-orderid="<?php echo $order->get_id(); ?>"/>
		</p>
	</div>
	<?php
}

/**
 * Loads Scripts.
 *
 * @param string $hook The page hook.
 */
function admin_scripts( $hook = '' ) {
	$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	wp_register_script( 'woo_minecraft_js', WMC_URL . "/assets/js/jquery.woo{$min}.js", array( 'jquery' ), WMC_VERSION, true );
	wp_register_style( 'woo_minecraft_css', WMC_URL . "/style{$min}.css", array( 'woocommerce_admin_styles' ), WMC_VERSION );

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
 * Adds the WooMinecraft commands field to the general product meta-box.
 */
function add_group_field() {
	global $post;

	if ( ! isset( $post->ID ) || ! $post instanceof \WP_Post ) {
		return;
	}

	$commands    = get_post_meta( $post->ID, 'wmc_commands', true );
	$command_key = 'simple';
	$post_id     = $post->ID;
	include_once 'views/commands.php';
}
