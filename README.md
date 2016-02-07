# WooMinecraft - WordPress Plugin
**Contributors:** [JayWood](https://github.com/JayWood), [Ethan Smith](https://github.com/TekkitCommando)   
**Tags:** minecraft, woocommerce, donations   
**License:** GPLv2 or later   
**Requires at least:** 4.4.2   
**Tested up to:** 4.4.2   
**Stable tag:** 1.0.0   

Works in conjunction with the WooMinecraft java plugin for Minecraft to provide a self-hosted donation platform.

## Description

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

### v1.0.0
* First official release on .org
* First clean release on Github

## Installation

1. Login and navigate to Plugins &rarr; Add New
2. Type "WooMinecraft-WP" into the Search Input and click the "Search" button.
3. Find WooMinecraft-WP in the list and click "Install Now"
4. Activate the plugin.