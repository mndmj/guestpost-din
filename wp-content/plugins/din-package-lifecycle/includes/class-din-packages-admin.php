<?php

defined( 'ABSPATH' ) || exit;

final class DIN_Packages_Admin {
	private const ORDER_COLUMNS = array(
		'din_package_name' => 'Package',
		'din_package_period' => 'Period',
		'din_package_status' => 'Package Status',
		'din_package_start' => 'Start Date',
		'din_package_end' => 'Expires / Stopped',
		'din_package_remaining' => 'Remaining Days',
		'din_package_source' => 'Original Order',
		'din_package_changes' => 'Upgrade / Renewal',
	);
	private static $column_order_id = 0;
	private static $column_entries = array();

	public static function boot() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'approve_order' ), 80, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_stop_page' ) );
		add_action( 'admin_post_din_stop_package', array( __CLASS__, 'handle_stop' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'order_columns' ), 30 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'order_columns' ), 30 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'render_order_column' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_order_column' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'order_assets' ) );
	}

	public static function order_columns( $columns ) {
		unset( $columns['din_package_validity'] );
		$result = array();
		foreach ( $columns as $key => $title ) {
			if ( isset( self::ORDER_COLUMNS[ $key ] ) ) {
				continue;
			}
			$result[ $key ] = $title;
			if ( 'order_status' === $key ) {
				$result += self::ORDER_COLUMNS;
			}
		}
		return $result + self::ORDER_COLUMNS;
	}

	public static function order_assets() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'admin_page_wc-orders', 'edit-shop_order', 'shop_order' ), true ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_enqueue_style( 'din-package-orders', plugins_url( 'assets/admin-orders.css', DIN_PACKAGES_FILE ), array(), filemtime( dirname( DIN_PACKAGES_FILE ) . '/assets/admin-orders.css' ) );
		if ( 'shop_order' === $screen->id || ( in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'admin_page_wc-orders' ), true ) && in_array( $_GET['action'] ?? '', array( 'edit', 'new' ), true ) ) ) {
			wp_enqueue_script( 'din-package-expiry', plugins_url( 'assets/admin-expiry.js', DIN_PACKAGES_FILE ), array(), filemtime( dirname( DIN_PACKAGES_FILE ) . '/assets/admin-expiry.js' ), true );
			return;
		}
		wp_enqueue_script( 'din-package-orders', plugins_url( 'assets/admin-orders.js', DIN_PACKAGES_FILE ), array(), DIN_PACKAGES_VERSION, true );
	}

	public static function render_order_column( $column, $order_or_id ) {
		if ( ! isset( self::ORDER_COLUMNS[ $column ] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			return;
		}
		if ( self::$column_order_id !== $order->get_id() ) {
			self::$column_entries = self::order_entries( $order );
			self::$column_order_id = $order->get_id();
		}
		if ( ! self::$column_entries ) {
			echo '&mdash;';
			return;
		}
		$labels = array( 'pending' => 'Awaiting Activation', 'active' => 'Active', 'expiring' => 'Expiring Soon', 'expired' => 'Expired', 'lifetime' => 'Lifetime', 'review' => 'Pending Review', 'stopped' => 'Stopped' );
		foreach ( self::$column_entries as $entry ) {
			$package = $entry['package'];
			$status = DIN_Packages::status( $package );
			$value = '—';
			switch ( $column ) {
				case 'din_package_name':
					$value = $package['product_name'];
					break;
				case 'din_package_period':
					$value = 'lifetime' === $package['period'] ? 'Lifetime' : 'Annual';
					break;
				case 'din_package_status':
					$value = $labels[ $status ] ?? $labels['review'];
					break;
				case 'din_package_start':
					$value = self::date( $package['started_at'] ?? 0 );
					break;
				case 'din_package_end':
					$value = 'stopped' === $status ? self::date( $package['stopped_at'] ?? 0 ) : ( 'lifetime' === $status ? 'No expiration date' : self::date( $package['expires_at'] ?? 0 ) );
					break;
				case 'din_package_remaining':
					if ( 'lifetime' === $status ) {
						$value = 'No expiration date';
					} elseif ( in_array( $status, array( 'active', 'expiring' ), true ) ) {
						$value = max( 0, (int) ceil( ( (int) $package['expires_at'] - time() ) / DAY_IN_SECONDS ) );
					}
					break;
				case 'din_package_source':
					$value = '#' . $package['order_id'];
					break;
				case 'din_package_changes':
					$value = $entry['changes'] ? implode( '; ', array_unique( $entry['changes'] ) ) : '—';
					break;
			}
			echo '<div class="din-order-package-value">';
			if ( 'din_package_name' === $column || count( self::$column_entries ) > 1 ) {
				echo '<span class="din-order-package-id">' . esc_html( 'Package #' . $package['id'] ) . '</span>';
			}
			echo esc_html( $value ) . '</div>';
		}
	}

	private static function order_entries( $order ) {
		$entries = array();
		foreach ( DIN_Packages::for_order( $order->get_id() ) as $package ) {
			$entries[ $package['id'] ] = array( 'package' => $package, 'changes' => array() );
		}
		$actions = array( 'renew_1' => '1-Year Renewal', 'renew_2' => '2-Year Renewal', 'renew_custom' => 'Custom renewal', 'lifetime' => 'Upgrade to Lifetime' );
		foreach ( $order->get_items() as $item ) {
			$purchase = $item->get_meta( '_din_package_purchase', true );
			$package_id = is_array( $purchase ) ? ( $purchase['package_id'] ?? null ) : null;
			if ( ! is_scalar( $package_id ) || ! ctype_digit( (string) $package_id ) || (int) $package_id < 1 || ! is_string( $purchase['action'] ?? null ) || ! isset( $actions[ $purchase['action'] ] ) ) {
				continue;
			}
			$package = DIN_Packages::get( (int) $package_id );
			if ( ! $package || (int) $order->get_customer_id() !== (int) $package['customer_id'] || ! current_user_can( 'edit_shop_order', $package['order_id'] ) ) {
				continue;
			}
			$source = wc_get_order( $package['order_id'] );
			if ( ! $source || (int) $source->get_customer_id() !== (int) $package['customer_id'] ) {
				continue;
			}
			$applied = false;
			$custom_years = is_array( $purchase['admin_request'] ?? null ) ? ( $purchase['admin_request']['years'] ?? null ) : null;
			foreach ( $package['history'] ?? array() as $event ) {
				if ( (int) ( $event['order_id'] ?? 0 ) === $order->get_id() && (int) ( $event['item_id'] ?? 0 ) === $item->get_id() && ( $event['action'] ?? '' ) === $purchase['action'] ) {
					$applied = true;
					$custom_years = $event['years'] ?? $custom_years;
					break;
				}
			}
			if ( ! isset( $entries[ $package['id'] ] ) ) {
				$entries[ $package['id'] ] = array( 'package' => $package, 'changes' => array() );
			}
			$change_status = $applied ? 'Applied' : ( $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ? 'Not applied' : 'Awaiting approval' );
			$label = 'renew_custom' === $purchase['action'] ? self::custom_renewal_label( $custom_years ) : $actions[ $purchase['action'] ];
			$entries[ $package['id'] ]['changes'][] = $label . ' — ' . $change_status;
		}
		return $entries;
	}

	private static function custom_renewal_label( $years ) {
		return ( is_int( $years ) || is_string( $years ) ) && preg_match( '/^[1-9][0-9]{0,3}$/D', (string) $years )
			? sprintf( __( '%d-Year Renewal', 'din-package-lifecycle' ), (int) $years )
			: __( 'Custom renewal (invalid duration)', 'din-package-lifecycle' );
	}

	public static function register_stop_page() {
		$hook = add_submenu_page( '', 'Cancel Package', 'Cancel Package', 'manage_woocommerce', 'din-stop-package', array( __CLASS__, 'stop_page' ) );
		add_action( 'load-' . $hook, static function () {
			$GLOBALS['title'] = 'Cancel Package';
		} );
	}

	private static function editable_package( $id ) {
		$id = is_scalar( $id ) && ctype_digit( (string) $id ) ? absint( $id ) : 0;
		$package = $id ? DIN_Packages::get( $id ) : null;
		if ( ! current_user_can( 'manage_woocommerce' ) || ! $package || ! current_user_can( 'edit_shop_order', $package['order_id'] ) || ! wc_get_order( $package['order_id'] ) ) {
			wp_die( esc_html__( 'Package management access denied.', 'din-package-lifecycle' ), '', array( 'response' => 403 ) );
		}
		return $package;
	}

	public static function stop_page() {
		$package = self::editable_package( $_GET['package_id'] ?? '' );
		$order = wc_get_order( $package['order_id'] );
		echo '<div class="wrap"><h1>Cancel Package #' . esc_html( $package['id'] ) . '</h1>';
		echo '<p><strong>' . esc_html( $package['product_name'] ) . '</strong><br>Heading Post: ' . esc_html( $package['heading'] ) . '</p>';
		if ( ! empty( $package['stopped_at'] ) ) {
			echo '<p>Stopped: ' . esc_html( self::date( $package['stopped_at'] ) ) . '<br>' . nl2br( esc_html( $package['stop_reason'] ?? '' ) ) . '</p>';
		} elseif ( empty( $package['started_at'] ) ) {
			echo '<p>The package is not yet active and cannot be cancelled.</p>';
		} else {
			echo '<p>The package status has changed to <strong>Stopped</strong> and cannot be reactivated via this page. The order, payment, proof, and history are retained. No automatic refund is issued.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="din_stop_package"><input type="hidden" name="package_id" value="' . esc_attr( $package['id'] ) . '">';
			wp_nonce_field( 'din_stop_package_' . $package['id'], 'din_stop_nonce' );
			echo '<p><label for="din-stop-reason"><strong>Reason for termination (visible to the buyer)</strong></label><br><textarea id="din-stop-reason" name="din_stop_reason" class="large-text" rows="4" required></textarea></p>';
			echo '<p>The termination date is automatically recorded upon successful confirmation.</p>';
			echo '<p><label><input type="checkbox" name="din_stop_confirm" value="yes" required> Yes, I have confirmed the buyer$#39;s request to stop this package.</label></p>';
			echo '<p><button type="submit" class="button button-primary">Yes, Cancel Package</button></p></form>';
		}
		echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '"><div class="button">Back to Order</div></a></div>';
	}

	public static function handle_stop() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'POST required.', '', array( 'response' => 405 ) );
		}
		$package = self::editable_package( $_POST['package_id'] ?? '' );
		$nonce = $_POST['din_stop_nonce'] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'din_stop_package_' . $package['id'] ) || 'yes' !== ( $_POST['din_stop_confirm'] ?? '' ) ) {
			wp_die( 'Invalid confirmation. Reopen the Stop Plan page.', '', array( 'response' => 403 ) );
		}
		$reason = isset( $_POST['din_stop_reason'] ) && is_string( $_POST['din_stop_reason'] ) ? wp_unslash( $_POST['din_stop_reason'] ) : '';
		$result = DIN_Packages::stop( $package['id'], get_current_user_id(), $reason );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400, 'back_link' => true ) );
		}
		set_transient( 'din_packages_notice_' . get_current_user_id(), array( 'order_id' => $package['order_id'], 'messages' => array( 'Package #' . $package['id'] . ' stopped. Order and payment records are unchanged.' ) ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( wc_get_order( $package['order_id'] )->get_edit_order_url() );
		exit;
	}

	public static function product_fields() {
		global $product_object;
		if ( ! $product_object instanceof WC_Product || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$config = DIN_Packages::config( $product_object );
		echo '<div class="options_group">';
		woocommerce_wp_select(
			array(
				'id' => '_din_package_period',
				'label' => __( 'Package validity period', 'din-package-lifecycle' ),
				'value' => $config['period'] ?: 'none',
				'options' => array( 'none' => __( 'Not a package', 'din-package-lifecycle' ), 'annual' => 'Annual', 'lifetime' => 'Lifetime' ),
				'description' => __( 'Copied to the new purchase; this change does not affect the already purchased package. Variants inherit from the parent if they do not have their own configuration.', 'din-package-lifecycle' ),
				'desc_tip' => true,
			)
		);
		foreach ( array( 'annual' => 'ID product Annual', 'lifetime' => 'ID product Lifetime' ) as $period => $label ) {
			woocommerce_wp_text_input(
				array(
					'id' => '_din_package_' . $period . '_product_id',
					'label' => $label,
					'value' => $config[ $period . '_product_id' ] ?: '',
					'type' => 'number',
					'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
					'description' => __( 'Simple product or variation ID; price follows the catalog. "Out of stock" status: this product is in use for the current period, so it is unavailable for other periods. A two-year term costs twice the annual price.', 'din-package-lifecycle' ),
					'desc_tip' => true,
				)
			);
		}
		echo '</div>';
	}

	public static function save_product( $product ) {
		// WooCommerce validates its product-save nonce before this native hook.
		if ( ! $product instanceof WC_Product || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return;
		}
		$period = $_POST['_din_package_period'] ?? null;
		if ( ! is_string( $period ) || ! in_array( $period, array( 'none', 'annual', 'lifetime' ), true ) ) {
			return;
		}
		$product->update_meta_data( '_din_package_period', $period );
		foreach ( array( 'annual', 'lifetime' ) as $target_period ) {
			$key = '_din_package_' . $target_period . '_product_id';
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			$raw = is_string( $_POST[ $key ] ) ? trim( wp_unslash( $_POST[ $key ] ) ) : null;
			if ( '' === $raw || '0' === $raw ) {
				$product->update_meta_data( $key, 0 );
				continue;
			}
			$id = is_string( $raw ) && ctype_digit( $raw ) ? absint( $raw ) : 0;
			$target = $id === $product->get_id() ? $product : wc_get_product( $id );
			if ( ! $id || ! $target || ! $target->is_type( array( 'simple', 'variation' ) ) ) {
				WC_Admin_Meta_Boxes::add_error( __( 'Bundle pair not saved: enter a valid simple product ID or variation ID.', 'din-package-lifecycle' ) );
				continue;
			}
			$product->update_meta_data( $key, $id );
		}
	}

	public static function register_meta_boxes() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box( 'din-packages', __( 'Package Validity Period', 'din-package-lifecycle' ), array( __CLASS__, 'render_meta_box' ), $screen, 'normal', 'default' );
		}
	}

	public static function render_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
		if ( ! $order || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			return;
		}
		wp_nonce_field( 'din_packages_approve_' . $order->get_id(), 'din_packages_nonce' );
		$notice = $order->get_meta( '_din_packages_admin_notice', true );
		if ( is_string( $notice ) && '' !== $notice ) {
			echo '<div class="notice notice-warning inline din-package-notice"><h3>' . esc_html__( 'Attention required', 'din-package-lifecycle' ) . '</h3><p>' . esc_html( $notice ) . '</p></div>';
		}
		if ( ! $order->get_meta( '_din_packages_version', true ) ) {
			echo '<div class="notice notice-warning inline din-package-notice"><h3>' . esc_html__( 'Manual review required', 'din-package-lifecycle' ) . '</h3><p>' . esc_html__( 'Old orders are not automatically activated. Migration requires a separate review of dates and supporting evidence.', 'din-package-lifecycle' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-info inline din-package-notice"><h3>' . esc_html__( 'Initial activation', 'din-package-lifecycle' ) . '</h3><ol>';
			echo '<li>' . esc_html__( 'Enter Publication Date in Guest Post Result.', 'din-package-lifecycle' ) . '</li>';
			echo '<li>' . esc_html__( 'Upload valid proof via DIN Order Attach.', 'din-package-lifecycle' ) . '</li>';
			echo '<li>' . esc_html__( 'Select "Completed," then save the order using Update.', 'din-package-lifecycle' ) . '</li></ol>';
			echo '<p>' . esc_html__( 'The service starts at midnight on Publication Date in the site timezone. Missing, invalid or future dates prevent activation. Re-saving an active package does not change its dates.', 'din-package-lifecycle' ) . '</p></div>';
		}
		$has_purchase = false;
		$action_labels = array(
			'lifetime' => __( 'Upgrade to Lifetime', 'din-package-lifecycle' ),
			'renew_1' => __( 'Renew Annual +1 year', 'din-package-lifecycle' ),
			'renew_2' => __( 'Renew Annual +2 years', 'din-package-lifecycle' ),
		);
		foreach ( $order->get_items() as $item ) {
			$purchase = $item->get_meta( '_din_package_purchase', true );
			if ( ! is_array( $purchase ) || empty( $purchase['package_id'] ) || ! is_scalar( $purchase['package_id'] ) || ! is_string( $purchase['action'] ?? null ) ) {
				continue;
			}
			if ( ! $has_purchase ) {
				echo '<section class="din-package-request" aria-labelledby="din-package-request-title"><h3 id="din-package-request-title">' . esc_html__( 'Renewal / upgrade request', 'din-package-lifecycle' ) . '</h3>';
				$has_purchase = true;
			}
			$request = $purchase['admin_request'] ?? null;
			$label = 'renew_custom' === $purchase['action'] ? self::custom_renewal_label( is_array( $request ) ? ( $request['years'] ?? null ) : null ) : ( $action_labels[ $purchase['action'] ] ?? $purchase['action'] );
			echo '<div class="din-package-request-summary"><strong>' . esc_html__( 'Package #', 'din-package-lifecycle' ) . esc_html( $purchase['package_id'] ) . '</strong><span class="din-package-request-badge">' . esc_html( $label ) . '</span></div>';
			if ( is_array( $request ) && is_scalar( $request['expires_at'] ?? null ) && ctype_digit( (string) $request['expires_at'] ) && is_string( $request['reason'] ?? null ) ) {
				echo '<p><strong>' . esc_html__( 'Requested expiry:', 'din-package-lifecycle' ) . '</strong> ' . esc_html( $request['expires_at'] ? self::date( $request['expires_at'] ) : __( 'No expiration date', 'din-package-lifecycle' ) ) . '<br><strong>' . esc_html__( 'Reason (visible to the buyer):', 'din-package-lifecycle' ) . '</strong> ' . nl2br( esc_html( $request['reason'] ) ) . '</p>';
			}
		}
		if ( $has_purchase ) {
			echo '<div class="din-package-confirmation"><label for="din-packages-payment-confirmed"><input type="checkbox" id="din-packages-payment-confirmed" name="din_packages_payment_confirmed" value="1" aria-describedby="din-packages-payment-help"><span>' . esc_html__( 'I have verified the renewal/upgrade payment.', 'din-package-lifecycle' ) . '</span></label><p id="din-packages-payment-help">' . esc_html__( 'Select this only after payment is confirmed, then save as "Completed." Proof of the original package may be used. This checkbox is not saved for future approvals.', 'din-package-lifecycle' ) . '</p></div></section>';
		}
		$packages = DIN_Packages::for_order( $order->get_id() );
		if ( ! $packages ) {
			echo '<div class="din-package-empty"><p>' . esc_html__( 'There are no active packages for this order yet. Eligible packages will be recorded following admin approval.', 'din-package-lifecycle' ) . '</p></div>';
			return;
		}
		$labels = array(
			'pending' => 'Awaiting Activation',
			'active' => 'Active',
			'expiring' => 'Expiring Soon',
			'expired' => 'Expired',
			'lifetime' => 'Lifetime',
			'review' => 'Pending Review',
			'stopped' => 'Stopped',
		);
		echo '<div class="din-package-table-wrap" role="region" aria-label="' . esc_attr( __( 'Package summary', 'din-package-lifecycle' ) ) . '" tabindex="0"><table class="widefat striped"><thead><tr><th>Package / Heading Post</th><th>Status</th><th>Service Start</th><th>Expires / Stopped</th><th>Action</th></tr></thead><tbody>';
		foreach ( $packages as $package ) {
			$status = DIN_Packages::status( $package );
			echo '<tr><td><strong>' . esc_html( $package['product_name'] ?? '' ) . '</strong><br>' . esc_html( $package['id'] ?? '' ) . '<br>' . esc_html( $package['heading'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $labels[ $status ] ?? $status );
			foreach ( (array) ( $package['review'] ?? array() ) as $reason ) {
				if ( is_string( $reason ) && '' !== $reason ) {
					echo '<br><span>' . esc_html( $reason ) . '</span>';
				}
			}
			if ( 'stopped' === $status ) {
				echo '<br>' . nl2br( esc_html( $package['stop_reason'] ?? '' ) ) . '<br>Admin #' . esc_html( $package['stopped_by'] ?? '' );
			}
			echo '</td><td>' . esc_html( self::date( $package['started_at'] ?? 0 ) ) . '</td><td>';
			echo esc_html( 'stopped' === $status ? self::date( $package['stopped_at'] ) : ( 'lifetime' === ( $package['period'] ?? '' ) ? 'Lifetime' : self::date( $package['expires_at'] ?? 0 ) ) );
			echo '</td><td>';
			if ( 'stopped' !== $status && ! empty( $package['started_at'] ) ) {
				$url = add_query_arg( 'package_id', $package['id'], admin_url( 'admin.php?page=din-stop-package' ) );
				echo '<a href="' . esc_url( $url ) . '"><div class="button">Cancel Package</div></a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		if ( $order->has_status( 'completed' ) ) {
			foreach ( $packages as $package ) {
				if ( 'annual' === ( $package['period'] ?? '' ) && ! empty( $package['started_at'] ) && ( $package['expires_at'] ?? 0 ) > 0 && ( $package['revision'] ?? 0 ) > 0 && ctype_digit( (string) $package['id'] ) && (int) $package['id'] > 0 && in_array( DIN_Packages::status( $package ), array( 'active', 'expiring', 'expired' ), true ) ) {
					self::render_expiry_editor( $package );
				}
			}
		}
	}

	private static function render_expiry_editor( $package ) {
		$id = 'din-package-expiry-' . (int) $package['id'];
		$name = 'din_packages_expiry[' . (int) $package['id'] . ']';
		$base = max( time(), (int) $package['expires_at'] );
		$base_date = wp_date( 'Y-m-d', $base );
		$expiry = ( new DateTimeImmutable( '@' . (int) $package['expires_at'] ) )->setTimezone( wp_timezone() );
		$minimum = ( new DateTimeImmutable( '@' . $base ) )->setTimezone( wp_timezone() )->setTime( (int) $expiry->format( 'H' ), (int) $expiry->format( 'i' ), (int) $expiry->format( 's' ) );
		if ( $minimum->getTimestamp() <= $base ) {
			$minimum = $minimum->modify( '+1 day' );
		}
		echo '<fieldset class="din-package-expiry" data-expiry-base="' . esc_attr( $base_date ) . '" data-expiry-1="' . esc_attr( wp_date( 'Y-m-d', DIN_Packages::add_years( $base, 1 ) ) ) . '" data-expiry-2="' . esc_attr( wp_date( 'Y-m-d', DIN_Packages::add_years( $base, 2 ) ) ) . '"><legend>' . esc_html( sprintf( __( 'Request renewal / upgrade — Package #%d', 'din-package-lifecycle' ), $package['id'] ) ) . '</legend>';
		echo '<p class="din-package-expiry-current">' . esc_html( sprintf( __( 'Current expiry: %1$s · Site timezone: %2$s', 'din-package-lifecycle' ), wp_date( 'Y-m-d H:i:s', (int) $package['expires_at'] ), wp_timezone()->getName() ) ) . '</p>';
		$request_id = $package['payment_request']['order_id'] ?? 0;
		$request = is_scalar( $request_id ) && ctype_digit( (string) $request_id ) && (int) $request_id > 0 ? wc_get_order( (int) $request_id ) : false;
		if ( $request instanceof WC_Order && (int) $request->get_customer_id() === (int) $package['customer_id'] && current_user_can( 'edit_shop_order', $request->get_id() ) ) {
			echo '<p><a href="' . esc_url( $request->get_edit_order_url() ) . '">' . esc_html( sprintf( __( 'Payment order #%d', 'din-package-lifecycle' ), $request->get_id() ) ) . '</a> · <a href="' . esc_url( $request->get_checkout_payment_url() ) . '">' . esc_html__( 'Buyer payment link', 'din-package-lifecycle' ) . '</a></p>';
		}
		echo '<input type="hidden" name="' . esc_attr( $name . '[revision]' ) . '" value="' . esc_attr( $package['revision'] ) . '">';
		echo '<div class="din-package-expiry-fields"><div><label for="' . esc_attr( $id . '-extension' ) . '">' . esc_html__( 'Duration type', 'din-package-lifecycle' ) . '</label>';
		echo '<select id="' . esc_attr( $id . '-extension' ) . '" name="' . esc_attr( $name . '[extension]' ) . '" aria-describedby="' . esc_attr( $id . '-help' ) . '"><option value="none">' . esc_html__( 'Select duration', 'din-package-lifecycle' ) . '</option><option value="1">' . esc_html__( '1 Year', 'din-package-lifecycle' ) . '</option><option value="2">' . esc_html__( '2 Years', 'din-package-lifecycle' ) . '</option><option value="lifetime">' . esc_html__( 'Lifetime', 'din-package-lifecycle' ) . '</option><option value="custom">' . esc_html__( 'Custom', 'din-package-lifecycle' ) . '</option></select></div>';
		echo '<div class="din-package-expiry-years" hidden><label for="' . esc_attr( $id . '-years' ) . '">' . esc_html__( 'Custom duration (whole years)', 'din-package-lifecycle' ) . '</label><input type="number" id="' . esc_attr( $id . '-years' ) . '" name="' . esc_attr( $name . '[years]' ) . '" min="1" step="1" max="' . esc_attr( 9999 - (int) substr( $base_date, 0, 4 ) ) . '" value="" disabled aria-describedby="' . esc_attr( $id . '-help' ) . '"></div>';
		echo '<div class="din-package-expiry-date" hidden><label for="' . esc_attr( $id . '-date' ) . '">' . esc_html__( 'New expiry date', 'din-package-lifecycle' ) . '</label>';
		echo '<input type="date" id="' . esc_attr( $id . '-date' ) . '" name="' . esc_attr( $name . '[date]' ) . '" value="" min="' . esc_attr( $minimum->format( 'Y-m-d' ) ) . '" disabled aria-describedby="' . esc_attr( $id . '-help' ) . '"></div></div>';
		echo '<p class="din-package-expiry-lifetime" role="status" hidden>' . esc_html__( 'No expiration date', 'din-package-lifecycle' ) . '</p>';
		echo '<p id="' . esc_attr( $id . '-help' ) . '" class="din-package-expiry-help">' . esc_html__( 'Choose a duration first. Custom accepts whole years only; its price is the Annual catalog price multiplied by the number of years. Annual dates start from the current expiry, or now if expired, and can be edited. The requested date must extend the service and keeps the current expiry time.', 'din-package-lifecycle' ) . '</p>';
		echo '<label for="' . esc_attr( $id . '-reason' ) . '">' . esc_html__( 'Reason (required for payment request, visible to the buyer)', 'din-package-lifecycle' ) . '</label>';
		echo '<textarea id="' . esc_attr( $id . '-reason' ) . '" name="' . esc_attr( $name . '[reason]' ) . '" rows="2" maxlength="1000"></textarea>';
		echo '<p class="din-package-expiry-warning">' . esc_html__( 'Update creates a payment order at the configured catalog price. The current service stays unchanged until payment and admin approval are complete.', 'din-package-lifecycle' ) . '</p>';
		echo '<noscript><p>' . esc_html__( 'Enable JavaScript to choose a duration and create a payment order. No service change is applied without payment.', 'din-package-lifecycle' ) . '</p></noscript>';
		echo '<div class="din-package-confirmation"><label for="' . esc_attr( $id . '-apply' ) . '"><input type="checkbox" id="' . esc_attr( $id . '-apply' ) . '" name="' . esc_attr( $name . '[apply]' ) . '" value="1" disabled aria-describedby="' . esc_attr( $id . '-apply-help' ) . '"><span>' . esc_html__( 'Create payment order', 'din-package-lifecycle' ) . '</span></label>';
		echo '<p id="' . esc_attr( $id . '-apply-help' ) . '" class="din-package-expiry-apply-help">' . esc_html__( 'Select this checkbox, then click Update to create the payment request. Leave it clear for an ordinary order update.', 'din-package-lifecycle' ) . '</p></div></fieldset>';
	}

	private static function save_expiry_changes( $order ) {
		$changes = $_POST['din_packages_expiry'] ?? array();
		$invalid = __( 'Payment request not created: invalid request form. Reload the order and try again.', 'din-package-lifecycle' );
		if ( ! is_array( $changes ) ) {
			return array( $invalid );
		}
		$messages = array();
		foreach ( $changes as $id => $change ) {
			if ( ! is_array( $change ) ) {
				$messages[] = $invalid;
				continue;
			}
			if ( '1' !== ( $change['apply'] ?? '' ) ) {
				continue;
			}
			if ( ! ctype_digit( (string) $id ) || (int) $id < 1 || (string) (int) $id !== ltrim( (string) $id, '0' ) ) {
				$messages[] = $invalid;
				continue;
			}
			$result = DIN_Packages_Requests::create( (int) $id, $order->get_id(), get_current_user_id(), wp_unslash( $change ) );
			$messages[] = is_wp_error( $result ) ? $result->get_error_message() : sprintf( __( 'Payment order #%1$d created for package #%2$d. The service is unchanged, awaiting payment and admin approval.', 'din-package-lifecycle' ), $result->get_id(), $id );
		}
		return $messages;
	}

	public static function approve_order( $order_id, $post_or_order = null ) {
		$order_id = is_scalar( $order_id ) ? absint( $order_id ) : 0;
		$nonce = $_POST['din_packages_nonce'] ?? '';
		if ( ! $order_id || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order_id ) || ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'din_packages_approve_' . $order_id ) ) {
			return;
		}
		// Do not reuse the HPOS hook object: status and attachments were saved at 40/50.
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		try {
			$order->get_data_store()->read( $order );
			$order->read_meta_data( true );
		} catch (Exception $error) {
			WC_Admin_Meta_Boxes::add_error( __( 'Package not approved: order cannot be re-read. Reload the page and try again.', 'din-package-lifecycle' ) );
			return;
		}
		$payment_confirmed = '1' === ( $_POST['din_packages_payment_confirmed'] ?? '' );
		$messages = DIN_Packages::approve_order( $order, get_current_user_id(), $payment_confirmed );
		$messages = array_merge( $messages, self::save_expiry_changes( $order ) );
		if ( $messages ) {
			set_transient( 'din_packages_notice_' . get_current_user_id(), array( 'order_id' => $order_id, 'messages' => $messages ), 5 * MINUTE_IN_SECONDS );
		}
	}

	public static function notices() {
		$notice = get_transient( 'din_packages_notice_' . get_current_user_id() );
		if ( ! is_array( $notice ) || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $notice['order_id'] ) ) {
			return;
		}
		delete_transient( 'din_packages_notice_' . get_current_user_id() );
		foreach ( array_unique( (array) $notice['messages'] ) as $message ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( 'Package — Order #' . $notice['order_id'] . ': ' . $message ) . '</p></div>';
		}
	}

	private static function date( $timestamp ) {
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp ) : '—';
	}
}
