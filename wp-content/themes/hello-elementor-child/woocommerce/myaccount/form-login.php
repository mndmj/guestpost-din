<?php
/**
 * Login Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-login.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$gpm_registration_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
$gpm_auth_view = isset( $_GET['gpm_auth'] ) && is_string( $_GET['gpm_auth'] ) ? sanitize_key( wp_unslash( $_GET['gpm_auth'] ) ) : 'choose';
if ( ! in_array( $gpm_auth_view, array( 'login', 'register' ), true ) ) {
	$gpm_auth_view = 'choose';
}
// ponytail: the submitted form wins so native WooCommerce errors keep the right screen.
if ( isset( $_POST['login'] ) ) {
	$gpm_auth_view = 'login';
} elseif ( isset( $_POST['register'] ) ) {
	$gpm_auth_view = 'register';
}
if ( ! $gpm_registration_enabled ) {
	$gpm_auth_view = 'login';
}
$gpm_account_url = wc_get_page_permalink( 'myaccount' );
$gpm_login_url = add_query_arg( 'gpm_auth', 'login', $gpm_account_url );
$gpm_register_url = add_query_arg( 'gpm_auth', 'register', $gpm_account_url );

do_action( 'woocommerce_before_customer_login_form' ); ?>

<div class="gpm-auth-card" id="customer_login" aria-labelledby="gpm-auth-title">

<?php if ( 'choose' === $gpm_auth_view ) : ?>

	<h2 id="gpm-auth-title"><?php esc_html_e( 'My Account', 'guest-post-child' ); ?></h2>
	<p><?php esc_html_e( 'Log in to your account or register to get started.', 'guest-post-child' ); ?></p>
	<nav class="gpm-auth-choices" aria-label="<?php esc_attr_e( 'Account access', 'guest-post-child' ); ?>">
		<a class="button" href="<?php echo esc_url( $gpm_login_url ); ?>"><?php esc_html_e( 'Login', 'woocommerce' ); ?></a>
		<a class="button" href="<?php echo esc_url( $gpm_register_url ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></a>
	</nav>
	<p class="gpm-auth-home">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Home', 'guest-post-child' ); ?></a>
	</p>

<?php elseif ( 'login' === $gpm_auth_view ) : ?>

		<h2 id="gpm-auth-title"><?php esc_html_e( 'Login', 'woocommerce' ); ?></h2>

		<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

			<?php do_action( 'woocommerce_login_form_start' ); ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
			</p>

			<?php do_action( 'woocommerce_login_form' ); ?>

			<p class="form-row">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
				</label>
				<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
				<button type="submit" class="woocommerce-button button woocommerce-form-login__submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Log in', 'woocommerce' ); ?></button>
			</p>
			<p class="woocommerce-LostPassword lost_password">
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
			</p>
			<?php if ( $gpm_registration_enabled ) : ?>
				<p class="gpm-auth-switch">
					<a href="<?php echo esc_url( $gpm_register_url ); ?>"><?php esc_html_e( 'Do not have an Account ?', 'guest-post-child' ); ?></a>
				</p>
			<?php endif; ?>

			<?php do_action( 'woocommerce_login_form_end' ); ?>

		</form>

<?php elseif ( 'register' === $gpm_auth_view ) : ?>

		<h2 id="gpm-auth-title"><?php esc_html_e( 'Register', 'woocommerce' ); ?></h2>

		<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

			<?php do_action( 'woocommerce_register_form_start' ); ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) && is_string( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
			</p>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" maxlength="4096" required aria-required="true" />
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="reg_password_confirm"><?php esc_html_e( 'Confirm password', 'guest-post-child' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password_confirm" id="reg_password_confirm" autocomplete="new-password" maxlength="4096" required aria-required="true" />
			</p>

			<?php do_action( 'woocommerce_register_form' ); ?>

			<p class="woocommerce-form-row form-row">
				<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
				<button type="submit" class="woocommerce-Button woocommerce-button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?> woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
			</p>
			<p class="gpm-auth-switch">
				<a href="<?php echo esc_url( $gpm_login_url ); ?>"><?php esc_html_e( 'Have an Account ?', 'guest-post-child' ); ?></a>
			</p>

			<?php do_action( 'woocommerce_register_form_end' ); ?>

		</form>

<?php endif; ?>

</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
