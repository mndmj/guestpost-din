<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gpm_is_cart = function_exists( 'is_cart' ) && is_cart();

if (
	( function_exists( 'is_checkout' ) && is_checkout() ) ||
	$gpm_is_cart
) {
	?>
	<header id="site-header" class="gpm-checkout-header">
		<a href="<?php echo esc_url( $gpm_is_cart ? home_url( '/' ) : wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="gpm-checkout-back-link">
			<div class="gpm-checkout-back-button">
				<span aria-hidden="true">&larr;</span>
				<?php echo $gpm_is_cart ? esc_html__( 'Back to Home Page', 'guest-post-child' ) : esc_html__( 'Back to Dashboard', 'guest-post-child' ); ?>
			</div>
		</a>
	</header>
	<?php
	return;
}

require get_template_directory() . '/template-parts/dynamic-header.php';
