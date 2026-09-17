<?php
defined( 'ABSPATH' ) || exit;

final class DIN_Packages {
	public static function boot() {
		add_filter( 'woocommerce_order_needs_payment', array( __CLASS__, 'order_needs_payment' ), 20, 2 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_status_changed' ), 20, 4 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'order_refunded' ), 20 );
		foreach ( array( 'added_order_meta', 'updated_order_meta', 'deleted_order_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'proofs_changed' ), 20, 3 );
		}
	}

	public static function proofs_changed( $meta_id, $order_id, $key ) {
		if ( '_din_order_attach_files' !== $key ) {
			return;
		}
		// Wait until the whole attachment batch (including a failed-delete rollback) has finished.
		add_action( 'shutdown', static function () use ($order_id) {
			self::audit_proofs( $order_id );
		} );
	}

	public static function audit_proofs( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$order->read_meta_data( true );
		if ( self::valid_proofs( $order ) ) {
			return;
		}
		foreach ( self::for_order( $order_id ) as $p ) {
			if ( $p['started_at'] && ! $p['review'] ) {
				self::mark_review( $p['id'], 'No proof of the original order is available; the admin needs to review it.' );
			}
		}
	}

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'din_packages';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		// One JSON state per unit makes the entitlement, history, and idempotency key one atomic write.
		dbDelta( "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			order_item_id bigint(20) unsigned NOT NULL,
			unit int unsigned NOT NULL DEFAULT 1,
			revision bigint(20) unsigned NOT NULL DEFAULT 1,
			data longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_unit (order_item_id,unit),
			KEY customer_id (customer_id),
			KEY order_id (order_id)
		) " . $wpdb->get_charset_collate() . ';' );
		update_option( 'din_packages_schema', DIN_PACKAGES_VERSION, false );
	}

	private static function decode( $row ) {
		if ( ! $row ) {
			return null;
		}
		$data = json_decode( $row['data'], true );
		if ( ! is_array( $data ) ) {
			return null;
		}
		unset( $row['data'] );
		return array_merge( $data, array_map( 'intval', $row ) );
	}

	public static function get( $id ) {
		global $wpdb;
		return self::decode( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ) );
	}

	public static function for_customer( $customer_id, $page = 1, $limit = 20 ) {
		global $wpdb;
		if ( $customer_id < 1 ) {
			return array();
		}
		$limit = max( 1, min( 100, (int) $limit ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE customer_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', $customer_id, $limit, ( max( 1, (int) $page ) - 1 ) * $limit ), ARRAY_A );
		return array_values( array_filter( array_map( array( __CLASS__, 'decode' ), $rows ) ) );
	}

	public static function for_order( $order_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE order_id = %d ORDER BY id', $order_id ), ARRAY_A );
		return array_values( array_filter( array_map( array( __CLASS__, 'decode' ), $rows ) ) );
	}

	public static function all_after( $after_id, $limit = 100 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id > %d ORDER BY id LIMIT %d', $after_id, max( 1, min( 100, (int) $limit ) ) ), ARRAY_A );
		return array_values( array_filter( array_map( array( __CLASS__, 'decode' ), $rows ) ) );
	}

	public static function mutate( $id, $callback ) {
		global $wpdb;
		for ( $attempt = 0; $attempt < 5; ++$attempt ) {
			$old = self::get( $id );
			if ( ! $old ) {
				return new WP_Error( 'missing_package', 'Package not found.' );
			}
			$new = $callback( $old );
			if ( is_wp_error( $new ) || $new === $old ) {
				return $new;
			}
			$data = $new;
			foreach ( array( 'id', 'customer_id', 'order_id', 'order_item_id', 'unit', 'revision' ) as $key ) {
				unset( $data[ $key ] );
			}
			$written = $wpdb->update( self::table(), array( 'data' => wp_json_encode( $data ), 'revision' => $old['revision'] + 1 ), array( 'id' => $id, 'revision' => $old['revision'] ), array( '%s', '%d' ), array( '%d', '%d' ) );
			if ( false === $written ) {
				return new WP_Error( 'package_storage', 'Package storage failed. Please try again.' );
			}
			if ( 1 === $written ) {
				return array_merge( $old, $data, array( 'revision' => $old['revision'] + 1 ) );
			}
		}
		return new WP_Error( 'package_busy', 'The package is being updated. Please save the order again.' );
	}

	public static function config( $product ) {
		$empty = array( 'period' => '', 'annual_product_id' => 0, 'lifetime_product_id' => 0 );
		if ( ! $product ) {
			return $empty;
		}
		$source = $product;
		if ( $product->is_type( 'variation' ) && ! $product->get_meta( '_din_package_period' ) ) {
			$source = wc_get_product( $product->get_parent_id() ) ?: $product;
		}
		$period = $source->get_meta( '_din_package_period' );
		if ( 'none' === $period ) {
			return $empty;
		}
		if ( ! $period ) {
			$billing = strtolower( trim( $product->get_attribute( 'billing' ) ?: $product->get_attribute( 'pa_billing' ) ) );
			$period = in_array( $billing, array( 'annual', 'year', 'yearly' ), true ) ? 'annual' : ( 'lifetime' === $billing ? 'lifetime' : '' );
		}
		if ( ! in_array( $period, array( 'annual', 'lifetime' ), true ) ) {
			return $empty;
		}
		return array(
			'period' => $period,
			'annual_product_id' => absint( $source->get_meta( '_din_package_annual_product_id' ) ) ?: ( 'annual' === $period ? $product->get_id() : 0 ),
			'lifetime_product_id' => absint( $source->get_meta( '_din_package_lifetime_product_id' ) ) ?: ( 'lifetime' === $period ? $product->get_id() : 0 ),
		);
	}

	public static function capture_order( $order ) {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order );
		}
		// Existing orders are never assigned today's activation date at installation.
		if ( ! $order || ! $order->get_meta( '_din_packages_version' ) || ! $order->get_customer_id() ) {
			return;
		}
		global $wpdb;
		foreach ( $order->get_items() as $item ) {
			if ( $item->get_meta( '_din_package_purchase' ) ) {
				continue;
			}
			$config = $item->get_meta( '_din_package_config' );
			if ( ! is_array( $config ) || ! in_array( $config['period'] ?? '', array( 'annual', 'lifetime' ), true ) ) {
				continue;
			}
			for ( $unit = 1; $unit <= $item->get_quantity(); ++$unit ) {
				$data = array_merge( $config, array(
					'product_id' => $item->get_variation_id() ?: $item->get_product_id(),
					'product_name' => $item->get_name(), 'heading' => $order->get_customer_note(),
					'currency' => $order->get_currency(), 'started_at' => 0, 'expires_at' => 0,
					'proof_ids' => array(), 'activated_by' => 0, 'history' => array(),
					'generation' => 0, 'review' => '', 'emails' => array(),
				) );
				$created = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::table() . ' (customer_id,order_id,order_item_id,unit,data) VALUES (%d,%d,%d,%d,%s)', $order->get_customer_id(), $order->get_id(), $item->get_id(), $unit, wp_json_encode( $data ) ) );
				if ( false === $created ) {
					wc_get_logger()->error( 'Package capture failed for order ' . $order->get_id(), array( 'source' => 'din-packages' ) );
				}
			}
		}
	}

	public static function status( $p, $now = null ) {
		$now = $now ?? time();
		if ( ! empty( $p['stopped_at'] ) ) {
			return 'stopped';
		}
		if ( ! empty( $p['review'] ) ) {
			return 'review';
		}
		if ( empty( $p['started_at'] ) ) {
			return 'pending';
		}
		if ( 'lifetime' === $p['period'] ) {
			return 'lifetime';
		}
		if ( $p['expires_at'] <= $now ) {
			return 'expired';
		}
		return $p['expires_at'] - $now <= 14 * DAY_IN_SECONDS ? 'expiring' : 'active';
	}

	public static function stop( $id, $admin_id, $reason ) {
		if ( ! user_can( $admin_id, 'manage_woocommerce' ) ) {
			return new WP_Error( 'package_access', 'Package management access denied.' );
		}
		$reason = is_string( $reason ) ? trim( sanitize_textarea_field( $reason ) ) : '';
		if ( '' === $reason ) {
			return new WP_Error( 'package_stop_reason', 'Enter the reason for stopping this package.' );
		}
		$result = self::mutate( $id, static function ( $package ) use ( $admin_id, $reason ) {
			$order = wc_get_order( $package['order_id'] );
			if ( ! $order || ! user_can( $admin_id, 'edit_shop_order', $order->get_id() ) ) {
				return new WP_Error( 'package_access', 'Source order access denied.' );
			}
			if ( ! empty( $package['stopped_at'] ) ) {
				return $package;
			}
			if ( empty( $package['started_at'] ) ) {
				return new WP_Error( 'package_stop_state', 'Only an activated package can be stopped.' );
			}
			if ( DIN_Packages_Mail::has_sending( $package ) ) {
				return new WP_Error( 'package_busy', 'A notification is being sent. Try stopping this package again in a moment.' );
			}
			$package['stopped_at'] = time();
			$package['stopped_by'] = (int) $admin_id;
			$package['stop_reason'] = $reason;
			++$package['generation'];
			$package['history'][] = array( 'action' => 'stopped', 'at' => $package['stopped_at'], 'by' => (int) $admin_id, 'reason' => $reason );
			return $package;
		} );
		if ( ! is_wp_error( $result ) ) {
			DIN_Packages_Mail::schedule( $result );
		}
		return $result;
	}

	public static function adjust_expiry( $id, $order_id, $admin_id, $change ) {
		if ( ! user_can( $admin_id, 'manage_woocommerce' ) || ! user_can( $admin_id, 'edit_shop_order', $order_id ) ) {
			return new WP_Error( 'package_access', 'Package management or source order access denied.' );
		}
		$revision = is_array( $change ) ? ( $change['revision'] ?? null ) : null;
		if ( ! ( is_int( $revision ) || is_string( $revision ) ) || ! preg_match( '/^[0-9]+$/D', (string) $revision ) || (int) $revision < 1 || (string) (int) $revision !== ltrim( (string) $revision, '0' ) ) {
			return new WP_Error( 'package_revision', 'Reload the order before editing the package expiry.' );
		}
		$date = $change['date'] ?? null;
		$day = is_string( $date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() ) : false;
		if ( ! $day || $day->format( 'Y-m-d' ) !== $date || ! in_array( $change['extension'] ?? null, array( 'none', '1' ), true ) ) {
			return new WP_Error( 'package_expiry_input', 'Enter a valid expiry date and duration selection.' );
		}
		$reason = is_string( $change['reason'] ?? null ) ? trim( sanitize_textarea_field( $change['reason'] ) ) : '';
		if ( 1 !== preg_match( '/^.{1,1000}$/us', $reason ) ) {
			return new WP_Error( 'package_expiry_reason', 'Enter a reason for this adjustment (maximum 1000 characters).' );
		}
		$result = self::mutate( $id, static function ( $package ) use ( $order_id, $admin_id, $revision, $change, $date, $day, $reason ) {
			if ( (int) $package['order_id'] !== (int) $order_id ) {
				return new WP_Error( 'package_source', 'This package does not belong to the edited order.' );
			}
			if ( (int) $package['revision'] !== (int) $revision ) {
				return new WP_Error( 'package_revision', 'The package changed after this form was opened. Reload the order and try again.' );
			}
			$order = wc_get_order( $package['order_id'] );
			if ( ! $order || ! user_can( $admin_id, 'edit_shop_order', $order->get_id() ) ) {
				return new WP_Error( 'package_access', 'Source order access denied.' );
			}
			try {
				$order->get_data_store()->read( $order );
				$order->read_meta_data( true );
			} catch ( Exception $error ) {
				return new WP_Error( 'package_source', 'The source order could not be re-read. Reload the page and try again.' );
			}
			$item = $order->get_item( $package['order_item_id'] );
			if ( ! $order->has_status( 'completed' ) || (int) $order->get_customer_id() !== (int) $package['customer_id'] || ! $item || (int) $item->get_order_id() !== (int) $order_id || $package['unit'] < 1 || $package['unit'] > $item->get_quantity() || (int) $package['product_id'] !== (int) ( $item->get_variation_id() ?: $item->get_product_id() ) || $package['currency'] !== $order->get_currency() || $order->get_total_refunded() > 0 || ! self::valid_proofs( $order ) ) {
				return new WP_Error( 'package_source', 'Review the source order status, owner, items, currency, refunds and proof before editing expiry.' );
			}
			if ( 'annual' !== $package['period'] || empty( $package['started_at'] ) || $package['expires_at'] <= 0 || ! empty( $package['stopped_at'] ) || ! empty( $package['review'] ) ) {
				return new WP_Error( 'package_expiry_state', 'Only an activated Annual package without a stop or pending review can have its expiry adjusted.' );
			}
			$old_date = ( new DateTimeImmutable( '@' . (int) $package['expires_at'] ) )->setTimezone( wp_timezone() );
			$now = time();
			if ( '1' === $change['extension'] ) {
				if ( $date !== $old_date->format( 'Y-m-d' ) ) {
					return new WP_Error( 'package_expiry_conflict', 'Choose either a manual date change or an extra year, not both at once.' );
				}
				$expires_at = self::add_years( max( $now, $package['expires_at'] ), 1 );
			} else {
				$expires_at = $date === $old_date->format( 'Y-m-d' ) ? $package['expires_at'] : $day->setTime( (int) $old_date->format( 'H' ), (int) $old_date->format( 'i' ), (int) $old_date->format( 's' ) )->getTimestamp();
			}
			if ( $expires_at <= $package['started_at'] ) {
				return new WP_Error( 'package_expiry_date', 'The expiry must be later than the service start.' );
			}
			if ( $expires_at === $package['expires_at'] ) {
				return $package;
			}
			if ( DIN_Packages_Mail::has_sending( $package ) ) {
				return new WP_Error( 'package_busy', 'A notification is being sent. Try changing this expiry again in a moment.' );
			}
			$old_expiry = $package['expires_at'];
			$package['expires_at'] = $expires_at;
			++$package['generation'];
			$package['history'][] = array( 'action' => 'expiry_adjusted', 'at' => $now, 'by' => (int) $admin_id, 'old_expires_at' => $old_expiry, 'expires_at' => $expires_at, 'reason' => $reason, 'extension' => $change['extension'] );
			return $package;
		} );
		if ( ! is_wp_error( $result ) && $result['revision'] !== (int) $revision ) {
			DIN_Packages_Mail::schedule( $result );
		}
		return $result;
	}

	public static function add_years( $timestamp, $years ) {
		$date = ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() );
		$year = (int) $date->format( 'Y' ) + (int) $years;
		$month = (int) $date->format( 'n' );
		$last_day = (int) $date->setDate( $year, $month, 1 )->format( 't' );
		return $date->setDate( $year, $month, min( (int) $date->format( 'j' ), $last_day ) )->getTimestamp();
	}

	public static function valid_proofs( $order ) {
		$ids = array();
		if ( ! $order || ! class_exists( 'DIN_Order_Attach_Storage' ) ) {
			return $ids;
		}
		$storage = new DIN_Order_Attach_Storage();
		foreach ( $storage->get_order_files( $order ) as $file ) {
			$path = $storage->resolve_path( $file['path'] ?? '' );
			if ( ! empty( $file['id'] ) && ! is_wp_error( $path ) && ! is_wp_error( $storage->inspect_stored_file( $file, $path ) ) ) {
				$ids[] = (string) $file['id'];
			}
		}
		return $ids;
	}

	public static function custom_years( $value ) {
		return ( is_int( $value ) || is_string( $value ) ) && preg_match( '/^[1-9][0-9]{0,3}$/D', (string) $value ) ? (int) $value : 0;
	}

	public static function purchase_option( $package_id, $action, $customer_id, $check_window = true, $custom_years = 0 ) {
		$p = self::get( $package_id );
		if ( ! $p || ! $customer_id || $p['customer_id'] !== (int) $customer_id ) {
			return new WP_Error( 'package_owner', 'The package is not available for this account.' );
		}
		$source = wc_get_order( $p['order_id'] );
		if ( ! $source || (int) $source->get_customer_id() !== (int) $customer_id || ! $source->has_status( 'completed' ) ) {
			return new WP_Error( 'package_source', 'Original orders require admin checks.' );
		}
		if ( ! empty( $p['stopped_at'] ) || empty( $p['started_at'] ) || 'annual' !== $p['period'] || ! empty( $p['review'] ) ) {
			return new WP_Error( 'package_state', 'This package cannot yet be renewed or upgraded.' );
		}
		$choices = array( 'renew_1' => array( 1, 1, 'Extend for 1 Year' ), 'renew_2' => array( 2, 2, 'Extend for 2 Years' ), 'lifetime' => array( 0, 1, 'Upgrade Lifetime' ) );
		$years = self::custom_years( $custom_years );
		$base_year = (int) ( new DateTimeImmutable( '@' . max( time(), (int) $p['expires_at'] ) ) )->setTimezone( wp_timezone() )->format( 'Y' );
		if ( $years && $years <= 9999 - $base_year ) {
			$choices['renew_custom'] = array( $years, $years, $years . ( 1 === $years ? ' Year' : ' Years' ) );
		}
		if ( ! isset( $choices[ $action ] ) ) {
			return new WP_Error( 'package_action', 'Invalid package selection.' );
		}
		if ( $check_window && 'lifetime' !== $action && 'active' === self::status( $p ) ) {
			return new WP_Error( 'package_window', 'Renewal is available starting 14 days before the active period expires.' );
		}
		if ( $p['currency'] !== get_woocommerce_currency() ) {
			return new WP_Error( 'package_currency', 'The order currency differs from the source currency. Please contact the administrator.' );
		}
		$id = (int) $p[ 'lifetime' === $action ? 'lifetime_product_id' : 'annual_product_id' ];
		$product = $id ? wc_get_product( $id ) : false;
		$config = self::config( $product );
		$expected = 'lifetime' === $action ? 'lifetime' : 'annual';
		if ( ! $product || ! $product->is_type( array( 'simple', 'variation' ) ) || ! $product->is_purchasable() || ! $product->is_in_stock() || $config['period'] !== $expected ) {
			return new WP_Error( 'package_product', 'Renewal or upgrade products are not yet available. Please contact the administrator.' );
		}
		return array( 'product_id' => $id, 'years' => $choices[ $action ][0], 'multiplier' => $choices[ $action ][1], 'label' => $choices[ $action ][2], 'package' => $p );
	}

	public static function activate( $p, $proof_ids, $admin_id, $now, $published_at = '' ) {
		if ( ! empty( $p['stopped_at'] ) ) {
			return new WP_Error( 'package_stopped', 'This package has been stopped and cannot be reactivated.' );
		}
		if ( ! empty( $p['started_at'] ) ) {
			return $p;
		}
		if ( ! $proof_ids || ! $admin_id || ! empty( $p['review'] ) ) {
			return new WP_Error( 'package_proof', 'Activation awaits admin approval and at least one piece of valid proof.' );
		}
		$publication = is_string( $published_at ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $published_at )
			? DateTimeImmutable::createFromFormat( '!Y-m-d', $published_at, wp_timezone() ) : false;
		if ( ! $publication || $publication->format( 'Y-m-d' ) !== $published_at || $publication->getTimestamp() <= 0 ) {
			return new WP_Error( 'package_publication_date', 'Enter a valid Publication Date in Guest Post Result before activating the package.' );
		}
		if ( $publication->getTimestamp() > $now ) {
			return new WP_Error( 'package_publication_date', 'Publication Date cannot be in the future. Approve the package after publication.' );
		}
		$p['started_at'] = $publication->getTimestamp();
		$p['expires_at'] = 'annual' === $p['period'] ? self::add_years( $p['started_at'], 1 ) : 0;
		$p['activated_by'] = $admin_id;
		$p['proof_ids'] = $proof_ids;
		++$p['generation'];
		$p['history'][] = array( 'action' => 'activate', 'at' => $now, 'by' => $admin_id, 'started_at' => $p['started_at'], 'expires_at' => $p['expires_at'] );
		return $p;
	}

	public static function order_needs_payment( $needs_payment, $order ) {
		if ( ! $needs_payment ) {
			return false;
		}
		$quote = $order->get_meta( '_din_package_admin_request' );
		$items = array_values( $order->get_items() );
		$managed = $quote || $order->get_meta( '_din_package_request_key' ) || 'din-package-renewal' === $order->get_created_via();
		foreach ( $items as $item ) {
			$purchase = $item->get_meta( '_din_package_purchase' );
			$managed = $managed || ( is_array( $purchase ) && ( array_key_exists( 'admin_request', $purchase ) || 'renew_custom' === ( $purchase['action'] ?? '' ) ) );
		}
		if ( ! $managed ) {
			return $needs_payment;
		}
		if ( ! is_array( $quote ) || ! is_array( $quote['admin_request'] ?? null ) || count( $items ) !== 1 || $items[0]->get_meta( '_din_package_purchase' ) !== $quote || 1.0 !== (float) $items[0]->get_quantity() || $order->get_total_refunded() > 0 ) {
			return false;
		}
		$p = self::get( $quote['package_id'] ?? 0 );
		$action = $quote['action'] ?? '';
		if ( ! $p || 'annual' !== $p['period'] || empty( $p['started_at'] ) || ! empty( $p['stopped_at'] ) || ! empty( $p['review'] ) || isset( $p['rejected_items'][ $items[0]->get_id() ] ) || (int) $order->get_customer_id() !== (int) $p['customer_id'] || $order->get_currency() !== $p['currency'] || ! in_array( $action, array( 'renew_1', 'renew_2', 'renew_custom', 'lifetime' ), true ) ) {
			return false;
		}
		$product_id = $p[ 'lifetime' === $action ? 'lifetime_product_id' : 'annual_product_id' ];
		if ( (int) $product_id !== (int) ( $items[0]->get_variation_id() ?: $items[0]->get_product_id() ) ) {
			return false;
		}
		return ! is_wp_error( self::validate_admin_request( $p, $action, $order->get_id(), $quote['admin_request'], time() ) );
	}

	private static function validate_admin_request( $p, $action, $order_id, $request, $now ) {
		$saved = $p['payment_request'] ?? array();
		if ( ! is_array( $request ) || ! is_array( $saved ) || 'ready' !== ( $saved['state'] ?? '' ) || (int) ( $saved['order_id'] ?? 0 ) !== (int) $order_id || ( $saved['action'] ?? '' ) !== $action || ( $request['generation'] ?? null ) !== $p['generation'] ) {
			return new WP_Error( 'package_request', 'The paid request no longer matches this service. Review the payment order before applying it.' );
		}
		$fields = array( 'expires_at', 'reason', 'requested_by', 'generation' );
		if ( 'renew_custom' === $action ) {
			if ( ! is_int( $request['years'] ?? null ) || ! self::custom_years( $request['years'] ) ) {
				return new WP_Error( 'package_request_years', 'The requested duration must contain a positive whole number of years.' );
			}
			$fields[] = 'years';
		}
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $request ) || ! array_key_exists( $field, $saved ) || $request[ $field ] !== $saved[ $field ] ) {
				return new WP_Error( 'package_request', 'The requested duration, expiry or reason changed after the payment order was created.' );
			}
		}
		if ( ! is_int( $request['expires_at'] ) || ! is_string( $request['reason'] ) || '' === trim( $request['reason'] ) || ! is_int( $request['requested_by'] ) || $request['requested_by'] < 1 || ( 'lifetime' === $action ? 0 !== $request['expires_at'] : $request['expires_at'] <= max( $now, $p['expires_at'] ) ) ) {
			return new WP_Error( 'package_request_date', 'The requested expiry must still be in the future and extend the current service. Review this payment order; its date was not recalculated.' );
		}
		$source = wc_get_order( $p['order_id'] );
		if ( ! $source ) {
			return new WP_Error( 'package_source', 'The original order is unavailable.' );
		}
		try {
			$source->get_data_store()->read( $source );
			$source->read_meta_data( true );
		} catch ( Exception $error ) {
			return new WP_Error( 'package_source', 'The original order could not be refreshed. Reload and try again.' );
		}
		$item = $source->get_item( $p['order_item_id'] );
		if ( ! $source->has_status( 'completed' ) || (int) $source->get_customer_id() !== (int) $p['customer_id'] || ! $item || (int) $item->get_order_id() !== (int) $p['order_id'] || $p['unit'] < 1 || $p['unit'] > $item->get_quantity() || (int) $p['product_id'] !== (int) ( $item->get_variation_id() ?: $item->get_product_id() ) || $p['currency'] !== $source->get_currency() || $source->get_total_refunded() > 0 || ! self::valid_proofs( $source ) ) {
			return new WP_Error( 'package_source', 'Review the original order owner, items, currency, refunds and proof before applying the paid change.' );
		}
		return true;
	}

	public static function apply_purchase( $p, $action, $order_id, $item_id, $admin_id, $now, $admin_request = null ) {
		foreach ( $p['history'] as $event ) {
			if ( (int) ( $event['item_id'] ?? 0 ) === (int) $item_id ) {
				return $p;
			}
		}
		if ( isset( $p['rejected_items'][ (int) $item_id ] ) ) {
			return new WP_Error( 'package_cancelled_purchase', 'This purchase has been cancelled or refunded; the package change was not applied.' );
		}
		if ( ! empty( $p['stopped_at'] ) || empty( $p['started_at'] ) || 'annual' !== $p['period'] || ! empty( $p['review'] ) || ! $admin_id || ! in_array( $action, array( 'renew_1', 'renew_2', 'renew_custom', 'lifetime' ), true ) || ( 'renew_custom' === $action && null === $admin_request ) ) {
			return new WP_Error( 'package_transition', 'The package change is no longer valid; Admin review is required.' );
		}
		if ( null !== $admin_request ) {
			$valid = self::validate_admin_request( $p, $action, $order_id, $admin_request, $now );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}
		foreach ( $p['emails'] as $email ) {
			if ( 'sending' === ( $email['state'] ?? '' ) && ( $email['lease_until'] ?? 0 ) > $now ) {
				return new WP_Error( 'package_busy', 'The notification is being sent. Please try saving again in a moment.' );
			}
		}
		$old_expiry = $p['expires_at'];
		if ( 'lifetime' === $action ) {
			$p['period'] = 'lifetime';
			$p['expires_at'] = 0;
		} else {
			$p['expires_at'] = null !== $admin_request ? $admin_request['expires_at'] : self::add_years( max( $now, $old_expiry ), 'renew_2' === $action ? 2 : 1 );
		}
		++$p['generation'];
		$event = array( 'action' => $action, 'order_id' => $order_id, 'item_id' => $item_id, 'at' => $now, 'by' => $admin_id, 'old_expires_at' => $old_expiry, 'expires_at' => $p['expires_at'] );
		if ( null !== $admin_request ) {
			$event['reason'] = $admin_request['reason'];
			$event['requested_by'] = $admin_request['requested_by'];
			if ( 'renew_custom' === $action ) {
				$event['years'] = $admin_request['years'];
			}
		}
		$p['history'][] = $event;
		return $p;
	}

	public static function approve_order( $order, $admin_id, $payment_confirmed = false ) {
		if ( ! $order || ! user_can( $admin_id, 'manage_woocommerce' ) || ! user_can( $admin_id, 'edit_shop_order', $order->get_id() ) ) {
			return array( 'Package management access denied.' );
		}
		if ( ! $order->has_status( 'completed' ) ) {
			return array();
		}
		self::capture_order( $order );
		$messages = array();
		$proofs = self::valid_proofs( $order );
		foreach ( self::for_order( $order->get_id() ) as $p ) {
			$source_item = $order->get_item( $p['order_item_id'] );
			if ( ! $source_item || $p['unit'] > $source_item->get_quantity() || $p['product_id'] !== ( $source_item->get_variation_id() ?: $source_item->get_product_id() ) || $p['currency'] !== $order->get_currency() || $order->get_total_refunded() > 0 ) {
				self::mark_review( $p['id'], 'The item, quantity, currency, or original refund order needs to be reviewed.' );
				$messages[] = 'Order items for package #' . $p['id'] . ' have changed, or a refund is being issued.';
				continue;
			}
			if ( $p['customer_id'] !== (int) $order->get_customer_id() || ( ! empty( $p['started_at'] ) && ! $proofs ) ) {
				self::mark_review( $p['id'], 'The owner has changed, or the original proof of order is unavailable.' );
				$messages[] = 'Package #' . $p['id'] . ': requires owner review/proof.';
				continue;
			}
			$result = self::mutate( $p['id'], static function ( $current ) use ( $proofs, $admin_id, $order ) {
				return self::activate( $current, $proofs, $admin_id, time(), $order->get_meta( '_gpm_published_at' ) );
			} );
			if ( is_wp_error( $result ) ) {
				$messages[] = 'Package #' . $p['id'] . ': ' . $result->get_error_message();
			} else {
				DIN_Packages_Mail::schedule( $result );
			}
		}
		foreach ( $order->get_items() as $item ) {
			$purchase = $item->get_meta( '_din_package_purchase' );
			if ( ! is_array( $purchase ) || empty( $purchase['package_id'] ) ) {
				continue;
			}
			$p = self::get( $purchase['package_id'] );
			$applied = false;
			foreach ( $p['history'] ?? array() as $event ) {
				if ( (int) ( $event['item_id'] ?? 0 ) === $item->get_id() ) {
					$applied = true;
				}
			}
			if ( $applied ) {
				continue;
			}
			$quote = $order->get_meta( '_din_package_admin_request' );
			$admin_request = null;
			if ( $quote || $order->get_meta( '_din_package_request_key' ) || 'din-package-renewal' === $order->get_created_via() || 'renew_custom' === ( $purchase['action'] ?? '' ) || array_key_exists( 'admin_request', $purchase ) || (int) ( $p['payment_request']['order_id'] ?? 0 ) === (int) $order->get_id() ) {
				if ( ! is_array( $quote ) || $quote !== $purchase || ! is_array( $purchase['admin_request'] ?? null ) || count( $order->get_items() ) !== 1 ) {
					$messages[] = 'The admin payment request metadata changed. Review the original request before approval.';
					continue;
				}
				$admin_request = $purchase['admin_request'];
			}
			// WooCommerce sets date_paid even on a manual Completed transition; do not treat that as gateway proof.
			if ( ! $payment_confirmed || ! $order->is_paid() || ! $order->get_date_paid() || $order->get_total_refunded() > 0 ) {
				$messages[] = 'Extension pending verification of the admin payment and non-refundable order.';
				continue;
			}
			$source = $p ? wc_get_order( $p['order_id'] ) : false;
			if ( ! $p || (int) $order->get_customer_id() !== $p['customer_id'] || ! $source || (int) $source->get_customer_id() !== $p['customer_id'] || ! $source->has_status( 'completed' ) || ! self::valid_proofs( $source ) || 1 !== (int) $item->get_quantity() || $order->get_currency() !== $p['currency'] ) {
				$messages[] = 'Extension requires review: owner, proof, quantity, or currency is invalid.';
				continue;
			}
			$expected_id = $p[ 'lifetime' === ( $purchase['action'] ?? '' ) ? 'lifetime_product_id' : 'annual_product_id' ];
			if ( (int) $expected_id !== ( $item->get_variation_id() ?: $item->get_product_id() ) ) {
				$messages[] = 'The renewal order product does not match the original package.';
				continue;
			}
			$result = self::mutate( $p['id'], static function ( $current ) use ( $purchase, $order, $item, $admin_id, $admin_request ) {
				return self::apply_purchase( $current, $purchase['action'] ?? '', $order->get_id(), $item->get_id(), $admin_id, time(), $admin_request );
			} );
			if ( is_wp_error( $result ) ) {
				$messages[] = $result->get_error_message();
			} else {
				DIN_Packages_Mail::schedule( $result );
			}
		}
		$order->update_meta_data( '_din_packages_admin_notice', implode( ' ', array_unique( $messages ) ) );
		$order->save_meta_data();
		return array_unique( $messages );
	}

	public static function mark_review( $id, $reason ) {
		$result = self::mutate( $id, static function ( $p ) use ( $reason ) {
			if ( $p['review'] === $reason ) {
				return $p;
			}
			$p['review'] = $reason;
			++$p['generation'];
			return $p;
		} );
		if ( ! is_wp_error( $result ) ) {
			DIN_Packages_Mail::schedule( $result );
		}
	}

	public static function order_status_changed( $id, $from, $to, $order ) {
		if ( in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			self::review_order( $order, 'Order status changed to ' . $to . '; requires admin review.' );
		}
	}

	public static function order_refunded( $order_id ) {
		self::review_order( wc_get_order( $order_id ), 'The order is eligible for a refund; the package status requires admin review.' );
	}

	private static function review_order( $order, $reason ) {
		if ( ! $order ) {
			return;
		}
		foreach ( self::for_order( $order->get_id() ) as $p ) {
			self::mark_review( $p['id'], $reason );
		}
		foreach ( $order->get_items() as $item ) {
			$purchase = $item->get_meta( '_din_package_purchase' );
			if ( ! is_array( $purchase ) || empty( $purchase['package_id'] ) ) {
				continue;
			}
			$item_id = $item->get_id();
			$result = self::mutate( $purchase['package_id'], static function ( $p ) use ( $item_id, $reason ) {
				foreach ( $p['history'] as $event ) {
					if ( (int) ( $event['item_id'] ?? 0 ) === $item_id ) {
						if ( $p['review'] !== $reason ) {
							$p['review'] = $reason;
							++$p['generation'];
						}
						return $p;
					}
				}
				// Invalidate an approval's CAS revision without suspending the original service.
				$p['rejected_items'][ $item_id ] = $reason;
				return $p;
			} );
			if ( ! is_wp_error( $result ) ) {
				DIN_Packages_Mail::schedule( $result );
			}
		}
	}
}
