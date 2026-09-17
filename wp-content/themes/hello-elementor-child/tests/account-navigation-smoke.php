<?php
// Run: studio wp eval-file wp-content/themes/hello-elementor-child/tests/account-navigation-smoke.php
// Render only; no account, order, login or option writes.
if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this check through studio wp eval-file.\n" );
}

$original_query_vars = $GLOBALS['wp']->query_vars;
$home_filter = static function () { return 'https://example.test/store'; };
add_filter( 'pre_option_home', $home_filter );

try {
	foreach ( array( 'dashboard', 'orders' ) as $active ) {
		$GLOBALS['wp']->query_vars = 'dashboard' === $active ? array() : array( 'orders' => '' );
		$existing_items = wc_get_account_menu_items();
		ob_start();
		include dirname( __DIR__ ) . '/woocommerce/myaccount/navigation.php';
		$html = ob_get_clean();
		preg_match( '/<nav\b[^>]*class="woocommerce-MyAccount-navigation"[^>]*>(.*?)<\/nav>/s', $html, $nav );
		preg_match_all( '/<a\b[^>]*>.*?<\/a>/s', $nav[1] ?? '', $links );
		$home = $links[0][0] ?? '';
		if ( trim( wp_strip_all_tags( $home ) ) !== 'Home' || ! str_contains( $home, 'href="https://example.test/store/"' ) ) {
			throw new RuntimeException( 'Home must be the first sidebar link and use the configured site homepage, including its subdirectory.' );
		}
		if ( ! str_contains( $home, 'woocommerce-MyAccount-navigation-link woocommerce-MyAccount-navigation-link--home' ) || str_contains( $home, 'is-active' ) || str_contains( $home, 'aria-current' ) ) {
			throw new RuntimeException( 'Home must reuse menu styling without replacing the current account-page state.' );
		}
		if ( count( $links[0] ) !== count( $existing_items ) + 1 ) {
			throw new RuntimeException( 'Adding Home must retain every existing menu item.' );
		}
		foreach ( array_keys( $existing_items ) as $index => $endpoint ) {
			$link = $links[0][ $index + 1 ];
			if ( ! str_contains( $link, 'woocommerce-MyAccount-navigation-link--' . $endpoint ) || ( str_contains( $link, 'aria-current="page"' ) !== ( $endpoint === $active ) ) ) {
				throw new RuntimeException( 'Existing menu order or active state changed: ' . $endpoint );
			}
		}
	}
	echo "PASS: Home is first, homepage URL is portable, existing menus and active states are preserved.\n";
} finally {
	$GLOBALS['wp']->query_vars = $original_query_vars;
	remove_filter( 'pre_option_home', $home_filter );
}
