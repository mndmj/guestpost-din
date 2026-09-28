<?php
// Run: C:/Users/donis/.studio/php-bin/8.4.23-studio-1/php.exe wp-content/themes/hello-elementor-child/tests/email-order-layout-smoke.php
// Standalone render only: no WordPress bootstrap, database, emails, or network.
namespace Automattic\WooCommerce\Utilities {
	class FeaturesUtil {
		public static bool $improved = false;
		public static function feature_is_enabled( $feature ) {
			return 'email_improvements' === $feature && self::$improved;
		}
	}
}

namespace {
	use Automattic\WooCommerce\Utilities\FeaturesUtil;

	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
	require ABSPATH . 'wp-includes/plugin.php'; // Exercise actual WordPress hook ordering/removal.
	require ABSPATH . 'wp-includes/shortcodes.php';
	function __( $text, $domain = '' ) { return $text; }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
	function esc_attr( $value ) { return esc_html( $value ); }
	function esc_url( $value ) { return esc_html( $value ); }
	function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
	function esc_html_e( $text, $domain = '' ) { echo esc_html__( $text, $domain ); }
	function wp_kses( $value, $allowed ) { return strip_tags( (string) $value, implode( '', array_map( static fn( $tag ) => "<$tag>", array_keys( $allowed ) ) ) ); }
	function wp_kses_post( $value ) { return strip_tags( (string) $value, '<a><b><br><span><time>' ); }
	function wc_wptexturize_order_note( $value ) { return $value; }
	function wc_format_datetime( $date ) { return $date->format( 'Y-m-d' ); }
	function wc_strtoupper( $value ) { return strtoupper( $value ); }
	function wc_get_email_order_items( $order, $args ) { return $args['plain_text'] ? "Product Delta\n" : '<tr><td>Product Delta</td></tr>'; }

	class SmokeOrder {
		public function __construct( private string $note ) {}
		public function get_customer_note() { return $this->note; }
		public function get_order_number() { return '731'; }
		public function get_date_created() { return new \DateTimeImmutable( '2026-09-25T10:00:00+07:00' ); }
		public function get_edit_order_url() { return 'https://example.invalid/admin/order/731'; }
		public function get_order_item_totals() { return array( 'total' => array( 'type' => 'total', 'label' => 'Total:', 'value' => '$42' ) ); }
	}

	function render_order( $order, $sent_to_admin, $plain_text, $email ) {
		ob_start();
		include dirname( __DIR__ ) . '/woocommerce/emails/' . ( $plain_text ? 'plain/' : '' ) . 'email-order-details.php';
		return ob_get_clean();
	}

	class SmokeMailer {
		public function order_downloads( $order, $sent_to_admin, $plain_text, $email ) { echo 'DOWNLOADS-MARKER'; }
		public function order_details( $order, $sent_to_admin, $plain_text, $email ) { echo render_order( $order, $sent_to_admin, $plain_text, $email ); }
	}

	$failures = array();
	$check = static function ( $condition, $message ) use ( &$failures ) {
		if ( ! $condition ) { $failures[] = $message; }
	};
	$mailer = new SmokeMailer();
	$notes = array( 'empty' => '', 'basic' => 'Heading Alpha', 'unsafe multiline' => "Line 1\n<script>alert(1)</script><b>Line 2</b>" );
	foreach ( array( false, true ) as $improved ) {
		FeaturesUtil::$improved = $improved;
		foreach ( array( false, true ) as $plain ) {
			foreach ( array( false, true ) as $admin ) {
				foreach ( $notes as $note_case => $note ) {
					$case = ( $plain ? 'plain' : 'HTML' ) . ', improvements ' . (int) $improved . ', admin ' . (int) $admin . ', ' . $note_case;
					$out = render_order( new SmokeOrder( $note ), $admin, $plain, $mailer );
					$heading = strpos( $out, 'Heading Post' );
					$number = stripos( $out, 'Order #731' );
					$product = strpos( $out, 'Product Delta' );
					$total = strpos( $out, 'Total:' );
					$check( false !== $number && false !== $product && false !== $total && $number < $product && $product < $total, "$case: order number, product, and total must remain in order." );
					$check( substr_count( $out, 'Heading Post' ) === ( '' === $note ? 0 : 1 ), "$case: Heading Post must appear exactly once when a note exists, otherwise not at all." );
					if ( '' !== $note ) {
						$check( false !== $heading && $heading < $number && $heading < $product && $heading < $total, "$case: Heading Post must precede order summary, number, products, and totals." );
						$check( str_contains( $out, 'Heading Alpha' ) === ( 'basic' === $note_case ), "$case: note content must be preserved." );
					}
					if ( 'unsafe multiline' === $note_case ) {
						$check( str_contains( $out, 'Line 1' ) && str_contains( $out, 'Line 2' ) && ! str_contains( $out, '<script' ) && ! str_contains( $out, '<b>Line 2</b>' ), "$case: multiline note must retain text but strip unsafe markup." );
						if ( ! $plain ) { $check( (bool) preg_match( '/Line 1.*<br\s*\/?\s*>.*Line 2/s', $out ), "$case: HTML note must retain its line break." ); }
					}
					if ( $admin ) { $check( str_contains( $out, 'https://example.invalid/admin/order/731' ), "$case: admin order link must remain." ); }
				}
			}
		}
	}

	// Mirror WooCommerce's two native registrations, then initialize the real child hook.
	add_action( 'woocommerce_email_order_details', array( $mailer, 'order_downloads' ), 10, 4 );
	add_action( 'woocommerce_email_order_details', array( $mailer, 'order_details' ), 10, 4 );
	ob_start();
	include dirname( __DIR__ ) . '/functions.php';
	ob_end_clean();
	for ( $i = 0; $i < 2; ++$i ) { do_action( 'woocommerce_email', $mailer ); }
	FeaturesUtil::$improved = true;
	ob_start();
	do_action( 'woocommerce_email_order_details', new SmokeOrder( 'Heading Alpha' ), false, false, $mailer );
	$out = ob_get_clean();
	$check( 1 === substr_count( $out, 'DOWNLOADS-MARKER' ) && strpos( $out, 'DOWNLOADS-MARKER' ) > strpos( $out, 'Total:' ), 'Woo downloads must render once, after order details, even when mailer initializes twice.' );
	remove_action( 'woocommerce_email_order_details', array( $mailer, 'order_downloads' ), 20 );
	do_action( 'woocommerce_email', $mailer );
	ob_start();
	do_action( 'woocommerce_email_order_details', new SmokeOrder( '' ), false, false, $mailer );
	$out = ob_get_clean();
	$check( ! str_contains( $out, 'DOWNLOADS-MARKER' ), 'Initializer must not re-enable downloads when native callback is absent.' );

	if ( $failures ) { throw new \RuntimeException( "FAIL: Email order layout\n" . implode( "\n", $failures ) ); }
	echo "PASS: HTML/plain order layout, note safety, customer/admin, and Woo downloads hook ordering.\n";
}
