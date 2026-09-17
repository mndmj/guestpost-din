<?php
/** Run without WordPress: php wp-content/plugins/din-package-lifecycle/tests/admin-smoke.php */
define( 'ABSPATH', __DIR__ );
define( 'DIN_PACKAGES_FILE', dirname( __DIR__ ) . '/din-package-lifecycle.php' );
define( 'DIN_PACKAGES_VERSION', '1.0.3' );
class WP_Error {
	public function __construct( private $code, private $message ) {}
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
$GLOBALS['test_caps'] = array( 'manage_woocommerce' => true, 'edit_shop_order' => true, 'edit_product' => true );
$GLOBALS['test_hooks'] = $GLOBALS['test_transients'] = $GLOBALS['test_fields'] = array();
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['test_hooks'][ $hook ] = array( $callback, $priority, $args ); }
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) { add_action( $hook, $callback, $priority, $args ); }
function get_current_screen() { return $GLOBALS['test_screen'] ?? null; }
function plugins_url( $path, $plugin ) { return '/plugins/din-package-lifecycle/' . $path; }
function wp_enqueue_style( $handle, $url, $dependencies, $version ) { $GLOBALS['test_styles'][ $handle ] = $url; }
function wp_enqueue_script( $handle, $url, $dependencies, $version, $footer ) { $GLOBALS['test_scripts'][ $handle ] = array( $url, $footer ); }
function current_user_can( $cap, ...$args ) { return ! empty( $GLOBALS['test_caps'][ $cap ] ) && ! ( 'edit_shop_order' === $cap && in_array( $args[0] ?? 0, $GLOBALS['denied_orders'] ?? array(), true ) ); }
function get_current_user_id() { return 7; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value ); }
function admin_url( $path = '' ) { return '/wp-admin/' . $path; }
function add_query_arg( $key, $value, $url ) { return $url . '&' . $key . '=' . urlencode( $value ); }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( 'DENIED: ' . $message ); }
function wp_safe_redirect( $url ) { throw new RuntimeException( 'REDIRECT: ' . $url ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function wp_verify_nonce( $value, $action ) { return 'valid-' . $action === $value; }
function __( $value, $domain = '' ) { return $value; }
function esc_html__( $value, $domain = '' ) { return htmlspecialchars( $value ); }
function esc_html_e( $value, $domain = '' ) { echo esc_html__( $value, $domain ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $value ) { return esc_html( $value ); }
function set_transient( $key, $value, $ttl ) { $GLOBALS['test_transients'][ $key ] = $value; }
function get_transient( $key ) { return $GLOBALS['test_transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['test_transients'][ $key ] ); }
function wp_nonce_field( $action, $name ) { echo '<input name="' . esc_attr( $name ) . '" value="valid-' . esc_attr( $action ) . '">'; }
function get_option( $key ) { return 'date_format' === $key ? 'Y-m-d' : 'H:i'; }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }
function wp_timezone() { return new DateTimeZone( 'Asia/Jakarta' ); }
function woocommerce_wp_select( $args ) { $GLOBALS['test_fields'][ $args['id'] ] = $args; }
function woocommerce_wp_text_input( $args ) { $GLOBALS['test_fields'][ $args['id'] ] = $args; }
function wc_get_page_screen_id( $type ) { return 'woocommerce_page_wc-orders'; }
function add_meta_box( $id, $title, $callback, $screen, ...$args ) { $GLOBALS['test_boxes'][ $screen ] = $callback; }
function wc_get_order( $id ) { $GLOBALS['test_order_lookups'] = ( $GLOBALS['test_order_lookups'] ?? 0 ) + 1; return 99 === (int) $id ? $GLOBALS['test_order'] : ( $GLOBALS['test_orders'][ (int) $id ] ?? false ); }
function wc_get_product( $id ) { return $GLOBALS['test_products'][ (int) $id ] ?? false; }
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
class WC_Order {
	public $id = 99;
	public $customer = 7;
	public $status = 'completed';
	public $meta = array( '_din_packages_version' => '1.0.0' );
	public $items = array();
	public $refreshed = false;
	public $meta_refreshed = false;
	public function get_data_store() { return new class { public function read( $order ) { if ( ! empty( $GLOBALS['test_order_read_failure'] ) ) { throw new RuntimeException( 'Storage unavailable' ); } $order->refreshed = true; } }; }
	public function read_meta_data( $force = false ) { $this->meta_refreshed = $force; }
	public function get_id() { return $this->id; }
	public function get_customer_id() { return $this->customer; }
	public function get_order_number() { return (string) $this->id; }
	public function get_edit_order_url() { return '/wp-admin/admin.php?page=wc-orders&action=edit&id=' . $this->id; }
	public function get_checkout_payment_url() { return '/checkout/order-pay/' . $this->id . '/?pay_for_order=true&key=test'; }
	public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
	public function get_items() { return $this->items; }
	public function has_status( $status ) { return in_array( $this->status, (array) $status, true ); }
}
class WC_Product {
	public $meta = array();
	public function __construct( private $id = 1 ) {}
	public function get_id() { return $this->id; }
	public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function is_type( $types ) { return in_array( 'simple', (array) $types, true ); }
}
class WC_Admin_Meta_Boxes {
	public static $errors = array();
	public static function add_error( $message ) { self::$errors[] = $message; }
}
class DIN_Packages {
	public static $approved = array();
	public static $stopped = array();
	public static $packages = array();
	public static $adjusted = array();
	public static $adjustment_result = null;
	public static $order_reads = 0;
	public static function approve_order( $order, $user, $paid = false ) { self::$approved[] = array( $order, $user, $paid ); return array( 'Status <checked>' ); }
	public static function config( $product ) { return array( 'period' => $product->get_meta( '_din_package_period' ) ?: 'none', 'annual_product_id' => 0, 'lifetime_product_id' => 0 ); }
	public static function for_order( $id ) { ++self::$order_reads; return array_values( array_filter( self::$packages, static function ( $package ) use ( $id ) { return ! isset( $package['order_id'] ) || $package['order_id'] === $id; } ) ); }
	public static function get( $id ) { foreach ( self::$packages as $package ) { if ( (int) $package['id'] === (int) $id ) { return $package; } } return null; }
	public static function stop( $id, $admin_id, $reason ) { self::$stopped[] = array( $id, $admin_id, $reason ); return self::get( $id ); }
	public static function adjust_expiry( $id, $order_id, $admin_id, $change ) { self::$adjusted[] = array( $id, $order_id, $admin_id, $change ); return self::$adjustment_result ?? self::get( $id ); }
	public static function add_years( $timestamp, $years ) { return ( new DateTimeImmutable( '@' . $timestamp ) )->modify( '+' . $years . ' years' )->getTimestamp(); }
	public static function status( $p ) { return $p['status']; }
}
class DIN_Packages_Requests {
	public static $created = array();
	public static $result = null;
	public static function create( $id, $order_id, $admin_id, $change ) {
		self::$created[] = array( $id, $order_id, $admin_id, $change );
		$order = new WC_Order(); $order->id = 123;
		return self::$result ?? $order;
	}
}
function admin_expect( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$source = dirname( __DIR__ ) . '/includes/class-din-packages-admin.php';
admin_expect( is_file( $source ), 'Admin implementation missing.' );
require $source;
if ( in_array( '--render-expiry', $argv ?? array(), true ) ) {
	$GLOBALS['test_order'] = new WC_Order();
	$fixture_expiry = in_array( '--leap-day', $argv, true ) ? '2032-02-29 12:30:00 UTC' : '2030-10-05 12:30:00 UTC';
	DIN_Packages::$packages = array( array( 'id' => 1, 'revision' => 12, 'customer_id' => 7, 'order_id' => 99, 'product_name' => 'Annual', 'heading' => 'Article', 'period' => 'annual', 'status' => 'active', 'started_at' => strtotime( '2026-10-05 12:30:00 UTC' ), 'expires_at' => strtotime( $fixture_expiry ) ) );
	DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] );
	exit;
}
DIN_Packages_Admin::boot();
admin_expect( 80 === $GLOBALS['test_hooks']['woocommerce_process_shop_order_meta'][1], 'Activation must run after status and attachment saves.' );
$GLOBALS['test_order'] = new WC_Order();
$_POST = array();
DIN_Packages_Admin::approve_order( 99, new WC_Order() );
admin_expect( ! DIN_Packages::$approved, 'Missing nonce must never approve packages.' );
foreach ( array( 'wrong', array( 'valid-din_packages_approve_99' ), 'valid-din_packages_approve_100' ) as $nonce ) {
	$_POST['din_packages_nonce'] = $nonce;
	DIN_Packages_Admin::approve_order( 99 );
	admin_expect( ! DIN_Packages::$approved, 'Wrong, malformed, or another order nonce must not approve packages.' );
}
$_POST = array( 'din_packages_nonce' => 'valid-din_packages_approve_99', 'din_packages_payment_confirmed' => '1' );
foreach ( array( 'manage_woocommerce', 'edit_shop_order' ) as $cap ) {
	$GLOBALS['test_caps'][ $cap ] = false;
	DIN_Packages_Admin::approve_order( 99 );
	admin_expect( ! DIN_Packages::$approved, 'Both internal management and order-edit capabilities are required.' );
	$GLOBALS['test_caps'][ $cap ] = true;
}
DIN_Packages_Admin::approve_order( 99, new WC_Order() );
admin_expect( DIN_Packages::$approved[0] === array( $GLOBALS['test_order'], 7, true ), 'Must reload the saved order and pass explicit payment confirmation.' );
admin_expect( $GLOBALS['test_order']->refreshed, 'A cached WC_Order must be reread from its WooCommerce data store before approval.' );
admin_expect( $GLOBALS['test_order']->meta_refreshed, 'Attachment metadata must be forcibly reread before approval.' );
$_POST['din_packages_payment_confirmed'] = array( '1' );
DIN_Packages_Admin::approve_order( 99 );
admin_expect( false === DIN_Packages::$approved[1][2], 'Malformed payment confirmation must not count as verified.' );
unset( $_POST['din_packages_payment_confirmed'] );
DIN_Packages_Admin::approve_order( 99 );
admin_expect( false === DIN_Packages::$approved[2][2], 'Payment confirmation must not leak between saves.' );
$product = new WC_Product();
$_POST = array( '_din_package_period' => 'annual', '_din_package_annual_product_id' => '12', '_din_package_lifetime_product_id' => 'not-a-product' );
$GLOBALS['test_products'][12] = new WC_Product( 12 );
DIN_Packages_Admin::save_product( $product );
admin_expect( 'annual' === $product->meta['_din_package_period'], 'Valid period must be saved.' );
admin_expect( 12 === $product->meta['_din_package_annual_product_id'], 'Existing product ID must be accepted.' );
admin_expect( 0 === ( $product->meta['_din_package_lifetime_product_id'] ?? 0 ), 'Malformed product ID must not be accepted.' );
$_POST = array( '_din_package_period' => 'annual', '_din_package_annual_product_id' => '999999' );
DIN_Packages_Admin::save_product( $product );
admin_expect( 12 === $product->meta['_din_package_annual_product_id'], 'Invalid replacement must preserve the previous counterpart.' );
$_POST = array( '_din_package_period' => array( 'lifetime' ) );
DIN_Packages_Admin::save_product( $product );
admin_expect( 'annual' === $product->meta['_din_package_period'], 'Malformed period must preserve existing settings.' );
$GLOBALS['test_caps']['edit_product'] = false;
$_POST = array( '_din_package_period' => 'lifetime' );
DIN_Packages_Admin::save_product( $product );
admin_expect( 'annual' === $product->meta['_din_package_period'], 'Unauthorized save must preserve product settings.' );
$GLOBALS['test_caps']['edit_product'] = true;
$GLOBALS['product_object'] = $product;
ob_start(); DIN_Packages_Admin::product_fields(); ob_end_clean();
admin_expect( 'annual' === $GLOBALS['test_fields']['_din_package_period']['value'], 'Product UI must expose the effective package period.' );
admin_expect( isset( $GLOBALS['test_fields']['_din_package_annual_product_id'], $GLOBALS['test_fields']['_din_package_lifetime_product_id'] ), 'Product UI must expose both catalog counterpart IDs.' );
DIN_Packages_Admin::register_meta_boxes();
admin_expect( isset( $GLOBALS['test_boxes']['shop_order'], $GLOBALS['test_boxes']['woocommerce_page_wc-orders'] ), 'Both HPOS and legacy order screens need the summary.' );
$GLOBALS['test_order']->meta = array();
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $legacy = ob_get_clean();
admin_expect( false !== strpos( $legacy, 'Old orders are not automatically activated.' ), 'Historical orders must clearly explain no automatic adoption.' );
$GLOBALS['test_order']->meta['_din_packages_version'] = '1.0.0';
$GLOBALS['test_order']->meta['_din_packages_admin_notice'] = 'Menunggu verifikasi pembayaran';
DIN_Packages::$packages = array( array( 'id' => '99:1:1', 'product_name' => '<script>unsafe</script>', 'heading' => 'Title', 'period' => 'annual', 'status' => 'review', 'started_at' => 1788739200, 'expires_at' => 1820275200, 'review' => array( 'Bukti hilang' ) ) );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $html = ob_get_clean();
admin_expect( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'Package data must be escaped in admin output.' );
admin_expect( false !== strpos( $html, 'Bukti hilang' ) && false !== strpos( $html, '2026-09-07' ), 'Summary must show review reason and initial activation date.' );
admin_expect( false !== strpos( $html, 'Menunggu verifikasi pembayaran' ), 'Persistent order review reasons must remain visible after a redirect notice is consumed.' );
admin_expect( preg_match( '/<h3\b[^>]*>Initial activation<\/h3>/', $html ) && preg_match( '/<ol\b[^>]*>(.*?)<\/ol>/s', $html, $steps ) && 3 === substr_count( $steps[1], '<li>' ), 'Activation instructions need a heading and three ordered steps.' );
foreach ( array( 'Publication Date', 'Guest Post Result', 'DIN Order Attach', 'Completed', 'midnight', 'site timezone', 'Missing, invalid or future dates prevent activation.', 'Re-saving an active package does not change its dates.' ) as $instruction ) {
	admin_expect( str_contains( $html, $instruction ), 'Activation guidance must retain: ' . $instruction );
}
admin_expect( preg_match( '/<div\b[^>]*class="[^"]*notice-warning[^"]*"[^>]*>.*?<p>Menunggu verifikasi pembayaran<\/p>.*?<\/div>/s', $html ), 'Stored warning must use a notice container with a paragraph.' );
$GLOBALS['test_order']->meta['_din_packages_admin_notice'] = '<script>warning</script>';
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $warning_html = ob_get_clean();
admin_expect( str_contains( $warning_html, '&lt;script&gt;warning&lt;/script&gt;' ) && ! str_contains( $warning_html, '<script>' ), 'Styled warning must keep untrusted messages escaped.' );
$GLOBALS['test_order']->items = array( new class {
	public function get_meta( $key, $single = true ) { return array( 'package_id' => '99:1:1', 'action' => 'renew_1' ); }
} );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $html = ob_get_clean();
admin_expect( false !== strpos( $html, 'name="din_packages_payment_confirmed"' ) && false === strpos( $html, 'checked' ), 'Renewal payment confirmation must render unchecked on each view.' );
admin_expect( str_contains( $html, 'Renewal / upgrade request' ) && str_contains( $html, 'Renew Annual +1 year' ), 'Renewal summary must distinguish the requested action from active package status.' );
admin_expect( preg_match( '/<label\b[^>]*for="din-packages-payment-confirmed"[^>]*>.*?<input\b[^>]*id="din-packages-payment-confirmed"[^>]*value="1"[^>]*aria-describedby="din-packages-payment-help"[^>]*>.*?I have verified the renewal\/upgrade payment\..*?<\/label>/s', $html ) && str_contains( $html, 'id="din-packages-payment-help"' ), 'Native confirmation needs a clickable label and associated helper.' );
foreach ( array( 'lifetime' => 'Upgrade to Lifetime', 'renew_2' => 'Renew Annual +2 years', '<unknown>' => '&lt;unknown&gt;' ) as $action => $label ) {
	$GLOBALS['test_order']->items = array( new class( $action ) {
		public function __construct( private $action ) {}
		public function get_meta( $key, $single = true ) { return array( 'package_id' => '99:1:1', 'action' => $this->action ); }
	} );
	ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $action_html = ob_get_clean();
	admin_expect( str_contains( $action_html, $label ) && ! str_contains( $action_html, '<unknown>' ), 'Request badge must use a clear action label and escape unknown actions.' );
}
DIN_Packages::$packages = array();
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $empty_html = ob_get_clean();
admin_expect( str_contains( $empty_html, 'class="din-package-empty"' ) && str_contains( $empty_html, 'There are no active packages for this order yet.' ), 'No packages must show the separate empty state.' );
$GLOBALS['test_order']->items = array();
ob_start(); DIN_Packages_Admin::notices(); $notice = ob_get_clean();
admin_expect( false !== strpos( $notice, 'Status &lt;checked&gt;' ), 'Approval notices must escape messages.' );
ob_start(); DIN_Packages_Admin::notices(); $notice = ob_get_clean();
admin_expect( '' === $notice, 'Approval notice must not repeat on subsequent pages.' );
admin_expect( isset( $GLOBALS['test_hooks']['admin_post_din_stop_package'] ), 'Stop handler must use a separate authenticated POST action.' );
DIN_Packages::$packages = array( array( 'id' => 1, 'order_id' => 99, 'product_name' => '<Lifetime>', 'heading' => '<Post>', 'period' => 'lifetime', 'status' => 'lifetime', 'started_at' => time(), 'expires_at' => 0, 'review' => '' ) );
$_GET = array( 'package_id' => '1' );
ob_start(); DIN_Packages_Admin::stop_page(); $confirmation = ob_get_clean();
admin_expect( str_contains( $confirmation, 'method="post"' ) && str_contains( $confirmation, 'din_stop_nonce' ) && str_contains( $confirmation, 'din_stop_confirm' ) && str_contains( $confirmation, 'required' ), 'Stop confirmation needs POST, nonce, explicit confirmation and a reason.' );
admin_expect( str_contains( $confirmation, '&lt;Post&gt;' ) && ! str_contains( $confirmation, '<Post>' ), 'Stop confirmation escapes package data.' );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $stop_table = ob_get_clean();
admin_expect( str_contains( $stop_table, 'din-stop-package' ) && ! str_contains( $stop_table, '<form' ), 'Order table links to confirmation without nesting or submitting the order form.' );
$valid_stop = array( 'package_id' => '1', 'din_stop_nonce' => 'valid-din_stop_package_1', 'din_stop_confirm' => 'yes', 'din_stop_reason' => 'Buyer asked to stop' );
$run_stop = static function () { try { DIN_Packages_Admin::handle_stop(); } catch ( RuntimeException $error ) { return $error->getMessage(); } return ''; };
$_POST = $valid_stop;
$_SERVER['REQUEST_METHOD'] = 'GET';
admin_expect( str_starts_with( $run_stop(), 'DENIED:' ) && ! DIN_Packages::$stopped, 'GET must never stop a package.' );
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ( array( array( 'din_stop_nonce' => 'wrong' ), array( 'din_stop_nonce' => array( 'valid-din_stop_package_1' ) ), array( 'din_stop_confirm' => '' ), array( 'package_id' => array( '1' ) ), array( 'package_id' => '999' ) ) as $invalid ) {
	$_POST = array_merge( $valid_stop, $invalid );
	admin_expect( str_starts_with( $run_stop(), 'DENIED:' ) && ! DIN_Packages::$stopped, 'Invalid nonce, confirmation or target must not reach the stop service.' );
}
$_POST = $valid_stop;
foreach ( array( 'manage_woocommerce', 'edit_shop_order' ) as $capability ) {
	$GLOBALS['test_caps'][ $capability ] = false;
	admin_expect( str_starts_with( $run_stop(), 'DENIED:' ) && ! DIN_Packages::$stopped, 'Stop action requires both management and source-order edit permission.' );
	$GLOBALS['test_caps'][ $capability ] = true;
}
admin_expect( str_starts_with( $run_stop(), 'REDIRECT:' ) && DIN_Packages::$stopped === array( array( 1, 7, 'Buyer asked to stop' ) ), 'Valid confirmed request stops only its target and returns to the source order.' );
DIN_Packages::$packages[0] = array_merge( DIN_Packages::$packages[0], array( 'status' => 'stopped', 'stopped_at' => time(), 'stopped_by' => 7, 'stop_reason' => '<Buyer reason>' ) );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $stopped_table = ob_get_clean();
admin_expect( str_contains( $stopped_table, 'Stopped' ) && str_contains( $stopped_table, '&lt;Buyer reason&gt;' ) && ! str_contains( $stopped_table, 'din-stop-package' ), 'Stopped summary shows escaped reason and no further stop action.' );
$package_columns = array(
	'din_package_name' => 'Package',
	'din_package_period' => 'Period',
	'din_package_status' => 'Package Status',
	'din_package_start' => 'Start Date',
	'din_package_end' => 'Expires / Stopped',
	'din_package_remaining' => 'Remaining Days',
	'din_package_source' => 'Original Order',
	'din_package_changes' => 'Upgrade / Renewal',
);
foreach ( array( 'manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns' ) as $hook ) {
	admin_expect( isset( $GLOBALS['test_hooks'][ $hook ] ), 'Package columns must be registered for HPOS and legacy lists.' );
	$columns = call_user_func( $GLOBALS['test_hooks'][ $hook ][0], array( 'order_number' => 'Order', 'order_status' => 'Status', 'din_package_validity' => 'Package Validity', 'order_total' => 'Total' ) );
	$expected = array_merge( array( 'order_number' => 'Order', 'order_status' => 'Status' ), $package_columns, array( 'order_total' => 'Total' ) );
	admin_expect( $columns === $expected, 'Eight independent columns must replace Package Validity after Status without removing native columns.' );
	admin_expect( DIN_Packages_Admin::order_columns( $columns ) === $expected, 'Repeated column registration must remain idempotent.' );
}
admin_expect( DIN_Packages_Admin::order_columns( array( 'order_number' => 'Order' ) ) === array_merge( array( 'order_number' => 'Order' ), $package_columns ), 'Columns remain available when another plugin removes the native Status column.' );
$render_columns = static function ( $order, $hook = null ) use ( $package_columns ) {
	$cache = new ReflectionProperty( DIN_Packages_Admin::class, 'column_order_id' );
	$cache->setValue( null, 0 );
	$result = array();
	foreach ( $package_columns as $key => $label ) {
		ob_start();
		if ( $hook ) { call_user_func( $GLOBALS['test_hooks'][ $hook ][0], $key, $order ); }
		else { DIN_Packages_Admin::render_order_column( $key, $order ); }
		$result[ $key ] = ob_get_clean();
	}
	return $result;
};
$GLOBALS['test_order']->items = array();
$annual = array( 'id' => 1, 'order_id' => 99, 'customer_id' => 7, 'product_name' => '<Annual>', 'period' => 'annual', 'status' => 'active', 'started_at' => time() - 20 * DAY_IN_SECONDS, 'expires_at' => time() + 90 * DAY_IN_SECONDS, 'history' => array() );
DIN_Packages::$packages = array( $annual );
$before_column = serialize( array( DIN_Packages::$packages, $GLOBALS['test_order'] ) );
$outputs = array();
foreach ( array( 'manage_woocommerce_page_wc-orders_custom_column' => $GLOBALS['test_order'], 'manage_shop_order_posts_custom_column' => 99 ) as $hook => $argument ) {
	admin_expect( isset( $GLOBALS['test_hooks'][ $hook ] ), 'Both order list render hooks must be registered.' );
	$reads_before = DIN_Packages::$order_reads;
	$outputs[] = $render_columns( $argument, $hook );
	admin_expect( DIN_Packages::$order_reads === $reads_before + 1, 'Eight columns must share one package lookup for the order row.' );
}
admin_expect( $outputs[0] === $outputs[1], 'HPOS and legacy must render identical cells.' );
$cells = $outputs[0];
foreach ( array( 'din_package_name' => '&lt;Annual&gt;', 'din_package_period' => 'Annual', 'din_package_status' => 'Active', 'din_package_start' => gmdate( 'Y-m-d', $annual['started_at'] ), 'din_package_end' => gmdate( 'Y-m-d', $annual['expires_at'] ), 'din_package_remaining' => '90', 'din_package_source' => '#99' ) as $key => $value ) {
	admin_expect( str_contains( $cells[ $key ], $value ), 'Expected value is missing from its dedicated column: ' . $key );
}
admin_expect( ! str_contains( implode( '', $cells ), '<Annual>' ) && ! str_contains( $cells['din_package_name'], 'Start:' ), 'Names must be escaped and no longer contain bundled date fields.' );
admin_expect( ! str_contains( $cells['din_package_period'], 'Active' ) && ! str_contains( $cells['din_package_status'], '90' ) && ! str_contains( $cells['din_package_start'], gmdate( 'Y-m-d', $annual['expires_at'] ) ), 'Period, status and date values must not spill into other columns.' );
admin_expect( $before_column === serialize( array( DIN_Packages::$packages, $GLOBALS['test_order'] ) ), 'Reading the column must not write or activate packages.' );
foreach ( array( 'pending' => 'Awaiting Activation', 'expired' => 'Expired', 'review' => 'Pending Review', 'stopped' => 'Stopped', 'lifetime' => 'No expiration date', 'expiring' => 'Expiring Soon' ) as $status => $label ) {
	$package = array_merge( $annual, array( 'status' => $status ) );
	if ( 'pending' === $status ) { $package['started_at'] = $package['expires_at'] = 0; }
	if ( 'lifetime' === $status ) { $package['period'] = 'lifetime'; $package['expires_at'] = 0; }
	if ( 'stopped' === $status ) { $package['stopped_at'] = time(); }
	DIN_Packages::$packages = array( $package );
	$cells = $render_columns( 99 );
	admin_expect( str_contains( $cells[ 'lifetime' === $status ? 'din_package_remaining' : 'din_package_status' ], $label ), 'Column missing package status: ' . $status );
	if ( in_array( $status, array( 'pending', 'expired', 'review', 'stopped' ), true ) ) { admin_expect( '—' === html_entity_decode( trim( strip_tags( $cells['din_package_remaining'] ) ) ), 'Inactive or uncertain status must not advertise active remaining time.' ); }
	if ( 'stopped' === $status ) { admin_expect( str_contains( $cells['din_package_end'], gmdate( 'Y-m-d', $package['stopped_at'] ) ) && ! str_contains( $cells['din_package_end'], gmdate( 'Y-m-d', $package['expires_at'] ) ), 'Stopped column must use termination date instead of expiry.' ); }
}
$lifetime = array_merge( $annual, array( 'id' => 2, 'period' => 'lifetime', 'status' => 'lifetime', 'expires_at' => 0 ) );
DIN_Packages::$packages = array( $annual, $lifetime );
$cells = $render_columns( 99 );
foreach ( $cells as $key => $cell ) {
	admin_expect( str_contains( $cell, 'Package #1' ) && str_contains( $cell, 'Package #2' ) && strpos( $cell, 'Package #1' ) < strpos( $cell, 'Package #2' ), 'Every multi-package cell must identify packages in the same order: ' . $key );
}
admin_expect( str_contains( $cells['din_package_remaining'], '90' ) && str_contains( $cells['din_package_remaining'], 'No expiration date' ), 'Mixed orders must show both Annual and Lifetime values.' );
DIN_Packages::$packages = array();
foreach ( $render_columns( 99 ) as $cell ) { admin_expect( '—' === html_entity_decode( trim( $cell ) ), 'Unrecorded or non-package orders must not invent values.' ); }
ob_start(); DIN_Packages_Admin::render_order_column( 'order_total', 99 ); $column = ob_get_clean();
admin_expect( '' === $column, 'Renderer must not alter other order columns.' );
DIN_Packages::$packages = array( $annual );
$upgrade = new WC_Order(); $upgrade->id = 100;
$upgrade->items = array( new class {
	public $purchase = array( 'package_id' => 1, 'action' => 'lifetime' );
	public function get_meta( $key, $single = true ) { return '_din_package_purchase' === $key ? $this->purchase : ''; }
	public function get_id() { return 101; }
} );
$GLOBALS['test_orders'][100] = $upgrade;
$cells = $render_columns( $upgrade );
admin_expect( str_contains( $cells['din_package_source'], '#99' ) && str_contains( $cells['din_package_changes'], 'Awaiting approval' ) && str_contains( $cells['din_package_remaining'], '90' ) && str_contains( $cells['din_package_period'], 'Annual' ), 'Pending upgrades must show the original Annual term, not grant Lifetime.' );
$upgrade->status = 'cancelled';
$cells = $render_columns( 100 );
admin_expect( str_contains( $cells['din_package_changes'], 'Not applied' ) && ! str_contains( $cells['din_package_changes'], 'Awaiting approval' ), 'Cancelled upgrades must not remain pending in the column.' );
$upgrade->status = 'completed';
$upgrade->items[0]->purchase = array( 'package_id' => 1, 'action' => 'renew_custom', 'admin_request' => array( 'years' => 3 ) );
$cells = $render_columns( 100 );
admin_expect( str_contains( $cells['din_package_changes'], '3-Year Renewal' ) && str_contains( $cells['din_package_changes'], 'Awaiting approval' ) && str_contains( $cells['din_package_remaining'], '90' ), 'Custom pending renewals must show their whole-year duration without granting service.' );
DIN_Packages::$packages[0]['history'] = array( array( 'order_id' => 100, 'item_id' => 101, 'action' => 'renew_custom', 'years' => 5 ) );
$cells = $render_columns( 100 );
admin_expect( str_contains( $cells['din_package_changes'], '5-Year Renewal' ) && str_contains( $cells['din_package_changes'], 'Applied' ), 'Applied custom renewals must display their recorded history duration.' );
DIN_Packages::$packages[0]['history'] = array();
foreach ( array( null, 0, '1.5', '03', array( 3 ), 'invalid' ) as $invalid_years ) {
	$upgrade->items[0]->purchase['admin_request']['years'] = $invalid_years;
	$cells = $render_columns( 100 );
	admin_expect( str_contains( $cells['din_package_changes'], 'invalid duration' ) && ! str_contains( $cells['din_package_changes'], '1-Year Renewal' ), 'Malformed custom duration must not be presented as a one-year renewal.' );
}
$upgrade->items[0]->purchase['admin_request'] = (object) array( 'years' => 3 );
$cells = $render_columns( 100 );
admin_expect( str_contains( $cells['din_package_changes'], 'invalid duration' ), 'Malformed request metadata must render an invalid-duration label safely.' );
ob_start(); DIN_Packages_Admin::render_meta_box( $upgrade ); $malformed_request_summary = ob_get_clean();
admin_expect( str_contains( $malformed_request_summary, 'invalid duration' ), 'Malformed custom request metadata must not crash the order editor.' );
$upgrade->items[0]->purchase = array( 'package_id' => 1, 'action' => 'renew_1' );
foreach ( array( 'renew_1', 'renew_2' ) as $action ) {
	$upgrade->items[0]->purchase['action'] = $action;
	DIN_Packages::$packages[0] = array_merge( $annual, array( 'expires_at' => time() + 455 * DAY_IN_SECONDS, 'history' => array( array( 'order_id' => 100, 'item_id' => 101, 'action' => $action ) ) ) );
	$cells = $render_columns( 100 );
	admin_expect( str_contains( $cells['din_package_changes'], 'Renewal' ) && str_contains( $cells['din_package_changes'], 'Applied' ) && str_contains( $cells['din_package_remaining'], '455' ), 'Renewal transactions must read the updated source package term.' );
}
$upgrade->items[0]->purchase['action'] = 'lifetime';
DIN_Packages::$packages[0] = array_merge( $lifetime, array( 'id' => 1, 'history' => array( array( 'order_id' => 100, 'item_id' => 101, 'action' => 'lifetime' ) ) ) );
$cells = $render_columns( 100 );
admin_expect( str_contains( $cells['din_package_changes'], 'Applied' ) && str_contains( $cells['din_package_remaining'], 'No expiration date' ) && ! str_contains( $cells['din_package_changes'], 'Awaiting approval' ), 'Applied upgrades must show current Lifetime state.' );
foreach ( array( array( 'package_id' => array( 1 ), 'action' => 'lifetime' ), array( 'package_id' => '1abc', 'action' => 'lifetime' ), array( 'package_id' => 1, 'action' => array( 'lifetime' ) ) ) as $malformed ) {
	$upgrade->items[0]->purchase = $malformed;
	foreach ( $render_columns( 100 ) as $cell ) { admin_expect( '—' === html_entity_decode( trim( $cell ) ), 'Malformed purchase metadata must not resolve a package.' ); }
}
$upgrade->items[0]->purchase = array( 'package_id' => 1, 'action' => 'lifetime' );
$upgrade->customer = 8;
admin_expect( ! str_contains( $render_columns( 100 )['din_package_source'], '#99' ), 'Malformed foreign-owner package references must not disclose another order.' );
$upgrade->customer = 7;
$GLOBALS['denied_orders'] = array( 99 );
admin_expect( ! str_contains( $render_columns( 100 )['din_package_source'], '#99' ), 'Linked package display must respect source-order permissions.' );
$GLOBALS['denied_orders'] = array();
$GLOBALS['test_caps']['manage_woocommerce'] = false;
admin_expect( '' === implode( '', $render_columns( 100 ) ), 'Unauthorized viewers must not see package details.' );
$GLOBALS['test_caps']['manage_woocommerce'] = true;
admin_expect( isset( $GLOBALS['test_hooks']['admin_enqueue_scripts'] ), 'Order table assets must load through the admin enqueue hook.' );
foreach ( array( 'dashboard', 'shop_order', 'woocommerce_page_wc-orders', 'admin_page_wc-orders', 'edit-shop_order' ) as $screen_id ) {
	$GLOBALS['test_screen'] = (object) array( 'id' => $screen_id );
	$GLOBALS['test_styles'] = $GLOBALS['test_scripts'] = array();
	call_user_func( $GLOBALS['test_hooks']['admin_enqueue_scripts'][0] );
	$expected_styles = in_array( $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders', 'admin_page_wc-orders', 'edit-shop_order' ), true );
	$expected_scripts = 'dashboard' === $screen_id ? array() : array( 'shop_order' === $screen_id ? 'din-package-expiry' : 'din-package-orders' );
	admin_expect( (bool) $GLOBALS['test_styles'] === $expected_styles && array_keys( $GLOBALS['test_scripts'] ) === $expected_scripts, 'Only order editors may load expiry JavaScript; only lists may load table JavaScript.' );
}
foreach ( array( 'woocommerce_page_wc-orders', 'admin_page_wc-orders' ) as $screen_id ) {
	$GLOBALS['test_screen'] = (object) array( 'id' => $screen_id );
	foreach ( array( 'edit', 'new' ) as $action ) {
		$_GET['action'] = $action;
		$GLOBALS['test_styles'] = $GLOBALS['test_scripts'] = array();
		DIN_Packages_Admin::order_assets();
		admin_expect( isset( $GLOBALS['test_styles']['din-package-orders'] ) && array_keys( $GLOBALS['test_scripts'] ) === array( 'din-package-expiry' ), 'HPOS edit and new order forms need expiry JavaScript, not list-table JavaScript.' );
	}
}
unset( $_GET['action'] );
$GLOBALS['test_caps']['manage_woocommerce'] = false;
$GLOBALS['test_styles'] = $GLOBALS['test_scripts'] = array();
DIN_Packages_Admin::order_assets();
admin_expect( ! $GLOBALS['test_styles'] && ! $GLOBALS['test_scripts'], 'Order assets must remain restricted to WooCommerce managers.' );
// The real admin renderer must expose explicit, per-package date controls without changing normal order saves.
$GLOBALS['test_caps']['manage_woocommerce'] = true;
$GLOBALS['test_order'] = new WC_Order();
$expiry_package = array_merge( $annual, array( 'revision' => 12, 'heading' => '<Expiry heading>', 'expires_at' => strtotime( '2030-10-05 12:30:00 UTC' ) ) );
DIN_Packages::$packages = array( $expiry_package );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $expiry_html = ob_get_clean();
admin_expect( str_contains( $expiry_html, '<fieldset' ) && str_contains( $expiry_html, 'din-package-expiry' ), 'An activated Annual package needs its expiry editor.' );
admin_expect( ! str_contains( $expiry_html, '<form' ), 'Expiry controls must use the existing order form rather than a nested form.' );
admin_expect( str_contains( $expiry_html, 'Asia/Jakarta' ), 'Expiry editor must disclose the site timezone.' );
preg_match_all( '/<(?:input|select|textarea)\b[^>]*\bname="din_packages_expiry\[1\]\[([^\]]+)\]"[^>]*>/s', $expiry_html, $control_matches, PREG_SET_ORDER );
$expiry_controls = array();
foreach ( $control_matches as $control ) { $expiry_controls[ $control[1] ] = $control[0]; }
foreach ( array( 'date', 'extension', 'years', 'reason', 'revision', 'apply' ) as $field ) {
	admin_expect( isset( $expiry_controls[ $field ] ), 'Expiry editor is missing its submitted field: ' . $field );
	if ( 'revision' !== $field ) {
		admin_expect( preg_match( '/\bid="([^"]+)"/', $expiry_controls[ $field ], $field_id ) && str_contains( $expiry_html, 'for="' . $field_id[1] . '"' ), 'Each editable expiry field needs an associated label: ' . $field );
	}
}
admin_expect( str_contains( $expiry_controls['date'], 'type="date"' ) && str_contains( $expiry_controls['date'], 'disabled' ) && str_contains( $expiry_controls['date'], 'value=""' ), 'New expiry must start empty and disabled until an Annual duration is selected.' );
admin_expect( strpos( $expiry_html, $expiry_controls['extension'] ) < strpos( $expiry_html, $expiry_controls['date'] ), 'Choose duration before selecting the requested expiry.' );
admin_expect( str_contains( $expiry_controls['date'], 'min="2030-10-06"' ), 'Requested expiry must move strictly beyond the existing expiry date.' );
admin_expect( str_contains( $expiry_html, 'data-expiry-1="2031-10-05"' ) && str_contains( $expiry_html, 'data-expiry-2="2032-10-05"' ), 'The server must provide one- and two-year date defaults.' );
admin_expect( str_contains( $expiry_html, 'data-expiry-base="2030-10-05"' ), 'Custom duration must start from the server-provided site-calendar base date.' );
foreach ( array( 'type="number"', 'min="1"', 'step="1"', 'max="7969"', 'disabled' ) as $constraint ) {
	admin_expect( str_contains( $expiry_controls['years'], $constraint ), 'Custom years must expose its whole-year input constraint: ' . $constraint );
}
admin_expect( ! isset( $expiry_controls['price'] ), 'Custom renewal must never accept an admin-entered price.' );
admin_expect( preg_match( '/<noscript>.*?Enable JavaScript.*?<\/noscript>/s', $expiry_html ) && str_contains( $expiry_controls['apply'], 'disabled' ), 'Without JavaScript, requesting payment must be disabled with clear instructions.' );
admin_expect( str_contains( $expiry_controls['revision'], 'type="hidden"' ) && str_contains( $expiry_controls['revision'], 'value="12"' ), 'Rendered revision must accompany the edit for stale-save protection.' );
admin_expect( str_contains( $expiry_controls['apply'], 'type="checkbox"' ) && str_contains( $expiry_controls['apply'], 'value="1"' ) && ! preg_match( '/\schecked(?:\s|=|>)/', $expiry_controls['apply'] ), 'Apply checkbox must require a fresh explicit decision.' );
admin_expect( str_contains( $expiry_controls['reason'], 'maxlength="1000"' ) && ! preg_match( '/\srequired(?:\s|=|>)/', $expiry_controls['reason'] ), 'Reason is bounded but must not block unrelated order saves through unconditional required.' );
admin_expect( preg_match( '/<select\b[^>]*name="din_packages_expiry\[1\]\[extension\]"[^>]*>(.*?)<\/select>/s', $expiry_html, $extension_options ), 'Duration options must be selectable.' );
foreach ( array( 'none', '1', '2', 'lifetime', 'custom' ) as $option ) {
	admin_expect( str_contains( $extension_options[1], 'value="' . $option . '"' ), 'Duration choice is missing: ' . $option );
}
$request_order = new WC_Order(); $request_order->id = 123;
$GLOBALS['test_orders'][123] = $request_order;
DIN_Packages::$packages[0]['payment_request'] = array( 'order_id' => 123 );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $request_link_html = ob_get_clean();
admin_expect( str_contains( $request_link_html, esc_url( $request_order->get_edit_order_url() ) ) && str_contains( $request_link_html, esc_url( $request_order->get_checkout_payment_url() ) ), 'Existing payment request must expose native order and buyer payment links.' );
$request_order->customer = 8;
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $foreign_request_html = ob_get_clean();
admin_expect( ! str_contains( $foreign_request_html, 'order-pay/123' ) && ! str_contains( $foreign_request_html, 'id=123' ), 'Foreign-owner request links must never be exposed.' );
$GLOBALS['test_order']->items = array( new class {
	public $expiry = 1959508800;
	public $action = 'renew_2';
	public $years = 2;
	public function get_meta( $key, $single = true ) { return array( 'package_id' => 1, 'action' => $this->action, 'admin_request' => array( 'years' => $this->years, 'expires_at' => $this->expiry, 'reason' => '<Buyer agreed>', 'requested_by' => 7, 'generation' => 'original' ) ); }
} );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $requested_summary = ob_get_clean();
admin_expect( str_contains( $requested_summary, '2032-02-04' ) && str_contains( $requested_summary, '&lt;Buyer agreed&gt;' ) && ! str_contains( $requested_summary, '<Buyer agreed>' ), 'Payment order must render the requested expiry and escaped buyer-visible reason.' );
$GLOBALS['test_order']->items[0]->expiry = 0;
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $requested_lifetime = ob_get_clean();
admin_expect( str_contains( $requested_lifetime, 'No expiration date' ), 'Lifetime requests must state that there is no expiry date.' );
$GLOBALS['test_order']->items[0]->action = 'renew_custom';
$GLOBALS['test_order']->items[0]->years = 3;
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $custom_summary = ob_get_clean();
admin_expect( str_contains( $custom_summary, '3-Year Renewal' ) && ! str_contains( $custom_summary, '>renew_custom<' ), 'The payment summary must name the custom whole-year renewal.' );
$GLOBALS['test_order']->items[0]->years = '1.5';
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $invalid_custom_summary = ob_get_clean();
admin_expect( str_contains( $invalid_custom_summary, 'invalid duration' ) && ! str_contains( $invalid_custom_summary, '1-Year Renewal' ), 'Invalid custom metadata must not fall back to a one-year label.' );
$GLOBALS['test_order']->items = array();
foreach ( array( 'active', 'expiring', 'expired', 'pending', 'review', 'stopped', 'lifetime' ) as $status ) {
	$fixture = array_merge( $expiry_package, array( 'status' => $status ) );
	if ( 'pending' === $status ) { $fixture['started_at'] = 0; }
	if ( 'lifetime' === $status ) { $fixture['period'] = 'lifetime'; $fixture['expires_at'] = 0; }
	if ( 'stopped' === $status ) { $fixture['stopped_at'] = time(); }
	DIN_Packages::$packages = array( $fixture );
	ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $state_html = ob_get_clean();
	admin_expect( str_contains( $state_html, 'name="din_packages_expiry[1][date]"' ) === in_array( $status, array( 'active', 'expiring', 'expired' ), true ), 'Expiry controls must respect package eligibility: ' . $status );
}
DIN_Packages::$packages = array( $expiry_package );
$GLOBALS['test_order']->status = 'on-hold';
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $not_completed_html = ob_get_clean();
admin_expect( ! str_contains( $not_completed_html, 'name="din_packages_expiry[1][date]"' ), 'Uncompleted source orders must not offer manual expiry adjustment.' );
$GLOBALS['test_order']->status = 'completed';
foreach ( array( array( 'started_at' => 0 ), array( 'expires_at' => 0 ), array( 'revision' => 0 ), array( 'id' => '1invalid' ) ) as $invalid_editor ) {
	DIN_Packages::$packages = array( array_merge( $expiry_package, $invalid_editor ) );
	ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $invalid_editor_html = ob_get_clean();
	admin_expect( ! str_contains( $invalid_editor_html, 'name="din_packages_expiry[' ), 'Incomplete or malformed package state must not offer an expiry editor.' );
}
DIN_Packages::$packages = array( $expiry_package );

// Exercise the real save boundary. Persistence and pricing live in the request service.
$expiry_change = array( 'date' => '2031-11-05', 'extension' => '1', 'reason' => "Buyer\\'s approved extension", 'revision' => '12' );
$expiry_post = array( 'din_packages_nonce' => 'valid-din_packages_approve_99', 'din_packages_expiry' => array( 1 => $expiry_change ) );
$_POST = $expiry_post;
DIN_Packages_Admin::approve_order( 99 );
admin_expect( ! DIN_Packages_Requests::$created && ! DIN_Packages::$adjusted, 'Ordinary Update must ignore expiry fields without explicit confirmation.' );
foreach ( array( '', '0', 1, array( '1' ) ) as $unchecked ) {
	$_POST = $expiry_post;
	$_POST['din_packages_expiry'][1]['apply'] = $unchecked;
	DIN_Packages_Admin::approve_order( 99 );
	admin_expect( ! DIN_Packages_Requests::$created && ! DIN_Packages::$adjusted, 'Only the exact submitted checkbox value may authorize a payment request.' );
}
$expiry_post['din_packages_expiry'][1]['apply'] = '1';
$_POST = $expiry_post;
DIN_Packages_Admin::approve_order( 99 );
admin_expect( count( DIN_Packages_Requests::$created ) === 1 && ! DIN_Packages::$adjusted, 'Confirmed change must create one payment request and never directly adjust expiry.' );
$created = DIN_Packages_Requests::$created[0];
admin_expect( array_slice( $created, 0, 3 ) === array( 1, 99, 7 ), 'Request must bind the submitted package to the edited order and logged-in manager.' );
admin_expect( $created[3]['reason'] === "Buyer's approved extension" && $created[3]['date'] === '2031-11-05' && $created[3]['extension'] === '1' && $created[3]['revision'] === '12', 'Request payload must retain the edited date, duration, revision and unslashed reason.' );
$messages = $GLOBALS['test_transients']['din_packages_notice_7']['messages'] ?? array();
admin_expect( in_array( 'Status <checked>', $messages, true ) && count( $messages ) >= 2, 'Expiry success must not discard ordinary approval messages.' );
admin_expect( str_contains( implode( ' ', $messages ), '#123' ) && str_contains( implode( ' ', $messages ), 'unchanged' ) && str_contains( implode( ' ', $messages ), 'payment' ), 'Success must identify the payment order and explain unchanged service pending payment.' );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $after_save_html = ob_get_clean();
admin_expect( preg_match( '/<input\b[^>]*name="din_packages_expiry\[1\]\[apply\]"[^>]*>/', $after_save_html, $after_save_apply ) && ! preg_match( '/\schecked(?:\s|=|>)/', $after_save_apply[0] ), 'Posted confirmation must not preselect the next expiry edit.' );
$_POST['din_packages_expiry'][1] = array_merge( $expiry_post['din_packages_expiry'][1], array( 'extension' => 'custom', 'years' => '3', 'date' => '2033-12-09' ) );
DIN_Packages_Admin::approve_order( 99 );
$custom_request = end( DIN_Packages_Requests::$created );
admin_expect( $custom_request[3]['extension'] === 'custom' && $custom_request[3]['years'] === '3' && $custom_request[3]['date'] === '2033-12-09' && ! DIN_Packages::$adjusted, 'Custom requests must preserve whole years and edited date for paid request validation, never direct adjustment.' );
$_POST = $expiry_post;
DIN_Packages_Requests::$result = new WP_Error( 'expiry_conflict', '<Stale package>' );
DIN_Packages_Admin::approve_order( 99 );
ob_start(); DIN_Packages_Admin::notices(); $expiry_error_html = ob_get_clean();
admin_expect( str_contains( $expiry_error_html, '&lt;Stale package&gt;' ) && str_contains( $expiry_error_html, 'Status &lt;checked&gt;' ) && ! str_contains( $expiry_error_html, '<Stale package>' ), 'Core refusal must be visible, escaped and merged with existing approval notices.' );
DIN_Packages_Requests::$result = null;
$_POST = $expiry_post;
$_POST['din_packages_expiry'] = array( 200 => $expiry_post['din_packages_expiry'][1] );
DIN_Packages_Requests::$result = new WP_Error( 'expiry_order', 'Package belongs to another order.' );
DIN_Packages_Admin::approve_order( 99 );
$foreign_adjustment = end( DIN_Packages_Requests::$created );
admin_expect( array_slice( $foreign_adjustment, 0, 3 ) === array( 200, 99, 7 ) && in_array( 'Package belongs to another order.', $GLOBALS['test_transients']['din_packages_notice_7']['messages'], true ), 'Foreign package input must stay bound to the edited order so core can reject it visibly.' );
DIN_Packages_Requests::$result = null;
foreach ( array( 'invalid', array( 1 => 'invalid' ), array( '1bad' => array_merge( $expiry_change, array( 'apply' => '1' ) ) ) ) as $malformed ) {
	DIN_Packages_Requests::$created = array();
	$_POST = $expiry_post;
	$_POST['din_packages_expiry'] = $malformed;
	DIN_Packages_Admin::approve_order( 99 );
	admin_expect( ! DIN_Packages_Requests::$created && ! DIN_Packages::$adjusted && count( $GLOBALS['test_transients']['din_packages_notice_7']['messages'] ?? array() ) > 1, 'Malformed expiry form structure must produce a notice and never create a payment request.' );
}
DIN_Packages_Requests::$created = array();
foreach ( array( 'wrong', array( 'valid-din_packages_approve_99' ), 'valid-din_packages_approve_100' ) as $nonce ) {
	$_POST = $expiry_post;
	$_POST['din_packages_nonce'] = $nonce;
	$lookups = $GLOBALS['test_order_lookups'];
	DIN_Packages_Admin::approve_order( 99 );
	admin_expect( ! DIN_Packages_Requests::$created && ! DIN_Packages::$adjusted && $GLOBALS['test_order_lookups'] === $lookups, 'Invalid nonce must reject an expiry request before order reads or requests.' );
}
foreach ( array( 'manage_woocommerce', 'edit_shop_order' ) as $capability ) {
	$_POST = $expiry_post;
	$GLOBALS['test_caps'][ $capability ] = false;
	$lookups = $GLOBALS['test_order_lookups'];
	DIN_Packages_Admin::approve_order( 99 );
	admin_expect( ! DIN_Packages_Requests::$created && ! DIN_Packages::$adjusted && $GLOBALS['test_order_lookups'] === $lookups, 'Unauthorized expiry edit must not read or write an order.' );
	$GLOBALS['test_caps'][ $capability ] = true;
}
$_POST = $expiry_post;
$GLOBALS['test_order_read_failure'] = true;
$errors_before = count( WC_Admin_Meta_Boxes::$errors );
DIN_Packages_Admin::approve_order( 99 );
admin_expect( ! DIN_Packages_Requests::$created && ! DIN_Packages::$adjusted && count( WC_Admin_Meta_Boxes::$errors ) === $errors_before + 1, 'Order refresh failure must block payment requests and explain the problem.' );
$GLOBALS['test_order_read_failure'] = false;
$_POST = array();
echo "DIN Package Lifecycle admin smoke: OK (confirmed per-package stop and expiry edits)\n";
