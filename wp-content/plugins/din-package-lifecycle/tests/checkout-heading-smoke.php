<?php
defined( 'STDIN' ) || exit;
define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text, $domain = '' ) { return $text; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return $GLOBALS['buyer']; }
function WC() { return $GLOBALS['wc']; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { add_action( $hook, $callback ); }

class DIN_Packages {
	public static function purchase_option( $id, $action, $buyer ) {
		if ( 7 !== $buyer || ! in_array( $id, array( 10, 11 ), true ) || ! in_array( $action, array( 'lifetime', 'renew_1', 'renew_2' ), true ) ) {
			return new WP_Error();
		}
		return array( 'product_id' => 22, 'package' => array( 'id' => $id, 'heading' => $GLOBALS['heading'] ) );
	}
}

function heading_check( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}

require dirname( __DIR__ ) . '/includes/class-din-packages-customer.php';
DIN_Packages_Customer::boot();
heading_check( isset( $GLOBALS['hooks']['woocommerce_after_order_notes'] ), 'Upgrade Heading Post must render inside Additional information.' );
$GLOBALS['buyer'] = 7;
$GLOBALS['heading'] = "Original <script>alert(1)</script> & heading\nSecond line";
$cart = new class {
	public $cart_contents = array();
	public function get_cart() { return $this->cart_contents; }
};
$GLOBALS['wc'] = (object) array( 'cart' => $cart );
$normal = array( 'product_id' => 22, 'quantity' => 1 );
$upgrade = $normal + array( '_din_package_purchase' => array( 'package_id' => 10, 'action' => 'lifetime' ) );
$render = static function () {
	ob_start();
	call_user_func( $GLOBALS['hooks']['woocommerce_after_order_notes'] );
	return ob_get_clean();
};
$cart->cart_contents = array( $upgrade );
$html = $render();
heading_check( str_contains( $html, 'Heading Post' ) && str_contains( $html, '#10' ) && str_contains( $html, 'Second line' ), 'Upgrade displays source heading and package identity.' );
heading_check( str_contains( $html, '&lt;script&gt;' ) && ! str_contains( $html, '<script>' ) && ! preg_match( '/<(input|textarea)\b/', $html ), 'Source heading is escaped and read-only.' );
heading_check( ! isset( DIN_Packages_Customer::checkout_fields( array( 'order' => array( 'order_comments' => array() ) ) )['order']['order_comments'] ), 'Pure upgrade must not require a new Heading Post.' );
heading_check( '#10: ' . $GLOBALS['heading'] === DIN_Packages_Customer::checkout_data( array( 'order_comments' => 'Forged title' ) )['order_comments'], 'Server still preserves the original heading.' );
$cart->cart_contents[] = $normal;
heading_check( $html === $render(), 'Mixed cart still displays the upgrade heading.' );
$fields = array( 'order' => array( 'order_comments' => array( 'required' => true ) ) );
heading_check( $fields === DIN_Packages_Customer::checkout_fields( $fields ), 'Mixed cart still requires the new publication heading.' );
$second = $upgrade;
$second['_din_package_purchase']['package_id'] = 11;
$cart->cart_contents = array( $upgrade, $second );
heading_check( substr_count( $render(), 'Heading Post' ) === 2 && str_contains( $render(), '#11' ), 'Multiple upgrades retain distinct package headings.' );
foreach ( array( 0, 8 ) as $buyer ) {
	$GLOBALS['buyer'] = $buyer;
	heading_check( '' === $render(), 'Guests and other buyers cannot see source headings.' );
}
$GLOBALS['buyer'] = 7;
$invalid = $upgrade;
$invalid['product_id'] = 99;
$malformed = $normal + array( '_din_package_purchase' => 'invalid' );
$renewal = $upgrade;
$renewal['_din_package_purchase']['action'] = 'renew_1';
foreach ( array( array(), array( $normal ), array( $invalid ), array( $malformed ), array( $renewal ) ) as $items ) {
	$cart->cart_contents = $items;
	heading_check( '' === $render(), 'Empty, ordinary, invalid and renewal-only carts are unchanged.' );
}
$GLOBALS['wc']->cart = null;
heading_check( '' === $render(), 'Missing cart does not render or error.' );
echo "PASS: Upgrade checkout headings, hook placement, escaping, ownership, mixed carts and unchanged source data.\n";
