<?php
// Run: php tests/requests-smoke.php. Real package core, SQLite memory DB, fake Woo persistence/mail only.
require __DIR__ . '/core-smoke.php';
define( 'DIN_PACKAGES_VERSION', '1.0.0' );
function wp_generate_uuid4() { static $n = 0; return 'request-token-' . ++$n; }
function wc_prices_include_tax() { return false; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['request_actions'][$hook][] = $callback; }
function remove_action( $hook, $callback, $priority = 10 ) { $GLOBALS['request_actions'][$hook] = array_filter( $GLOBALS['request_actions'][$hook] ?? array(), static function ( $entry ) use ( $callback ) { return $entry !== $callback; } ); }
function wc_get_price_excluding_tax( $product, $args = array() ) {
	check( $args['order']->get_customer_id() === 7, 'Tax pricing must use the buyer order, not the logged-in administrator.' );
	return (float) ( $args['price'] ?? $product->get_price() ) * ( $args['qty'] ?? 1 );
}
function request_stage( $stage ) {
	if ( isset( $GLOBALS['request_hook'] ) ) { $GLOBALS['request_hook']( $stage ); }
	if ( ( $GLOBALS['request_failure'] ?? '' ) === $stage ) { throw new RuntimeException( 'Injected ' . $stage . ' failure' ); }
}
function WC() { return new class {
	public function mailer() { return new class { public function get_emails() { return array( 'WC_Email_Customer_Invoice' => new class {
		public function trigger( $id, $order = false ) {
			request_stage( 'mail' ); $GLOBALS['invoice_ids'][] = $id;
			foreach ( $GLOBALS['request_actions']['woocommerce_email_sent'] ?? array() as $callback ) { $callback( empty( $GLOBALS['mail_rejected'] ), 'customer_invoice', $this ); }
		}
	} ); } }; }
}; }
class Request_Product extends Test_Product {
	public $price = 125;
	public function get_price() { return $this->price; }
}
class WC_Order_Item_Product extends Test_Item {
	public $total = 0; public $subtotal = 0;
	public function __construct() { parent::__construct( 0, 0, 1, array() ); }
	public function set_product( $product ) { $this->product = $product->get_id(); }
	public function set_quantity( $quantity ) { $this->quantity = $quantity; }
	public function set_subtotal( $value ) { $this->subtotal = $value; }
	public function set_total( $value ) { $this->total = $value; }
	public function get_total() { return $this->total; }
	public function update_meta_data( $key, $value ) { $this->meta[$key] = $value; }
}
class WC_Order extends Test_Order {
	public $addresses = array( 'billing' => array( 'email' => 'buyer@example.test', 'country' => 'ID' ), 'shipping' => array( 'country' => 'ID', 'city' => 'Jakarta' ) );
	public $created_via = ''; public $date_paid = null; public $total = 0; public $prices_include_tax; public $notes = array();
	public function __construct( $id = 0, $items = array() ) { parent::__construct( $id, $items ); if ( ! $id ) { $this->meta = array(); $this->status = 'pending'; } }
	public function set_customer_id( $id ) { $this->customer = $id; }
	public function set_status( $status ) { $this->status = $status; }
	public function get_status() { return $this->status; }
	public function has_status( $status ) { return in_array( $this->status, (array) $status, true ); }
	public function set_currency( $currency ) { $this->currency = $currency; }
	public function set_created_via( $value ) { $this->created_via = $value; }
	public function get_created_via() { return $this->created_via; }
	public function set_prices_include_tax( $value ) { $this->prices_include_tax = $value; }
	public function set_date_paid( $value ) { $this->date_paid = $value; }
	public function get_date_paid() { return $this->date_paid; }
	public function get_address( $type ) { return $this->addresses[$type]; }
	public function set_address( $address, $type ) { $this->addresses[$type] = $address; }
	public function get_billing_email() { return $this->addresses['billing']['email'] ?? ''; }
	public function get_total() { return $this->total; }
	public function add_item( $item ) { request_stage( 'items' ); $item->id = 5000 + count( $this->items ); $item->order_id = $this->id; $this->items[] = $item; }
	public function save() {
		if ( ! $this->id ) { request_stage( 'create' ); $this->id = ++$GLOBALS['request_sequence']; }
		$GLOBALS['orders'][$this->id] = $this;
		request_stage( 'saved' );
		return $this->id;
	}
	public function calculate_totals( $taxes = true ) {
		request_stage( 'totals' );
		check( $taxes && 'ID' === $this->get_address( 'billing' )['country'], 'Taxes must be recalculated using copied buyer addresses.' );
		$this->total = array_sum( array_map( static function ( $item ) { return $item->get_total(); }, $this->items ) ) * ( 'yes' === $this->get_meta( 'is_vat_exempt' ) ? 1 : 1.1 );
		$this->save();
		return $this->total;
	}
	public function add_order_note( $note ) { $this->notes[] = $note; }
}
class Request_DB extends Memory_DB {
	public $fail_writes = false;
	public function update( $table, $data, $where, ...$formats ) { return $this->fail_writes ? false : parent::update( $table, $data, $where, ...$formats ); }
}
function request_fixture() {
	unset( $GLOBALS['request_failure'], $GLOBALS['request_hook'], $GLOBALS['source_refresh'], $GLOBALS['mail_rejected'] );
	$GLOBALS['request_actions'] = array();
	$GLOBALS['denied_caps'] = array();
	$GLOBALS['invoice_ids'] = array();
	$GLOBALS['request_sequence'] = 1000;
	$GLOBALS['wpdb'] = new Request_DB();
	$GLOBALS['products'] = array( 1 => new Request_Product( 1, 'annual' ), 2 => new Request_Product( 2, 'lifetime' ) );
	$GLOBALS['products'][2]->price = 475;
	$source = new WC_Order( 100, array( new Test_Item( 101, 1, 1, array( '_din_package_config' => DIN_Packages::config( $GLOBALS['products'][1] ) ) ) ) );
	$source->status = 'completed'; $source->date_paid = time(); $source->proofs = array( array( 'id' => 'proof', 'path' => 'valid' ) );
	$GLOBALS['orders'] = array( 100 => $source );
	DIN_Packages::approve_order( $source, 9 );
	$base = DIN_Packages::mutate( 1, static function ( $p ) {
		$p['started_at'] = ( new DateTimeImmutable( '2024-02-29 14:30:17', wp_timezone() ) )->getTimestamp();
		$p['expires_at'] = ( new DateTimeImmutable( '2028-02-29 14:30:17', wp_timezone() ) )->getTimestamp();
		return $p;
	} );
	DIN_Packages_Mail::$scheduled = array();
	return array( $base, array( 'revision' => $base['revision'], 'extension' => '2', 'date' => '2030-02-28', 'reason' => "<b>Renewal</b>\nApproved" ) );
}
function unchanged_entitlement( $before ) {
	$after = DIN_Packages::get( 1 );
	foreach ( array( 'started_at', 'expires_at', 'period', 'generation', 'history', 'emails', 'proof_ids' ) as $field ) {
		check( $after[$field] === $before[$field], 'Creating a payment request must never grant service or alter ' . $field );
	}
}
$file = dirname( __DIR__ ) . '/includes/class-din-packages-requests.php';
if ( is_file( $file ) ) { require $file; }
check( class_exists( 'DIN_Packages_Requests' ), 'Paid admin request creation service is missing.' );

// Wrong pricing/quantity, early grant, or missing invoice metadata must fail these observable assertions.
list( $base, $change ) = request_fixture();
$source_before = serialize( $orders[100] );
$order = DIN_Packages_Requests::create( 1, 100, 9, $change );
check( $order instanceof WC_Order, 'A valid two-year selection must create a Woo payment order.' );
$item = array_values( $order->get_items() )[0];
$purchase = $item->get_meta( '_din_package_purchase' );
check( 'pending' === $order->get_status() && ! $order->get_date_paid() && 7 === $order->get_customer_id(), 'Request must remain unpaid and owned by the original buyer.' );
check( 1 === $item->get_quantity() && 250.0 === (float) $item->get_total() && 275.0 === $order->get_total(), 'Two years must cost two annual prices on one unit plus recalculated taxes.' );
check( 'renew_2' === $purchase['action'] && 1 === $purchase['package_id'], 'Invoice must use the native package purchase flow.' );
check( '2030-02-28 14:30:17' === ( new DateTimeImmutable( '@' . $purchase['admin_request']['expires_at'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ), 'Requested date must preserve the old expiry local clock.' );
check( "Renewal\nApproved" === $purchase['admin_request']['reason'] && 9 === $purchase['admin_request']['requested_by'], 'The buyer-visible request must retain sanitized reason and admin identity.' );
check( $order->get_meta( '_din_package_admin_request' ) === $purchase && $item->get_meta( 'New expiry' ) && $item->get_meta( 'Reason' ) && $item->get_meta( 'Duration' ), 'Protected order mirror and visible invoice metadata must be present.' );
check( ! $item->get_meta( '_din_package_config' ) && ! DIN_Packages::for_order( $order->get_id() ), 'A renewal invoice must not capture another package.' );
check( 'ready' === DIN_Packages::get( 1 )['payment_request']['state'] && $invoice_ids === array( $order->get_id() ), 'Send the native invoice once, only after the request is ready.' );
check( DIN_Packages::order_needs_payment( true, $order ), 'The generated ready invoice must pass the native payment guard.' );
check( $order->addresses === $orders[100]->addresses && serialize( $orders[100] ) === $source_before, 'New order must copy buyer addresses without changing the original receipt.' );
unchanged_entitlement( $base );
$replay = DIN_Packages_Requests::create( 1, 100, 9, $change );
check( $replay instanceof WC_Order && $replay->get_id() === $order->get_id() && 2 === count( $orders ) && 1 === count( $invoice_ids ), 'Same-form replay must reuse the invoice, never create or email twice.' );
$same = DIN_Packages_Requests::create( 1, 100, 9, array_merge( $change, array( 'revision' => DIN_Packages::get( 1 )['revision'] ) ) );
check( $same instanceof WC_Order && $same->get_id() === $order->get_id() && 2 === count( $orders ), 'A reloaded form with the same request must reuse the ready invoice.' );
$fresh = array_merge( $change, array( 'revision' => DIN_Packages::get( 1 )['revision'], 'reason' => 'Another request' ) );
check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $fresh ) ) && 2 === count( $orders ), 'A different request must not overlap an existing unpaid order.' );
$order->status = 'cancelled';
check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ), 'A stale cancelled request must not silently recreate its order.' );
$retry = DIN_Packages_Requests::create( 1, 100, 9, $fresh );
check( $retry instanceof WC_Order && $retry->get_id() !== $order->get_id() && 3 === count( $orders ), 'An explicitly reloaded request may replace a cancelled order without deleting it.' );

list( $base, $change ) = request_fixture();
$lifetime = DIN_Packages_Requests::create( 1, 100, 9, array_merge( $change, array( 'extension' => 'lifetime', 'date' => '' ) ) );
check( $lifetime instanceof WC_Order && 475.0 === (float) $lifetime->get_items()[0]->get_total() && 2 === $lifetime->get_items()[0]->get_product_id(), 'Lifetime must bill its mapped product price once.' );
check( 0 === $lifetime->get_items()[0]->get_meta( '_din_package_purchase' )['admin_request']['expires_at'], 'Lifetime request has no expiry timestamp.' );
unchanged_entitlement( $base );

// Each invalid fixture must fail without creating even an empty order.
foreach ( array(
	array( 'revision' => null ), array( 'revision' => '1x' ), array( 'revision' => PHP_INT_MAX . '0' ), array( 'revision' => 1 ),
	array( 'extension' => 'none' ), array( 'extension' => '3' ), array( 'extension' => array( '1' ) ),
	array( 'date' => '2029-02-29' ), array( 'date' => '2030-2-28' ), array( 'date' => '2028-02-29' ), array( 'date' => '2020-01-01' ),
	array( 'extension' => 'lifetime', 'date' => '2030-02-28' ), array( 'reason' => '' ), array( 'reason' => array( 'reason' ) ), array( 'reason' => str_repeat( 'a', 1001 ) ),
) as $invalid ) {
	list( $base, $change ) = request_fixture();
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, array_merge( $change, $invalid ) ) ) && 1 === count( $orders ), 'Invalid dates, stale revisions, duration or reason must not create invoices: ' . json_encode( $invalid ) );
	unchanged_entitlement( $base );
}
foreach ( array( 'manage_woocommerce', 'edit_shop_order' ) as $capability ) {
	list( $base, $change ) = request_fixture(); $GLOBALS['denied_caps'] = array( $capability );
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && 1 === count( $orders ), 'Both admin capabilities must be enforced.' );
}
foreach ( array( 'status' => 'processing', 'customer' => 8, 'refunded' => 1, 'currency' => 'EUR', 'proofs' => array() ) as $field => $invalid ) {
	list( $base, $change ) = request_fixture(); $orders[100]->$field = $invalid;
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && 1 === count( $orders ), 'Invalid source ' . $field . ' must block invoices.' );
}
foreach ( array( 'quantity' => 0, 'product' => 2, 'id' => 999, 'order_id' => 999 ) as $field => $invalid ) {
	list( $base, $change ) = request_fixture(); $orders[100]->items[0]->$field = $invalid;
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && 1 === count( $orders ), 'Changed source item ' . $field . ' must block invoices.' );
}
foreach ( array( array( 'stopped_at' => 1 ), array( 'review' => 'Review' ), array( 'started_at' => 0 ), array( 'period' => 'lifetime' ), array( 'expires_at' => 0 ) ) as $invalid ) {
	list( $base, $change ) = request_fixture();
	$p = DIN_Packages::mutate( 1, static function ( $p ) use ( $invalid ) { return array_merge( $p, $invalid ); } );
	$change['revision'] = $p['revision'];
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && 1 === count( $orders ), 'Invalid package state must not create a payable request.' );
}
list( $base, $change ) = request_fixture();
foreach ( array( array( 1, 100, 7 ), array( 1, 999, 9 ), array( 999, 100, 9 ) ) as $args ) {
	check( is_wp_error( DIN_Packages_Requests::create( ...array_merge( $args, array( $change ) ) ) ), 'Wrong actor/source/package must be rejected.' );
}
$GLOBALS['source_refresh'] = static function ( $source ) { $source->status = 'cancelled'; };
check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && 1 === count( $orders ), 'Stored source state must be refreshed before reservation.' );

// CAS contention must choose one reservation owner, not create an order inside a retrying callback.
list( $base, $change ) = request_fixture();
$wpdb->contend = static function () use ( $change ) { $GLOBALS['winning_invoice'] = DIN_Packages_Requests::create( 1, 100, 9, $change ); };
$race = DIN_Packages_Requests::create( 1, 100, 9, $change );
check( $race instanceof WC_Order && $winning_invoice instanceof WC_Order && $race->get_id() === $winning_invoice->get_id() && 2 === count( $orders ) && 1 === count( $invoice_ids ), 'CAS retry must reuse the concurrent winning invoice.' );
unchanged_entitlement( $base );
list( $base, $change ) = request_fixture();
$GLOBALS['request_hook'] = static function ( $stage ) use ( $change ) {
	if ( 'create' === $stage ) { $GLOBALS['inflight_replay'] = DIN_Packages_Requests::create( 1, 100, 9, $change ); }
	if ( 'items' === $stage ) { check( DIN_Packages::get( 1 )['payment_request']['order_id'] > 0, 'Order ID must be durably attached before item side effects.' ); }
};
$first = DIN_Packages_Requests::create( 1, 100, 9, $change );
check( $first instanceof WC_Order && is_wp_error( $inflight_replay ) && 2 === count( $orders ), 'An unresolved creating reservation must never be stolen or duplicated.' );

foreach ( array( 'create', 'items', 'totals' ) as $stage ) {
	list( $base, $change ) = request_fixture(); $GLOBALS['request_failure'] = $stage;
	$failed = DIN_Packages_Requests::create( 1, 100, 9, $change );
	check( is_wp_error( $failed ) && ! $invoice_ids, 'Creation failure must stay controlled and must not send an invoice.' );
	$count = count( $orders ); unset( $GLOBALS['request_failure'] );
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && count( $orders ) === $count, 'Retrying an old form after failure cannot duplicate its order.' );
	if ( 'create' !== $stage ) {
		$failed_id = DIN_Packages::get( 1 )['payment_request']['order_id'];
		check( $failed_id > 0 && 'failed' === $orders[$failed_id]->status, 'A partially created order is retained as Failed, not deleted.' );
		check( ! DIN_Packages::order_needs_payment( true, $orders[$failed_id] ), 'A retained failed request must not be payable through native WooCommerce checkout.' );
		$change['revision'] = DIN_Packages::get( 1 )['revision'];
		check( DIN_Packages_Requests::create( 1, 100, 9, $change ) instanceof WC_Order && count( $orders ) === $count + 1, 'Fresh form may safely replace a known Failed order.' );
	}
	unchanged_entitlement( $base );
}
list( $base, $change ) = request_fixture();
$GLOBALS['request_hook'] = static function ( $stage ) { if ( 'saved' === $stage ) { $GLOBALS['wpdb']->fail_writes = true; } };
$failed_attach = DIN_Packages_Requests::create( 1, 100, 9, $change );
check( is_wp_error( $failed_attach ) && 2 === count( $orders ) && ! $invoice_ids, 'Lost request-ID persistence must fail closed with the created order retained.' );
$wpdb->fail_writes = false; unset( $GLOBALS['request_hook'] );
$change['revision'] = DIN_Packages::get( 1 )['revision'];
check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && 2 === count( $orders ), 'Unresolved reservation cannot be replaced even by a fresh form.' );
unchanged_entitlement( $base );
list( $base, $change ) = request_fixture(); $GLOBALS['request_failure'] = 'mail';
$mail_failed = DIN_Packages_Requests::create( 1, 100, 9, $change );
check( $mail_failed instanceof WC_Order && 'ready' === DIN_Packages::get( 1 )['payment_request']['state'], 'Email delivery failure must leave the invoice ready for manual resend.' );
unset( $GLOBALS['request_failure'] );
check( DIN_Packages_Requests::create( 1, 100, 9, $change )->get_id() === $mail_failed->get_id() && 2 === count( $orders ) && ! $invoice_ids, 'Replay after email failure must not duplicate or automatically resend the invoice.' );
unchanged_entitlement( $base );
list( $base, $change ) = request_fixture(); $GLOBALS['mail_rejected'] = true;
$mail_rejected = DIN_Packages_Requests::create( 1, 100, 9, $change );
check( $mail_rejected instanceof WC_Order && $mail_rejected->notes && 'ready' === DIN_Packages::get( 1 )['payment_request']['state'], 'Native email send=false must record a manual-resend warning without rolling back the payment request.' );
check( empty( $GLOBALS['request_actions']['woocommerce_email_sent'] ), 'The temporary invoice-result listener must not leak to unrelated emails.' );
list( $base, $change ) = request_fixture();
$orders[100]->update_meta_data( 'is_vat_exempt', 'yes' );
$exempt = DIN_Packages_Requests::create( 1, 100, 9, array_merge( $change, array( 'extension' => '1' ) ) );
check( $exempt instanceof WC_Order && 'yes' === $exempt->get_meta( 'is_vat_exempt' ) && 125.0 === (float) $exempt->get_total(), 'A tax-exempt source buyer must not be charged tax on the one-year request.' );
foreach ( array( 'customer', 'currency', 'mirror', 'key', 'quantity', 'product', 'total', 'refund' ) as $tamper ) {
	list( $base, $change ) = request_fixture();
	$prior = DIN_Packages_Requests::create( 1, 100, 9, $change );
	switch ( $tamper ) {
		case 'customer': $prior->customer = 8; break;
		case 'currency': $prior->currency = 'EUR'; break;
		case 'mirror': $prior->meta['_din_package_admin_request'] = array(); break;
		case 'key': $prior->meta['_din_package_request_key'] = ''; break;
		case 'quantity': $prior->items[0]->quantity = 2; break;
		case 'product': $prior->items[0]->product = 2; break;
		case 'total': $prior->total = 0; break;
		case 'refund': $prior->refunded = 1; break;
	}
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $change ) ) && 2 === count( $orders ) && 1 === count( $invoice_ids ), 'A ready order with changed ' . $tamper . ' must not be reused or replaced automatically.' );
}
list( $base, $change ) = request_fixture();
$paid = DIN_Packages_Requests::create( 1, 100, 9, $change );
$paid->status = 'completed'; $paid->date_paid = time();
DIN_Packages::approve_order( $paid, 9 );
unchanged_entitlement( $base );
DIN_Packages::approve_order( $paid, 9, true );
$approved = DIN_Packages::get( 1 );
check( ( new DateTimeImmutable( '2030-02-28 14:30:17', wp_timezone() ) )->getTimestamp() === $approved['expires_at'] && $approved['generation'] === $base['generation'] + 1, 'The actual created invoice must apply the requested date only after paid Completed plus explicit admin confirmation.' );
DIN_Packages::approve_order( $paid, 9, true );
check( DIN_Packages::get( 1 ) === $approved, 'Repeated approval of the generated invoice must not grant service twice.' );

// Custom years affect the catalog-derived price and immutable quote, never free service or the posted price.
list( $base, $change ) = request_fixture();
$custom_change = array_merge( $change, array( 'extension' => 'custom', 'years' => '3', 'date' => '2031-04-15', 'price' => '0.01' ) );
$custom = DIN_Packages_Requests::create( 1, 100, 9, $custom_change );
check( $custom instanceof WC_Order, 'A valid custom whole-year duration must create a payable request.' );
$custom_item = $custom->get_items()[0];
$custom_purchase = $custom_item->get_meta( '_din_package_purchase' );
check( 1 === $custom_item->get_quantity() && 375.0 === (float) $custom_item->get_total() && abs( 412.5 - $custom->get_total() ) < 0.00001, 'Three custom years must cost the annual catalog price 125 times 3, quantity one, plus tax; ignore posted price.' );
check( 'renew_custom' === $custom_purchase['action'] && 3 === $custom_purchase['admin_request']['years'] && 3 === DIN_Packages::get( 1 )['payment_request']['years'], 'Custom years must be an integer in both immutable request snapshots.' );
check( '3 Years' === $custom_item->get_meta( 'Duration' ) && '2031-04-15 14:30:17' === $custom_item->get_meta( 'New expiry' ), 'Custom duration must be visible on the invoice and its expiry date must remain editable with the old local clock.' );
check( $custom->get_meta( '_din_package_admin_request' ) === $custom_purchase && DIN_Packages::order_needs_payment( true, $custom ), 'Custom quote must retain a matching protected order mirror and be payable through the native guard.' );
unchanged_entitlement( $base );
$custom_replay = DIN_Packages_Requests::create( 1, 100, 9, $custom_change );
check( $custom_replay instanceof WC_Order && $custom_replay->get_id() === $custom->get_id() && 2 === count( $orders ) && 1 === count( $invoice_ids ), 'Replaying a custom duration must not create or email twice.' );
$other_years = array_merge( $custom_change, array( 'revision' => DIN_Packages::get( 1 )['revision'], 'years' => 4 ) );
check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $other_years ) ) && 2 === count( $orders ), 'A changed custom year count must not reuse a pending quote for another duration.' );
$custom_item->meta['_din_package_purchase']['admin_request']['years'] = 4;
$custom->meta['_din_package_admin_request'] = $custom_item->meta['_din_package_purchase'];
check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $custom_change ) ) && ! DIN_Packages::order_needs_payment( true, $custom ), 'Matching order/item tampering still must not override the stored custom year snapshot.' );
$custom_item->meta['_din_package_purchase'] = $custom_purchase;
$custom->meta['_din_package_admin_request'] = $custom_purchase;
$custom->status = 'completed'; $custom->date_paid = time();
DIN_Packages::approve_order( $custom, 9 );
unchanged_entitlement( $base );
DIN_Packages::approve_order( $custom, 9, true );
$custom_approved = DIN_Packages::get( 1 );
check( ( new DateTimeImmutable( '2031-04-15 14:30:17', wp_timezone() ) )->getTimestamp() === $custom_approved['expires_at'] && 3 === end( $custom_approved['history'] )['years'], 'Paid custom approval must use the exact editable expiry and retain purchased years in history.' );
DIN_Packages::approve_order( $custom, 9, true );
check( DIN_Packages::get( 1 ) === $custom_approved, 'Repeated custom approval must not extend service twice.' );
foreach ( array( null, '', 0, '0', -1, '-1', 1.5, '1.5', 1.0, '1.0', '1e2', ' 3 ', '+3', '3abc', array( 3 ), true, '999999999999999999', '10000', 7972 ) as $invalid_years ) {
	list( $base, $change ) = request_fixture();
	$invalid_custom = array_merge( $change, array( 'extension' => 'custom', 'years' => $invalid_years ) );
	check( is_wp_error( DIN_Packages_Requests::create( 1, 100, 9, $invalid_custom ) ) && 1 === count( $orders ) && DIN_Packages::get( 1 ) === $base, 'Invalid or out-of-calendar-range custom years must not reserve or create an order: ' . json_encode( $invalid_years ) );
}
foreach ( array( '1', '2', 'lifetime' ) as $legacy_extension ) {
	list( $base, $change ) = request_fixture();
	$legacy_change = array_merge( $change, array( 'extension' => $legacy_extension, 'years' => '3' ) );
	if ( 'lifetime' === $legacy_extension ) { $legacy_change['date'] = ''; }
	$legacy = DIN_Packages_Requests::create( 1, 100, 9, $legacy_change );
	check( $legacy instanceof WC_Order && ! isset( $legacy->get_items()[0]->get_meta( '_din_package_purchase' )['admin_request']['years'] ) && ! isset( DIN_Packages::get( 1 )['payment_request']['years'] ), 'Unused custom input must never change predefined duration quotes.' );
}
echo "DIN Package Lifecycle paid request smoke: OK (in-memory only, no live orders or email)\n";
