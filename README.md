# WooMinecraft - WordPress Plugin
**Contributors:** [JayWood](https://github.com/JayWood), [Ethan Smith](https://github.com/TekkitCommando)   
**Tags:** minecraft, woocommerce, donations   
**License:** GPLv2 or later   
**Requires at least:** 4.4.2   
**Tested up to:** 4.6   
**Stable tag:** 1.1  

A FREE Minecraft Donation plugin which works in conjunction with my WooMinecraft java plugin for Minecraft to provide a self-hosted donation platform.

## Description

**Version 1.0.5 is only compatible with WooMinecraft Java plugin version 1.0.6-RC1+ and WILL NOT WORK for earlier versions.**

Contribute to this plugins development on [Github](https://github.com/WooMinecraft/woominecraft-wp) if you'd like.

This plugin works in conjunction with our [WooMinecraft JAVA Plugin](https://github.com/WooMinecraft/WooMinecraft) for Minecraft on GitHub
and is intended for Minecraft servers.  This plugin allows you to add commands to your [WooCommerce](https://wordpress.org/plugins/woocommerce/) products to have
them executed on your server, once a user purchases that product.

We're providing this to you in full faith you'll follow the [Mojang Donation Guidelines](https://mojang.com/2014/06/lets-talk-server-monetisation-the-follow-up-qa/).

Supports the following:
* Multiple commands per product
* Multiple commands per product variation
* MC Username verification ( we only support paid legit accounts via the Mojang API )
* Resend Donations per-order ( should you need to wipe your server ).
* More to come?!

## Frequently Asked Questions

### Does this support recurring payments?
* No, this only provides commands for products.

### Will you support offline servers?
* No, under no circumstances will we ever support offline servers.

### Why does the general command run for a variable product?
This is intended, if you don't want to run the general commands, simply don't fill it in for variable products.

### What payment gateways does this support?
* Anything? Well to be honest, this plugin does not mess with gateways at all. It relies solely on WooCommerce for the
payments - [check google](http://lmgtfy.com/?q###Woocommerce+payment+gateways)

### I just got a donation but it's not being sent!
* The order must be set to 'completed' in order for the order to be sent, that is the ONLY status that we're hooked into.

### This plugin sucks, BuyCraft is better
* Yea sure, we've all use to BuyCraft, but well, it doesn't have the flexibility of Woocommerce, and it's not free... and it's not open source... etc... need I say more?

## Screenshots

![Adding/removing multiple commands for general products.](https://raw.githubusercontent.com/WooMinecraft/woominecraft-wp/dev/screenshot-1.png)   
![Adding commands to single variations.](https://raw.githubusercontent.com/WooMinecraft/woominecraft-wp/dev/screenshot-2.png)

## Changelog

### 1.1
* Multi-server support added in.
* Moved server key settings to WooCommerce->Settings ( near the bottom )
* Added multiple error messages to send back to the client of any errors.

**TBD:**
* Added username column in order listing
* Added delivery column in order listing

### 1.0.8
* Hotfix for [#18](https://github.com/WooMinecraft/woominecraft-wp/issues/18) - Apparently WooCommerce uses the 'key' key as well...

### 1.0.7
* Better error handling for keys, instead of blindly killing over.

### 1.0.6
* Fixed [#96](https://github.com/WooMinecraft/WooMinecraft/issues/96) - Checking wrong post type for cache busting.

### 1.0.5
* Remove custom DB table requirements
* Update plugin to use order meta instead of DB table
* Code cleanup
* Auto-migrates old DB pending deliveries to order meta

### 1.0.4
* Update debugging information by not escaping $_REQUEST that is sent back to java.
* Fixed - Multiple players were getting re-sent donations, this was due to assumptions in posted data.

### 1.0.3
* Security fix - prevents sending back database stored key - Thanks to [FinlayDaG33k](https://github.com/FinlayDaG33k) [PR#15](https://github.com/WooMinecraft/woominecraft-wp/pull/15)

### 1.0.2
* Hotfix for meta data - fixed [#76](https://github.com/WooMinecraft/WooMinecraft/issues/76)

### 1.0.1
* Donations hotfix that was looping over donations infinitely

### 1.0.0
* First official release on .org
* First clean release on Github

## Installation

1. Login and navigate to Plugins &rarr; Add New
2. Type "WooMinecraft-WP" into the Search Input and click the "Search" button.
3. Find WooMinecraft-WP in the list and click "Install Now"
4. Activate the plugin.