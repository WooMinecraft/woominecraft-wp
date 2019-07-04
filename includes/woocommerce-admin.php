<?php

namespace WooMinecraft\WooCommerce;

/**
 * Returns the namespace'd function name.
 * @param $function
 *
 * @return string
 */
function setup() {
	$n = function ( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'admin_enqueue_scripts', $n( 'admin_scripts' ) );
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

