<?php
/*
	Plugin Name: Minecraft WooCommerce
	Plugin URI: http://plugish.com/plugins/minecraft_woo
	Description: To be used in conjunction with the minecraft_woo plugin.  If you do not have it you can get it on the repository at <a href="https://github.com/JayWood/WooMinecraft">Github</a>.  Please be sure and fork the repository and make pull requests.
	Author: Jerry Wood
	Version: 0.1.0
	Author URI: http://plugish.com
*/

//include 'inc/admin.class.php';
//include 'inc/main.class.php';


function wmc_autoload_classes( $class_name ) {
	if ( 0 != strpos( $class_name, 'WCM_' ) ) {
		return false;
	}

	$filename = strtolower( str_ireplace(
		array( 'WDSCPN_', '_' ),
		array( '', '-' ),
		$class_name
	) );

	WooMinecraft::include_file( $filename );
	return true;
}

class WooMinecraft{


	/**
	 * Include a file from the includes directory
	 *
	 * @since  0.1.0
	 * @param  string  $filename Name of the file to be included
	 * @return bool    Result of include call.
	 */
	public static function include_file( $filename ) {
		$file = self::dir( 'includes/'. $filename .'.php' );
		if ( file_exists( $file ) ) {
			return include_once( $file );
		}
		return false;
	}

	/**
	 * This plugin's directory
	 *
	 * @since  0.1.0
	 * @param  string $path (optional) appended path
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
	 * @param  string $path (optional) appended path
	 * @return string       URL and path
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );
		return $url . $path;
	}
}

function has_commands( $data ) {
	if ( is_array( $data ) ) {
		// Assume $data is cart contents
		foreach ( $data as $item ) {
			$metag = get_post_meta( $item['product_id'], 'minecraft_woo_g', true );
			$metav = get_post_meta( $item['variation_id'], 'minecraft_woo_v', true );
			if ( empty( $metag ) && empty( $metav ) ) {
				continue;
			} else {
				return true;
			}
		}
	}

	return false;
}

new Woo_Minecraft_Admin;
register_activation_hook( __FILE__, array( 'Woo_Minecraft_Admin', 'install' ) );
register_uninstall_hook( __FILE__, array( 'Woo_Minecraft_Admin', 'uninstall' ) );
new Woo_Minecraft;