<?php
/**
 * Plugin Name: DIN Package Lifecycle
 * Description: Masa aktif, perpanjangan, dan upgrade paket guest post WooCommerce.
 * Version: 1.0.5
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, din-order-attach
 * Author: DIN Studio
 * Text Domain: din-package-lifecycle
 */

defined( 'ABSPATH' ) || exit;
define( 'DIN_PACKAGES_VERSION', '1.0.5' );
define( 'DIN_PACKAGES_FILE', __FILE__ );
require_once __DIR__ . '/includes/class-din-packages.php';
require_once __DIR__ . '/includes/class-din-packages-requests.php';
require_once __DIR__ . '/includes/class-din-packages-admin.php';
require_once __DIR__ . '/includes/class-din-packages-customer.php';
require_once __DIR__ . '/includes/class-din-packages-mail.php';
require_once __DIR__ . '/includes/class-din-packages-orders.php';

register_activation_hook( __FILE__, static function () {
	DIN_Packages::install();
	add_rewrite_endpoint( 'din-packages', EP_ROOT | EP_PAGES );
	flush_rewrite_rules( false );
} );
add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'DIN_Order_Attach_Storage' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-error"><p>DIN Package Lifecycle requires WooCommerce and DIN Order Attach to be active..</p></div>';
		} );
		return;
	}
	DIN_Packages::boot();
	DIN_Packages_Admin::boot();
	DIN_Packages_Customer::boot();
	DIN_Packages_Mail::boot();
	DIN_Packages_Orders::boot();
}, 20 );
