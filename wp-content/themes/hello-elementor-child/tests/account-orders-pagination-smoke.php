<?php
// Run: studio wp eval-file wp-content/themes/hello-elementor-child/tests/account-orders-pagination-smoke.php
// Render/query contract only: no orders, users or options are written.
if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this check through studio wp eval-file.\n" );
}

$failures = array();
$expect = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};
$args = array( 'customer' => 987654321, 'page' => 3, 'paginate' => true, 'limit' => 10, 'exclude' => array( 17, 23 ) );
$filtered = apply_filters( 'woocommerce_my_account_my_orders_query', $args );
$expect( 20 === ( $filtered['limit'] ?? null ), 'Account order query must request 20 orders per page.' );
unset( $args['limit'], $filtered['limit'] );
$expect( $args === array_intersect_key( $filtered, $args ), 'Page size must preserve customer, page, pagination and excluded renewal orders: ' . var_export( $filtered, true ) );

$plain = false;
$permalink_filter = static function () use ( &$plain ) { return $plain ? '' : '/%postname%/'; };
$account_filter = static function () use ( &$plain ) { return $plain ? 'https://example.test/?page_id=123' : 'https://example.test/my-account/'; };
$original_query_vars = $GLOBALS['wp']->query_vars;
$original_trailing_slashes = $GLOBALS['wp_rewrite']->use_trailing_slashes;
add_filter( 'pre_option_permalink_structure', $permalink_filter );
add_filter( 'woocommerce_get_myaccount_page_permalink', $account_filter );
$GLOBALS['wp_rewrite']->use_trailing_slashes = true;

try {
	foreach ( array( false, true ) as $plain ) {
		foreach ( array( array( 0, 1 ), array( 20, 1 ), array( 21, 1 ), array( 21, 2 ), array( 41, 2 ), array( 401, 1 ), array( 401, 11 ), array( 401, 21 ) ) as list( $total, $current_page ) ) {
			$has_orders = $total > 0;
			$customer_orders = (object) array( 'orders' => array(), 'total' => $total, 'max_num_pages' => (int) ceil( $total / 20 ) );
			$wp_button_class = '';
			$GLOBALS['wp']->query_vars = array( 'orders' => $current_page );
			$before = did_action( 'woocommerce_before_account_orders' );
			$after = did_action( 'woocommerce_after_account_orders' );
			$before_pagination = did_action( 'woocommerce_before_account_orders_pagination' );
			ob_start();
			include dirname( __DIR__ ) . '/woocommerce/myaccount/orders.php';
			$html = ob_get_clean();
			$case = ( $plain ? 'plain' : 'pretty' ) . " total=$total page=$current_page";
			$expect( did_action( 'woocommerce_before_account_orders' ) === $before + 1 && did_action( 'woocommerce_after_account_orders' ) === $after + 1, "$case: before/after order hooks must remain." );
			$expect( did_action( 'woocommerce_before_account_orders_pagination' ) === $before_pagination + (int) $has_orders, "$case: pagination hook must remain." );
			preg_match( '/<nav\b[^>]*aria-label=[\'"]Order pages[\'"][^>]*>(.*?)<\/nav>/s', $html, $nav );
			if ( $total <= 20 ) {
				$expect( empty( $nav ) && ! str_contains( $html, 'woocommerce-button--next' ) && ! str_contains( $html, 'woocommerce-button--previous' ), "$case: zero/one page must not show pagination." );
				continue;
			}
			$expect( ! empty( $nav ), "$case: numbered pagination needs a named navigation landmark." );
			$pagination = $nav[1] ?? '';
			preg_match( '/<span\b[^>]*aria-current=[\'"]page[\'"][^>]*>(.*?)<\/span>/s', $pagination, $active );
			$expect( trim( wp_strip_all_tags( $active[1] ?? '' ) ) === (string) $current_page, "$case: current page must be non-clickable and announced." );
			preg_match_all( '/<a\b[^>]*href=[\'"]([^\'"]*)[\'"][^>]*>(.*?)<\/a>/s', $pagination, $links, PREG_SET_ORDER );
			$labels = array();
			foreach ( $links as $link ) {
				$label = trim( wp_strip_all_tags( $link[2] ) );
				$labels[] = $label;
				$page = ctype_digit( $label ) ? (int) $label : ( 'Previous' === $label ? $current_page - 1 : ( 'Next' === $label ? $current_page + 1 : 0 ) );
				$expect( $page >= 1 && $page <= $customer_orders->max_num_pages, "$case: links must stay within valid order pages ($label)." );
				$url = html_entity_decode( $link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$expected_url = $plain ? "https://example.test/?page_id=123&orders=$page" : "https://example.test/my-account/orders/$page/";
				$expect( $url === $expected_url, "$case: $label must link to the account orders endpoint; got $url." );
			}
			$expect( in_array( 'Previous', $labels, true ) === ( $current_page > 1 ), "$case: keep Previous only after page one." );
			$expect( in_array( 'Next', $labels, true ) === ( $current_page < $customer_orders->max_num_pages ), "$case: keep Next only before the last page." );
			foreach ( array( 1, $customer_orders->max_num_pages ) as $edge ) {
				$expect( $edge === $current_page || in_array( (string) $edge, $labels, true ), "$case: first and last page must remain reachable." );
			}
			if ( 401 === $total ) {
				$expect( count( $links ) < 15 && ( str_contains( $pagination, 'dots' ) || str_contains( html_entity_decode( $pagination, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), '…' ) ), "$case: large histories must use compact pagination with an ellipsis." );
			}
		}
	}
} finally {
	$GLOBALS['wp']->query_vars = $original_query_vars;
	$GLOBALS['wp_rewrite']->use_trailing_slashes = $original_trailing_slashes;
	remove_filter( 'pre_option_permalink_structure', $permalink_filter );
	remove_filter( 'woocommerce_get_myaccount_page_permalink', $account_filter );
}

if ( $failures ) {
	throw new RuntimeException( "FAIL: Account Orders pagination\n" . implode( "\n", $failures ) );
}
echo "PASS: Account Orders requests 20 orders, renders accessible numbered pagination and Previous/Next, handles empty/one/many pages and pretty/plain endpoint URLs without writes.\n";
