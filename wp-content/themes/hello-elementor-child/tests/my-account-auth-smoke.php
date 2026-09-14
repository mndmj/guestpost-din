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
	array( array(), array(), 'yes', 'choose' ),
	array( array( 'gpm_auth' => 'login' ), array(), 'yes', 'login' ),
	array( array( 'gpm_auth' => 'register' ), array(), 'yes', 'register' ),
	array( array( 'gpm_auth' => 'invalid' ), array(), 'yes', 'choose' ),
	array( array( 'gpm_auth' => array( 'register' ) ), array(), 'yes', 'choose' ),
	array( array( 'gpm_auth' => 'register' ), array( 'login' => 'Log in' ), 'yes', 'login' ),
	array( array( 'gpm_auth' => 'login' ), array( 'register' => 'Register', 'email' => 'bad-email' ), 'yes', 'register' ),
	array( array(), array(), 'no', 'login' ),
	array( array( 'gpm_auth' => 'register' ), array(), 'no', 'login' ),
);

try {
	foreach ( $cases as $index => $case ) {
		list( $_GET, $_POST, $registration, $expected ) = $case;
		ob_start();
		include dirname( __DIR__ ) . '/woocommerce/myaccount/form-login.php';
		$html = ob_get_clean();
		$has_login = str_contains( $html, 'woocommerce-form-login login' );
		$has_register = str_contains( $html, 'woocommerce-form-register register' );
		if ( $has_login !== ( 'login' === $expected ) || $has_register !== ( 'register' === $expected ) ) {
			throw new RuntimeException( "Case $index: expected only $expected, got login=" . (int) $has_login . ', register=' . (int) $has_register );
		}
		$links = array();
		if ( 'choose' === $expected ) {
			$links = array( 'Login' => 'login', 'Register' => 'register' );
			if ( ! preg_match( '/<a\b[^>]*href="' . preg_quote( esc_url( home_url( '/' ) ), '/' ) . '"[^>]*>\s*Back to Home\s*<\/a>/', $html ) ) {
				throw new RuntimeException( 'The account chooser must link back to the WordPress homepage.' );
			}
		} elseif ( 'register' === $expected ) {
			$links = array( 'Have an Account ?' => 'login' );
		} elseif ( 'yes' === $registration ) {
			$links = array( 'Do not have an Account ?' => 'register' );
		}
		foreach ( $links as $label => $destination ) {
			if ( ! preg_match( '/<a\b[^>]*href="[^"]*gpm_auth=' . $destination . '[^"]*"[^>]*>\s*' . preg_quote( $label, '/' ) . '\s*<\/a>/', $html ) ) {
				throw new RuntimeException( "Case $index: missing navigation to $destination" );
			}
		}
		if ( 'choose' !== $expected && ! str_contains( $html, 'name="woocommerce-' . $expected . '-nonce"' ) ) {
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
	echo "PASS: 9 navigation/form cases, 10 password validation cases, 5 request-scope checks and native password handoff.\n";
} finally {
	$_GET = $original_get;
	$_POST = $original_post;
	remove_filter( 'pre_option_woocommerce_enable_myaccount_registration', $registration_filter );
}
