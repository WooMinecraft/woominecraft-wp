<?php

/**
 * The Main class for WooCommerce functionality
 *
 * @since 2.0 Rest API integration.
 * @package WooMinecraft-WP
 */

class WCM_WooCommerce {

	/**
	 * @var Woo_Minecraft
	 */
	protected $plugin;

	/**
	 * WCM_WooCommerce constructor.
	 *
	 * @param Woo_Minecraft $plugin
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->hooks();
	}

	public function hooks() {

	}

}