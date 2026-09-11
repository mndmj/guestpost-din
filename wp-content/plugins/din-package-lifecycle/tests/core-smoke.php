<?php
// Run: php wp-content/plugins/din-package-lifecycle/tests/core-smoke.php
// All orders/products and the SQLite database live only in memory; no mail is sent.
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );
function wp_timezone() { return new DateTimeZone( 'Asia/Jakarta' ); }
function absint( $v ) { return abs( (int) $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function user_can( $id, ...$args ) { return 9 === $id && ! in_array( $args[0], $GLOBALS['denied_caps'] ?? array(), true ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function wc_get_product( $id ) { return $GLOBALS['products'][ $id ] ?? false; }
function get_woocommerce_currency() { return 'USD'; }
function wc_get_logger() { return new class { public function error( ...$args ) { throw new RuntimeException( $args[0] ); } }; }
class WP_Error {
	public $code; public $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
class Memory_DB {
	public $prefix = 'wp_'; public $pdo; public $contend;
	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->pdo->exec( 'CREATE TABLE wp_din_packages (id INTEGER PRIMARY KEY AUTOINCREMENT,customer_id INTEGER,order_id INTEGER,order_item_id INTEGER,unit INTEGER,revision INTEGER DEFAULT 1,data TEXT,UNIQUE(order_item_id,unit))' );
	}
	public function prepare( $sql, ...$args ) { foreach ( $args as $v ) { $sql = preg_replace_callback( '/%[ds]/', function ( $m ) use ( $v ) { return '%d' === $m[0] ? (string) (int) $v : $this->pdo->quote( $v ); }, $sql, 1 ); } return $sql; }
	public function get_row( $sql, $format ) { return $this->pdo->query( $sql )->fetch( PDO::FETCH_ASSOC ); }
	public function get_results( $sql, $format ) { return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_ASSOC ); }
	public function query( $sql ) { return $this->pdo->exec( str_replace( 'INSERT IGNORE', 'INSERT OR IGNORE', $sql ) ); }
	public function update( $table, $data, $where, ...$formats ) {
		if ( $this->contend ) { $call = $this->contend; $this->contend = null; $call(); }
		$q = $this->pdo->prepare( "UPDATE $table SET data=?, revision=? WHERE id=? AND revision=?" );
		$q->execute( array( $data['data'], $data['revision'], $where['id'], $where['revision'] ) ); return $q->rowCount();
	}
}
class Test_Product {
	public $id; public $period;
	public function __construct( $id, $period ) { $this->id = $id; $this->period = $period; }
	public function get_id() { return $this->id; }
	public function is_type( $types ) { return in_array( 'simple', (array) $types, true ); }
	public function get_meta( $key ) { return '_din_package_period' === $key ? $this->period : ( '_din_package_lifetime_product_id' === $key ? 2 : 1 ); }
	public function is_purchasable() { return true; }
	public function is_in_stock() { return true; }
}
class Test_Item {
	public $id; public $product; public $quantity; public $meta;
	public function __construct( $id, $product, $quantity, $meta ) { $this->id=$id; $this->product=$product; $this->quantity=$quantity; $this->meta=$meta; }
	public function get_id() { return $this->id; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function get_quantity() { return $this->quantity; }
	public function get_product_id() { return $this->product; }
	public function get_variation_id() { return 0; }
	public function get_name() { return 'Guest post ' . $this->product; }
}
class Test_Order {
	public $id; public $customer=7; public $status='processing'; public $proofs=array(); public $items; public $meta=array( '_din_packages_version'=>'1.0.0' ); public $refunded=0;
	public function __construct( $id, $items ) { $this->id=$id; $this->items=$items; }
	public function get_id() { return $this->id; }
	public function get_customer_id() { return $this->customer; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function save_meta_data() {}
	public function read_meta_data( $force = false ) {}
	public function get_items() { return $this->items; }
	public function get_item( $id ) { foreach ( $this->items as $item ) { if ( $item->get_id() === $id ) { return $item; } } return false; }
	public function get_customer_note() { return 'Heading original'; }
	public function get_currency() { return 'USD'; }
	public function has_status( $s ) { return $this->status === $s; }
	public function is_paid() { return 'completed' === $this->status; }
	public function get_date_paid() { return $this->is_paid() ? time() : null; }
	public function get_total_refunded() { return $this->refunded; }
}
class DIN_Order_Attach_Storage {
	public function get_order_files( $order ) { return $order->proofs; }
	public function resolve_path( $path ) { return 'valid' === $path ? $path : new WP_Error( 'missing', 'Missing file' ); }
	public function inspect_stored_file( $record, $path ) { return 'valid' === $path ? array( 'size'=>1 ) : new WP_Error( 'bad', 'Invalid file' ); }
}
class DIN_Packages_Mail {
	public static $scheduled = array();
	public static $sending = false;
	public static function schedule( $p ) { self::$scheduled[] = $p['id']; }
	public static function has_sending( $package ) { return self::$sending; }
}
require dirname( __DIR__ ) . '/includes/class-din-packages.php';
$wpdb = new Memory_DB();
$products = array( 1=>new Test_Product( 1, 'annual' ), 2=>new Test_Product( 2, 'lifetime' ) );
$config = DIN_Packages::config( $products[1] );
$items = array( new Test_Item( 11, 1, 2, array( '_din_package_config'=>$config ) ), new Test_Item( 12, 2, 1, array( '_din_package_config'=>DIN_Packages::config( $products[2] ) ) ) );
$order = new Test_Order( 10, $items ); $orders = array( 10=>$order );
DIN_Packages::capture_order( $order ); DIN_Packages::capture_order( $order );
check( count( DIN_Packages::for_order( 10 ) ) === 3, 'Quantity/mixed order capture must produce 3 units exactly once.' );
check( count( DIN_Packages::for_customer( 8 ) ) === 0, 'Owner query leaked packages.' );
DIN_Packages::approve_order( $order, 9 );
check( DIN_Packages::get( 1 )['started_at'] === 0, 'Non-Completed activated.' );
$order->status = 'completed';
DIN_Packages::approve_order( $order, 9 );
check( DIN_Packages::get( 1 )['started_at'] === 0, 'Completed without proof activated.' );
$order->proofs = array( array( 'id'=>'missing', 'path'=>'invalid' ) );
DIN_Packages::approve_order( $order, 9 );
check( DIN_Packages::get( 1 )['started_at'] === 0, 'Missing physical proof activated.' );
$order->proofs = array( array( 'id'=>'proof-1', 'path'=>'valid' ) );
DIN_Packages::approve_order( $order, 8 );
check( DIN_Packages::get( 1 )['started_at'] === 0, 'Buyer activated a package.' );
DIN_Packages::approve_order( $order, 9 ); $initial = DIN_Packages::get( 1 );
DIN_Packages::approve_order( $order, 9 );
check( DIN_Packages::get( 1 ) === $initial, 'Repeated admin update reset activation.' );
check( DIN_Packages::get( 3 )['expires_at'] === 0 && DIN_Packages::status( DIN_Packages::get( 3 ) ) === 'lifetime', 'Lifetime should have no expiry.' );
check( is_wp_error( DIN_Packages::purchase_option( 1, 'lifetime', 8 ) ), 'Foreign package purchase allowed.' );
check( is_wp_error( DIN_Packages::purchase_option( 1, 'renew_1', 7 ) ), 'Early renewal allowed.' );
check( ! is_wp_error( DIN_Packages::purchase_option( 1, 'lifetime', 7 ) ), 'Valid lifetime upgrade unavailable.' );
$leap = ( new DateTimeImmutable( '2028-02-29 14:30:00', wp_timezone() ) )->getTimestamp();
check( ( new DateTimeImmutable( '@' . DIN_Packages::add_years( $leap, 1 ) ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i' ) === '2029-02-28 14:30', 'Calendar leap-year clamping failed.' );
$p = DIN_Packages::apply_purchase( $initial, 'renew_2', 20, 21, 9, time() );
check( $p['expires_at'] === DIN_Packages::add_years( $initial['expires_at'], 2 ), 'Early payment lost remaining duration.' );
check( DIN_Packages::apply_purchase( $p, 'renew_2', 20, 21, 9, time() ) === $p, 'Duplicate renewal applied twice.' );
$p = DIN_Packages::apply_purchase( $initial, 'renew_1', 20, 21, 9, $initial['expires_at'] + 86400 );
check( $p['expires_at'] === DIN_Packages::add_years( $initial['expires_at'] + 86400, 1 ), 'Expired renewal did not start at approval.' );
$renewal = new Test_Order( 20, array( new Test_Item( 21, 1, 1, array( '_din_package_purchase'=>array( 'package_id'=>1, 'action'=>'renew_1' ) ) ) ) );
$orders[20] = $renewal; $renewal->status='completed';
DIN_Packages::approve_order( $renewal, 9 );
check( DIN_Packages::get( 1 ) === $initial, 'Manual Completed alone counted as verified payment.' );
DIN_Packages::approve_order( $renewal, 9, true ); $renewed=DIN_Packages::get( 1 );
DIN_Packages::approve_order( $renewal, 9, true );
check( DIN_Packages::get( 1 ) === $renewed && $renewed['generation'] === 2, 'Paid renewal gate/idempotency failed.' );
$wpdb->contend = static function () { DIN_Packages::mutate( 2, static function ( $p ) { return DIN_Packages::apply_purchase( $p, 'renew_1', 30, 31, 9, time() ); } ); };
$r = DIN_Packages::mutate( 2, static function ( $p ) { return DIN_Packages::apply_purchase( $p, 'renew_1', 40, 41, 9, time() ); } );
check( ! is_wp_error( $r ) && count( $r['history'] ) === 3 && $r['expires_at'] === DIN_Packages::add_years( $initial['expires_at'], 2 ), 'CAS retry lost concurrent renewal.' );
$upgraded = DIN_Packages::apply_purchase( $renewed, 'lifetime', 50, 51, 9, time() );
check( $upgraded['period']==='lifetime' && $upgraded['expires_at']===0 && $upgraded['started_at']===$initial['started_at'], 'Lifetime lost original history/start.' );
check( is_wp_error( DIN_Packages::apply_purchase( $upgraded, 'lifetime', 60, 61, 9, time() ) ), 'Second upgrade applied.' );
DIN_Packages::order_refunded( 20 );
check( DIN_Packages::status( DIN_Packages::get( 1 ) ) === 'review', 'Refund after renewal failed to flag review.' );
$old = new Test_Order( 70, array( new Test_Item( 71, 1, 1, array( '_din_package_config'=>$config ) ) ) ); $old->meta=array();
DIN_Packages::capture_order( $old );
check( ! DIN_Packages::for_order( 70 ), 'Historic order silently imported.' );
$order->items[0]->quantity = 1;
DIN_Packages::approve_order( $order, 9 );
check( DIN_Packages::status( DIN_Packages::get( 2 ) ) === 'review', 'Removed quantity unit kept its valid entitlement.' );
$lifetime_before = DIN_Packages::get( 3 );
DIN_Packages::audit_proofs( 10 );
check( DIN_Packages::get( 3 ) === $lifetime_before, 'Valid/rolled-back proof removal changed the package.' );
$order->proofs = array();
DIN_Packages::audit_proofs( 10 );
check( DIN_Packages::status( DIN_Packages::get( 3 ) ) === 'review' && DIN_Packages::get( 3 )['proof_ids'] === $lifetime_before['proof_ids'] && DIN_Packages::get( 3 )['started_at'] === $lifetime_before['started_at'], 'Last-proof removal failed to flag Lifetime without losing audit history.' );
foreach ( array( 'refunded', 'cancelled' ) as $terminal_status ) {
	$wpdb = new Memory_DB();
	$origin = new Test_Order( 100, array( new Test_Item( 101, 1, 1, array( '_din_package_config' => $config ) ) ) );
	$origin->status = 'completed';
	$origin->proofs = array( array( 'id' => 'proof-100', 'path' => 'valid' ) );
	$orders[100] = $origin;
	DIN_Packages::capture_order( $origin );
	$wpdb->contend = static function () use ( $origin, $terminal_status ) {
		$origin->status = $terminal_status;
		if ( 'refunded' === $terminal_status ) {
			$origin->refunded = 1;
			DIN_Packages::order_refunded( 100 );
		} else {
			DIN_Packages::order_status_changed( 100, 'completed', $terminal_status, $origin );
		}
	};
	DIN_Packages::approve_order( $origin, 9 );
	check( ! DIN_Packages::get( 1 )['started_at'] && 'review' === DIN_Packages::status( DIN_Packages::get( 1 ) ), 'Concurrent ' . $terminal_status . ' failed to invalidate pending activation.' );
}
foreach ( array( 'refunded', 'cancelled' ) as $terminal_status ) {
	$wpdb = new Memory_DB();
	$origin = new Test_Order( 100, array( new Test_Item( 101, 1, 1, array( '_din_package_config' => $config ) ) ) );
	$origin->status = 'completed';
	$origin->proofs = array( array( 'id' => 'proof-100', 'path' => 'valid' ) );
	$orders[100] = $origin;
	DIN_Packages::approve_order( $origin, 9 );
	$before = DIN_Packages::get( 1 );
	$racing = new Test_Order( 80, array( new Test_Item( 81, 1, 1, array( '_din_package_purchase' => array( 'package_id' => 1, 'action' => 'renew_1' ) ) ) ) );
	$racing->status = 'completed';
	$orders[80] = $racing;
	$wpdb->contend = static function () use ( $racing, $terminal_status ) {
		$racing->status = $terminal_status;
		if ( 'refunded' === $terminal_status ) {
			$racing->refunded = 1;
			DIN_Packages::order_refunded( 80 );
		} else {
			DIN_Packages::order_status_changed( 80, 'completed', $terminal_status, $racing );
		}
	};
	DIN_Packages::approve_order( $racing, 9, true );
	$after = DIN_Packages::get( 1 );
	check( $before['expires_at'] === $after['expires_at'] && $before['history'] === $after['history'], 'Concurrent ' . $terminal_status . ' incorrectly extended the package.' );
	check( empty( $after['review'] ), 'An unapplied renewal refund/cancellation must not suspend the original service.' );
	check( is_wp_error( DIN_Packages::apply_purchase( $after, 'renew_1', 80, 81, 9, time() ) ), 'Rejected renewal must also be blocked on later CAS retries.' );
}
check( method_exists( 'DIN_Packages', 'stop' ), 'Admin package stopping is missing.' );
$wpdb = new Memory_DB();
$origin = new Test_Order( 200, array(
	new Test_Item( 201, 2, 1, array( '_din_package_config' => DIN_Packages::config( $products[2] ) ) ),
	new Test_Item( 202, 1, 1, array( '_din_package_config' => $config ) ),
) );
$orders[200] = $origin;
DIN_Packages::capture_order( $origin );
check( is_wp_error( DIN_Packages::stop( 1, 9, 'Buyer request' ) ), 'Unactivated package cannot be stopped.' );
$origin->status = 'completed';
$origin->proofs = array( array( 'id' => 'proof-200', 'path' => 'valid' ) );
DIN_Packages::approve_order( $origin, 9 );
$original_lifetime = DIN_Packages::get( 1 );
$other_package = DIN_Packages::get( 2 );
$original_order = serialize( $origin );
check( is_wp_error( DIN_Packages::stop( 1, 7, 'Buyer request' ) ), 'Buyer cannot stop packages through the admin service.' );
foreach ( array( 'manage_woocommerce', 'edit_shop_order' ) as $capability ) {
	$GLOBALS['denied_caps'] = array( $capability );
	check( is_wp_error( DIN_Packages::stop( 1, 9, 'Buyer request' ) ), 'Both management and source-order edit capabilities are required.' );
}
$GLOBALS['denied_caps'] = array();
foreach ( array( '', '   ', array( 'Buyer request' ) ) as $reason ) {
	check( is_wp_error( DIN_Packages::stop( 1, 9, $reason ) ), 'Stopping requires a nonempty scalar reason.' );
}
check( is_wp_error( DIN_Packages::stop( 999, 9, 'Buyer request' ) ), 'Missing package must be rejected.' );
DIN_Packages_Mail::$sending = true;
check( is_wp_error( DIN_Packages::stop( 1, 9, 'Buyer request' ) ), 'In-flight mail must finish before the stop can commit.' );
DIN_Packages_Mail::$sending = false;
check( DIN_Packages::get( 1 ) === $original_lifetime, 'Rejected stop must preserve the package.' );
$stopped = DIN_Packages::stop( 1, 9, "<b>Buyer request</b>\nConfirmed by support" );
check( ! is_wp_error( $stopped ) && 'stopped' === DIN_Packages::status( $stopped ), 'Activated Lifetime must become Stopped.' );
$stopped = DIN_Packages::get( 1 );
check( $stopped['stopped_at'] > 0 && 9 === $stopped['stopped_by'] && "Buyer request\nConfirmed by support" === $stopped['stop_reason'], 'Stopping records server time, admin and sanitized reason.' );
foreach ( array( 'period', 'started_at', 'expires_at', 'proof_ids', 'product_id', 'order_id', 'heading' ) as $field ) {
	check( $stopped[ $field ] === $original_lifetime[ $field ], 'Stop must preserve original ' . $field );
}
check( array_slice( $stopped['history'], 0, -1 ) === $original_lifetime['history'] && 'stopped' === end( $stopped['history'] )['action'], 'Stopping appends rather than replaces history.' );
check( $stopped['generation'] === $original_lifetime['generation'] + 1 && in_array( 1, DIN_Packages_Mail::$scheduled, true ), 'Stopping invalidates queued reminders.' );
check( DIN_Packages::stop( 1, 9, 'Repeated click' ) === $stopped, 'Repeated stop must not rewrite date, reason or history.' );
check( DIN_Packages::get( 2 ) === $other_package && serialize( $origin ) === $original_order, 'Stop is per-package and never modifies payment orders.' );
DIN_Packages::approve_order( $origin, 9 );
check( DIN_Packages::get( 1 ) === $stopped, 'Saving Completed again must not reactivate Stopped packages.' );
DIN_Packages::mark_review( 1, 'Refund review' );
check( 'stopped' === DIN_Packages::status( DIN_Packages::get( 1 ) ), 'Review cannot replace terminal Stopped status.' );
$wpdb->contend = static function () { DIN_Packages::stop( 2, 9, 'Concurrent buyer request' ); };
$racing_purchase = DIN_Packages::mutate( 2, static function ( $package ) { return DIN_Packages::apply_purchase( $package, 'lifetime', 300, 301, 9, time() ); } );
check( is_wp_error( $racing_purchase ) && 'stopped' === DIN_Packages::status( DIN_Packages::get( 2 ) ), 'CAS retry cannot apply a purchase after stopping.' );
foreach ( array( 'renew_1', 'renew_2', 'lifetime' ) as $action ) {
	check( is_wp_error( DIN_Packages::purchase_option( 2, $action, 7, false ) ), 'Stopped cart selections must be rejected.' );
	check( is_wp_error( DIN_Packages::apply_purchase( DIN_Packages::get( 2 ), $action, 400, 401, 9, time() ) ), 'Stopped packages cannot renew or upgrade.' );
}
echo "DIN Package Lifecycle core smoke: OK (in-memory only, including package stopping)\n";
