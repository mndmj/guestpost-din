<?php
/** Run with PHP CLI; renders only, without WordPress boot, database writes or checkout. */
if ( 'cli' !== PHP_SAPI ) {
	exit;
}
define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
require ABSPATH . 'wp-includes/plugin.php';

// Isolate translation and the cart URL from WordPress options/database.
function esc_html_e( $text, $domain = '' ) {
	echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}
function wc_get_cart_url() {
	return 'https://checkout.test/cart/?source=checkout&review=1';
}

if ( ! in_array( '--without-details', $argv, true ) ) {
	add_action( 'woocommerce_cart_has_errors', static function () {
		echo '<p class="test-cart-detail">Please review the selected package.</p>';
	} );
}

$template = dirname( __DIR__ ) . '/woocommerce/checkout/cart-errors.php';
ob_start();
include file_exists( $template ) ? $template : ABSPATH . 'wp-content/plugins/woocommerce/templates/checkout/cart-errors.php';
$html = ob_get_clean();

if ( in_array( '--render', $argv, true ) ) {
	echo $html;
	exit;
}

if ( ! preg_match( '/<section\b[^>]*aria-labelledby="([^"]+)"[^>]*>/', $html, $section )
	|| ! preg_match( '/<h[12]\b[^>]*id="' . preg_quote( $section[1], '/' ) . '"/', $html ) ) {
	throw new RuntimeException( 'The checkout error must render as a named card with a visible heading.' );
}
if ( 1 !== substr_count( $html, 'class="test-cart-detail"' ) ) {
	throw new RuntimeException( 'Native cart error details must remain visible exactly once.' );
}
if ( ! str_contains( $html, 'href="https://checkout.test/cart/?source=checkout&amp;review=1"' )
	|| ! str_contains( $html, 'wc-backward' )
	|| ! str_contains( $html, 'Return to cart' ) ) {
	throw new RuntimeException( 'The native return-to-cart link and escaped URL must remain intact.' );
}
echo "PASS: named checkout error card, native error details and return-to-cart link.\n";
