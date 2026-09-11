<?php
// Read-only integration assertions against the running WordPress site (studio wp eval-file).
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
function din_site_check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
din_site_check( class_exists( 'DIN_Packages' ), 'Plugin not loaded.' );
global $wpdb;
$table = $wpdb->prefix . 'din_packages';
din_site_check( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table, 'Package table missing.' );
$indexes = $wpdb->get_results( 'SHOW INDEX FROM ' . $table, ARRAY_A );
din_site_check( count( array_filter( $indexes, static function ( $row ) { return 'source_unit' === $row['Key_name'] && 0 === (int) $row['Non_unique']; } ) ) === 2, 'Unique source unit index missing.' );
din_site_check( 80 === has_action( 'woocommerce_process_shop_order_meta', array( 'DIN_Packages_Admin', 'approve_order' ) ), 'Admin gate priority incorrect.' );
din_site_check( false !== has_action( 'woocommerce_store_api_checkout_order_processed', array( 'DIN_Packages_Customer', 'capture_checkout' ) ), 'Block checkout integration missing.' );
din_site_check( false !== has_action( 'woocommerce_checkout_order_created', array( 'DIN_Packages_Customer', 'capture_checkout' ) ), 'Classic checkout integration missing.' );
din_site_check( as_has_scheduled_action( 'din_packages_reconcile', array(), 'din-package-lifecycle' ), 'Reconciliation not scheduled.' );
foreach ( array( 'administrator', 'shop_manager' ) as $role ) {
	$object = get_role( $role );
	din_site_check( $object && $object->has_cap( 'manage_woocommerce' ) && $object->has_cap( 'edit_shop_orders' ), 'Internal role lacks order capability.' );
}
foreach ( array( 91=>90, 83=>51 ) as $annual=>$lifetime ) {
	$config = DIN_Packages::config( wc_get_product( $annual ) );
	din_site_check( 'annual' === $config['period'] && $config['annual_product_id'] === $annual && $config['lifetime_product_id'] === $lifetime, 'Catalog mapping missing.' );
}
echo wp_json_encode( array( 'result'=>'PASS', 'version'=>DIN_PACKAGES_VERSION, 'hpos'=>\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'packages'=>(int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ), 'account_endpoint'=>wc_get_account_endpoint_url('din-packages'), 'timezone'=>wp_timezone_string(), 'checks'=>'schema, unique index, classic/block hooks, admin priority, internal roles, catalog mappings, scheduler' ), JSON_PRETTY_PRINT ) . "\n";
