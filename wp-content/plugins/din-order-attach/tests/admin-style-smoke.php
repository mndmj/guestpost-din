<?php
/**
 * Standalone asset regression check: php tests/admin-style-smoke.php
 * No WordPress bootstrap, database, session, network, or filesystem writes.
 */

namespace Automattic\WooCommerce\Utilities {
	final class OrderUtil {
		public static bool $is_order_edit = false;

		public static function is_order_edit_screen( $order_type = 'shop_order' ): bool {
			return self::$is_order_edit;
		}
	}
}

namespace {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	define( 'DIN_ORDER_ATTACH_FILE', dirname( __DIR__ ) . '/din-order-attach.php' );

	function get_current_screen() {
		return $GLOBALS['din_test_screen'];
	}

	function wc_get_page_screen_id( $screen ) {
		return 'woocommerce_page_wc-orders';
	}

	function plugins_url( $path, $plugin ) {
		return 'https://example.test/wp-content/plugins/din-order-attach/' . $path;
	}

	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		$GLOBALS['din_test_styles'][ $handle ] = compact( 'src', 'deps', 'ver', 'media' );
	}

	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		$GLOBALS['din_test_scripts'][ $handle ] = compact( 'src', 'deps', 'ver', 'in_footer' );
	}

	function din_admin_style_expect( $condition, $message ) {
		if ( ! $condition ) {
			throw new \RuntimeException( $message );
		}
	}

	require dirname( __DIR__ ) . '/includes/class-din-order-attach.php';

	// Missing enqueue must fail editors; missing OrderUtil guard must fail list/new.
	$cases = array(
		// label, screen ID, native edit result, stylesheet expected, preview expected.
		array( 'legacy editor', 'shop_order', true, true, true ),
		array( 'HPOS editor', 'woocommerce_page_wc-orders', true, true, true ),
		array( 'HPOS list', 'woocommerce_page_wc-orders', false, false, true ),
		array( 'HPOS new order', 'woocommerce_page_wc-orders', false, false, true ),
		array( 'legacy new order', 'shop_order', false, false, true ),
		array( 'dashboard', 'dashboard', false, false, false ),
		array( 'no screen', null, false, false, false ),
	);

	foreach ( $cases as [ $label, $screen_id, $is_edit, $want_style, $want_preview ] ) {
		$GLOBALS['din_test_screen']  = null === $screen_id ? null : (object) array( 'id' => $screen_id );
		$GLOBALS['din_test_styles']  = array();
		$GLOBALS['din_test_scripts'] = array();
		\Automattic\WooCommerce\Utilities\OrderUtil::$is_order_edit = $is_edit;
		DIN_Order_Attach::instance()->enqueue_admin_assets();

		din_admin_style_expect(
			( $want_style ? array( 'din-order-admin' ) : array() ) === array_keys( $GLOBALS['din_test_styles'] ),
			"{$label}: din-order-admin must load only on existing order editors."
		);
		if ( $want_style ) {
			$style = $GLOBALS['din_test_styles']['din-order-admin'];
			$file  = dirname( __DIR__ ) . '/assets/admin-order.css';
			din_admin_style_expect( is_file( $file ), "{$label}: admin-order.css is missing." );
			din_admin_style_expect(
				'https://example.test/wp-content/plugins/din-order-attach/assets/admin-order.css' === $style['src']
				&& array() === $style['deps'] && 'all' === $style['media']
				&& (string) filemtime( $file ) === (string) $style['ver'],
				"{$label}: stylesheet URL, dependencies, or filemtime cache version is incorrect."
			);
		}

		$preview = array(
			'din-order-attach-preview' => array(
				'src'       => 'https://example.test/wp-content/plugins/din-order-attach/assets/admin-preview.js',
				'deps'      => array(),
				'ver'       => '1.6.0',
				'in_footer' => true,
			),
		);
		din_admin_style_expect(
			( $want_preview ? $preview : array() ) === $GLOBALS['din_test_scripts'],
			"{$label}: existing preview-script behavior changed."
		);
	}

	echo "DIN Order Attach admin stylesheet: OK (7 screen cases).\n";
}
