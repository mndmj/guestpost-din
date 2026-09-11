<?php
/** Run with PHP CLI. Real dashboard/template code; in-memory data only, no database or mail. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'DASHBOARD_TEMPLATE', dirname( __DIR__, 3 ) . '/themes/hello-elementor-child/woocommerce/myaccount/dashboard.php' );
set_error_handler( static function ( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );

function dashboard_expect( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
}
function __( $text, $domain = '' ) { return $text; }
function get_current_user_id() { return 7; }
function get_avatar( ...$args ) { return ''; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ); }
function wp_kses_post( $value ) { return $value; }
function wp_kses( $value, $allowed ) { return $value; }
function wc_logout_url() { return '/logout/'; }
function wc_get_account_endpoint_url( $endpoint ) { return '/my-account/' . $endpoint . '/'; }
function wc_get_endpoint_url( $endpoint ) { return wc_get_account_endpoint_url( $endpoint ); }
function wc_shipping_enabled() { return false; }
function wc_get_order_status_name( $status ) { return ucfirst( $status ); }
function get_option( $key ) { return array( 'date_format' => 'Y-m-d', 'time_format' => 'H:i' )[ $key ] ?? false; }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function wc_format_datetime( $date ) { return $date->date( 'Y-m-d' ); }
function gpm_render_notification_list( $notifications ) {}
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = $callback;
}
function remove_action( $hook, $callback, $priority = 10 ) {
	foreach ( $GLOBALS['hooks'][ $hook ][ $priority ] ?? array() as $key => $registered ) {
		if ( $registered === $callback ) { unset( $GLOBALS['hooks'][ $hook ][ $priority ][ $key ] ); }
	}
}
function do_action( $hook ) {
	$GLOBALS['hook_calls'][ $hook ] = ( $GLOBALS['hook_calls'][ $hook ] ?? 0 ) + 1;
	$callbacks = $GLOBALS['hooks'][ $hook ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $priority ) { foreach ( $priority as $callback ) { $callback(); } }
}

class Dashboard_Test_Date extends DateTimeImmutable {
	public function date( $format ) { return $this->format( $format ); }
}
class WC_Order {
	public function __construct( public $id, public $status, public $customer = 7 ) {}
	public function get_id() { return $this->id; }
	public function get_customer_id() { return $this->customer; }
	public function get_status() { return $this->status; }
	public function has_status( $status ) { return in_array( $this->status, (array) $status, true ); }
	public function needs_payment() { return $this->has_status( array( 'pending', 'failed' ) ); }
	public function get_order_number() { return $this->id; }
	public function get_view_order_url() { return '/my-account/view-order/' . $this->id . '/'; }
	public function get_checkout_payment_url() { return '/my-account/order-pay/' . $this->id . '/'; }
	public function get_formatted_order_total() { return '$100.00'; }
	public function get_date_created() { return new Dashboard_Test_Date( '@' . ( 1704067200 + $this->id ) ); }
	public function get_payment_method_title() { return 'Bank transfer'; }
}
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function wc_get_orders( $args ) {
	// Publication metadata is absent from these dashboard fixtures.
	$orders = empty( $args['meta_query'] ) ? array_values( $GLOBALS['orders'] ) : array();
	$orders = array_values( array_filter( $orders, static function ( $order ) use ( $args ) {
		return ( ! isset( $args['customer_id'] ) || $order->get_customer_id() === $args['customer_id'] )
			&& ( ! isset( $args['status'] ) || $order->has_status( $args['status'] ) );
	} ) );
	usort( $orders, static fn( $left, $right ) => $right->id <=> $left->id );
	$total = count( $orders );
	if ( isset( $args['limit'] ) && $args['limit'] >= 0 ) { $orders = array_slice( $orders, 0, $args['limit'] ); }
	if ( ( $args['return'] ?? '' ) === 'ids' ) { $orders = array_map( static fn( $order ) => $order->id, $orders ); }
	return empty( $args['paginate'] ) ? $orders : (object) array( 'orders' => $orders, 'total' => $total );
}
function wc_get_customer_order_count( $customer ) { return count( wc_get_orders( array( 'customer_id' => $customer ) ) ); }
class DIN_Packages {
	public static function for_customer( $customer, $page, $limit ) {
		return array_slice( $GLOBALS['packages'], ( $page - 1 ) * $limit, $limit );
	}
	public static function status( $package ) { return $package['status']; }
	public static function purchase_option( ...$args ) { return new WP_Error(); }
}
require dirname( __DIR__ ) . '/includes/class-din-packages-customer.php';

function dashboard_package( $status = 'active', $customer = 7, $order = 20 ) {
	return array( 'id' => 10, 'customer_id' => $customer, 'order_id' => $order, 'status' => $status,
		'product_name' => 'Running publication', 'heading' => 'Existing article', 'period' => 'lifetime' === $status ? 'lifetime' : 'annual',
		'started_at' => 1704067200, 'expires_at' => 1893456000, 'review' => '' );
}
function dashboard_render( $orders, $packages, $expect_packages ) {
	$GLOBALS['orders'] = array();
	foreach ( $orders as $order ) { $GLOBALS['orders'][ $order->id ] = $order; }
	$GLOBALS['packages'] = $packages;
	$GLOBALS['hooks'] = $GLOBALS['hook_calls'] = array();
	add_action( 'woocommerce_account_dashboard', array( 'DIN_Packages_Customer', 'dashboard' ) );
	add_action( 'woocommerce_account_dashboard', static function () { echo '<aside>Other dashboard extension</aside>'; } );
	$current_user = (object) array( 'ID' => 7, 'display_name' => 'Buyer' );
	ob_start();
	include DASHBOARD_TEMPLATE;
	$html = ob_get_clean();
	dashboard_expect( 1 === ( $GLOBALS['hook_calls']['woocommerce_account_dashboard'] ?? 0 ), 'Generic dashboard hook executes once.' );
	dashboard_expect( 1 === substr_count( $html, 'Other dashboard extension' ), 'Unrelated dashboard callback is retained once.' );
	dashboard_expect( (int) $expect_packages === substr_count( $html, '<section class="din-packages"' ), 'Package section follows availability and is never duplicated.' );
	dashboard_expect( (int) ! $expect_packages === substr_count( $html, '<section class="gpm-order-progress"' ), 'Exactly one shared slot appears: packages or Order Progress.' );
	return $html;
}
function dashboard_section( $html, $class ) {
	preg_match( '/<section class="' . preg_quote( $class, '/' ) . '"[^>]*>.*?<\/section>/s', $html, $matches );
	return $matches[0] ?? '';
}

// Removing unfinished-status filtering would select order 20 and fail the progress assertion.
$html = dashboard_render( array( new WC_Order( 10, 'processing' ), new WC_Order( 20, 'completed' ) ), array( dashboard_package() ), false );
$progress = dashboard_section( $html, 'gpm-order-progress' );
dashboard_expect( str_contains( $progress, 'Order #10' ) && ! str_contains( $progress, 'Order #20' ), 'Older processing order takes priority over a newer completed order and a running package.' );
dashboard_expect( str_contains( dashboard_section( $html, 'gpm-invoice-card' ), '<dd>#20</dd>' ), 'Invoice retains the latest eligible order independently of progress.' );

foreach ( array( 'pending', 'on-hold', 'processing' ) as $status ) {
	$html = dashboard_render( array( new WC_Order( 10, $status ) ), array(), false );
	dashboard_expect( str_contains( dashboard_section( $html, 'gpm-order-progress' ), 'Order #10' ), 'Unfinished order displays progress: ' . $status );
}
foreach ( array( 'active', 'expiring', 'lifetime' ) as $status ) {
	$html = dashboard_render( array( new WC_Order( 20, 'completed' ) ), array( dashboard_package( $status ) ), true );
	dashboard_expect( str_contains( $html, 'Service Validity Period' ) && str_contains( $html, 'Running publication' ), 'Running service is rendered: ' . $status );
	dashboard_expect( str_contains( dashboard_section( $html, 'gpm-invoice-card' ), '<dd>#20</dd>' ), 'Completed invoice survives the package branch.' );
}
$html = dashboard_render( array(), array(), false );
dashboard_expect( str_contains( dashboard_section( $html, 'gpm-order-progress' ), 'No active orders yet.' ), 'Neither order nor running package retains the progress empty state.' );
foreach ( array( 'pending', 'expired', 'review', 'stopped' ) as $status ) {
	dashboard_render( array( new WC_Order( 20, 'completed' ) ), array( dashboard_package( $status ) ), false );
}
// Either ownership check missing would expose this service and choose the wrong branch.
dashboard_render( array( new WC_Order( 20, 'completed', 8 ) ), array( dashboard_package() ), false );
dashboard_render( array( new WC_Order( 20, 'completed' ) ), array( dashboard_package( 'active', 8 ) ), false );
dashboard_render( array(), array( dashboard_package() ), false );
dashboard_render( array( new WC_Order( 20, 'refunded' ) ), array( dashboard_package() ), false );

// Filtering only the first result page would miss the valid older service.
$packages = array_fill( 0, 100, dashboard_package( 'expired' ) );
$packages[] = dashboard_package();
dashboard_render( array( new WC_Order( 20, 'completed' ) ), $packages, true );
$stopped_package = array_merge( dashboard_package( 'stopped' ), array( 'period' => 'lifetime', 'expires_at' => 0, 'stopped_at' => 1788847200, 'stop_reason' => "Buyer <request>\nService ended" ) );
$GLOBALS['packages'] = array( $stopped_package );
ob_start(); DIN_Packages_Customer::account(); $stopped_html = ob_get_clean();
dashboard_expect( str_contains( $stopped_html, 'din-packages__status--stopped' ) && str_contains( $stopped_html, 'Stopped on' ), 'My Package retains stopped services with their stop date.' );
dashboard_expect( str_contains( $stopped_html, 'Buyer &lt;request&gt;' ) && ! str_contains( $stopped_html, 'No expiration date' ), 'Stopped Lifetime shows escaped reason instead of an ongoing entitlement.' );
dashboard_expect( ! str_contains( $stopped_html, 'din_package_action' ) && str_contains( $stopped_html, '/view-order/20/' ), 'Stopped packages retain evidence links without renewal controls.' );
echo "PASS: Dashboard branches, ownership, older running packages, and stopped package history rendering.\n";
