<?php
/** Buyer account and native WooCommerce purchase flow. */
defined( 'ABSPATH' ) || exit;

final class DIN_Packages_Customer {
	public static function boot() {
		add_action( 'init', array( __CLASS__, 'endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ) );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'dashboard' ) );
		add_action( 'woocommerce_account_din-packages_endpoint', array( __CLASS__, 'account' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ), 40 );
		add_action( 'template_redirect', array( __CLASS__, 'purchase' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add' ), 20, 6 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'validate_cart_data' ), 20, 4 );
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'price_cart' ), 30 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'price_cart' ), 30 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_cart_errors' ), 10, 2 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_quantity' ), 20, 4 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'quantity_updated' ), 20, 4 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'quantity_html' ), 20, 3 );
		foreach ( array( 'minimum', 'maximum', 'multiple_of' ) as $limit ) {
			add_filter( 'woocommerce_store_api_product_quantity_' . $limit, array( __CLASS__, 'quantity_limit' ), 20, 3 );
		}
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( __CLASS__, 'quantity_editable' ), 20, 3 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 20, 2 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 100 );
		add_action( 'woocommerce_after_order_notes', array( __CLASS__, 'checkout_upgrade_heading' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'checkout_data' ), 100 );
		add_filter( 'woocommerce_checkout_registration_required', array( __CLASS__, 'account_required' ), 100 );
		add_filter( 'woocommerce_checkout_registration_enabled', array( __CLASS__, 'account_required' ), 100 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'snapshot_item' ), 20, 4 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'mark_order' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'mark_order' ), 20 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'source_heading' ), 20 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'capture_checkout' ), 30 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'capture_checkout' ), 30 );
	}

	public static function endpoint() {
		add_rewrite_endpoint( 'din-packages', EP_ROOT | EP_PAGES );
	}

	public static function query_vars( $vars ) {
		$vars['din-packages'] = 'din-packages';
		return $vars;
	}

	public static function menu( $items ) {
		$result = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$result['din-packages'] = __( 'My Package', 'din-package-lifecycle' );
			}
			$result[ $key ] = $label;
		}
		if ( ! isset( $result['din-packages'] ) ) {
			$result['din-packages'] = __( 'My Package', 'din-package-lifecycle' );
		}
		return $result;
	}

	public static function styles() {
		if ( is_account_page() && is_user_logged_in() ) {
			wp_enqueue_style( 'din-packages', plugins_url( '../assets/packages.css', __FILE__ ), array(), DIN_PACKAGES_VERSION );
		}
	}

	public static function dashboard( $packages = null ) {
		$packages = $packages ?? self::running_packages( get_current_user_id() );
		if ( $packages ) {
			self::render_packages( 1, 3, true, $packages );
		}
	}

	public static function running_packages( $customer_id ) {
		if ( ! $customer_id ) {
			return array();
		}
		$running = array();
		// ponytail: scan pages until three matches; index service status if accounts grow to thousands of packages.
		for ( $page = 1; ; ++$page ) {
			$packages = DIN_Packages::for_customer( $customer_id, $page, 100 );
			foreach ( $packages as $package ) {
				if ( (int) $package['customer_id'] !== (int) $customer_id || ! in_array( DIN_Packages::status( $package ), array( 'active', 'expiring', 'lifetime' ), true ) ) {
					continue;
				}
				$order = wc_get_order( $package['order_id'] );
				if ( ! $order || (int) $order->get_customer_id() !== (int) $customer_id || ! $order->has_status( 'completed' ) ) {
					continue;
				}
				$running[] = $package;
				if ( count( $running ) === 3 ) {
					return $running;
				}
			}
			if ( count( $packages ) < 100 ) {
				return $running;
			}
		}
	}

	public static function account() {
		$page = isset( $_GET['paket-page'] ) && is_scalar( $_GET['paket-page'] ) ? max( 1, absint( $_GET['paket-page'] ) ) : 1; // Read-only pagination.
		self::render_packages( $page, 20, false );
	}

	public static function render_packages( $page, $limit, $summary, $packages = null ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$packages = $packages ?? DIN_Packages::for_customer( $user_id, $page, $limit );
		$url = wc_get_account_endpoint_url( 'din-packages' );
		$labels = array( 'pending' => 'Awaiting Activation', 'active' => 'Active', 'expiring' => 'Expiring Soon', 'expired' => 'Expired', 'lifetime' => 'Lifetime', 'review' => 'Pending Review', 'stopped' => 'Stopped' );
		?>
		<section class="din-packages" aria-labelledby="din-packages-title">
			<header class="din-packages__header">
				<div>
					<p class="din-packages__eyebrow">Service Validity Period</p>
					<h2 id="din-packages-title">My Package</h2>
				</div>
				<?php if ( $summary ) : ?><a href="<?php echo esc_url( $url ); ?>">View all packages</a><?php endif; ?>
			</header>
			<?php if ( ! $packages ) : ?>
				<p>There are no packages on this page yet. New packages will appear after checking out a configured product.</p>
			<?php endif; ?>
			<div class="din-packages__list">
				<?php foreach ( $packages as $package ) : ?>
					<?php
					if ( (int) $package['customer_id'] !== $user_id ) {
						continue;
					}
					$order = wc_get_order( $package['order_id'] );
					if ( ! $order || (int) $order->get_customer_id() !== $user_id ) {
						continue;
					}
					$status = DIN_Packages::status( $package );
					$expiry = (int) $package['expires_at'];
					$days = $expiry ? max( 0, (int) ceil( ( $expiry - time() ) / DAY_IN_SECONDS ) ) : 0;
					?>
					<article class="din-packages__card" aria-labelledby="din-package-<?php echo esc_attr( $package['id'] ); ?>">
						<div class="din-packages__identity">
							<h3 id="din-package-<?php echo esc_attr( $package['id'] ); ?>">
								<?php echo esc_html( $package['product_name'] ); ?>
							</h3><span
								class="din-packages__status din-packages__status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $labels[ $status ] ?? $labels['review'] ); ?></span>
						</div>
						<?php if ( ! empty( $package['upgrade_badges'] ) ) : ?>
							<div class="din-order-package-badges"><?php echo wp_kses_post( $package['upgrade_badges'] ); ?></div>
						<?php endif; ?>
						<p class="din-packages__heading"><strong>Heading Post:</strong>
							<?php echo esc_html( $package['heading'] ?: '—' ); ?></p>
						<dl class="din-packages__dates">
							<div>
								<dt>Package / Period</dt>
								<dd>#<?php echo esc_html( $package['id'] ); ?> ·
									<?php echo 'lifetime' === $package['period'] ? 'Lifetime' : 'Annual'; ?>
								</dd>
							</div>
							<div>
								<dt>Start</dt>
								<dd><?php self::date_html( $package['started_at'] ); ?></dd>
							</div>
							<div>
								<dt><?php echo 'stopped' === $status ? 'Stopped on' : 'End'; ?></dt>
								<dd><?php if ( 'stopped' === $status ) {
									self::date_html( $package['stopped_at'] );
								} elseif ( 'lifetime' === $package['period'] ) {
									echo 'No expiration date';
								} else {
									self::date_html( $expiry );
								} ?>
								</dd>
							</div>
							<div>
								<dt>Remaining Time</dt>
								<dd><?php echo esc_html( 'stopped' === $status ? 'Stopped' : ( $expiry ? $days . ' day' : ( 'lifetime' === $package['period'] && $package['started_at'] ? 'Lifetime' : 'Awaiting Activation' ) ) ); ?>
								</dd>
							</div>
						</dl>
						<?php if ( 'stopped' === $status ) : ?>
							<p class="din-packages__heading"><strong>Reason for stopping:</strong>
								<?php echo esc_html( $package['stop_reason'] ?? '' ); ?></p>
						<?php endif; ?>
						<?php if ( 'review' === $status ) : ?>
							<p>The package requires admin review. Contact the store using the original order details.</p><?php endif; ?>
						<a class="din-packages__order" href="<?php echo esc_url( $package['evidence_url'] ?? $order->get_view_order_url() ); ?>">View Order
							#<?php echo esc_html( $order->get_order_number() ); ?> and evidence</a>
						<div class="din-packages__actions">
							<?php foreach ( array( 'renew_1', 'renew_2', 'lifetime' ) as $action ) : ?>
								<?php $option = DIN_Packages::purchase_option( $package['id'], $action, $user_id ); ?>
								<?php if ( ! is_wp_error( $option ) ) : ?>
									<form method="post" action="<?php echo esc_url( $url ); ?>">
										<?php wp_nonce_field( 'din_package_purchase_' . $package['id'], '_din_package_nonce' ); ?>
										<input type="hidden" name="din_package_id" value="<?php echo esc_attr( $package['id'] ); ?>">
										<button class="din-packages__button" type="submit" name="din_package_action"
											value="<?php echo esc_attr( $action ); ?>"
											aria-label="<?php echo esc_attr( $option['label'] . ' — Package #' . $package['id'] ); ?>"><?php echo esc_html( $option['label'] ); ?></button>
									</form>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( ! $summary && ( $page > 1 || count( $packages ) === $limit ) ) : ?>
				<nav class="din-packages__pagination" aria-label="My Package Page">
					<?php if ( $page > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( 'paket-page', $page - 1, $url ) ); ?>">←
							Previous</a><?php endif; ?>
					<span aria-current="page">Page <?php echo esc_html( $page ); ?></span>
					<?php if ( count( $packages ) === $limit ) : ?><a
							href="<?php echo esc_url( add_query_arg( 'paket-page', $page + 1, $url ) ); ?>">Next
							→</a><?php endif; ?>
				</nav>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function date_html( $timestamp ) {
		if ( ! $timestamp ) {
			echo '—';
			return;
		}
		echo '<time datetime="' . esc_attr( gmdate( 'c', (int) $timestamp ) ) . '">' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp ) ) . '</time>';
	}

	public static function purchase() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['din_package_action'] ) ) {
			return;
		}
		$id = isset( $_POST['din_package_id'] ) && is_scalar( $_POST['din_package_id'] ) ? absint( $_POST['din_package_id'] ) : 0;
		$action = is_string( $_POST['din_package_action'] ) ? sanitize_key( wp_unslash( $_POST['din_package_action'] ) ) : '';
		$nonce = isset( $_POST['_din_package_nonce'] ) && is_string( $_POST['_din_package_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_din_package_nonce'] ) ) : '';
		if ( ! is_user_logged_in() || ! wp_verify_nonce( $nonce, 'din_package_purchase_' . $id ) ) {
			wp_die( esc_html__( 'Session invalid. Log in and try again from My Package.', 'din-package-lifecycle' ), '', array( 'response' => 403 ) );
		}
		$option = DIN_Packages::purchase_option( $id, $action, get_current_user_id() );
		if ( is_wp_error( $option ) ) {
			self::notice( $option );
			wp_safe_redirect( wc_get_account_endpoint_url( 'din-packages' ) );
			exit;
		}
		if ( ! WC()->cart ) {
			wc_load_cart();
		}
		$product = wc_get_product( $option['product_id'] );
		if ( ! $product ) {
			self::notice( new WP_Error( 'din_product', 'The package product is not available.' ) );
			wp_safe_redirect( wc_get_account_endpoint_url( 'din-packages' ) );
			exit;
		}
		$is_variation = $product->is_type( 'variation' );
		$added = WC()->cart->add_to_cart( $is_variation ? $product->get_parent_id() : $product->get_id(), 1, $is_variation ? $product->get_id() : 0, $is_variation ? $product->get_variation_attributes() : array(), array( '_din_package_purchase' => array( 'package_id' => $id, 'action' => $action ) ) );
		if ( $added ) {
			wc_add_notice( __( 'Package options have been added. Prices and durations can be checked before payment.', 'din-package-lifecycle' ), 'success' );
		}
		wp_safe_redirect( $added ? wc_get_cart_url() : wc_get_account_endpoint_url( 'din-packages' ) );
		exit;
	}

	/** A single trust boundary for request data, restored carts, pricing and checkout. */
	public static function option_for_item( $item ) {
		$purchase = $item['_din_package_purchase'] ?? null;
		if ( ! is_array( $purchase ) || ! isset( $purchase['package_id'], $purchase['action'] ) || ! is_scalar( $purchase['package_id'] ) || ! is_string( $purchase['action'] ) || ! is_numeric( $item['quantity'] ?? null ) || 1.0 !== (float) $item['quantity'] ) {
			return new WP_Error( 'din_package_item', __( 'Invalid package purchase. The quantity must be one; select a duration from My Package.', 'din-package-lifecycle' ) );
		}
		$option = DIN_Packages::purchase_option( absint( $purchase['package_id'] ), $purchase['action'], get_current_user_id() );
		if ( is_wp_error( $option ) ) {
			return $option;
		}
		if ( (int) $option['product_id'] !== (int) ( ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? 0 ) ) ) {
			return new WP_Error( 'din_package_product', __( 'The package product has changed. Remove this selection from your cart and select it again via My Package.', 'din-package-lifecycle' ) );
		}
		return $option;
	}

	public static function validate_cart_data( $data, $product_id, $variation_id, $quantity ) {
		if ( ! array_key_exists( '_din_package_purchase', $data ) ) {
			return $data;
		}
		$item = array_merge( $data, array( 'product_id' => $product_id, 'variation_id' => $variation_id, 'quantity' => $quantity ) );
		$option = self::option_for_item( $item );
		if ( is_wp_error( $option ) ) {
			throw new Exception( $option->get_error_message() );
		}
		foreach ( WC()->cart->get_cart() as $existing ) {
			if ( isset( $existing['_din_package_purchase']['package_id'] ) && (int) $existing['_din_package_purchase']['package_id'] === (int) $option['package']['id'] ) {
				throw new Exception( __( 'This package already has a selection in the cart. Remove that selection before changing the duration.', 'din-package-lifecycle' ) );
			}
		}
		$data['_din_package_purchase'] = array( 'package_id' => (int) $option['package']['id'], 'action' => $data['_din_package_purchase']['action'] );
		return $data;
	}

	public static function validate_add( $passed, $product_id, $quantity, $variation_id = 0, $variation = array(), $data = array() ) {
		if ( ! array_key_exists( '_din_package_purchase', $data ) ) {
			return $passed;
		}
		$error = self::option_for_item( array_merge( $data, array( 'product_id' => $product_id, 'variation_id' => $variation_id, 'quantity' => $quantity ) ) );
		if ( is_wp_error( $error ) ) {
			self::notice( $error );
			return false;
		}
		return $passed;
	}

	public static function price_cart( $cart ) {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! array_key_exists( '_din_package_purchase', $item ) ) {
				continue;
			}
			$option = self::option_for_item( $item );
			if ( is_wp_error( $option ) ) {
				self::notice( $option );
				continue;
			}
			$product = wc_get_product( $option['product_id'] );
			if ( $product ) {
				// Clone the fresh catalog product: ordinary cart lines and repeated totals stay untouched.
				$product = clone $product;
				$product->set_price( wc_format_decimal( (float) $product->get_price( 'edit' ) * (int) $option['multiplier'] ) );
				$cart->cart_contents[ $key ]['data'] = $product;
			}
		}
	}

	private static function cart_errors( $cart ) {
		$errors = array();
		$seen = array();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! array_key_exists( '_din_package_purchase', $item ) ) {
				continue;
			}
			$option = self::option_for_item( $item );
			if ( is_wp_error( $option ) ) {
				$errors[] = $option;
				continue;
			}
			$id = $option['package']['id'];
			if ( isset( $seen[ $id ] ) ) {
				$errors[] = new WP_Error( 'din_package_duplicate', __( 'Only one purchase option for each package may be in the cart.', 'din-package-lifecycle' ) );
			}
			$seen[ $id ] = true;
		}
		return $errors;
	}

	private static function notice( $error ) {
		if ( ! wc_has_notice( $error->get_error_message(), 'error' ) ) {
			wc_add_notice( $error->get_error_message(), 'error' );
		}
	}

	public static function check_cart() {
		if ( WC()->cart ) {
			foreach ( self::cart_errors( WC()->cart ) as $error ) {
				self::notice( $error );
			}
		}
	}

	public static function store_cart_errors( $errors, $cart ) {
		foreach ( self::cart_errors( $cart ) as $index => $error ) {
			$errors->add( 'din_package_' . $index, $error->get_error_message() );
		}
	}

	public static function validate_quantity( $passed, $key, $item, $quantity ) {
		if ( array_key_exists( '_din_package_purchase', $item ) && 0 < $quantity && 1.0 !== (float) $quantity ) {
			self::notice( new WP_Error( 'din_quantity', __( 'The package quantity must be one. The duration is selected via My Package.', 'din-package-lifecycle' ) ) );
			return false;
		}
		return $passed;
	}

	public static function quantity_updated( $key, $quantity, $old_quantity, $cart ) {
		if ( isset( $cart->cart_contents[ $key ]['_din_package_purchase'] ) && $quantity > 0 && 1.0 !== (float) $quantity ) {
			$cart->set_quantity( $key, 1, false );
		}
	}

	public static function quantity_html( $html, $key, $item ) {
		return array_key_exists( '_din_package_purchase', $item ) ? '<span>1</span>' : $html;
	}

	public static function quantity_limit( $value, $product, $item ) {
		return is_array( $item ) && array_key_exists( '_din_package_purchase', $item ) ? 1 : $value;
	}

	public static function quantity_editable( $value, $product, $item ) {
		return is_array( $item ) && array_key_exists( '_din_package_purchase', $item ) ? false : $value;
	}

	public static function item_data( $data, $item ) {
		if ( array_key_exists( '_din_package_purchase', $item ) ) {
			$option = self::option_for_item( $item );
			if ( ! is_wp_error( $option ) ) {
				$data[] = array( 'key' => 'Destination package', 'value' => '#' . $option['package']['id'] . ' — ' . $option['label'] );
				$data[] = array( 'key' => 'Original Post Heading', 'value' => $option['package']['heading'] );
				$data[] = array( 'key' => 'Activation of changes', 'value' => 'After payment and approval have been completed by the admin.' );
			}
		}
		return $data;
	}

	/** Mixed carts retain the store's normal new-publication heading requirement. */
	private static function renewal_heading() {
		if ( ! WC()->cart || ! WC()->cart->get_cart() ) {
			return false;
		}
		$headings = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! array_key_exists( '_din_package_purchase', $item ) ) {
				return false;
			}
			$option = self::option_for_item( $item );
			if ( is_wp_error( $option ) ) {
				return false;
			}
			$headings[] = '#' . $option['package']['id'] . ': ' . $option['package']['heading'];
		}
		return implode( "\n", $headings );
	}

	public static function checkout_fields( $fields ) {
		if ( false !== self::renewal_heading() ) {
			unset( $fields['order']['order_comments'] );
		}
		return $fields;
	}

	public static function checkout_upgrade_heading() {
		if ( ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$option = self::option_for_item( $item );
			if ( is_wp_error( $option ) || 'lifetime' !== $item['_din_package_purchase']['action'] ) {
				continue;
			}
			echo '<p class="form-row form-row-wide din-packages-checkout-heading"><strong>';
			echo esc_html( sprintf( __( 'Heading Post — Package #%d', 'din-package-lifecycle' ), $option['package']['id'] ) );
			echo '</strong><br>' . nl2br( esc_html( $option['package']['heading'] ?: '—' ) ) . '</p>';
		}
	}

	public static function checkout_data( $data ) {
		$heading = self::renewal_heading();
		if ( false !== $heading ) {
			$data['order_comments'] = $heading;
		}
		return $data;
	}

	public static function account_required( $required ) {
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				$config = DIN_Packages::config( $item['data'] ?? false );
				if ( isset( $item['_din_package_purchase'] ) || ! empty( $config['period'] ) ) {
					return true;
				}
			}
		}
		return $required;
	}

	public static function source_heading( $order ) {
		$heading = self::renewal_heading();
		if ( false !== $heading ) {
			$order->set_customer_note( $heading );
		}
	}

	public static function snapshot_item( $item, $cart_key, $values, $order ) {
		if ( array_key_exists( '_din_package_purchase', $values ) ) {
			$option = self::option_for_item( $values );
			if ( is_wp_error( $option ) ) {
				throw new Exception( $option->get_error_message() );
			}
			$item->update_meta_data( '_din_package_purchase', array( 'package_id' => (int) $option['package']['id'], 'action' => $values['_din_package_purchase']['action'] ) );
			$item->update_meta_data( '_din_package_heading', $option['package']['heading'] );
			$item->add_meta_data( 'Destination package', '#' . $option['package']['id'] . ' — ' . $option['label'], true );
			$item->add_meta_data( 'Original Heading Post', $option['package']['heading'], true );
		} elseif ( ! empty( $values['data'] ) ) {
			$item->update_meta_data( '_din_package_config', DIN_Packages::config( $values['data'] ) );
		}
	}

	public static function mark_order( $order ) {
		$order->update_meta_data( '_din_packages_version', DIN_PACKAGES_VERSION );
		self::source_heading( $order );
	}

	public static function capture_checkout( $order ) {
		if ( ! $order->get_customer_id() ) {
			foreach ( $order->get_items() as $item ) {
				$config = $item->get_meta( '_din_package_config' );
				if ( $item->get_meta( '_din_package_purchase' ) || ! empty( $config['period'] ) ) {
					throw new Exception( __( 'The package requires an account. Log in or create an account before proceeding to payment.', 'din-package-lifecycle' ) );
				}
			}
		}
		self::mark_order( $order );
		$order->save();
		DIN_Packages::capture_order( $order );
	}
}
