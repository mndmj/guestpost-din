<?php
/** PHP CLI only: real WooCommerce cart/session APIs, in-memory users/products, no site bootstrap. */
define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
define( 'WC_ABSPATH', dirname( __DIR__, 2 ) . '/woocommerce/' );
require ABSPATH . 'wp-includes/plugin.php';
require ABSPATH . 'wp-includes/class-wp-error.php';
require WC_ABSPATH . 'src/Enums/ProductStatus.php';
require WC_ABSPATH . 'src/Enums/ProductType.php';
require WC_ABSPATH . 'includes/abstracts/abstract-wc-session.php';
require WC_ABSPATH . 'includes/class-wc-cart.php';
require dirname( __DIR__ ) . '/includes/class-din-packages-customer.php';

function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function maybe_serialize( $value ) { return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value; }
function maybe_unserialize( $value ) { return is_string( $value ) && preg_match( '/^[aObis]:/', $value ) ? unserialize( $value ) : $value; }
function get_current_user_id() { return $GLOBALS['buyer']; }
function get_current_blog_id() { return 1; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && 'din_package_purchase_' . $_POST['din_package_id'] === $action; }
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? false; }
function get_post_type( $id ) { return 'product'; }
function wc_get_cart_item_data_hash( $product ) { return (string) $product->get_id(); }
function wc_get_product_variation_attributes( $id ) { return array(); }
function wc_array_filter_default_attributes( $value ) { return '' !== $value; }
function wc_format_decimal( $value ) { return (string) $value; }
function wc_string_to_bool( $value ) { return (bool) $value; }
function wc_get_cart_url() { return '/cart/'; }
function wc_get_account_endpoint_url( $endpoint ) { return '/my-account/' . $endpoint . '/'; }
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['notices'][ $type ][] = $message; }
function wc_has_notice( $message, $type = 'success' ) { return in_array( $message, $GLOBALS['notices'][ $type ] ?? array(), true ); }
function wc_setcookie( $name, $value, ...$args ) { $GLOBALS['cookies'][ $name ] = $value; }
function wc_get_logger() { return new class { public function error( $message, $context ) {} }; }
function update_user_meta( $user, $key, $value ) { $GLOBALS['user_meta'][ $user ][ $key ] = $value; }
function WC() { return $GLOBALS['wc']; }
class Cart_Test_Redirect extends Exception {}
function wp_safe_redirect( $url ) { throw new Cart_Test_Redirect( $url ); }
function wp_die( $message, ...$args ) { throw new Cart_Test_Redirect( '403' ); }

class Cart_Test_Product {
	public $available = true;
	public function __construct( public $id, public $price, public $shippable = false ) {}
	public function get_id() { return $this->id; }
	public function is_type( $type ) { return 'simple' === $type; }
	public function get_status() { return 'publish'; }
	public function is_sold_individually() { return false; }
	public function is_purchasable() { return $this->available; }
	public function is_in_stock() { return true; }
	public function has_enough_stock( $quantity ) { return true; }
	public function managing_stock() { return false; }
	public function needs_shipping() { return $this->shippable; }
	public function get_attributes() { return array(); }
}
class Cart_Test_Variation extends Cart_Test_Product {
	public function is_type( $type ) { return 'variation' === $type; }
	public function get_parent_id() { return 333; }
	public function get_variation_attributes() { return array(); }
}
class WC_Tax {
	public static function get_tax_classes() { return array(); }
	public static function get_tax_class_slugs() { return array(); }
}
class DIN_Packages {
	public static function purchase_option( $id, $action, $buyer ) {
		if ( 7 !== $buyer || ! in_array( $id, array( 10, 11 ), true ) || ! in_array( $action, array( 'lifetime', 'renew_1', 'renew_2' ), true ) ) {
			return new WP_Error( 'forbidden', 'Package unavailable.' );
		}
		return array( 'product_id' => 'lifetime' === $action ? 33 : 22, 'package' => array( 'id' => $id ) );
	}
}
class Cart_Test_Session extends WC_Session {}
class Upgrade_Test_Cart extends WC_Cart {
	// Only tax/price calculation is isolated; addition, validation, session and persistence use native code.
	public function calculate_totals() {
		$total = 0;
		foreach ( $this->get_cart() as $item ) { $total += $item['quantity'] * $item['data']->price; }
		$this->set_totals( array( 'total' => $total ) );
		do_action( 'woocommerce_after_calculate_totals', $this );
	}
	public function get_cart_hash() { return md5( serialize( $this->get_cart_for_session() ) ); }
}
function cart_expect( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function cart_fixture( $empty = false ) {
	$GLOBALS['wp_filter'] = array();
	$GLOBALS['wp_actions'] = array( 'wp_loaded' => 1, 'woocommerce_load_cart_from_session' => 1 );
	$GLOBALS['buyer'] = 7;
	$GLOBALS['notices'] = $GLOBALS['user_meta'] = $GLOBALS['cookies'] = array();
	$_COOKIE = array();
	$GLOBALS['products'] = array( 22 => new Cart_Test_Product( 22, 100 ), 33 => new Cart_Test_Product( 33, 300 ), 88 => new Cart_Test_Product( 88, 50, true ) );
	$GLOBALS['wc'] = (object) array( 'session' => new Cart_Test_Session(), 'cart' => new Upgrade_Test_Cart() );
	$cart = WC()->cart;
	add_filter( 'woocommerce_add_cart_item_data', array( 'DIN_Packages_Customer', 'validate_cart_data' ), 20, 4 );
	if ( ! $empty ) {
		$cart->add_to_cart( 88, 2, 0, array(), array( 'custom' => 'keep this metadata' ) );
		$cart->set_applied_coupons( array( 'test-coupon' ) );
		$cart->set_coupon_discount_totals( array( 'test-coupon' => 10 ) );
		$cart->set_coupon_discount_tax_totals( array( 'test-coupon' => 1 ) );
		$cart->set_removed_cart_contents( array( 'undo' => array( 'product_id' => 88, 'quantity' => 1 ) ) );
		$cart->fees_api()->add_fee( array( 'id' => 'test-fee', 'name' => 'Test fee', 'amount' => 5, 'total' => 5 ) );
	}
	$cart->set_session();
	WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate:1' ) );
	WC()->session->set( 'shipping_method_counts', array( 1 ) );
	WC()->session->set( 'previous_shipping_methods', array( 'flat_rate:1' ) );
	WC()->session->set( 'order_awaiting_payment', 99 );
	WC()->session->set( 'store_api_draft_order', 100 );
	WC()->session->set( 'unrelated', 'preserve' );
	return $cart;
}
function cart_state() {
	$cart = WC()->cart;
	return array( $cart->get_cart(), $cart->get_removed_cart_contents(), $cart->get_applied_coupons(), $cart->get_coupon_discount_totals(), $cart->get_coupon_discount_tax_totals(), $cart->get_totals(), serialize( $cart->fees_api()->get_fees() ), WC()->session->get( 'cart' ), WC()->session->get( 'chosen_shipping_methods' ), WC()->session->get( 'order_awaiting_payment' ), WC()->session->get( 'store_api_draft_order' ), $GLOBALS['user_meta'] );
}
function select_package( $action, $id = 10, $nonce = 'valid' ) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = array( 'din_package_id' => $id, 'din_package_action' => $action, '_din_package_nonce' => $nonce );
	try { DIN_Packages_Customer::purchase(); } catch ( Cart_Test_Redirect $redirect ) { return $redirect->getMessage(); }
	return 'no redirect';
}

foreach ( array( 'lifetime' => 33, 'renew_1' => 22, 'renew_2' => 22 ) as $action => $product_id ) {
	foreach ( array( false, true ) as $empty ) {
		$cart = cart_fixture( $empty );
		cart_expect( '/cart/' === select_package( $action ), 'Valid selection redirects to Cart: ' . $action );
		$items = array_values( $cart->get_cart() );
		cart_expect( 1 === count( $items ) && $product_id === $items[0]['product_id'] && 1 === $items[0]['quantity'], 'Selection replaces the whole cart with one product, quantity one: ' . $action );
		cart_expect( array( 'package_id' => 10, 'action' => $action ) === $items[0]['_din_package_purchase'], 'Selected package and duration are preserved: ' . $action );
		cart_expect( array() === $cart->get_applied_coupons() && array() === $cart->get_removed_cart_contents(), 'Successful replacement clears coupons and old undo items.' );
		cart_expect( $cart->get_cart_for_session() === WC()->session->get( 'cart' ) && $cart->get_cart_for_session() === $GLOBALS['user_meta'][7]['_woocommerce_persistent_cart_1']['cart'], 'Replacement survives cart session and persistent-cart reload.' );
		cart_expect( null === WC()->session->get( 'order_awaiting_payment' ) && null === WC()->session->get( 'store_api_draft_order' ), 'New cart detaches old checkout references without deleting orders.' );
		cart_expect( 'preserve' === WC()->session->get( 'unrelated' ), 'Replacement leaves unrelated session state intact.' );
		cart_expect( '/cart/' === select_package( $action ) && 1 === count( $cart->get_cart() ), 'Repeated clicks do not duplicate the selection: ' . $action );
		cart_expect( '/cart/' === select_package( $action, 11 ) && 11 === array_values( $cart->get_cart() )[0]['_din_package_purchase']['package_id'], 'Another selection replaces the old package selection: ' . $action );
	}

	$cart = cart_fixture();
	$GLOBALS['products'][333] = new Cart_Test_Product( 333, 300 );
	$GLOBALS['products'][ $product_id ] = new Cart_Test_Variation( $product_id, 300 );
	cart_expect( '/cart/' === select_package( $action ), 'Variation is added using its parent product and variation ID: ' . $action );
	$variant = array_values( $cart->get_cart() )[0];
	cart_expect( 1 === count( $cart->get_cart() ) && 333 === $variant['product_id'] && $product_id === $variant['variation_id'], 'Replacement preserves native variation binding: ' . $action );

	$cart = cart_fixture( true );
	$cart->persistent_cart_update();
	$before = cart_state();
	$GLOBALS['products'][ $product_id ]->available = false;
	select_package( $action );
	cart_expect( $before === cart_state(), 'Failed selection from an empty cart preserves the previous checkout references: ' . $action );

	foreach ( array( 'unavailable', 'false', 'late_exception', 'late_error', 'session_exception', 'session_error', 'extra_item', 'quantity' ) as $failure ) {
		$cart = cart_fixture();
		$before = cart_state();
		if ( 'unavailable' === $failure ) { $GLOBALS['products'][ $product_id ]->available = false; }
		if ( 'false' === $failure ) { add_filter( 'woocommerce_add_to_cart_quantity', static function () { return 0; } ); }
		if ( 'quantity' === $failure ) { add_filter( 'woocommerce_add_to_cart_quantity', static function () { return 2; } ); }
		if ( 'late_exception' === $failure || 'late_error' === $failure ) {
			add_action( 'woocommerce_add_to_cart', static function () use ( $failure ) { if ( 'late_error' === $failure ) { throw new Error( 'Injected add failure' ); } throw new Exception( 'Injected add failure' ); }, 99 );
		}
		if ( 'session_exception' === $failure || 'session_error' === $failure ) {
			add_action( 'woocommerce_cart_updated', static function () use ( $failure ) { if ( 'session_error' === $failure ) { throw new Error( 'Injected session failure' ); } throw new Exception( 'Injected session failure' ); } );
		}
		if ( 'extra_item' === $failure ) {
			add_action( 'woocommerce_add_to_cart', static function () { WC()->cart->cart_contents['unexpected'] = array( 'product_id' => 88, 'quantity' => 1, 'data' => $GLOBALS['products'][88] ); }, 99 );
		}
		cart_expect( '/my-account/din-packages/' === select_package( $action ), 'Failed selection returns to My Package: ' . $action . '/' . $failure );
		cart_expect( $before === cart_state(), 'Failed selection restores cart, metadata, coupons, totals, session and persistent cart: ' . $action . '/' . $failure );
		cart_expect( ! empty( $GLOBALS['notices']['error'] ), 'Failed selection shows an error: ' . $action . '/' . $failure );
	}

	foreach ( array( 'bad_nonce', 'foreign_package', 'guest' ) as $failure ) {
		$cart = cart_fixture();
		$before = cart_state();
		if ( 'guest' === $failure ) { $GLOBALS['buyer'] = 0; }
		select_package( $action, 'foreign_package' === $failure ? 999 : 10, 'bad_nonce' === $failure ? 'invalid' : 'valid' );
		cart_expect( $before === cart_state(), 'Rejected request cannot change the cart: ' . $action . '/' . $failure );
	}
}

$cart = cart_fixture();
foreach ( array( 'renew_1', 'renew_2', 'lifetime', 'renew_1' ) as $action ) {
	cart_expect( '/cart/' === select_package( $action ), 'Switching duration for the same package succeeds: ' . $action );
	$items = array_values( $cart->get_cart() );
	cart_expect( 1 === count( $items ) && $action === $items[0]['_din_package_purchase']['action'], 'Switching duration leaves only the latest selection: ' . $action );
}
$before = cart_state();
select_package( 'invalid' );
cart_expect( $before === cart_state(), 'An invalid duration leaves the cart untouched.' );

$cart = cart_fixture();
cart_expect( $cart->add_to_cart( 22 ) && 2 === count( $cart->get_cart() ) && array( 'test-coupon' ) === $cart->get_applied_coupons(), 'Normal shopping still keeps other cart items and coupons.' );
echo "PASS: Lifetime and 1/2-year cart replacement, native session/persistence, duration changes, rollback failures, authorization, and unchanged normal shopping.\n";
