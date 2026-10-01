<?php
// Run: studio wp eval-file wp-content/themes/hello-elementor-child/tests/my-account-auth-smoke.php
// Render/validate only: no accounts, emails, option writes, or authentication attempts.
if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this check through studio wp eval-file.\n" );
}

$registration = 'yes';
$registration_filter = static function () use ( &$registration ) { return $registration; };
add_filter( 'pre_option_woocommerce_enable_myaccount_registration', $registration_filter );
$original_get = $_GET;
$original_post = $_POST;
$cases = array(
	array( array(), array(), 'yes', 'login' ),
	array( array( 'gpm_auth' => 'login' ), array(), 'yes', 'login' ),
	array( array( 'gpm_auth' => 'register' ), array(), 'yes', 'register' ),
	array( array( 'gpm_auth' => 'invalid' ), array(), 'yes', 'login' ),
	array( array( 'gpm_auth' => array( 'register' ) ), array(), 'yes', 'login' ),
	array( array( 'gpm_auth' => 'register' ), array( 'login' => 'Log in' ), 'yes', 'login' ),
	array( array( 'gpm_auth' => 'login' ), array( 'register' => 'Register', 'email' => 'bad-email' ), 'yes', 'register' ),
	array( array(), array(), 'no', 'login' ),
	array( array( 'gpm_auth' => 'register' ), array(), 'no', 'login' ),
	array( array( 'gpm_checkout' => '1' ), array(), 'yes', 'login', true ),
	array( array( 'gpm_auth' => 'register', 'gpm_checkout' => '1' ), array(), 'yes', 'register', true ),
	array( array( 'gpm_auth' => 'register', 'gpm_checkout' => '1' ), array( 'login' => 'Log in' ), 'yes', 'login', true ),
	array( array( 'gpm_auth' => 'login', 'gpm_checkout' => '1' ), array( 'register' => 'Register', 'email' => 'bad-email' ), 'yes', 'register', true ),
	array( array( 'gpm_auth' => 'register', 'gpm_checkout' => '1' ), array(), 'no', 'login', true ),
	array( array( 'gpm_checkout' => '1', 'redirect' => 'https://example.invalid/evil' ), array(), 'yes', 'login', true ),
	array( array( 'gpm_checkout' => array( '1' ) ), array(), 'yes', 'login' ),
	array( array( 'gpm_checkout' => 'invalid' ), array(), 'yes', 'login' ),
	array( array( 'redirect' => 'https://example.invalid/evil' ), array(), 'yes', 'login' ),
);

try {
	foreach ( $cases as $index => $case ) {
		list( $_GET, $_POST, $registration, $expected ) = $case;
		$checkout_expected = $case[4] ?? false;
		ob_start();
		include dirname( __DIR__ ) . '/woocommerce/myaccount/form-login.php';
		$html = ob_get_clean();
		$has_login = str_contains( $html, 'woocommerce-form-login login' );
		$has_register = str_contains( $html, 'woocommerce-form-register register' );
		if ( $has_login !== ( 'login' === $expected ) || $has_register !== ( 'register' === $expected ) ) {
			throw new RuntimeException( "Case $index: expected only $expected, got login=" . (int) $has_login . ', register=' . (int) $has_register );
		}
		$links = array();
		if ( 'register' === $expected ) {
			$links = array( 'Have an Account ?' => 'login' );
		} elseif ( 'yes' === $registration ) {
			$links = array( 'Do not have an Account ?' => 'register' );
		}
		foreach ( $links as $label => $destination ) {
			if ( ! preg_match( '/<a\b[^>]*href="([^"]*gpm_auth=' . $destination . '[^"]*)"[^>]*>\s*' . preg_quote( $label, '/' ) . '\s*<\/a>/', $html, $link ) ) {
				throw new RuntimeException( "Case $index: missing navigation to $destination" );
			}
			parse_str( wp_parse_url( html_entity_decode( $link[1] ), PHP_URL_QUERY ) ?? '', $query );
			if ( ( '1' === ( $query['gpm_checkout'] ?? '' ) ) !== $checkout_expected ) {
				throw new RuntimeException( "Case $index: switching auth views lost or added checkout intent." );
			}
		}
		$has_redirect = (bool) preg_match( '/<input\b[^>]*type="hidden"[^>]*name="redirect"[^>]*value="([^"]*)"/', $html, $redirect );
		if ( $has_redirect !== $checkout_expected || ( $has_redirect && html_entity_decode( $redirect[1] ) !== wc_get_checkout_url() ) ) {
			throw new RuntimeException( "Case $index: auth must return only to the generated checkout URL when requested." );
		}
		if ( ! str_contains( $html, 'name="woocommerce-' . $expected . '-nonce"' ) ) {
			throw new RuntimeException( "Case $index: missing native WooCommerce nonce" );
		}
		if ( 'no' === $registration && str_contains( $html, 'gpm_auth=register' ) ) {
			throw new RuntimeException( "Case $index: registration disabled but offered" );
		}
		if ( isset( $_POST['email'] ) && ! str_contains( $html, 'value="bad-email"' ) ) {
			throw new RuntimeException( 'Registration errors must preserve the entered email.' );
		}
		if ( 'register' === $expected ) {
			foreach ( array( 'reg_password', 'reg_password_confirm' ) as $field ) {
				if ( ! preg_match( '/<input\b[^>]*type="password"[^>]*id="' . $field . '"[^>]*autocomplete="new-password"[^>]*required/', $html ) ) {
					throw new RuntimeException( "Register must require $field for password managers and browser validation." );
				}
			}
			if ( str_contains( $html, 'id="reg_username"' ) || str_contains( $html, 'A link to set a new password' ) ) {
				throw new RuntimeException( 'Register should ask only for email, password and confirmation.' );
			}
		}
	}

	$registration = 'yes';
	$password_cases = array(
		array( '', '', true ),
		array( 'Chosen-Password-123!', null, true ),
		array( 'Chosen-Password-123!', 'Different-Password-123!', true ),
		array( array( 'invalid' ), 'Chosen-Password-123!', true ),
		array( 'Chosen-Password-123!', array( 'invalid' ), true ),
		array( '0', '0', true ), // WooCommerce treats "0" as empty and would generate a password.
		array( '   ', '   ', true ),
		array( str_repeat( 'a', 4097 ), str_repeat( 'a', 4097 ), true ),
		array( 'Chosen-Password-123!', 'Chosen-Password-123!', false ),
		array( wp_slash( " A'quoted\\Password-123! " ), wp_slash( " A'quoted\\Password-123! " ), false ),
	);
	foreach ( $password_cases as $index => $case ) {
		list( $password, $confirmation, $reject ) = $case;
		$_POST = array( 'register' => 'Register', 'password' => $password );
		if ( null !== $confirmation ) {
			$_POST['password_confirm'] = $confirmation;
		}
		$errors = apply_filters( 'woocommerce_process_registration_errors', new WP_Error(), '', $password, 'test@example.invalid' );
		if ( $errors->has_errors() !== $reject ) {
			throw new RuntimeException( "Password case $index: unexpected validation result." );
		}
	}
	$valid_post = array( 'register' => 'Register', 'email' => 'test@example.invalid', 'woocommerce-register-nonce' => wp_create_nonce( 'woocommerce-register' ) );
	foreach ( array(
		array( array(), 'yes' ),
		array( $valid_post, 'no' ),
		array( array( 'register' => 'Register' ), 'yes' ),
		array( array( 'register' => 'Register', 'email' => 'test@example.invalid', 'woocommerce-register-nonce' => 'invalid' ), 'yes' ),
		array( array( 'woocommerce-process-checkout-nonce' => 'test' ), 'yes' ),
	) as $case ) {
		$_POST = $case[0];
		if ( apply_filters( 'pre_option_woocommerce_registration_generate_password', 'yes' ) !== $case[1] ) {
			throw new RuntimeException( 'Password generation must change only for the account registration flow.' );
		}
	}
	// Exercise the real handler, stopping at its payload boundary before any DB write/email.
	$captured_password = null;
	$stop_creation = static function ( $data ) use ( &$captured_password ) {
		$captured_password = $data['user_pass'];
		throw new RuntimeException( 'GPM smoke: stopped before account creation.' );
	};
	$_POST = $valid_post + array( 'password' => 'Chosen-Password-123!', 'password_confirm' => 'Chosen-Password-123!' );
	add_filter( 'woocommerce_new_customer_data', $stop_creation );
	try {
		WC_Form_Handler::process_registration();
	} finally {
		remove_filter( 'woocommerce_new_customer_data', $stop_creation );
	}
	if ( 'Chosen-Password-123!' !== $captured_password ) {
		throw new RuntimeException( 'The native handler must retain the chosen password, not generate another.' );
	}
	if ( 20 !== has_action( 'template_redirect', 'gpm_require_checkout_login' ) ) {
		throw new RuntimeException( 'Checkout login redirect must run after WooCommerce handles an empty cart.' );
	}
	$original_cart = WC()->cart;
	$original_user = wp_get_current_user();
	$original_screen = $GLOBALS['current_screen'] ?? null;
	$original_query_vars = $GLOBALS['wp']->query_vars;
	$original_method = $_SERVER['REQUEST_METHOD'] ?? null;
	$checkout = true;
	$ajax = false;
	$checkout_filter = static function () use ( &$checkout ) { return $checkout; };
	$ajax_filter = static function () use ( &$ajax ) { return $ajax; };
	$captured_redirect = null;
	$stop_redirect = static function ( $location, $status ) use ( &$captured_redirect ) {
		$captured_redirect = array( $location, $status );
		throw new RuntimeException( 'GPM smoke: stopped before redirect headers.' );
	};
	add_filter( 'woocommerce_is_checkout', $checkout_filter );
	add_filter( 'wp_doing_ajax', $ajax_filter );
	add_filter( 'wp_redirect', $stop_redirect, 0, 2 );
	$route_cases = array(
		'guest GET' => array( 'redirect' => true ),
		'guest HEAD' => array( 'method' => 'HEAD', 'redirect' => true ),
		'logged in' => array( 'logged_in' => true ),
		'empty cart' => array( 'empty' => true ),
		'no cart' => array( 'no_cart' => true ),
		'POST' => array( 'method' => 'POST' ),
		'PUT' => array( 'method' => 'PUT' ),
		'AJAX' => array( 'ajax' => true ),
		'admin' => array( 'admin' => true ),
		'auth page' => array( 'checkout' => false ),
		'order pay' => array( 'endpoint' => 'order-pay' ),
		'order received' => array( 'endpoint' => 'order-received' ),
		'REST' => array( 'rest' => true ), // Last: constants last only for this CLI process.
	);
	try {
		foreach ( $route_cases as $label => $case ) {
			$checkout = $case['checkout'] ?? true;
			$ajax = $case['ajax'] ?? false;
			$_SERVER['REQUEST_METHOD'] = $case['method'] ?? 'GET';
			$GLOBALS['wp']->query_vars = isset( $case['endpoint'] ) ? array( $case['endpoint'] => '123' ) : array();
			$GLOBALS['current_user'] = new WP_User();
			$GLOBALS['current_user']->ID = empty( $case['logged_in'] ) ? 0 : 123;
			$GLOBALS['current_screen'] = new class {
				public $admin = false;
				public function in_admin() { return $this->admin; }
			};
			$GLOBALS['current_screen']->admin = $case['admin'] ?? false;
			WC()->cart = new class {
				public $empty = false;
				public function is_empty() { return $this->empty; }
			};
			WC()->cart->empty = $case['empty'] ?? false;
			if ( ! empty( $case['no_cart'] ) ) {
				WC()->cart = null;
			}
			if ( ! empty( $case['rest'] ) ) {
				define( 'REST_REQUEST', true );
			}
			$captured_redirect = null;
			try {
				gpm_require_checkout_login();
			} catch ( RuntimeException $error ) {
				if ( 'GPM smoke: stopped before redirect headers.' !== $error->getMessage() ) {
					throw $error;
				}
			}
			$target = add_query_arg( array( 'gpm_auth' => 'login', 'gpm_checkout' => '1' ), wc_get_page_permalink( 'myaccount' ) );
			$expected_redirect = empty( $case['redirect'] ) ? null : array( $target, 302 );
			if ( $captured_redirect !== $expected_redirect ) {
				throw new RuntimeException( "Checkout route failed: $label" );
			}
		}
	} finally {
		WC()->cart = $original_cart;
		$GLOBALS['current_user'] = $original_user;
		$GLOBALS['current_screen'] = $original_screen;
		$GLOBALS['wp']->query_vars = $original_query_vars;
		if ( null === $original_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $original_method;
		}
		remove_filter( 'woocommerce_is_checkout', $checkout_filter );
		remove_filter( 'wp_doing_ajax', $ajax_filter );
		remove_filter( 'wp_redirect', $stop_redirect, 0 );
	}
	echo 'PASS: ' . count( $cases ) . ' auth render cases, ' . count( $route_cases ) . " checkout routes, 10 password cases, 5 request-scope checks and native password handoff.\n";
} finally {
	$_GET = $original_get;
	$_POST = $original_post;
	remove_filter( 'pre_option_woocommerce_enable_myaccount_registration', $registration_filter );
}
