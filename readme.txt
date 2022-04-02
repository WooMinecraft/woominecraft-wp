=== WooMinecraft-WP ===
Contributors: jaycodez
Tags: minecraft, woocommerce, donations
Requires at least: 4.4.2
Tested up to: 5.8.2
Stable tag: 1.4.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A FREE Minecraft Donation plugin which works in conjunction with my WooMinecraft java plugin for Minecraft to provide a self-hosted donation platform.

== Description ==

Contribute to this plugins development on [Github](https://github.com/WooMinecraft/woominecraft-wp) if you'd like.

This plugin works in conjunction with our [WooMinecraft JAVA Plugin](https://github.com/WooMinecraft/WooMinecraft) for Minecraft on GitHub
and is intended for Minecraft servers.  This plugin allows you to add commands to your [WooCommerce](https://wordpress.org/plugins/woocommerce/) products to have
them executed on your server, once a user purchases that product.

We're providing this to you in full faith you'll follow the [Mojang Donation Guidelines](https://mojang.com/2014/06/lets-talk-server-monetisation-the-follow-up-qa/).

Supports the following:

* Multiple commands per product
* Multiple commands per product variation
* Multiple Servers
* MC Username verification ( we only support paid legit accounts via the Mojang API )
* Resend Donations per-order ( should you need to wipe your server ).
* More to come?!

== Frequently Asked Questions ==

= Does this support recurring payments? =
No, this only provides commands for products.

= Will you support offline servers? =
No, under no circumstances will we ever support offline servers.

= What payment gateways does this support? =
Anything? Well to be honest, this plugin does not mess with gateways at all. It relies solely on WooCommerce for the
payments - [check google](http://lmgtfy.com/?q=Woocommerce+payment+gateways)

= I just got a donation but it's not being sent! =
The order must be set to 'completed' in order for the order to be sent, that is the ONLY status that we're hooked into.

= This plugin sucks, BuyCraft is better =
Yea sure, we've all use to BuyCraft, but well, it doesn't have the flexibility of Woocommerce, and it's not free... and it's not open source... etc... need I say more?

== Screenshots ==

1. Adding/removing multiple commands for general products.
2. Adding commands to single variations.
3. Adding servers.

== Changelog ==

= 1.4.5 =
* Small readme fixes in automation processes.

= 1.4.4 =
* Fix warning in REST API registration (#101)[https://github.com/WooMinecraft/woominecraft-wp/issues/101]

= 1.4.3 =
* Deployment changes ( dev-related )
* Adds a check in the checkout sequence for when suer leaves Player field blank.

= 1.4.1 =
* Removes Mojang API requirements.
* Removes CSS and JS build processes in prep for wp-scripts

= 1.3.0 =
* Update to utilize the Rest API instead of a generic endpoint.
* Ensure backwards compatibility until 1.4
* Strip some items from the changelog pre 1.1
* Update some admin styles to match latest WooCommerce.

= 1.2 =
* Fix major bug in multiple-server setups with transient keys.
* Fix major vulnerability in build tools, updated gulp in package.json

= 1.1.1 =
* Update for WooCommerce 3.3.3
* Testing on WordPress 4.9.2
* Added tooltips to product panel.
* Restored previous placeholder of `give %s apple 1` in command slots.

= 1.1 =
* **Added** Multi-server support
* **Added** multiple error messages to send back to the client of any errors.
* **Added** username column in order listing, also makes it clickable/sortable
* **Added** delivery column in order listing
* Fixed command row bug with reindexing
* Moved server key settings to WooCommerce->Settings ( near the bottom )
* Resource updates for screenshots etc...
* **CHANGED** General command now no-longer run on-top of variation commands

= 1.0.8 =
* Hotfix for [#18](https://github.com/WooMinecraft/woominecraft-wp/issues/18) - Apparently WooCommerce uses the 'key' key as well...

= 1.0.7 =
* Multi-server support added in.
* Moved server key settings to WooCommerce->Settings ( near the bottom )
* Send messages to the Java world so users/admins are notified of errors.
* Added username column in order listing
* Added delivery column in order listing
* Better error handling for keys, instead of blindly killing over.

= 1.0.6 =
* Fixed [#96](https://github.com/WooMinecraft/WooMinecraft/issues/96) - Checking wrong post type for cache busting.

= 1.0.5 =
* Remove custom DB table requirements
* Update plugin to use order meta instead of DB table
* Code cleanup
* Auto-migrates old DB pending deliveries to order meta

= 1.0.4 =
* Update debugging information by not escaping $_REQUEST that is sent back to java.
* Fixed - Multiple players were getting re-sent donations, this was due to assumptions in posted data.

= 1.0.3 =
* Security fix - prevents sending back database stored key - Thanks to [FinlayDaG33k](https://github.com/FinlayDaG33k) [PR#15](https://github.com/WooMinecraft/woominecraft-wp/pull/15)

= 1.0.2 =
* Hotfix for meta data - fixed [#76](https://github.com/WooMinecraft/WooMinecraft/issues/76)

= 1.0.1 =
* Donations hotfix that was looping over donations infinitely

= 1.0.0 =
* First official release on .org

== Installation ==

1. Login and navigate to Plugins &rarr; Add New
2. Type "WooMinecraft-WP" into the Search Input and click the "Search" button.
3. Find WooMinecraft-WP in the list and click "Install Now"
4. Activate the plugin.

== Upgrade Notice ==
= 1.0.5 =
This version is only compatible with the Java Plugin v1.0.6-RC1+ anything lower will not work with this version.
