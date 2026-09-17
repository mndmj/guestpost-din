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
	public $id; public $product; public $quantity; public $meta; public $order_id;
	public function __construct( $id, $product, $quantity, $meta ) { $this->id=$id; $this->product=$product; $this->quantity=$quantity; $this->meta=$meta; }
	public function get_id() { return $this->id; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function get_quantity() { return $this->quantity; }
	public function get_product_id() { return $this->product; }
	public function get_variation_id() { return 0; }
	public function get_order_id() { return $this->order_id; }
	public function get_name() { return 'Guest post ' . $this->product; }
}
class Test_Order {
	public $id; public $customer=7; public $status='processing'; public $proofs=array(); public $items; public $meta=array( '_din_packages_version'=>'1.0.0' ); public $refunded=0; public $currency='USD';
	public function __construct( $id, $items ) { $this->id=$id; $this->items=$items; foreach ( $items as $item ) { $item->order_id = $id; } $this->meta['_gpm_published_at'] = ( new DateTimeImmutable( '-10 days', wp_timezone() ) )->format( 'Y-m-d' ); }
	public function get_id() { return $this->id; }
	public function get_customer_id() { return $this->customer; }
	public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
	public function get_created_via() { return $this->meta['_created_via'] ?? ''; }
	public function save_meta_data() {}
	public function read_meta_data( $force = false ) {}
	public function get_data_store() { return new class { public function read( $order ) { if ( isset( $GLOBALS['source_refresh'] ) ) { $GLOBALS['source_refresh']( $order ); } } }; }
	public function get_items() { return $this->items; }
	public function get_item( $id ) { foreach ( $this->items as $item ) { if ( $item->get_id() === $id ) { return $item; } } return false; }
	public function get_customer_note() { return 'Heading original'; }
	public function get_currency() { return $this->currency; }
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
$publication_date = $order->meta['_gpm_published_at'];
$pending_package = DIN_Packages::get( 1 );
foreach ( array( '', null, array( '2026-09-07' ), '2026-02-30', '2026-9-7', '0000-01-01', '2026-09-07 12:00', ( new DateTimeImmutable( 'tomorrow', wp_timezone() ) )->format( 'Y-m-d' ) ) as $invalid_date ) {
	$order->meta['_gpm_published_at'] = $invalid_date;
	$messages = DIN_Packages::approve_order( $order, 9 );
	check( DIN_Packages::get( 1 ) === $pending_package && ! DIN_Packages_Mail::$scheduled, 'Invalid or missing publication date must not activate or schedule a package.' );
	check( false !== strpos( implode( ' ', $messages ), 'Publication Date' ), 'Publication date rejection must explain the missing admin field.' );
}
$order->meta['_gpm_published_at'] = $publication_date;
$before_approval = time();
DIN_Packages::approve_order( $order, 9 ); $initial = DIN_Packages::get( 1 );
$expected_start = new DateTimeImmutable( $publication_date . ' 00:00:00', wp_timezone() );
check( $initial['started_at'] === $expected_start->getTimestamp(), 'Activation must start at publication midnight in the site timezone, not approval time.' );
check( $initial['history'][0]['at'] >= $before_approval && $initial['history'][0]['at'] <= time() && $initial['history'][0]['started_at'] === $initial['started_at'], 'Audit history must separate approval time from service start.' );
$order->meta['_gpm_published_at'] = '';
DIN_Packages::approve_order( $order, 9 );
check( DIN_Packages::get( 1 ) === $initial, 'Clearing the publication field on an active order must not reset activation.' );
$order->meta['_gpm_published_at'] = '2020-01-01';
DIN_Packages::approve_order( $order, 9 );
check( DIN_Packages::get( 1 ) === $initial, 'Editing publication metadata must not migrate an existing entitlement.' );
$order->meta['_gpm_published_at'] = $publication_date;
check( DIN_Packages::get( 3 )['started_at'] === $initial['started_at'], 'All units on an order must use its publication date, including Lifetime.' );
$unstarted = array_merge( $initial, array( 'started_at' => 0, 'expires_at' => 0, 'history' => array() ) );
$approval_time = ( new DateTimeImmutable( '2024-03-10 12:00:00', wp_timezone() ) )->getTimestamp();
$backdated = DIN_Packages::activate( $unstarted, array( 'proof-1' ), 9, $approval_time, '2024-02-29' );
check( ! is_wp_error( $backdated ) && $backdated['started_at'] === ( new DateTimeImmutable( '2024-02-29 00:00:00', wp_timezone() ) )->getTimestamp() && $backdated['expires_at'] === ( new DateTimeImmutable( '2025-02-28 00:00:00', wp_timezone() ) )->getTimestamp(), 'Publication-based annual expiry must clamp leap day in the site timezone.' );
check( 'expired' === DIN_Packages::status( $backdated, $backdated['expires_at'] ), 'A backdated term must expire at its real boundary.' );
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
// Manual expiry edits must never masquerade as a paid renewal or modify the source order.
check( method_exists( 'DIN_Packages', 'adjust_expiry' ), 'Manual Annual expiry adjustment is missing.' );
$wpdb = new Memory_DB();
$origin = new Test_Order( 500, array( new Test_Item( 501, 1, 2, array( '_din_package_config' => $config ) ) ) );
$orders[500] = $origin;
$origin->status = 'completed';
$origin->proofs = array( array( 'id' => 'proof-500', 'path' => 'valid' ) );
DIN_Packages::approve_order( $origin, 9 );
$base = DIN_Packages::mutate( 1, static function ( $p ) {
	$p['started_at'] = ( new DateTimeImmutable( '2024-02-29 14:30:17', wp_timezone() ) )->getTimestamp();
	$p['expires_at'] = ( new DateTimeImmutable( '2028-02-29 14:30:17', wp_timezone() ) )->getTimestamp();
	return $p;
} );
$other_package = DIN_Packages::get( 2 );
$original_order = serialize( $origin );
$change = array( 'revision' => (string) $base['revision'], 'date' => '2028-02-29', 'extension' => 'none', 'reason' => 'Support correction' );
DIN_Packages_Mail::$scheduled = array();
check( DIN_Packages::adjust_expiry( 1, 500, 9, $change ) === $base && ! DIN_Packages_Mail::$scheduled, 'Unchanged local date must preserve exact timestamp, revision, history and reminder queue.' );
foreach ( array( array( 1, 500, 7 ), array( 1, 999, 9 ), array( 999, 500, 9 ) ) as $args ) {
	check( is_wp_error( DIN_Packages::adjust_expiry( ...array_merge( $args, array( $change ) ) ) ), 'Buyer, wrong source order or missing package must not allow adjustment.' );
}
foreach ( array( 'manage_woocommerce', 'edit_shop_order' ) as $capability ) {
	$GLOBALS['denied_caps'] = array( $capability );
	check( is_wp_error( DIN_Packages::adjust_expiry( 1, 500, 9, $change ) ), 'Both management and source-order edit capabilities are mandatory for expiry changes.' );
}
$GLOBALS['denied_caps'] = array();
foreach ( array(
	array( 'revision' => 0 ), array( 'revision' => '3x' ), array( 'revision' => array( 3 ) ), array( 'revision' => PHP_INT_MAX . '0' ),
	array( 'date' => '2028-02-30' ), array( 'date' => '2028-2-29' ), array( 'date' => array( '2028-02-29' ) ),
	array( 'date' => '2024-02-29' ), array( 'date' => '2024-02-28' ),
	array( 'extension' => '2' ), array( 'extension' => array( '1' ) ), array( 'date' => '2029-03-01', 'extension' => '1' ),
	array( 'reason' => '' ), array( 'reason' => '   ' ), array( 'reason' => array( 'reason' ) ), array( 'reason' => str_repeat( 'a', 1001 ) ),
) as $invalid ) {
	check( is_wp_error( DIN_Packages::adjust_expiry( 1, 500, 9, array_merge( $change, $invalid ) ) ), 'Malformed, conflicting or pre-start expiry changes must be rejected.' );
}
foreach ( array( 'status' => 'processing', 'customer' => 8, 'refunded' => 1, 'currency' => 'EUR', 'proofs' => array() ) as $field => $invalid ) {
	$saved = $origin->$field; $origin->$field = $invalid;
	check( is_wp_error( DIN_Packages::adjust_expiry( 1, 500, 9, $change ) ), 'Invalid source ' . $field . ' must block manual expiry adjustments.' );
	$origin->$field = $saved;
}
foreach ( array( 'quantity' => 0, 'product' => 2, 'id' => 999, 'order_id' => 999 ) as $field => $invalid ) {
	$saved = $origin->items[0]->$field; $origin->items[0]->$field = $invalid;
	check( is_wp_error( DIN_Packages::adjust_expiry( 1, 500, 9, $change ) ), 'Changed source item ' . $field . ' must block expiry changes.' );
	$origin->items[0]->$field = $saved;
}
$GLOBALS['source_refresh'] = static function ( $order ) { $order->status = 'cancelled'; };
check( is_wp_error( DIN_Packages::adjust_expiry( 1, 500, 9, $change ) ), 'Expiry editing must reread stored source-order data, not trust a stale Completed object.' );
unset( $GLOBALS['source_refresh'] ); $origin->status = 'completed';
$GLOBALS['source_refresh'] = static function ( $order ) { throw new RuntimeException( 'Source order disappeared during refresh.' ); };
try { $unreadable_source = DIN_Packages::adjust_expiry( 1, 500, 9, $change ); } catch ( Exception $error ) { $unreadable_source = null; }
check( is_wp_error( $unreadable_source ), 'A missing or corrupt source order must return a controlled error, never bubble a read exception.' );
unset( $GLOBALS['source_refresh'] );
DIN_Packages_Mail::$sending = true;
check( is_wp_error( DIN_Packages::adjust_expiry( 1, 500, 9, array_merge( $change, array( 'date' => '2028-03-01' ) ) ) ), 'In-flight reminders must finish before expiry changes commit.' );
DIN_Packages_Mail::$sending = false;
check( DIN_Packages::get( 1 ) === $base && ! DIN_Packages_Mail::$scheduled, 'Rejected expiry changes must not alter package state or reminders.' );
$extended = DIN_Packages::adjust_expiry( 1, 500, 9, array_merge( $change, array( 'extension' => '1', 'reason' => "<b>Courtesy</b>\nApproved" ) ) );
check( ! is_wp_error( $extended ) && $extended['expires_at'] === ( new DateTimeImmutable( '2029-02-28 14:30:17', wp_timezone() ) )->getTimestamp(), 'Active +1 year must preserve remaining time and clamp leap day in the site timezone.' );
$event = end( $extended['history'] );
check( 'expiry_adjusted' === $event['action'] && 9 === $event['by'] && $event['at'] > 0 && "Courtesy\nApproved" === $event['reason'] && '1' === $event['extension'], 'Manual adjustments need a sanitized admin audit event distinct from a paid renewal.' );
check( $event['old_expires_at'] === $base['expires_at'] && $event['expires_at'] === $extended['expires_at'] && ! isset( $event['item_id'] ) && ! isset( $event['order_id'] ), 'Adjustment history must retain both expiry values without inventing purchase references.' );
check( array_slice( $extended['history'], 0, -1 ) === $base['history'] && $extended['generation'] === $base['generation'] + 1 && DIN_Packages_Mail::$scheduled === array( 1 ), 'One adjustment appends history and reschedules exactly one new generation.' );
foreach ( array( 'started_at', 'period', 'proof_ids', 'activated_by', 'emails', 'customer_id', 'order_id', 'order_item_id', 'product_id' ) as $field ) {
	check( $extended[$field] === $base[$field], 'Adjusting expiry must preserve ' . $field );
}
check( is_wp_error( DIN_Packages::adjust_expiry( 1, 500, 9, array_merge( $change, array( 'extension' => '1' ) ) ) ) && DIN_Packages::get( 1 ) === $extended && DIN_Packages_Mail::$scheduled === array( 1 ), 'Replaying a stale form must not add another year or another reminder.' );
$change = array_merge( $change, array( 'revision' => $extended['revision'], 'date' => '2025-05-10' ) );
$shortened = DIN_Packages::adjust_expiry( 1, 500, 9, $change );
check( ! is_wp_error( $shortened ) && $shortened['expires_at'] === ( new DateTimeImmutable( '2025-05-10 14:30:17', wp_timezone() ) )->getTimestamp() && 'expired' === DIN_Packages::status( $shortened ), 'Manual past expiry must keep local time and expire naturally without marking Stopped.' );
$change = array_merge( $change, array( 'revision' => $shortened['revision'], 'extension' => '1' ) );
$before_extend = new DateTimeImmutable( 'now', wp_timezone() );
$renewed_manual = DIN_Packages::adjust_expiry( 1, 500, 9, $change );
$after_extend = new DateTimeImmutable( 'now', wp_timezone() );
check( ! is_wp_error( $renewed_manual ) && $renewed_manual['expires_at'] >= $before_extend->modify( '+1 year' )->getTimestamp() && $renewed_manual['expires_at'] <= $after_extend->modify( '+1 year' )->getTimestamp(), 'Expired +1 year must begin now, not at the old expired date.' );
check( DIN_Packages::get( 2 ) === $other_package && serialize( $origin ) === $original_order, 'Expiry changes must not touch other units, source orders, invoice or payment state.' );
$change = array_merge( $change, array( 'revision' => $renewed_manual['revision'], 'date' => ( new DateTimeImmutable( '@' . $renewed_manual['expires_at'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' ) ) );
$scheduled_before_race = DIN_Packages_Mail::$scheduled;
$wpdb->contend = static function () use ( $change ) { DIN_Packages::adjust_expiry( 1, 500, 9, array_merge( $change, array( 'reason' => 'Concurrent adjustment' ) ) ); };
$raced = DIN_Packages::adjust_expiry( 1, 500, 9, $change );
$after_race = DIN_Packages::get( 1 );
check( is_wp_error( $raced ) && $after_race['generation'] === $renewed_manual['generation'] + 1 && count( DIN_Packages_Mail::$scheduled ) === count( $scheduled_before_race ) + 1, 'CAS contention must reject stale edits, never apply the extension twice.' );
foreach ( array( array( 'stopped_at' => time() ), array( 'review' => 'Review required' ), array( 'started_at' => 0 ), array( 'period' => 'lifetime' ), array( 'expires_at' => 0 ) ) as $invalid ) {
	DIN_Packages::mutate( 2, static function ( $p ) use ( $invalid, $other_package ) { return array_merge( $other_package, $invalid ); } );
	$guarded = DIN_Packages::get( 2 );
	$guard_change = array( 'revision' => $guarded['revision'], 'date' => '2030-05-10', 'extension' => 'none', 'reason' => 'State validation' );
	check( is_wp_error( DIN_Packages::adjust_expiry( 2, 500, 9, $guard_change ) ) && DIN_Packages::get( 2 ) === $guarded, 'Stopped, review, pending, Lifetime and missing-expiry packages must reject edits.' );
}
// A paid admin request must apply the agreed date, never grant service on mere order creation.
$wpdb = new Memory_DB();
$origin = new Test_Order( 700, array( new Test_Item( 701, 1, 1, array( '_din_package_config' => $config ) ) ) );
$orders[700] = $origin;
$origin->status = 'completed';
$origin->proofs = array( array( 'id' => 'proof-700', 'path' => 'valid' ) );
DIN_Packages::approve_order( $origin, 9 );
$base = DIN_Packages::mutate( 1, static function ( $p ) {
	$p['expires_at'] = ( new DateTimeImmutable( '2030-02-28 14:30:17', wp_timezone() ) )->getTimestamp();
	return $p;
} );
$requested_expiry = ( new DateTimeImmutable( '2032-04-15 14:30:17', wp_timezone() ) )->getTimestamp();
$request = array( 'expires_at' => $requested_expiry, 'reason' => 'Paid extension agreed with buyer', 'requested_by' => 9, 'generation' => $base['generation'] );
$purchase = array( 'package_id' => 1, 'action' => 'renew_2', 'admin_request' => $request );
$base = DIN_Packages::mutate( 1, static function ( $p ) use ( $request ) {
	$p['payment_request'] = array_merge( $request, array( 'key' => 'test-request', 'order_id' => 710, 'state' => 'ready', 'action' => 'renew_2' ) );
	return $p;
} );
$base = DIN_Packages::get( 1 );
$paid_order = new Test_Order( 710, array( new Test_Item( 711, 1, 1, array( '_din_package_purchase' => $purchase ) ) ) );
$orders[710] = $paid_order;
$paid_order->meta['_din_package_admin_request'] = $purchase;
DIN_Packages::approve_order( $paid_order, 9, true );
check( DIN_Packages::get( 1 ) === $base, 'An unpaid admin request must leave service and history unchanged.' );
$paid_order->status = 'completed';
DIN_Packages::approve_order( $paid_order, 9 );
check( DIN_Packages::get( 1 ) === $base, 'Completed without explicit payment confirmation must not apply the requested expiry.' );
DIN_Packages::approve_order( $paid_order, 9, true );
$applied = DIN_Packages::get( 1 );
check( $applied['expires_at'] === $requested_expiry, 'Paid admin renewal must use the agreed New expiry rather than recalculate two years.' );
$event = end( $applied['history'] );
check( $event['reason'] === $request['reason'] && $event['requested_by'] === 9 && $event['order_id'] === 710 && $event['item_id'] === 711, 'Paid adjustment history must preserve reason, requester and real payment transaction.' );
check( $applied['started_at'] === $base['started_at'] && $applied['generation'] === $base['generation'] + 1, 'Approval preserves start and advances exactly one reminder generation.' );
DIN_Packages::approve_order( $paid_order, 9, true );
check( DIN_Packages::get( 1 ) === $applied, 'Repeated paid approval must not extend the package again.' );
foreach ( array( 'expires_at' => $requested_expiry + DAY_IN_SECONDS, 'reason' => 'Changed after quote', 'requested_by' => 7, 'generation' => 999 ) as $key => $value ) {
	$bad_request = array_merge( $request, array( $key => $value ) );
	check( is_wp_error( DIN_Packages::apply_purchase( $base, 'renew_2', 710, 711, 9, time(), $bad_request ) ), 'Changing approved request ' . $key . ' must fail closed.' );
}
check( is_wp_error( DIN_Packages::apply_purchase( $base, 'renew_2', 710, 711, 9, $requested_expiry, $request ) ), 'A date that expired before paid approval must not be silently recalculated.' );
$changed_generation = array_merge( $base, array( 'generation' => $base['generation'] + 1 ) );
check( is_wp_error( DIN_Packages::apply_purchase( $changed_generation, 'renew_2', 710, 711, 9, time(), $request ) ), 'An intervening service change invalidates the pending quote.' );
// A paid Lifetime request is still a real purchase event and cancels annual expiry only on approval.
$lifetime_request = array_merge( $request, array( 'expires_at' => 0 ) );
$lifetime_base = $base;
$lifetime_base['payment_request'] = array_merge( $lifetime_request, array( 'order_id' => 720, 'state' => 'ready', 'action' => 'lifetime' ) );
$lifetime = DIN_Packages::apply_purchase( $lifetime_base, 'lifetime', 720, 721, 9, time(), $lifetime_request );
check( ! is_wp_error( $lifetime ) && $lifetime['period'] === 'lifetime' && $lifetime['expires_at'] === 0 && $lifetime['started_at'] === $base['started_at'], 'Paid Lifetime conversion must retain original service start without an expiry.' );
check( end( $lifetime['history'] )['reason'] === $request['reason'], 'Lifetime audit must retain the admin reason.' );
// Removing the item request must not fall back to the ordinary automatic-date renewal path.
DIN_Packages::mutate( 1, static function ( $p ) use ( $base ) { return $base; } );
$before_tamper = DIN_Packages::get( 1 );
unset( $paid_order->items[0]->meta['_din_package_purchase']['admin_request'] );
DIN_Packages::approve_order( $paid_order, 9, true );
check( DIN_Packages::get( 1 ) === $before_tamper, 'Stripping quoted item metadata must not bypass the protected admin request.' );
$paid_order->items[0]->meta['_din_package_purchase'] = $purchase;
foreach ( array( 'refunded' => 1, 'currency' => 'EUR' ) as $field => $invalid ) {
	$saved = $origin->$field; $origin->$field = $invalid;
	DIN_Packages::approve_order( $paid_order, 9, true );
	check( DIN_Packages::get( 1 ) === $before_tamper, 'Paid application must recheck source ' . $field . '.' );
	$origin->$field = $saved;
}
$origin->items[0]->quantity = 0;
DIN_Packages::approve_order( $paid_order, 9, true );
check( DIN_Packages::get( 1 ) === $before_tamper, 'Removed source quantity must block paid request application.' );
$origin->items[0]->quantity = 1;
// Native invoice, account buttons and direct order-pay must share the same readiness guard.
check( method_exists( 'DIN_Packages', 'order_needs_payment' ), 'Admin requests need a native order-payment guard.' );
$paid_order->status = 'pending';
check( DIN_Packages::order_needs_payment( true, $paid_order ) && ! DIN_Packages::order_needs_payment( false, $paid_order ), 'Ready requests are payable only when WooCommerce also requires payment.' );
foreach ( array( array( 'payment_request' => array_merge( $base['payment_request'], array( 'state' => 'failed' ) ) ), array( 'generation' => $base['generation'] + 1 ), array( 'stopped_at' => time() ), array( 'review' => 'Source requires review' ), array( 'rejected_items' => array( 711 => time() ) ) ) as $invalid ) {
	DIN_Packages::mutate( 1, static function ( $p ) use ( $base, $invalid ) { return array_merge( $base, $invalid ); } );
	check( ! DIN_Packages::order_needs_payment( true, $paid_order ), 'Failed, changed, stopped, review and rejected requests must not accept payment.' );
}
DIN_Packages::mutate( 1, static function ( $p ) use ( $base ) { return $base; } );
$paid_order->customer = 8;
check( ! DIN_Packages::order_needs_payment( true, $paid_order ), 'Transferred payment order must not be payable for another buyer service.' );
$paid_order->customer = 7;
$paid_order->meta['_created_via'] = 'din-package-renewal';
unset( $paid_order->meta['_din_package_admin_request'], $paid_order->items[0]->meta['_din_package_purchase']['admin_request'] );
check( ! DIN_Packages::order_needs_payment( true, $paid_order ), 'Removing both quote copies must not turn a managed request into an ordinary payable order.' );
DIN_Packages::mutate( 1, static function ( $p ) { $p['payment_request']['order_id'] = 799; return $p; } );
$before_replaced_request = DIN_Packages::get( 1 );
$paid_order->status = 'completed';
DIN_Packages::approve_order( $paid_order, 9, true );
check( DIN_Packages::get( 1 ) === $before_replaced_request, 'A replaced managed request with stripped quotes must not become an ordinary renewal at approval.' );
$ordinary_order = new Test_Order( 800, array() );
check( DIN_Packages::order_needs_payment( true, $ordinary_order ) && ! DIN_Packages::order_needs_payment( false, $ordinary_order ), 'Ordinary order payment must remain unchanged.' );
// Custom years price the selected term but still apply only the paid, immutable requested date.
DIN_Packages::mutate( 1, static function ( $p ) use ( $base ) { return $base; } );
$custom_option = DIN_Packages::purchase_option( 1, 'renew_custom', 7, false, 3 );
check( ! is_wp_error( $custom_option ) && 3 === $custom_option['multiplier'] && 3 === $custom_option['years'] && 1 === $custom_option['product_id'], 'Three custom years must use the Annual product and three times its price.' );
foreach ( array( 0, -1, 1.5, '3.5', '3e2', true, array( 3 ), '', '03', '8000', '10000' ) as $invalid_years ) {
	check( is_wp_error( DIN_Packages::purchase_option( 1, 'renew_custom', 7, false, $invalid_years ) ), 'Custom duration must reject non-whole, malformed or unsupported years.' );
}
check( is_wp_error( DIN_Packages::purchase_option( 1, 'renew_custom', 7, false ) ), 'Ordinary buyer paths cannot request custom renewal without an admin term.' );
$custom_request = array_merge( $request, array( 'years' => 3 ) );
$custom_purchase = array( 'package_id' => 1, 'action' => 'renew_custom', 'admin_request' => $custom_request );
DIN_Packages::mutate( 1, static function ( $p ) use ( $custom_request ) {
	$p['payment_request'] = array_merge( $custom_request, array( 'key' => 'custom', 'order_id' => 810, 'state' => 'ready', 'action' => 'renew_custom' ) );
	return $p;
} );
$custom_base = DIN_Packages::get( 1 );
$custom_order = new Test_Order( 810, array( new Test_Item( 811, 1, 1, array( '_din_package_purchase' => $custom_purchase ) ) ) );
$custom_order->meta['_din_package_admin_request'] = $custom_purchase;
$custom_order->status = 'pending'; $orders[810] = $custom_order;
check( DIN_Packages::order_needs_payment( true, $custom_order ), 'A valid custom request must be payable through native WooCommerce.' );
$custom_order->items[0]->meta['_din_package_purchase']['admin_request']['years'] = 4;
$custom_order->meta['_din_package_admin_request'] = $custom_order->items[0]->meta['_din_package_purchase'];
check( ! DIN_Packages::order_needs_payment( true, $custom_order ), 'Changing both order copies of quoted years must not change the immutable request.' );
$custom_order->items[0]->meta['_din_package_purchase'] = $custom_purchase;
$custom_order->meta['_din_package_admin_request'] = $custom_purchase;
check( is_wp_error( DIN_Packages::apply_purchase( $custom_base, 'renew_custom', 810, 811, 9, time() ) ), 'Custom renewal cannot fall back to an unquoted ordinary renewal.' );
$custom_order->status = 'completed';
DIN_Packages::approve_order( $custom_order, 9 );
check( DIN_Packages::get( 1 ) === $custom_base, 'Completed alone must not apply a custom term.' );
DIN_Packages::approve_order( $custom_order, 9, true );
$custom_applied = DIN_Packages::get( 1 );
check( $custom_applied['expires_at'] === $requested_expiry && end( $custom_applied['history'] )['years'] === 3 && end( $custom_applied['history'] )['action'] === 'renew_custom', 'Approved custom duration must keep the agreed date and truthful three-year audit history.' );
DIN_Packages::approve_order( $custom_order, 9, true );
check( DIN_Packages::get( 1 ) === $custom_applied, 'Custom paid approval must remain idempotent.' );
$unquoted_custom = new Test_Order( 820, array( new Test_Item( 821, 1, 1, array( '_din_package_purchase' => array( 'package_id' => 1, 'action' => 'renew_custom' ) ) ) ) );
$unquoted_custom->status = 'pending';
check( ! DIN_Packages::order_needs_payment( true, $unquoted_custom ), 'An unquoted custom action must never be treated as an ordinary payable order.' );
echo "DIN Package Lifecycle core smoke: OK (in-memory only, including paid admin expiry requests)\n";
