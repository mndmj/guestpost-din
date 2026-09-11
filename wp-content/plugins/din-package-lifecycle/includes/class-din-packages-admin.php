<?php

defined( 'ABSPATH' ) || exit;

final class DIN_Packages_Admin {
	public static function boot() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'approve_order' ), 80, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_stop_page' ) );
		add_action( 'admin_post_din_stop_package', array( __CLASS__, 'handle_stop' ) );
	}

	public static function register_stop_page() {
		$hook = add_submenu_page( '', 'Hentikan Paket', 'Hentikan Paket', 'manage_woocommerce', 'din-stop-package', array( __CLASS__, 'stop_page' ) );
		add_action( 'load-' . $hook, static function () { $GLOBALS['title'] = 'Hentikan Paket'; } );
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
		echo '<div class="wrap"><h1>Hentikan Paket #' . esc_html( $package['id'] ) . '</h1>';
		echo '<p><strong>' . esc_html( $package['product_name'] ) . '</strong><br>Heading Post: ' . esc_html( $package['heading'] ) . '</p>';
		if ( ! empty( $package['stopped_at'] ) ) {
			echo '<p>Stopped: ' . esc_html( self::date( $package['stopped_at'] ) ) . '<br>' . nl2br( esc_html( $package['stop_reason'] ?? '' ) ) . '</p>';
		} elseif ( empty( $package['started_at'] ) ) {
			echo '<p>Paket belum aktif dan tidak dapat dihentikan.</p>';
		} else {
			echo '<p>Status paket menjadi <strong>Stopped</strong> dan tidak dapat diaktifkan kembali melalui halaman ini. Order, pembayaran, bukti, dan riwayat tetap disimpan. Tidak ada refund otomatis.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="din_stop_package"><input type="hidden" name="package_id" value="' . esc_attr( $package['id'] ) . '">';
			wp_nonce_field( 'din_stop_package_' . $package['id'], 'din_stop_nonce' );
			echo '<p><label for="din-stop-reason"><strong>Alasan penghentian (terlihat oleh buyer)</strong></label><br><textarea id="din-stop-reason" name="din_stop_reason" class="large-text" rows="4" required></textarea></p>';
			echo '<p>Tanggal penghentian dicatat otomatis saat konfirmasi berhasil.</p>';
			echo '<p><label><input type="checkbox" name="din_stop_confirm" value="yes" required> Ya, saya telah mengonfirmasi permintaan buyer untuk menghentikan paket ini.</label></p>';
			echo '<p><button type="submit" class="button button-primary">Ya, Hentikan Paket</button></p></form>';
		}
		echo '<a href="' . esc_url( $order->get_edit_order_url() ) . '"><div class="button">Kembali ke Order</div></a></div>';
	}

	public static function handle_stop() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'POST required.', '', array( 'response' => 405 ) );
		}
		$package = self::editable_package( $_POST['package_id'] ?? '' );
		$nonce = $_POST['din_stop_nonce'] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'din_stop_package_' . $package['id'] ) || 'yes' !== ( $_POST['din_stop_confirm'] ?? '' ) ) {
			wp_die( 'Konfirmasi tidak valid. Buka kembali halaman Hentikan Paket.', '', array( 'response' => 403 ) );
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
		foreach ( array( 'annual' => 'ID produk Annual', 'lifetime' => 'ID produk Lifetime' ) as $period => $label ) {
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
			echo '<p class="notice notice-warning inline">' . esc_html( $notice ) . '</p>';
		}
		if ( ! $order->get_meta( '_din_packages_version', true ) ) {
			echo '<p>' . esc_html__( 'Old orders are not automatically activated. Migration requires a separate review of dates and supporting evidence.', 'din-package-lifecycle' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Initial activation: upload valid proof via DIN Order Attach, select "Completed," then save the order. Without valid proof, the package will remain in "Pending activation" status. Re-saving the order does not reset the start date.', 'din-package-lifecycle' ) . '</p>';
		}
		$has_purchase = false;
		foreach ( $order->get_items() as $item ) {
			$purchase = $item->get_meta( '_din_package_purchase', true );
			if ( ! is_array( $purchase ) || empty( $purchase['package_id'] ) || ! is_scalar( $purchase['package_id'] ) || ! is_string( $purchase['action'] ?? null ) ) {
				continue;
			}
			$has_purchase = true;
			echo '<p><strong>' . esc_html__( 'Renewal/upgrade paket:', 'din-package-lifecycle' ) . '</strong> ' . esc_html( $purchase['package_id'] ) . ' — ' . esc_html( $purchase['action'] ) . '</p>';
		}
		if ( $has_purchase ) {
			echo '<p><label><input type="checkbox" name="din_packages_payment_confirmed" value="1"> ' . esc_html__( 'I have verified the renewal/upgrade payment.', 'din-package-lifecycle' ) . '</label><br><span class="description">' . esc_html__( 'Select this only after payment is confirmed, then save as "Completed." Proof of the original package may be used. This checkbox is not saved for future approvals.', 'din-package-lifecycle' ) . '</span></p>';
		}
		$packages = DIN_Packages::for_order( $order->get_id() );
		if ( ! $packages ) {
			echo '<p>' . esc_html__( 'There are no active packages for this order yet. Eligible packages will be recorded following admin approval.', 'din-package-lifecycle' ) . '</p>';
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
		echo '<table class="widefat striped"><thead><tr><th>Package / Heading Post</th><th>Status</th><th>Initial Activation</th><th>Expires / Stopped</th><th>Action</th></tr></thead><tbody>';
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
				echo '<a href="' . esc_url( $url ) . '"><div class="button">Hentikan Paket</div></a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
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
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( 'Paket — Order #' . $notice['order_id'] . ': ' . $message ) . '</p></div>';
		}
	}

	private static function date( $timestamp ) {
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp ) : '—';
	}
}
