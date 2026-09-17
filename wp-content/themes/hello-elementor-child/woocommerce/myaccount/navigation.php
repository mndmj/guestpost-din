<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/navigation.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_account_navigation' );
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_html_e( 'Account pages', 'woocommerce' ); ?>">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<div class="woocommerce-MyAccount-navigation-link woocommerce-MyAccount-navigation-link--home">
			<?php esc_html_e( 'Home', 'guest-post-child' ); ?>
		</div>
	</a>
	<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
		<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
			<div class="<?php echo wc_get_account_menu_item_classes( $endpoint ); ?>">
				<?php echo esc_html( $label ); ?>
			</div>
		</a>
	<?php endforeach; ?>
</nav>

<dialog id="gpm-logout-dialog" aria-labelledby="gpm-logout-title" aria-describedby="gpm-logout-description">
	<form method="dialog">
		<h2 id="gpm-logout-title">Logout?</h2>

		<p id="gpm-logout-description">
			Are you sure you want to log out of your account?
		</p>

		<div class="gpm-logout-actions">
			<button type="submit" value="cancel" class="gpm-logout-cancel" autofocus>
				Cancel
			</button>

			<button type="submit" value="logout" class="gpm-logout-confirm">
				Logout
			</button>
		</div>
	</form>
</dialog>

<script>
	(() => {
		const dialog = document.getElementById("gpm-logout-dialog");

		if (!dialog || typeof dialog.showModal !== "function") return;

		let logoutTrigger = null;

		document.addEventListener("click", (event) => {
			if (event.defaultPrevented || !(event.target instanceof Element)) return;

			const link = event.target.closest("a");

			if (
				!link ||
				!link.querySelector(
					".woocommerce-MyAccount-navigation-link--customer-logout, " +
					".gpm-welcome-card__logout"
				)
			) {
				return;
			}

			event.preventDefault();

			if (dialog.open) return;

			logoutTrigger = link;
			dialog.returnValue = "";
			dialog.showModal();
		});

		dialog.addEventListener("close", () => {
			if (!logoutTrigger) return;

			if (dialog.returnValue === "logout") {
				// Gunakan URL asli WooCommerce, termasuk token keamanannya.
				window.location.assign(logoutTrigger.href);
			} else {
				logoutTrigger.focus();
			}
		});
	})();
</script>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
