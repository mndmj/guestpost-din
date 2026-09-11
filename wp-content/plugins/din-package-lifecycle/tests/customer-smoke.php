<?php
/** Run with PHP CLI; isolated doubles prevent touching buyer data or sending mail. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
$customer_file = dirname( __DIR__ ) . '/includes/class-din-packages-customer.php';
if ( ! is_file( $customer_file ) ) {
	fwrite( STDERR, "FAIL: Customer checkout implementation is missing.\n" );
	exit( 1 );
}
class WP_Error {
	public function __construct( public $code = '', public $message = '' ) {}
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }
function get_current_user_id() { return $GLOBALS['buyer']; }
function absint( $value ) { return abs( (int) $value ); }
function wc_add_notice( $message, $type = '' ) { $GLOBALS['notices'][] = $message; }
function wc_has_notice( $message, $type = '' ) { return in_array( $message, $GLOBALS['notices'], true ); }
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? false; }
function WC() { return $GLOBALS['wc']; }
function wc_format_decimal( $value ) { return (string) $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function wc_get_account_endpoint_url( $endpoint ) { return '/my-account/' . $endpoint . '/'; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function get_option( $key ) { return array( 'date_format' => 'Y-m-d', 'time_format' => 'H:i' )[ $key ] ?? false; }
function wp_date( $format, $timestamp ) { return ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( $format ); }
function wp_nonce_field( $action, $name ) {
	$GLOBALS['nonce_calls'][] = array( $action, $name );
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test">';
}
class WC_Product {
	public function __construct( public $id, public $price ) {}
	public function get_id() { return $this->id; }
	public function get_price( $context = '' ) { return $this->price; }
	public function set_price( $price ) { $this->price = $price; }
}
class DIN_Packages {
	public static function for_customer( $customer, $page, $limit ) { $GLOBALS['package_pages'][] = $page; return array_slice( $GLOBALS['packages'] ?? array(), ( $page - 1 ) * $limit, $limit ); }
	public static function status( $package ) { return $package['status'] ?? 'active'; }
	public static function purchase_option( $id, $action, $buyer, $check_window = true ) {
		if ( 7 !== $buyer || 10 !== $id || ! in_array( $action, array( 'renew_1', 'renew_2', 'lifetime' ), true ) ) {
			return new WP_Error( 'forbidden', 'Paket tidak tersedia.' );
		}
		return array( 'product_id' => 22, 'years' => 'renew_2' === $action ? 2 : 1, 'multiplier' => 'renew_2' === $action ? 2 : 1, 'label' => 'Renewal', 'package' => array( 'id' => 10, 'heading' => 'Original heading', 'order_id' => 55 ) );
	}
	public static function config( $product ) { return array( 'period' => 22 === $product->get_id() ? 'annual' : '', 'annual_product_id' => 22, 'lifetime_product_id' => 33 ); }
}
class Customer_Test_Cart {
	public $cart_contents = array();
	public function get_cart() { return $this->cart_contents; }
	public function set_quantity( $key, $quantity, $refresh = true ) { $this->cart_contents[ $key ]['quantity'] = $quantity; }
}
class Customer_Test_Item {
	public $meta = array();
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function add_meta_data( $key, $value, $unique = false ) { $this->meta[ $key ] = $value; }
}
function customer_expect( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
require $customer_file;
$GLOBALS['buyer'] = 7;
$GLOBALS['notices'] = array();
$GLOBALS['products'] = array( 22 => new WC_Product( 22, '125.50' ) );
$cart = new Customer_Test_Cart();
$GLOBALS['wc'] = (object) array( 'cart' => $cart );
$normal = array( 'product_id' => 22, 'variation_id' => 0, 'quantity' => 1, 'data' => $GLOBALS['products'][22] );
$renewal = $normal + array( '_din_package_purchase' => array( 'package_id' => 10, 'action' => 'renew_2' ) );
customer_expect( ! is_wp_error( DIN_Packages_Customer::option_for_item( $renewal ) ), 'Owner renewal is valid.' );
$GLOBALS['buyer'] = 8;
customer_expect( is_wp_error( DIN_Packages_Customer::option_for_item( $renewal ) ), 'Another customer must not buy this package.' );
$GLOBALS['buyer'] = 0;
customer_expect( is_wp_error( DIN_Packages_Customer::option_for_item( $renewal ) ), 'Guest cannot buy a renewal.' );
$GLOBALS['buyer'] = 7;
$forged = $renewal;
$forged['variation_id'] = 99;
customer_expect( is_wp_error( DIN_Packages_Customer::option_for_item( $forged ) ), 'A forged variation must not get renewal rights.' );
$forged = $renewal;
$forged['quantity'] = 2;
customer_expect( is_wp_error( DIN_Packages_Customer::option_for_item( $forged ) ), 'Quantity two is not a two-year renewal.' );
$forged['_din_package_purchase'] = 'forged';
customer_expect( is_wp_error( DIN_Packages_Customer::option_for_item( $forged ) ), 'Malformed purchase metadata fails closed.' );
$forged = $renewal;
$forged['quantity'] = array( 1 );
customer_expect( is_wp_error( DIN_Packages_Customer::option_for_item( $forged ) ), 'Non-numeric quantity must fail closed instead of coercing an array to one.' );
$cart->cart_contents = array( 'normal' => $normal, 'renewal' => $renewal );
DIN_Packages_Customer::price_cart( $cart );
DIN_Packages_Customer::price_cart( $cart );
customer_expect( 251.0 === (float) $cart->cart_contents['renewal']['data']->get_price(), 'Two-year price is exactly two current catalog years even after repeated totals.' );
customer_expect( 125.5 === (float) $cart->cart_contents['normal']['data']->get_price(), 'Renewal pricing never changes ordinary item sharing the product.' );
customer_expect( 1 === DIN_Packages_Customer::quantity_limit( 9999, $normal['data'], $renewal ), 'Store API quantity is fixed to one.' );
customer_expect( 9999 === DIN_Packages_Customer::quantity_limit( 9999, $normal['data'], $normal ), 'Ordinary Store API quantity limits are unchanged.' );
$item = new Customer_Test_Item();
DIN_Packages_Customer::snapshot_item( $item, 'renewal', $renewal, null );
customer_expect( 'renew_2' === $item->meta['_din_package_purchase']['action'], 'Checkout retains server-validated purchase action.' );
customer_expect( 'Original heading' === $item->meta['_din_package_heading'], 'Renewal preserves source Heading Post.' );
$item = new Customer_Test_Item();
DIN_Packages_Customer::snapshot_item( $item, 'normal', $normal, null );
customer_expect( 'annual' === $item->meta['_din_package_config']['period'], 'Ordinary checkout snapshots the explicit catalog period.' );
customer_expect( method_exists( 'DIN_Packages_Customer', 'account_required' ), 'Configured package cart must require a native checkout account.' );
customer_expect( true === DIN_Packages_Customer::account_required( false ), 'Package cart enables native signup and requires an account.' );
$cart->cart_contents = array( 'unrelated' => array( 'data' => new WC_Product( 88, '50' ) ) );
customer_expect( false === DIN_Packages_Customer::account_required( false ), 'Unrelated products do not change account settings.' );
customer_expect( true === DIN_Packages_Customer::account_required( true ), 'Store account requirement is preserved.' );
$GLOBALS['orders'] = array( 55 => new class { public function get_customer_id() { return 8; } } );
$GLOBALS['packages'] = array( array( 'id' => 10, 'customer_id' => 7, 'order_id' => 55, 'product_name' => 'Private publication', 'heading' => 'Private publication heading', 'period' => 'annual', 'started_at' => 0, 'expires_at' => 0, 'review' => '' ) );
ob_start();
DIN_Packages_Customer::dashboard();
$html = ob_get_clean();
customer_expect( false === strpos( $html, 'Private publication' ), 'Source order ownership change hides all package details, not only the proof link.' );

// Positive rendering catches missing date helpers, unescaped text, and broken actionable forms.
$GLOBALS['orders'][55] = new class {
	public function get_customer_id() { return 7; }
	public function has_status( $status ) { return 'completed' === $status; }
	public function get_order_number() { return 'INV<&55'; }
	public function get_view_order_url() { return 'https://example.test/my-account/view-order/55/?ref=source&proof=1'; }
};
$GLOBALS['packages'][0]['product_name'] = '<script>alert("name")</script>';
$GLOBALS['packages'][0]['heading'] = '<img src=x onerror=alert(1)> & "origin"';
$GLOBALS['packages'][0]['started_at'] = 1704067200;
$GLOBALS['packages'][0]['expires_at'] = 1735689600;
$GLOBALS['nonce_calls'] = array();
ob_start();
DIN_Packages_Customer::dashboard();
$html = ob_get_clean();
$html = preg_replace( '/\s+/u', ' ', $html );
customer_expect( false !== strpos( $html, '&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;' ) && false === strpos( $html, '<script>' ), 'Product title is rendered as escaped text.' );
customer_expect( false !== strpos( $html, '&lt;img src=x onerror=alert(1)&gt; &amp; &quot;origin&quot;' ) && false === strpos( $html, '<img' ), 'Heading Post is rendered as escaped text.' );
customer_expect( false !== strpos( $html, '<time datetime="2024-01-01T00:00:00+00:00">2024-01-01 07:00</time>' ), 'Start time retains UTC machine value and uses site-local display time.' );
customer_expect( false !== strpos( $html, '<time datetime="2025-01-01T00:00:00+00:00">2025-01-01 07:00</time>' ), 'Expiry is displayed with the same site timezone.' );
customer_expect( false !== strpos( $html, 'href="https://example.test/my-account/view-order/55/?ref=source&amp;proof=1"' ) && false !== strpos( $html, 'Lihat order #INV&lt;&amp;55 dan bukti' ), 'The source order and proof link preserves escaped URL and order number.' );
customer_expect( 3 === substr_count( $html, '<form method="post" action="/my-account/din-packages/">' ), 'Each eligible action has its own POST form.' );
customer_expect( 3 === substr_count( $html, 'name="din_package_id" value="10"' ) && 3 === substr_count( $html, 'name="_din_package_nonce"' ), 'All action forms bind the target package and include a nonce.' );
customer_expect( array( array( 'din_package_purchase_10', '_din_package_nonce' ), array( 'din_package_purchase_10', '_din_package_nonce' ), array( 'din_package_purchase_10', '_din_package_nonce' ) ) === $GLOBALS['nonce_calls'], 'Nonce action is scoped to the rendered package, not a global purchase nonce.' );
foreach ( array( 'renew_1', 'renew_2', 'lifetime' ) as $action ) {
	customer_expect( false !== strpos( $html, 'name="din_package_action" value="' . $action . '"' ), 'Eligible action button is submitted explicitly: ' . $action );
}
customer_expect( false !== strpos( $html, 'aria-labelledby="din-package-10"' ) && false !== strpos( $html, 'aria-label="Renewal — Paket #10"' ), 'Cards and action buttons retain accessible package identity.' );
customer_expect( false !== strpos( $html, '>Aktif</span>' ) && preg_match( '/<dd>\s*0 hari\s*<\/dd>/', $html ), 'Status has readable text and past expiry never shows negative remaining days.' );

// Dashboard selection scans past non-running packages, while the dedicated endpoint remains unfiltered.
$sample = $GLOBALS['packages'][0];
$GLOBALS['packages'] = array();
foreach ( array( 'pending', 'expired', 'review', 'active', 'expiring', 'lifetime' ) as $index => $status ) {
	$GLOBALS['packages'][] = array_merge( $sample, array( 'id' => $index + 1, 'status' => $status ) );
}
customer_expect( array( 4, 5, 6 ) === array_column( DIN_Packages_Customer::running_packages( 7 ), 'id' ), 'Only active, expiring, and Lifetime packages qualify for the dashboard.' );
$GLOBALS['packages'] = array_fill( 0, 101, array_merge( $sample, array( 'status' => 'expired' ) ) );
$GLOBALS['packages'][] = array_merge( $sample, array( 'id' => 102, 'status' => 'active' ) );
$GLOBALS['package_pages'] = array();
customer_expect( array( 102 ) === array_column( DIN_Packages_Customer::running_packages( 7 ), 'id' ) && array( 1, 2 ) === $GLOBALS['package_pages'], 'An older active package beyond the first 100 records must remain visible.' );
$GLOBALS['packages'] = array(
	array_merge( $sample, array( 'customer_id' => 8 ) ),
	array_merge( $sample, array( 'order_id' => 999 ) ),
	array_merge( $sample, array( 'order_id' => 56 ) ),
);
$GLOBALS['orders'][56] = new class { public function get_customer_id() { return 7; } public function has_status( $status ) { return false; } };
customer_expect( array() === DIN_Packages_Customer::running_packages( 7 ), 'Foreign ownership, deleted source, and unfinished source cannot trigger the package panel.' );
$GLOBALS['packages'] = array( array_merge( $sample, array( 'status' => 'expired', 'product_name' => 'Expired service' ) ) );
ob_start(); DIN_Packages_Customer::dashboard(); $empty_dashboard = ob_get_clean();
customer_expect( '' === $empty_dashboard, 'No running packages must not emit an empty package section on the dashboard.' );
ob_start(); DIN_Packages_Customer::account(); $all_packages = ob_get_clean();
customer_expect( false !== strpos( $all_packages, 'Expired service' ), 'My Package endpoint must still display non-running packages.' );

$fields = array( 'order' => array( 'order_comments' => array( 'required' => true, 'label' => 'Heading Post' ), 'other_order_field' => array( 'required' => true ) ), 'billing' => array( 'billing_email' => array( 'required' => true ) ) );
$posted = array( 'order_comments' => 'New publication heading', 'billing_email' => 'buyer@example.test' );
$cart->cart_contents = array( 'renewal' => $renewal );
$renewal_fields = DIN_Packages_Customer::checkout_fields( $fields );
customer_expect( ! isset( $renewal_fields['order']['order_comments'] ), 'Pure renewal does not ask for a new publication Heading Post.' );
customer_expect( $fields['billing'] === $renewal_fields['billing'] && $fields['order']['other_order_field'] === $renewal_fields['order']['other_order_field'], 'Pure renewal leaves unrelated checkout field requirements intact.' );
customer_expect( array( 'order_comments' => '#10: Original heading', 'billing_email' => 'buyer@example.test' ) === DIN_Packages_Customer::checkout_data( $posted ), 'Pure renewal replaces user-supplied publication text with the source heading and retains billing data.' );
$cart->cart_contents = array( 'renewal' => $renewal, 'normal' => $normal );
customer_expect( $fields === DIN_Packages_Customer::checkout_fields( $fields ), 'Mixed cart retains the store-required Heading Post field.' );
customer_expect( $posted === DIN_Packages_Customer::checkout_data( $posted ), 'Mixed cart retains the buyer heading for the new publication.' );
$cart->cart_contents = array();
customer_expect( $fields === DIN_Packages_Customer::checkout_fields( $fields ) && $posted === DIN_Packages_Customer::checkout_data( $posted ), 'Empty cart does not override checkout requirements or data.' );
echo "PASS: Customer ownership, quantity, pricing, snapshots, complete escaped card rendering, and pure/mixed checkout headings.\n";
