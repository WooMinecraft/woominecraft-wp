<?php

namespace WooMinecraft\Mojang;

/**
 * Sets up all the things related to mojang.
 */
function setup() {
	$n = function( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};

	add_action( 'woocommerce_checkout_process', $n( 'validate_is_paid_player' ) );
}

/**
 * Checks if Minecraft Username is valid.
 *
 * @since 1.3.0
 *
 * @todo Change Global to use the wc() function instead.
 */
function validate_is_paid_player() {
	global $woocommerce;

	if ( ! $woocommerce instanceof \WooCommerce ) {
		return;
	}

	$player_id = isset( $_POST['player_id'] ) ? sanitize_text_field( $_POST['player_id'] ) : false; // @codingStandardsIgnoreLine No nonce needed.
	$items     = $woocommerce->cart->cart_contents;

	if ( ! \WooMinecraft\Helpers\wmc_items_have_commands( $items ) ) {
		return;
	}

	if ( ! $player_id ) {
		wc_add_notice( __( 'You MUST provide a Minecraft username.', 'woominecraft' ), 'error' );

		return;
	}

	// Grab JSON data
	$mc_json = get_player_from_cache( $player_id );
	if ( ! $mc_json ) {
		wc_add_notice( __( 'We cannot retrieve your account from the Mojang API. Try again later, or contact an administrator.', 'woominecraft' ), 'error' );
	}

	if ( isset( $mc_json->demo ) ) {
		wc_add_notice( __( 'We do not allow unpaid-accounts to make donations, sorry!', 'woominecraft' ), 'error' );

		return;
	}
}


/**
 * Caches the results of the mojang API based on player ID
 * Object is as follows
 * {
 *    "id": "0d252b7218b648bfb86c2ae476954d32",
 *    "name": "CasESensatIveUserName",
 *    "legacy": true,
 *    "demo": true
 * }
 *
 *
 * @param String $player_id Minecraft Username
 * @since 1.3.0
 * @return bool|object False on failure, Object on success
 */
function get_player_from_cache( $player_id ) {

	$key     = md5( 'minecraft_player_' . $player_id );
	$mc_json = wp_cache_get( $key, 'woominecraft' );

	if ( false == $mc_json ) { // @codingStandardsIgnoreLine Lose compare is fine here.

		$post_config = apply_filters(
			'mojang_profile_api_post_args',
			array(
				'body'    => json_encode( array( rawurlencode( $player_id ) ) ), // @codingStandardsIgnoreLine Nope, need this.
				'method'  => 'POST',
				'headers' => array( 'content-type' => 'application/json' ),
			)
		);

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
