<?php
/**
 * Checkout cart errors, presented as a dashboard-style card.
 *
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="gpm-checkout-error-stage">
	<section class="gpm-checkout-error" aria-labelledby="gpm-checkout-error-title">
		<span class="gpm-checkout-error__icon" aria-hidden="true">!</span>
		<h2 id="gpm-checkout-error-title"><?php esc_html_e( 'Review your cart', 'guest-post-child' ); ?></h2>
		<p class="gpm-checkout-error__message"><?php esc_html_e( 'There are some issues with the items in your cart. Please go back to the cart page and resolve these issues before checking out.', 'woocommerce' ); ?></p>
		<div class="gpm-checkout-error__details"><?php do_action( 'woocommerce_cart_has_errors' ); ?></div>
		<a class="button wc-backward gpm-checkout-error__button" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Return to cart', 'woocommerce' ); ?></a>
	</section>
</div>
