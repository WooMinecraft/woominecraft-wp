# WooMinecraft - WordPress Plugin

[![Twitter Follow](https://img.shields.io/twitter/follow/plugish.svg?style=for-the-badge&logo=twitter&label=Follow)](https://twitter.com/plugish) ![WordPress](https://img.shields.io/wordpress/v/woominecraft.svg?style=for-the-badge) ![WordPress plugin downloads](https://img.shields.io/wordpress/plugin/dt/woominecraft.svg?style=for-the-badge) ![WordPress plugin version](https://img.shields.io/wordpress/plugin/v/woominecraft.svg?style=for-the-badge) ![](https://img.shields.io/travis/com/WooMinecraft/woominecraft-wp/main?style=for-the-badge)
 
**License:** GPLv2 or later   
**Requires at least:** 4.9   
**Tested up to:** 5.8.2   
**Stable tag:** 1.4.3  

A FREE Minecraft Donation plugin which works in conjunction with my [WooMinecraft java plugin](https://github.com/woominecraft/woominecraft) for Minecraft to provide a self-hosted donation platform.

## Support
[Sponsorships](https://github.com/sponsors/JayWood) are always nice and will help me towards buying a new MacBook pro.

## Description

Contribute to this plugin's development on [Github](https://github.com/WooMinecraft/woominecraft-wp) if you'd like.

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

## Frequently Asked Questions

### Does this support recurring payments?
* No, this only provides commands for products.

### Will you support offline servers?
* No, under no circumstances will we ever support offline servers.

### What payment gateways does this support?
* Anything? Well to be honest, this plugin does not mess with gateways at all. It relies solely on WooCommerce for the
payments - [check google](http://lmgtfy.com/?q###Woocommerce+payment+gateways)

### I just got a donation but it's not being sent!
* The order must be set to 'completed' in order for the order to be sent, that is the ONLY status that we're hooked into.

### This plugin sucks, BuyCraft is better
* Yea sure, we've all use to BuyCraft, but well, it doesn't have the flexibility of Woocommerce, and it's not free... and it's not open source... etc... need I say more?

## Screenshots

![Adding/removing multiple commands for general products.](https://raw.githubusercontent.com/WooMinecraft/woominecraft-wp/main/.wordpress-org/screenshot-1.png)
   _( General Product Commands )_
   
![Adding commands to single variations.](https://raw.githubusercontent.com/WooMinecraft/woominecraft-wp/main/.wordpress-org/screenshot-2.png)
   _( Variable Product Commands )_
   
![Adding Servers](https://raw.githubusercontent.com/WooMinecraft/woominecraft-wp/main/.wordpress-org/screenshot-3.png)
   _( Multi-server Support )_

## Changelog

### 1.4.4
* Fix warning in REST API registration (#101)[https://github.com/WooMinecraft/woominecraft-wp/issues/101]

### 1.4.3
* Deployment changes ( dev-related )
* Adds a check in the checkout sequence for when suer leaves Player field blank.

### 1.4.1
* Removes Mojang API requirements.
* Removes CSS and JS build processes in prep for wp-scripts

### 1.3.0
* Update to utilize the Rest API instead of a generic endpoint.
* Ensure backwards compatibility until 1.4
* Strip some items from the changelog pre 1.1
* Update some admin styles to match latest WooCommerce.

### 1.2
* Fix major bug in multiple-server setups with transient keys.
* Fix major vulnerability in build tools, updated gulp in package.json

### 1.1.1
* Update for WooCommerce 3.3.3
* Testing on WordPress 4.9.2
* Added tooltips to product panel.
* Restored previous placeholder of `give %s apple 1` in command slots.

### 1.1
* **Added** Multi-server support
* **Added** multiple error messages to send back to the client of any errors.
* **Added** username column in order listing, also makes it clickable/sortable
* **Added** delivery column in order listing
* Fixed command row bug with reindexing
* Moved server key settings to WooCommerce->Settings ( near the bottom )
* Resource updates for screenshots etc... 
* **CHANGED** General command now no-longer run on-top of variation commands

## Installation

1. Login and navigate to Plugins &rarr; Add New
2. Type "WooMinecraft-WP" into the Search Input and click the "Search" button.
3. Find WooMinecraft-WP in the list and click "Install Now"
4. Activate the plugin.
