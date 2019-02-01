<?php
/*
Plugin Name: Minecraft WooCommerce
Plugin URI: http://woominecraft.com
Description: To be used in conjunction with the WooMinecraft Bukkit plugin.  If you do not have it you can get it on the repository at <a href="https://github.com/JayWood/WooMinecraft">Github</a>.  Please be sure and fork the repository and make pull requests.
Author: Jerry Wood
Version: 1.3
License: GPLv2
Text Domain: woominecraft
Domain Path: /languages
Author URI: http://plugish.com
*/

namespace WooMinecraft;

define( 'WMC_INCLUDES', plugin_dir_path( __FILE__ ) . 'includes/' );

/**
 * The legacy class will be removed in
 */
require_once 'legacy/legacy.php';

// Require the helpers file, for use in :allthethings:
require_once WMC_INCLUDES . 'helpers.php';

// Everything to do with the Mojang API.
require_once WMC_INCLUDES . 'mojang.php';
Mojang\setup();

// Handle everything order-related.
require_once WMC_INCLUDES . 'order-manager.php';
Orders\Manager\setup();

// Handle everything order-cache related.
require_once WMC_INCLUDES . 'order-cache-controller.php';
Orders\Cache\setup();

// Load the REST API
require_once WMC_INCLUDES . 'rest-api.php';
REST\setup();
