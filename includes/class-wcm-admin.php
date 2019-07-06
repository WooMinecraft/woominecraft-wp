<?php

/**
 * Class WCM_Admin
 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
 */
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

	/**
	 * Handles the hooks for WordPress and WooCommerce
	 * @deprecated 1.3.0 All APIs should move to using the new APIs outside of the legacy folder.
	 */
	public function hooks() {

		// Add deprecation notice.
		// _deprecated_function( __METHOD__, '1.3.0' );
	}


}
