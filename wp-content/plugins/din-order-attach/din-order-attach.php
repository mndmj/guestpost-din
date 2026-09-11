<?php
/**
 * Plugin Name: DIN Order Attach
 * Description: Protected order attachments for WooCommerce.
 * Version: 1.6.1
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Author: DIN Studio
 * Text Domain: din-order-attach
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIN_ORDER_ATTACH_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-din-order-attach-storage.php';
require_once __DIR__ . '/includes/class-din-order-attach-download.php';
require_once __DIR__ . '/includes/class-din-order-attach.php';

add_action( 'before_woocommerce_init', array( 'DIN_Order_Attach', 'declare_hpos_compatibility' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( 'DIN_Order_Attach', 'render_missing_woocommerce_notice' ) );
			return;
		}

		DIN_Order_Attach::instance()->boot();
	}
);
