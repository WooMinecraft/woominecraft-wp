<?php

namespace WooMinecraft\Orders\Manager;

/**
 * Sets up all the things related to Order handling.
 */
function setup() {
	$n = function( $string ) {
		return __NAMESPACE__ . '\\' . $string;
	};
}