<?php
/** Run without WordPress: php wp-content/plugins/din-package-lifecycle/tests/admin-smoke.php */
define( 'ABSPATH', __DIR__ );
function is_wp_error( $value ) { return $value instanceof WP_Error; }
$GLOBALS['test_caps'] = array( 'manage_woocommerce' => true, 'edit_shop_order' => true, 'edit_product' => true );
$GLOBALS['test_hooks'] = $GLOBALS['test_transients'] = $GLOBALS['test_fields'] = array();
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['test_hooks'][ $hook ] = array( $callback, $priority, $args ); }
function current_user_can( $cap, ...$args ) { return ! empty( $GLOBALS['test_caps'][ $cap ] ); }
function get_current_user_id() { return 7; }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
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
function woocommerce_wp_select( $args ) { $GLOBALS['test_fields'][ $args['id'] ] = $args; }
function woocommerce_wp_text_input( $args ) { $GLOBALS['test_fields'][ $args['id'] ] = $args; }
function wc_get_page_screen_id( $type ) { return 'woocommerce_page_wc-orders'; }
function add_meta_box( $id, $title, $callback, $screen, ...$args ) { $GLOBALS['test_boxes'][ $screen ] = $callback; }
function wc_get_order( $id ) { return 99 === (int) $id ? $GLOBALS['test_order'] : false; }
function wc_get_product( $id ) { return $GLOBALS['test_products'][ (int) $id ] ?? false; }
define( 'MINUTE_IN_SECONDS', 60 );
class WC_Order {
	public $meta = array( '_din_packages_version' => '1.0.0' );
	public $items = array();
	public $refreshed = false;
	public $meta_refreshed = false;
	public function get_data_store() { return new class { public function read( $order ) { $order->refreshed = true; } }; }
	public function read_meta_data( $force = false ) { $this->meta_refreshed = $force; }
	public function get_id() { return 99; }
	public function get_edit_order_url() { return '/wp-admin/admin.php?page=wc-orders&action=edit&id=99'; }
	public function get_meta( $key, $single = true ) { return $this->meta[ $key ] ?? ''; }
	public function get_items() { return $this->items; }
	public function has_status( $status ) { return 'completed' === $status; }
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
	public static function approve_order( $order, $user, $paid = false ) { self::$approved[] = array( $order, $user, $paid ); return array( 'Status <checked>' ); }
	public static function config( $product ) { return array( 'period' => $product->get_meta( '_din_package_period' ) ?: 'none', 'annual_product_id' => 0, 'lifetime_product_id' => 0 ); }
	public static function for_order( $id ) { return self::$packages; }
	public static function get( $id ) { foreach ( self::$packages as $package ) { if ( (int) $package['id'] === (int) $id ) { return $package; } } return null; }
	public static function stop( $id, $admin_id, $reason ) { self::$stopped[] = array( $id, $admin_id, $reason ); return self::get( $id ); }
	public static function status( $p ) { return $p['status']; }
}
function admin_expect( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$source = dirname( __DIR__ ) . '/includes/class-din-packages-admin.php';
admin_expect( is_file( $source ), 'Admin implementation missing.' );
require $source;
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
$GLOBALS['test_order']->items = array( new class {
	public function get_meta( $key, $single = true ) { return array( 'package_id' => '99:1:1', 'action' => 'renew_1' ); }
} );
ob_start(); DIN_Packages_Admin::render_meta_box( $GLOBALS['test_order'] ); $html = ob_get_clean();
admin_expect( false !== strpos( $html, 'name="din_packages_payment_confirmed"' ) && false === strpos( $html, 'checked' ), 'Renewal payment confirmation must render unchecked on each view.' );
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
echo "DIN Package Lifecycle admin smoke: OK (including confirmed per-package stop)\n";
