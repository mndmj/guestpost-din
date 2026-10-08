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
function wp_kses_post( $value ) { return $value; }
function wc_get_account_endpoint_url( $endpoint ) { return '/my-account/' . $endpoint . '/'; }
function is_wc_endpoint_url( $endpoint ) { return ( 'din-packages' === $endpoint && ( $GLOBALS['package_endpoint'] ?? false ) ) || ( 'view-order' === $endpoint && ( $GLOBALS['view_order_endpoint'] ?? false ) ); }
function add_query_arg( $key, $value, $url ) { return $url . '?' . rawurlencode( $key ) . '=' . $value; }
function paginate_links( $args ) {
	$GLOBALS['pagination_args'][] = $args;
	$links = array();
	for ( $page = 1; $page <= $args['total']; ++$page ) {
		$href = str_replace( array( '%_%', '%#%' ), array( $args['format'] ?? '', $page ), $args['base'] );
		$links[] = $page === $args['current'] ? '<span class="page-numbers current" aria-current="page">' . $page . '</span>' : '<a class="page-numbers" href="' . esc_url( $href ) . '">' . $page . '</a>';
	}
	return 'list' === ( $args['type'] ?? '' ) ? '<ul class="page-numbers"><li>' . implode( '</li><li>', $links ) . '</li></ul>' : implode( ' ', $links );
}
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? false; }
function get_option( $key ) { return array( 'date_format' => $GLOBALS['date_format'] ?? 'Y-m-d', 'time_format' => 'H:i' )[ $key ] ?? false; }
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
	public static function count_for_customer( $customer ) { return $customer ? count( array_filter( $GLOBALS['packages'] ?? array(), static fn( $package ) => (int) $package['customer_id'] === (int) $customer ) ) : 0; }
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
function customer_fixture( $count ) {
	$packages = array();
	for ( $id = $count; $id >= 1; --$id ) {
		$status = 10 === $id ? 'active' : array( 'pending', 'active', 'expiring', 'expired', 'lifetime', 'review', 'stopped' )[ $id % 7 ];
		$packages[] = array( 'id' => $id, 'customer_id' => 7, 'order_id' => 55, 'product_name' => 'Product <' . $id . '>', 'heading' => 'Heading ' . $id . ' <unsafe> & #27 ' . str_repeat( 'Long title ', 8 ), 'period' => 'lifetime' === $status ? 'lifetime' : 'annual', 'started_at' => 'pending' === $status ? 0 : 1704133800, 'expires_at' => 1735689600, 'review' => '', 'status' => $status, 'stopped_at' => 'stopped' === $status ? 1735689600 : 0, 'stop_reason' => 'Buyer request' );
	}
	$GLOBALS['packages'] = $packages;
	$GLOBALS['orders'][55] = new class {
		public function get_customer_id() { return 7; }
		public function has_status( $status ) { return 'completed' === $status; }
		public function get_order_number() { return '55'; }
		public function get_view_order_url() { return '/my-account/view-order/55/'; }
	};
}
if ( '--render-packages' === ( $argv[1] ?? '' ) ) {
	$GLOBALS['buyer'] = 7;
	$GLOBALS['package_endpoint'] = true;
	customer_fixture( 21 );
	$_GET['package-page'] = $argv[2] ?? '1';
	DIN_Packages_Customer::account();
	exit;
}
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
customer_expect( 10 === $item->meta['_din_package_purchase']['package_id'] && '#10 — Renewal' === $item->meta['Destination package'], 'Hiding buyer labels must not rewrite stored destination metadata or package references.' );
$item_data = DIN_Packages_Customer::item_data( array( array( 'key' => 'Unrelated', 'value' => 'Keep me' ) ), $renewal );
customer_expect( 'Renewal' === array_column( $item_data, 'value', 'key' )['Destination package'], 'Cart destination label omits its generated package ID.' );
customer_expect( 'Original heading' === array_column( $item_data, 'value', 'key' )['Original Post Heading'] && 'Keep me' === $item_data[0]['value'], 'Cart still includes its source heading and unrelated item data.' );
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
$GLOBALS['packages'][0]['heading'] = '<img src=x onerror=alert(1)> & "origin" #27';
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
customer_expect( false !== strpos( $html, 'href="https://example.test/my-account/view-order/55/?ref=source&amp;proof=1"' ) && false !== strpos( $html, 'View Order #INV&lt;&amp;55 and evidence' ), 'The source order and proof link preserves escaped URL and order number.' );
customer_expect( 3 === substr_count( $html, '<form method="post" action="/my-account/din-packages/">' ), 'Each eligible action has its own POST form.' );
customer_expect( 3 === substr_count( $html, 'name="din_package_id" value="10"' ) && 3 === substr_count( $html, 'name="_din_package_nonce"' ), 'All action forms bind the target package and include a nonce.' );
customer_expect( array( array( 'din_package_purchase_10', '_din_package_nonce' ), array( 'din_package_purchase_10', '_din_package_nonce' ), array( 'din_package_purchase_10', '_din_package_nonce' ) ) === $GLOBALS['nonce_calls'], 'Nonce action is scoped to the rendered package, not a global purchase nonce.' );
foreach ( array( 'renew_1', 'renew_2', 'lifetime' ) as $action ) {
	customer_expect( false !== strpos( $html, 'name="din_package_action" value="' . $action . '"' ), 'Eligible action button is submitted explicitly: ' . $action );
}
preg_match_all( '/aria-label="([^"]+)"/', $html, $action_labels );
customer_expect( false !== strpos( $html, 'aria-labelledby="din-package-10"' ) && 3 === count( $action_labels[1] ), 'Card linkage and three action accessible names remain available.' );
foreach ( $action_labels[1] as $action_label ) {
	customer_expect( str_contains( $action_label, 'Renewal' ) && str_contains( $action_label, '&lt;script&gt;' ) && str_contains( $action_label, '#27' ) && ! str_contains( $action_label, '#10' ), 'Action accessible names identify the product and buyer heading without a generated ID.' );
}
customer_expect( preg_match( '/<dt>Period<\/dt>\s*<dd>\s*Annual\s*<\/dd>/', $html ) && ! str_contains( strip_tags( $html ), '#10' ) && str_contains( strip_tags( $html ), '#27' ), 'Expanded shared cards show only Period and preserve a literal hash number in the buyer heading.' );
customer_expect( false !== strpos( $html, '>Active</span>' ) && preg_match( '/<dd>\s*0 day\s*<\/dd>/', $html ), 'Status has readable text and past expiry never shows negative remaining days.' );

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

// Account pagination is based on the owner's total, not whether a fetched page happens to be full.
$GLOBALS['package_endpoint'] = true;
foreach ( array( 0, 1, 10, 11, 20, 21 ) as $count ) {
	customer_fixture( $count );
	$_GET['package-page'] = '1';
	$GLOBALS['package_pages'] = array();
	ob_start(); DIN_Packages_Customer::account(); $page_html = ob_get_clean();
	preg_match_all( '/<article class="din-packages__card" aria-labelledby="din-package-(\d+)"/', $page_html, $card_matches );
	$want = $count ? range( $count, max( 1, $count - 9 ) ) : array();
	customer_expect( array_map( 'intval', $card_matches[1] ) === $want, "Account page one renders at most ten newest packages for count $count." );
	customer_expect( $GLOBALS['package_pages'] === array( 1 ), "Account fetches only page one for count $count." );
	customer_expect( ( $count > 10 ) === ( false !== strpos( $page_html, 'class="din-packages__pagination ' ) ), "Only multiple pages show navigation for count $count." );
	customer_expect( ( $count > 10 ) === ( false !== strpos( $page_html, 'Next' ) ), "Next appears only when another page exists for count $count." );
}
customer_fixture( 21 );
$_GET['package-page'] = '2';
$GLOBALS['package_pages'] = array();
$GLOBALS['nonce_calls'] = array();
ob_start(); DIN_Packages_Customer::account(); $page_two = ob_get_clean();
preg_match_all( '/<article class="din-packages__card" aria-labelledby="din-package-(\d+)"/', $page_two, $card_matches );
customer_expect( array_map( 'intval', $card_matches[1] ) === range( 11, 2 ) && $GLOBALS['package_pages'] === array( 2 ), 'Page two has the next ten IDs in descending order, disjoint from page one.' );
customer_expect( false !== strpos( $page_two, 'Previous' ) && false !== strpos( $page_two, 'Next' ) && false !== strpos( $page_two, 'aria-current="page">2' ), 'Middle page exposes previous, current and next navigation.' );
customer_expect( false !== strpos( $page_two, 'href="/my-account/din-packages/?package-page=1"' ) && false !== strpos( $page_two, 'href="/my-account/din-packages/?package-page=3"' ), 'Middle page navigation targets adjacent pages.' );
customer_expect( 10 === substr_count( $page_two, '<details class="din-packages__details"' ) && 10 === substr_count( $page_two, '<summary class="din-packages__summary"' ), 'Every endpoint card contains a native details/summary toggle.' );
customer_expect( ! preg_match( '/<details class="din-packages__details"[^>]*(?:\bopen\b|\bname=)/', $page_two ), 'Endpoint details start closed and remain independent.' );
customer_expect( false !== strpos( $page_two, 'Product &lt;10&gt;' ) && false === strpos( $page_two, 'Product <10>' ) && false !== strpos( $page_two, 'Heading 10 &lt;unsafe&gt; &amp;' ), 'Summary text escapes product and Heading Post.' );
customer_expect( preg_match( '/<summary class="din-packages__summary"[^>]*>.*?Product &lt;10&gt;.*?Start:.*?2024-01-02.*?Heading 10 &lt;unsafe&gt; &amp; #27.*?Active.*?<\/summary>/s', $page_two ), 'Summary exposes the local start date, heading preview and status without opening details.' );
preg_match_all( '/<span class="din-packages__summary-meta">(.*?)<\/span>/s', $page_two, $start_meta );
customer_expect( count( $start_meta[1] ) === 10 && in_array( 'Start: 2024-01-02', array_map( static fn( $value ) => trim( strip_tags( $value ) ), $start_meta[1] ), true ), 'Summary uses the site-local calendar day at a UTC date boundary, without a time.' );
customer_expect( ! preg_match( '/Package\s*#\d+/', $page_two ) && ! preg_match( '/<dd>\s*#\d+/', $page_two ), 'Collapsed cards and their bodies do not expose generated package numbers.' );
customer_expect( false !== strpos( $page_two, '<time datetime="2024-01-01T18:30:00+00:00">2024-01-02 01:30</time>' ) && false !== strpos( $page_two, 'View Order' ), 'Collapsed cards retain full start timestamps and source-order evidence inside the body.' );
customer_expect( 3 === substr_count( $page_two, 'name="din_package_id" value="10"' ) && 3 === substr_count( $page_two, 'name="_din_package_nonce"' ) && 3 === count( $GLOBALS['nonce_calls'] ), 'Eligible package retains three server-scoped nonce action forms inside details.' );
foreach ( array( 'renew_1', 'renew_2', 'lifetime' ) as $action ) {
	customer_expect( false !== strpos( $page_two, 'name="din_package_action"' ) && false !== strpos( $page_two, 'value="' . $action . '"' ), 'Native action remains available: ' . $action );
}
$_GET['package-page'] = '3';
$GLOBALS['package_pages'] = array();
ob_start(); DIN_Packages_Customer::account(); $last_page = ob_get_clean();
customer_expect( $GLOBALS['package_pages'] === array( 3 ) && 1 === substr_count( $last_page, '<article class="din-packages__card"' ) && false !== strpos( $last_page, 'din-package-1' ), 'Twenty-first package remains reachable on the last page.' );
customer_expect( false !== strpos( $last_page, 'Previous' ) && false === strpos( $last_page, 'Next' ) && false !== strpos( $last_page, 'aria-current="page">3' ), 'Last page never advertises an empty next page.' );
customer_fixture( 20 );
$_GET['package-page'] = '2';
ob_start(); DIN_Packages_Customer::account(); $full_last_page = ob_get_clean();
customer_expect( 10 === substr_count( $full_last_page, '<article class="din-packages__card"' ) && false !== strpos( $full_last_page, 'Previous' ) && false === strpos( $full_last_page, 'Next' ), 'A full last page of ten never offers a phantom page three.' );
customer_fixture( 21 );
foreach ( array( '', '0', '-2', '+2', '1.5', 'foo', array( '2' ) ) as $invalid_page ) {
	$_GET['package-page'] = $invalid_page;
	$GLOBALS['package_pages'] = array();
	ob_start(); DIN_Packages_Customer::account(); $invalid_html = ob_get_clean();
	customer_expect( $GLOBALS['package_pages'] === array( 1 ) && false !== strpos( $invalid_html, 'din-package-21' ), 'Malformed page input must normalize to page one.' );
}
unset( $_GET['package-page'] );
$GLOBALS['package_pages'] = array();
ob_start(); DIN_Packages_Customer::account(); $default_html = ob_get_clean();
customer_expect( $GLOBALS['package_pages'] === array( 1 ) && false !== strpos( $default_html, 'din-package-21' ), 'Missing page input defaults to page one.' );
$_GET['package-page'] = '999999999999999999999999';
$GLOBALS['package_pages'] = array();
ob_start(); DIN_Packages_Customer::account(); $huge_html = ob_get_clean();
customer_expect( $GLOBALS['package_pages'] === array( 3 ) && false !== strpos( $huge_html, 'din-package-1' ), 'Huge page requests clamp to the last real page.' );
$statuses = array( 'pending' => 'Awaiting Activation', 'active' => 'Active', 'expiring' => 'Expiring Soon', 'expired' => 'Expired', 'lifetime' => 'Lifetime', 'review' => 'Pending Review', 'stopped' => 'Stopped' );
customer_fixture( 7 );
foreach ( array_keys( $statuses ) as $index => $status ) {
	$GLOBALS['packages'][ $index ]['status'] = $status;
	$GLOBALS['packages'][ $index ]['started_at'] = 'pending' === $status ? 0 : 1704133800;
	if ( 'lifetime' === $status ) { $GLOBALS['packages'][ $index ]['period'] = 'lifetime'; }
	if ( 'stopped' === $status ) { $GLOBALS['packages'][ $index ]['stopped_at'] = 1735689600; $GLOBALS['packages'][ $index ]['stop_reason'] = 'Buyer request'; }
}
$_GET['package-page'] = '1';
ob_start(); DIN_Packages_Customer::account(); $states_html = ob_get_clean();
preg_match_all( '/<summary class="din-packages__summary"[^>]*>(.*?)<\/summary>/s', $states_html, $summaries );
foreach ( array_values( array_keys( $statuses ) ) as $index => $status ) {
	customer_expect( false !== strpos( $summaries[1][ $index ] ?? '', $statuses[ $status ] ), 'Summary renders the readable ' . $status . ' state.' );
	$start_text = trim( preg_replace( '/\s+/', ' ', strip_tags( $summaries[1][ $index ] ?? '' ) ) );
	customer_expect( str_contains( $start_text, 'Start: ' . ( 'pending' === $status ? 'Awaiting Activation' : '2024-01-02' ) ), 'Every status has a start date or explicit activation placeholder: ' . $status );
}
$GLOBALS['date_format'] = 'd/m/Y';
ob_start(); DIN_Packages_Customer::account(); $custom_date_html = ob_get_clean();
preg_match_all( '/<span class="din-packages__summary-meta">(.*?)<\/span>/s', $custom_date_html, $custom_start_meta );
customer_expect( str_contains( implode( ' ', $custom_start_meta[1] ), '02/01/2024' ) && ! str_contains( implode( ' ', $custom_start_meta[1] ), '01:30' ), 'Summary honors the WordPress date_format option and never appends time_format.' );
unset( $GLOBALS['date_format'] );
$valid_card = $GLOBALS['packages'][0];
$foreign_customer_card = array_merge( $valid_card, array( 'id' => 80, 'customer_id' => 8 ) );
$foreign_order_card = array_merge( $valid_card, array( 'id' => 81, 'order_id' => 56 ) );
$GLOBALS['orders'][56] = new class { public function get_customer_id() { return 8; } };
ob_start(); DIN_Packages_Customer::render_packages( 1, 3, false, array( $foreign_customer_card, $foreign_order_card, $valid_card ) ); $guarded_html = ob_get_clean();
customer_expect( 1 === substr_count( $guarded_html, '<article class="din-packages__card"' ) && false !== strpos( $guarded_html, 'din-package-7' ) && false === strpos( $guarded_html, 'din-package-80' ) && false === strpos( $guarded_html, 'din-package-81' ), 'Rendered cards must pass package and original-order ownership guards.' );
$GLOBALS['packages'] = array( $valid_card, $foreign_customer_card, $foreign_order_card, array_merge( $valid_card, array( 'id' => 82, 'order_id' => 999 ) ) );
$_GET['package-page'] = '1';
ob_start(); DIN_Packages_Customer::account(); $guarded_account = ob_get_clean();
customer_expect( 1 === substr_count( $guarded_account, '<article class="din-packages__card"' ) && false !== strpos( $guarded_account, 'din-package-7' ) && false === strpos( $guarded_account, 'din-package-82' ), 'Account path hides foreign packages, transferred orders and missing source orders.' );
$GLOBALS['buyer'] = 8;
ob_start(); DIN_Packages_Customer::account(); $foreign_account = ob_get_clean();
customer_expect( 0 === substr_count( $foreign_account, '<article class="din-packages__card"' ), 'Another buyer cannot render the account fixture even if a query returns foreign rows.' );
$GLOBALS['buyer'] = 7;
$GLOBALS['package_endpoint'] = false;
ob_start(); DIN_Packages_Customer::dashboard( array( $GLOBALS['packages'][0] ) ); $dashboard_card = ob_get_clean();
customer_expect( false !== strpos( $dashboard_card, '<article class="din-packages__card"' ) && false === strpos( $dashboard_card, '<details class="din-packages__details"' ), 'Dashboard cards stay expanded and keep the existing three-card view.' );
customer_expect( ! preg_match( '/Package\s*#\d+|<dd>\s*#\d+/', $dashboard_card ), 'Expanded dashboard cards do not expose a package number.' );
customer_expect( str_contains( $dashboard_card, 'class="din-packages__order"' ), 'Dashboard package cards retain their order link.' );
$GLOBALS['package_endpoint'] = true;
ob_start(); DIN_Packages_Customer::render_packages( 1, 3, false, array( $GLOBALS['packages'][0] ) ); $preloaded_card = ob_get_clean();
customer_expect( false !== strpos( $preloaded_card, '<article class="din-packages__card"' ) && false === strpos( $preloaded_card, '<details class="din-packages__details"' ), 'Preloaded order cards stay expanded even on the endpoint URL.' );
customer_expect( ! preg_match( '/Package\s*#\d+|<dd>\s*#\d+/', $preloaded_card ), 'Preloaded order cards do not expose a package number.' );
customer_expect( str_contains( $states_html, 'class="din-packages__order"' ), 'The My Package endpoint retains its order links.' );
unset( $_GET['package-page'] );
$GLOBALS['package_endpoint'] = false;

customer_fixture( 10 );
$GLOBALS['view_order_endpoint'] = true;
ob_start(); DIN_Packages_Customer::render_packages( 1, 3, false, array( $GLOBALS['packages'][0] ) ); $detail_card = ob_get_clean();
customer_expect( ! str_contains( $detail_card, 'class="din-packages__order"' ), 'Order-detail package cards must omit the redundant order/evidence link.' );
customer_expect( str_contains( $detail_card, '>My Package</h2>' ) && str_contains( $detail_card, '<strong>Heading Post:</strong>' ) && str_contains( $detail_card, 'class="din-packages__dates"' ) && 3 === substr_count( $detail_card, 'name="din_package_action"' ), 'Hiding the order link must retain package information and renewal/upgrade actions.' );
$GLOBALS['view_order_endpoint'] = false;

$fields = array( 'order' => array( 'order_comments' => array( 'required' => true, 'label' => 'Heading Post' ), 'other_order_field' => array( 'required' => true ) ), 'billing' => array( 'billing_email' => array( 'required' => true ) ) );
$posted = array( 'order_comments' => 'New publication heading', 'billing_email' => 'buyer@example.test' );
$cart->cart_contents = array( 'renewal' => $renewal );
$renewal_fields = DIN_Packages_Customer::checkout_fields( $fields );
customer_expect( ! isset( $renewal_fields['order']['order_comments'] ), 'Pure renewal does not ask for a new publication Heading Post.' );
customer_expect( $fields['billing'] === $renewal_fields['billing'] && $fields['order']['other_order_field'] === $renewal_fields['order']['other_order_field'], 'Pure renewal leaves unrelated checkout field requirements intact.' );
customer_expect( array( 'order_comments' => '#10: Original heading', 'billing_email' => 'buyer@example.test' ) === DIN_Packages_Customer::checkout_data( $posted ), 'Pure renewal replaces user-supplied publication text with the source heading and retains billing data.' );
$source_order = new class { public $note = ''; public function set_customer_note( $note ) { $this->note = $note; } };
DIN_Packages_Customer::source_heading( $source_order );
customer_expect( '#10: Original heading' === $source_order->note, 'Store API source heading storage retains its generated reference unchanged.' );
$cart->cart_contents = array( 'renewal' => $renewal, 'normal' => $normal );
customer_expect( $fields === DIN_Packages_Customer::checkout_fields( $fields ), 'Mixed cart retains the store-required Heading Post field.' );
customer_expect( $posted === DIN_Packages_Customer::checkout_data( $posted ), 'Mixed cart retains the buyer heading for the new publication.' );
$cart->cart_contents = array();
customer_expect( $fields === DIN_Packages_Customer::checkout_fields( $fields ) && $posted === DIN_Packages_Customer::checkout_data( $posted ), 'Empty cart does not override checkout requirements or data.' );
echo "PASS: Customer ownership, quantity, pricing, snapshots, complete escaped card rendering, and pure/mixed checkout headings.\n";
