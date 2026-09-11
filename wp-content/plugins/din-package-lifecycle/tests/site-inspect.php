<?php
// Read-only Studio WP-CLI eval-file diagnostic. Never expose through a web request.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }
$catalog = array();
foreach ( wc_get_products( array( 'limit'=>100, 'status'=>'publish', 'type'=>array( 'simple','variable','variation' ) ) ) as $product ) {
	$catalog[] = array( 'id'=>$product->get_id(), 'name'=>$product->get_name(), 'type'=>$product->get_type(), 'price'=>$product->get_price(), 'billing'=>$product->get_attribute( 'billing' ) ?: $product->get_attribute( 'pa_billing' ), 'config'=>class_exists( 'DIN_Packages' ) ? DIN_Packages::config( $product ) : null );
}
echo wp_json_encode( array( 'site'=>home_url('/'), 'plugins'=>get_option('active_plugins'), 'guest_checkout'=>get_option('woocommerce_enable_guest_checkout'), 'registration'=>get_option('woocommerce_enable_signup_and_login_from_checkout'), 'catalog'=>$catalog ), JSON_PRETTY_PRINT ) . "\n";
global $wpdb;
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'snippets' ) ) ) {
	foreach ( $wpdb->get_results( 'SELECT name,code FROM ' . $wpdb->prefix . 'snippets WHERE active=1' ) as $snippet ) {
		if ( preg_match( '/heading.post|order_comments|customer_note/i', $snippet->code ) ) { echo "SNIPPET " . $snippet->name . "\n" . $snippet->code . "\n"; }
	}
}
