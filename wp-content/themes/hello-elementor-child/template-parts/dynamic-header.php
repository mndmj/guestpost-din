<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	( function_exists( 'is_checkout' ) && is_checkout() ) ||
	( function_exists( 'is_cart' ) && is_cart() )
) {
	?>
	<header id="site-header" class="gpm-checkout-header">
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="gpm-checkout-back-link">
			<div class="gpm-checkout-back-button">
				<span aria-hidden="true">&larr;</span>
				<?php esc_html_e( 'Back to Dashboard', 'guest-post-child' ); ?>
			</div>
		</a>
	</header>
	<?php
	return;
}

require get_template_directory() . '/template-parts/dynamic-header.php';
