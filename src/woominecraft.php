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

