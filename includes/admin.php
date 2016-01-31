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

		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_player_name_in_order_meta' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_g_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_g_field' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_v_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'variable_fields_process' ), 10, 2 );
		//add_action( 'woocommerce_product_after_variable_attributes_js', array( $this, 'add_v_field_js' ) );
		//add_action( 'woocommerce_process_product_meta_variable', array( $this, 'variable_fields_process' ) );

		add_action( 'woocommerce_order_status_changed', array( $this, 'delete_sql_data' ), 10, 3 );

		//add_action( 'woocommerce_order_item_line_item_html', array( $this, 'line_item'), 10, 2);

		add_action( 'admin_menu', array( $this, 'setup_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function add_g_field() {
		global $post;
		$meta = get_post_meta( $post->ID, 'minecraft_woo_g', true );
		?>
		<div class="woo_minecraft">
		<p class="title"><?php _e( 'WooMinecraft', 'wmc' ); ?>></p>

		<p class="form-field woo_minecraft">
			<label for="woo_minecraft_general"><?php _e( 'Commands', 'wmc' ); ?></label>
			<input type="button" class="button button-primary woo_minecraft_add" name="Add" id="woo_minecraft_add" value="<?php _e( 'Add', 'wmc' ); ?>"/>
			<input type="button" class="button woo_minecraft_reset" name="Reset" id="woo_minecraft_reset" value="<?php _e( 'Reset Fields', 'wmc' ); ?>"/>
			<img class="help_tip" data-tip="<?php _e( 'Any commands added here, will run on top of variable commands if any. <br /><br />No leading slash is needed.', 'wmc' ); ?> />" src="<?php echo plugins_url( 'help.png', dirname( __FILE__ ) ); ?>" height="16" width="16"/>
				<span class="woo_minecraft_copyme" style="display:none">
					<input type="text" name="minecraft_woo[general][]" value="" class="short" placeholder="<?php _e( 'Use %s for player name', 'wmc' ); ?>"/>
					<input type="button" class="button button-small delete remove_row" value="Delete">
				</span>
			<?php if ( ! empty( $meta ) ) : ?>
				<?php foreach ( $meta as $command ) : ?>
					<span>
							<input type="text" name="minecraft_woo[general][]" value="<?php echo $command; ?>" class="short"/>
							<input type="button" class="button button-small delete remove_row" value="Delete">
						</span>
				<?php endforeach; ?>
			<?php endif; ?>
		</p>
		</div><?php

	}

	public function add_v_field( $loop, $variation_data, $variation ) {
		//$meta = get_post_meta( $variation_data['variation_post_id'], 'minecraft_woo_v', true );
		$meta = array();
		?>
		<tr>
			<td>
				<div class="woo_minecraft_v">
					<p class="title"><?php _e( 'WooMinecraft', 'wmc' ); ?></p>

					<p class="form-field woo_minecraft woo_minecraft_v">
						<label><?php _e( 'Commands', 'wmc' ); ?></label>
						<input type="button" class="button button-primary woo_minecraft_add" name="Add" id="woo_minecraft_add_v" value="<?php _e( 'Add', 'wmc' ); ?>" />
						<input type="button" class="button woo_minecraft_reset" name="Reset" id="woo_minecraft_reset_v" value="<?php _e( 'Reset Fields', 'wmc' ); ?>"/>
						<img class="help_tip" data-tip="<?php _e( 'Use %s for the player\'s name.<br /><br />No leading slash is needed.', 'wmc' ); ?>" src="<?php echo plugins_url( 'help.png', dirname( __FILE__ ) ) ?>" height="16" width="16"/>
				<span class="woo_minecraft_copyme" style="display:none">
					<input type="text" name="minecraft_woo[variable][<?php echo $loop; ?>][]" value="" class="short" placeholder="<?php _e( 'Use %s for player name', 'wmc' ); ?>" />
					<input type="button" class="button button-small delete remove_row" value="Delete">
				</span>
						<?php if ( ! empty( $meta ) ) : ?>
							<?php foreach ( $meta as $command ) : ?>
								<span>
									<input type="text" name="minecraft_woo[variable][<?php echo $loop; ?>][]" value="<?php echo $command; ?>" class="short"/>
									<input type="button" class="button button-small delete remove_row" value="Delete">
								</span>
							<?php endforeach; ?>
						<?php endif; ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

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


	public function display_player_name_in_order_meta( $order ) {
		$playerID = get_post_meta( $order->id, 'player_id', true ) or 'N/A';
		wp_nonce_field( 'woominecraft', 'woo_minecraft_nonce' );
		?>

		<p><strong><?php _e( 'Player Name:', 'wmc' ); ?></strong> <?php echo $playerID; ?></p>
		<?php if ( 'N/A' != $playerID ) : ?>
			<?php global $post; ?>
			<p>
			<input type="button" class="button button-primary" id="resendDonations" value="<?php _e( 'Resend Donations', 'wmc' ); ?>" data-id="<?php echo $playerID; ?>" data-orderid="<?php echo $post->ID; ?>"/>
		<?php endif;
	}

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

	public function line_item( $item_id, $item ) {
		global $post;
		$meta_v = get_post_meta( $item['variation_id'], 'minecraft_woo_v' );
		print_r( $item['variation_id'] );
		if ( ! empty( $meta_v ) ) {
			?>
			<span class="woominecraft resend_item">
					<button class="button button-primary woominecraft_resend_donation" data-orderid="<?php echo $post->ID ?>" data-variation="<?php echo $item['variation_id'] ?>">
						<span><?php _e( 'Resend Donation', 'wmc' ); ?></span>
					</button>
			</span>
			<?php
		}
	}

	public function save_g_field( $postid ) {
		$field = $_POST['minecraft_woo']['general'];
		if ( isset( $field ) && ! empty( $field ) ) {
			update_post_meta( $postid, 'minecraft_woo_g', array_filter( $_POST['minecraft_woo']['general'] ) );
		}
	}

	public function scripts() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script( 'woo_minecraft_js', $this->plugin->url( "assets/js/jquery.woo{$min}.js" ), array( 'jquery' ), '1.0', true );
		wp_register_style( 'woo_minecraft_css', plugins_url( 'style.css', dirname( __FILE__ ) ), false, '1.0' );

		wp_localize_script( 'woo_minecraft_js', 'woominecraft', array(
			'script_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? true : false,
			'confirm' => __( 'This will delete ALL commands, are you sure? This cannot be undone.', 'wmc' ),
		) );

		wp_enqueue_script( 'woo_minecraft_js' );
		wp_enqueue_style( 'woo_minecraft_css' );
	}


	public function setup_admin_page() {
		$output = '<div class="wrap">';
		$output .= '	<h2>'. sprintf( __( ' %s Options', 'wmc' ), 'WooMinecraft' ) .'</h2>';
		$output .= '	<form method="post" action="options.php">';
		ob_start();
		settings_fields( 'woo_minecraft' );
		$output .= ob_get_clean();
		$output .= '	<table class="form-table wide-fat">';
		$output .= '		<tbody>';
		$output .= '			<tr>';
		$output .= '				<th><label for="wm_key">' . __( 'Game Key', 'wmc' ) . '</label></th>';
		$output .= '				<td><input type="text" name="wm_key" id="wm_key" value="' . get_option( 'wm_key' ) . '"/>';
		$output .= '					<p class="description">' . sprintf( __( 'Type %s in-game as op to get your key.', 'wmc' ), '/woo register' ) . '</td>';
		$output .= '			</tr>';
		$output .= '		</tbody>';
		$output .= '	</table>';
		$output .= get_submit_button();
		$output .= '	</form>';
		$output .= '</div>';
		echo $output;
	}

	public function setup_admin_menu() {
		add_options_page( 'Woo Minecraft', 'Woo Minecraft', 'manage_options', 'woominecraft', array( $this, 'setup_admin_page' ) );
	}

	public function admin_init() {
		register_setting( 'woo_minecraft', 'wm_key' );
		$this->install();
	}

	public function uninstall() {
		global $wpdb;
		$query = 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'woo_minecraft';
		$wpdb->query( $query );
	}

	public function update_order_meta( $order_id ) {
		if ( $_POST['player_id'] ) {
			update_post_meta( $order_id, 'player_id', esc_attr( $_POST['player_id'] ) );
		}
	}

	public function variable_fields_process( $post_id, $i ) {
		error_log( print_r( $_POST, 1 ) );
		/*
		$variable_sku     = $_POST['variable_sku'];
		$variable_post_id = $_POST['variable_post_id'];
		$woo_minecraft    = $_POST['minecraft_woo']['variable'];
		for ( $i = 0; $i < sizeof( $variable_sku ); $i ++ ) {
			$variation_id = (int) $variable_post_id[ $i ];
			if ( isset( $woo_minecraft[ $i ] ) ) {
				update_post_meta( $variation_id, 'minecraft_woo_v', array_filter( $woo_minecraft[ $i ] ) );
			}
		}
		*/
	}
}