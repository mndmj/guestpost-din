<?php
defined( 'STDIN' ) || exit;
define( 'ABSPATH', __DIR__ );
define( 'DIN_PACKAGES_VERSION', 'test' );
$buyer = 7;
$admin = false;
$account = true;
$endpoint = 'orders';
$hooks = $queries = $orders = $packages = array();
function get_current_user_id() { return $GLOBALS['buyer']; }
function is_admin() { return $GLOBALS['admin']; }
function is_account_page() { return $GLOBALS['account']; }
function is_wc_endpoint_url( $endpoint ) { return $GLOBALS['endpoint'] === $endpoint; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { add_action( $hook, $callback ); }
function absint( $value ) { return abs( (int) $value ); }
function __( $text, $domain = '' ) { return $text; }
function _n( $single, $plural, $count, $domain = '' ) { return 1 === $count ? $single : $plural; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function wp_kses_post( $text ) { return $text; }
function wc_get_order_status_name( $status ) { return ucfirst( $status ); }
function wc_price( $price, $args = array() ) { return '$' . number_format( $price, 2 ); }
function wc_format_datetime( $date ) { return $date->format( 'Y-m-d H:i' ); }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function get_option( $key ) { return 'date_format' === $key ? 'Y-m-d' : 'H:i'; }
function wc_get_order( $id ) { return $GLOBALS['orders'][ (int) $id ] ?? false; }
function wc_get_product( $id ) { return new class( $id ) { public function __construct( public $id ) {} public function get_name() { return 200 === $this->id ? 'Guestpost Lifetime' : 'Guestpost Annual'; } }; }
function wc_get_orders( $args ) {
	$GLOBALS['queries'][] = $args;
	$result = array_filter( $GLOBALS['orders'], static function ( $order ) use ( $args ) {
		return $order->customer === (int) ( $args['customer_id'] ?? $args['customer'] ?? 0 ) && ! in_array( $order->id, $args['exclude'] ?? array(), true );
	} );
	usort( $result, static fn( $left, $right ) => $right->id <=> $left->id );
	$total = count( $result );
	$limit = $args['limit'] ?? 10;
	$result = array_slice( $result, ( ( $args['page'] ?? 1 ) - 1 ) * $limit, $limit );
	return empty( $args['paginate'] ) ? $result : (object) array( 'orders' => $result, 'total' => $total, 'max_num_pages' => (int) ceil( $total / $limit ) );
}
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class Orders_Test_Item {
	public function __construct( public $id, public $purchase = '', public $product = 200, public $quantity = 1 ) {}
	public function get_id() { return $this->id; }
	public function get_meta( $key ) { return '_din_package_purchase' === $key ? $this->purchase : ''; }
	public function get_product_id() { return $this->product; }
	public function get_variation_id() { return 0; }
	public function get_quantity() { return $this->quantity; }
}
class WC_Order {
	public function __construct( public $id, public $items = array(), public $status = 'completed', public $customer = 7 ) {}
	public function get_id() { return $this->id; }
	public function get_customer_id() { return $this->customer; }
	public function get_items() { return $this->items; }
	public function get_order_number() { return $this->id; }
	public function get_status() { return $this->status; }
	public function has_status( $status ) { return in_array( $this->status, (array) $status, true ); }
	public function get_date_created() { return new DateTimeImmutable( '@' . ( 1704067200 + $this->id ) ); }
	public function get_view_order_url() { return '/my-account/view-order/' . $this->id . '/'; }
	public function get_checkout_payment_url() { return '/checkout/order-pay/' . $this->id . '/?key=test'; }
	public function get_formatted_order_total() { return '$100.00'; }
	public function get_total_refunded() { return 'refunded' === $this->status ? 100 : 0; }
	public function get_currency() { return 'USD'; }
	public function get_payment_method_title() { return 'Bank transfer'; }
	public function get_item_count() { return count( $this->items ); }
	public function get_item_count_refunded() { return 0; }
	public function needs_payment() { return in_array( $this->status, array( 'pending', 'failed' ), true ); }
}
class DIN_Packages {
	public static function for_customer( $buyer, $page, $limit ) { return array_slice( array_values( array_filter( $GLOBALS['packages'], static fn( $package ) => $package['customer_id'] === $buyer ) ), ( $page - 1 ) * $limit, $limit ); }
	public static function status( $package ) { return ! empty( $package['stopped_at'] ) ? 'stopped' : $package['period']; }
	public static function purchase_option( $id, $action, $buyer, $check_window = true ) {
		$package = $GLOBALS['packages'][ $id ] ?? null;
		return ! $package || $package['customer_id'] !== $buyer || ! empty( $package['stopped_at'] ) || 'annual' !== $package['period'] ? new WP_Error() : array( 'product_id' => 'lifetime' === $action ? 200 : 100 );
	}
}
class DIN_Packages_Customer {
	public static function render_packages( $page, $limit, $summary, $packages = null ) {
		foreach ( $packages as $package ) { echo '<article>' . esc_html( $package['product_name'] . ' ' . $package['heading'] . ' ' . DIN_Packages::status( $package ) ) . '</article>'; }
	}
}
function orders_expect( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function orders_package( $id, $source, $period = 'annual' ) {
	return array( 'id' => $id, 'customer_id' => 7, 'order_id' => $source, 'period' => $period, 'product_name' => 'Guestpost Annual', 'heading' => '<Post> & original', 'annual_product_id' => 100, 'lifetime_product_id' => 200, 'started_at' => 1704067200, 'expires_at' => 1800000000, 'history' => array() );
}
function orders_purchase( $item, $package, $action = 'lifetime' ) { return new Orders_Test_Item( $item, array( 'package_id' => $package, 'action' => $action ), 'lifetime' === $action ? 200 : 100 ); }
$orders[10] = new WC_Order( 10, array( new Orders_Test_Item( 101 ) ) );
$orders[20] = new WC_Order( 20, array( new Orders_Test_Item( 201 ) ) );
$orders[30] = new WC_Order( 30, array( new Orders_Test_Item( 301 ) ) );
$packages[1] = orders_package( 1, 10, 'lifetime' );
$packages[1]['stopped_at'] = 1800000001;
$packages[1]['history'] = array( array( 'action' => 'lifetime', 'order_id' => 11, 'item_id' => 111, 'at' => 1704067211 ), array( 'action' => 'stopped', 'at' => 1800000001, 'reason' => '<Buyer request>' ) );
$packages[2] = orders_package( 2, 10 );
$packages[3] = orders_package( 3, 20 );
$packages[4] = orders_package( 4, 30, 'lifetime' );
$packages[5] = orders_package( 5, 666 );
$packages[6] = orders_package( 6, 777 ); $packages[6]['customer_id'] = 8;
$orders[11] = new WC_Order( 11, array( orders_purchase( 111, 1 ) ) );
$orders[12] = new WC_Order( 12, array( orders_purchase( 121, 2 ) ), 'pending' );
$orders[14] = new WC_Order( 14, array( orders_purchase( 141, 2 ), new Orders_Test_Item( 142 ) ), 'processing' );
$orders[15] = new WC_Order( 15, array( orders_purchase( 151, 3 ) ), 'cancelled' );
$orders[16] = new WC_Order( 16, array( orders_purchase( 161, 3 ) ), 'failed' );
$orders[17] = new WC_Order( 17, array( orders_purchase( 171, 3 ) ), 'completed' );
$orders[18] = new WC_Order( 18, array( orders_purchase( 181, 2 ), orders_purchase( 182, 3 ) ) );
$orders[19] = new WC_Order( 19, array( orders_purchase( 191, 6 ) ) );
$orders[21] = new WC_Order( 21, array( orders_purchase( 211, 5 ) ) );
$orders[22] = new WC_Order( 22, array( new Orders_Test_Item( 221, 'malformed' ) ) );
$orders[23] = new WC_Order( 23, array( orders_purchase( 231, 3, 'renew_1' ) ) );
$orders[24] = new WC_Order( 24, array( orders_purchase( 241, 1 ) ), 'pending' );
$orders[25] = new WC_Order( 25, array( orders_purchase( 251, '2invalid' ) ), 'pending' );
$orders[26] = new WC_Order( 26, array( orders_purchase( 261, 2, 'invalid' ) ), 'pending' );
$orders[27] = new WC_Order( 27, array( new Orders_Test_Item( 271, array( 'package_id' => 2, 'action' => 'lifetime' ), 200, 2 ) ), 'pending' );
$orders[28] = new WC_Order( 28, array( orders_purchase( 281, '2' ) ), 'pending' );
$orders[777] = new WC_Order( 777, array(), 'completed', 8 );
for ( $order_id = 1000; $order_id < 1103; ++$order_id ) { $orders[ $order_id ] = new WC_Order( $order_id, array( new Orders_Test_Item( $order_id ) ) ); }
$source = dirname( __DIR__ ) . '/includes/class-din-packages-orders.php';
orders_expect( is_file( $source ), 'Unified account-order implementation is missing.' );
require $source;
DIN_Packages_Orders::boot();
orders_expect( isset( $hooks['woocommerce_my_account_my_orders_query'], $hooks['woocommerce_view_order'] ), 'Unified order hooks must be registered.' );
$snapshot = serialize( array( $orders, $packages ) );
$query = DIN_Packages_Orders::orders_query( array( 'customer' => 7, 'page' => 1, 'limit' => 10, 'paginate' => true, 'exclude' => array( 9000 ) ) );
foreach ( array( 11, 12, 15, 16, 17, 23, 24, 28, 9000 ) as $hidden ) { orders_expect( in_array( $hidden, $query['exclude'], true ), 'Pure linked purchase must be excluded before pagination: ' . $hidden ); }
foreach ( array( 10, 20, 30, 14, 18, 19, 21, 22, 25, 26, 27, 1000 ) as $visible ) { orders_expect( ! in_array( $visible, $query['exclude'], true ), 'Root, mixed, invalid or unrelated order must remain visible: ' . $visible ); }
$page = wc_get_orders( $query );
orders_expect( count( $page->orders ) === 10 && $page->max_num_pages === (int) ceil( $page->total / 10 ), 'Pagination counts root/standalone orders rather than filtering rows afterwards.' );
$read_count = count( $queries );
$group = DIN_Packages_Orders::group( 10 );
$badges = DIN_Packages_Orders::badges( $group );
orders_expect( str_contains( $badges, '1 PACKAGE UPGRADED' ) && str_contains( $badges, 'UPGRADE PENDING' ), 'Multi-package root distinguishes applied upgrades and pending upgrades.' );
orders_expect( ! str_contains( DIN_Packages_Orders::badges( DIN_Packages_Orders::group( 20 ) ), 'UPGRADED' ), 'Completed transaction alone must not create a successful-upgrade badge.' );
orders_expect( '' === DIN_Packages_Orders::badges( DIN_Packages_Orders::group( 30 ) ), 'Direct Lifetime purchase never gets an upgrade badge.' );
orders_expect( null === DIN_Packages_Orders::group( 777 ), 'Foreign order data cannot be rendered.' );
orders_expect( count( $queries ) === $read_count, 'Buyer history should only be scanned once per request.' );
$endpoint = 'view-order';
ob_start(); DIN_Packages_Orders::detail( 10 ); $html = ob_get_clean();
orders_expect( str_contains( $html, 'Guestpost Lifetime' ) && str_contains( $html, '&lt;Post&gt;' ) && str_contains( $html, '&lt;Buyer request&gt;' ), 'Current package names and public service history are escaped.' );
orders_expect( str_contains( $html, 'order-pay/12/' ) && ! str_contains( $html, 'order-pay/24/' ), 'Pay Upgrade is available for valid pending packages, not stopped packages.' );
orders_expect( str_contains( $html, 'view-order/11/' ) && str_contains( $html, 'Transaction history' ) && str_contains( $html, 'Service history' ), 'Native transaction details and lifecycle timeline remain available.' );
orders_expect( ! str_contains( $html, '/777/' ) && ! str_contains( $html, 'private' ), 'Only the buyer-owned group is visible.' );
ob_start(); DIN_Packages_Orders::detail( 11 ); $child_html = ob_get_clean();
orders_expect( str_contains( $child_html, 'view-order/10/' ) && ! str_contains( $child_html, 'Transaction history' ), 'Existing child links remain transaction details with a link back to the main order.' );
orders_expect( $snapshot === serialize( array( $orders, $packages ) ), 'Grouping and current-name projection must never mutate transactions or packages.' );
$admin = true;
$original_query = array( 'customer' => 7 );
orders_expect( $original_query === DIN_Packages_Orders::orders_query( $original_query ), 'Admin order queries remain untouched.' );
$admin = false;
$buyer = 0;
orders_expect( null === DIN_Packages_Orders::group( 10 ), 'Guests cannot reuse a cached buyer group.' );
$buyer = 8;
orders_expect( null === DIN_Packages_Orders::group( 10 ), 'Changing buyers cannot reuse another buyer cache.' );
echo "PASS: Unified order grouping, pagination, badge states, source ownership, mixed carts, payment links and unchanged data.\n";
