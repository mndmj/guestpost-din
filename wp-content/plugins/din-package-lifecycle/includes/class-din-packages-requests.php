<?php
defined( 'ABSPATH' ) || exit;

final class DIN_Packages_Requests {
	public static function create( $id, $source_id, $admin_id, $change ) {
		if ( ! user_can( $admin_id, 'manage_woocommerce' ) || ! user_can( $admin_id, 'edit_shop_order', $source_id ) ) {
			return new WP_Error( 'package_access', 'Package management or source order access denied.' );
		}
		$revision = is_array( $change ) ? ( $change['revision'] ?? null ) : null;
		if ( ! ( is_int( $revision ) || is_string( $revision ) ) || ! preg_match( '/^[0-9]+$/D', (string) $revision ) || (int) $revision < 1 || (string) (int) $revision !== ltrim( (string) $revision, '0' ) ) {
			return new WP_Error( 'package_revision', 'Reload the order before requesting a package change.' );
		}
		$extension = $change['extension'] ?? null;
		$years = 'custom' === $extension ? DIN_Packages::custom_years( $change['years'] ?? null ) : 0;
		if ( 'custom' === $extension && ! $years ) {
			return new WP_Error( 'package_request_years', 'Enter a positive whole number of years for the custom duration.' );
		}
		$date = $change['date'] ?? '';
		$day = is_string( $date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $date ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() ) : false;
		if ( ! in_array( $extension, array( '1', '2', 'custom', 'lifetime' ), true ) || ( 'lifetime' === $extension ? '' !== $date : ( ! $day || $day->format( 'Y-m-d' ) !== $date ) ) ) {
			return new WP_Error( 'package_request_input', 'Choose a duration and a valid expiry date (no date for Lifetime).' );
		}
		$reason = is_string( $change['reason'] ?? null ) ? trim( sanitize_textarea_field( $change['reason'] ) ) : '';
		if ( 1 !== preg_match( '/^.{1,1000}$/us', $reason ) ) {
			return new WP_Error( 'package_request_reason', 'Enter a reason for the payment request (maximum 1000 characters).' );
		}
		$action = 'lifetime' === $extension ? 'lifetime' : 'renew_' . $extension;
		$token = wp_generate_uuid4();
		$source = $option = $product = null;
		$reserved = DIN_Packages::mutate( $id, static function ( $p ) use ( $source_id, $admin_id, $revision, $action, $years, $day, $reason, $token, &$source, &$option, &$product ) {
			$source = self::source( $p, $source_id, $admin_id );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			if ( $years && (int) ( new DateTimeImmutable( '@' . max( time(), (int) $p['expires_at'] ) ) )->setTimezone( wp_timezone() )->format( 'Y' ) + $years > 9999 ) {
				return new WP_Error( 'package_request_years', 'The custom duration exceeds the supported calendar year 9999.' );
			}
			$expires_at = 0;
			if ( 'lifetime' !== $action ) {
				$old = ( new DateTimeImmutable( '@' . (int) $p['expires_at'] ) )->setTimezone( wp_timezone() );
				$expires_at = $day->setTime( (int) $old->format( 'H' ), (int) $old->format( 'i' ), (int) $old->format( 's' ) )->getTimestamp();
				if ( $expires_at <= time() || $expires_at <= $p['expires_at'] ) {
					return new WP_Error( 'package_request_date', 'The requested expiry must be in the future and later than the current expiry.' );
				}
			}
			$request = array( 'action' => $action, 'expires_at' => $expires_at, 'reason' => $reason, 'requested_by' => (int) $admin_id, 'generation' => (int) $p['generation'] );
			if ( 'renew_custom' === $action ) {
				$request['years'] = $years;
			}
			$key = hash( 'sha256', wp_json_encode( array( $p['id'], (int) $revision, $request ) ) );
			$previous = $p['payment_request'] ?? null;
			if ( $previous ) {
				$prior = ! empty( $previous['order_id'] ) ? wc_get_order( $previous['order_id'] ) : false;
				if ( ! $prior ) {
					// ponytail: no lease stealing after uncertain persistence; an administrator must reconcile the retained reservation/order.
					return new WP_Error( 'package_request_busy', 'An unresolved payment request already exists. Review its order before attempting another request.' );
				}
				try {
					$prior->get_data_store()->read( $prior );
					$prior->read_meta_data( true );
				} catch ( Throwable $error ) {
					return new WP_Error( 'package_request_busy', 'The previous payment order could not be verified. Review it before trying again.' );
				}
				$applied = false;
				foreach ( $p['history'] as $event ) {
					if ( (int) ( $event['order_id'] ?? 0 ) === (int) $previous['order_id'] && ! empty( $event['item_id'] ) ) {
						$applied = true;
					}
				}
				if ( ! $applied && ! $prior->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
					$same = $request === array_intersect_key( $previous, $request );
					if ( 'ready' === ( $previous['state'] ?? '' ) && ( $key === ( $previous['key'] ?? '' ) || ( $same && (int) $p['revision'] === (int) $revision ) ) ) {
						return self::valid_order( $prior, $p, $previous ) ? $p : new WP_Error( 'package_request_changed', 'The existing payment order changed. Review its owner, items, amount and request details before continuing.' );
					}
					return new WP_Error( 'package_request_pending', 'A payment request already awaits payment or approval. Resolve that order before creating another request.' );
				}
			}
			if ( (int) $p['revision'] !== (int) $revision ) {
				return new WP_Error( 'package_revision', 'The package changed after this form was opened. Reload the order and try again.' );
			}
			$option = DIN_Packages::purchase_option( $p['id'], $action, $p['customer_id'], false, $years );
			if ( is_wp_error( $option ) ) {
				return $option;
			}
			$product = wc_get_product( $option['product_id'] );
			$price = $product ? $product->get_price() : '';
			if ( ! is_numeric( $price ) || ! is_finite( (float) $price ) || (float) $price <= 0 ) {
				return new WP_Error( 'package_request_price', 'The renewal or upgrade product must have a valid price greater than zero.' );
			}
			$p['payment_request'] = array_merge( $request, array( 'key' => $key, 'token' => $token, 'order_id' => 0, 'state' => 'creating', 'revision' => (int) $revision ) );
			return $p;
		} );
		if ( is_wp_error( $reserved ) ) {
			return $reserved;
		}
		$request = $reserved['payment_request'];
		if ( $request['token'] !== $token ) {
			return wc_get_order( $request['order_id'] ) ?: new WP_Error( 'package_request_missing', 'The existing payment order could not be loaded.' );
		}
		$purchase = array( 'package_id' => (int) $id, 'action' => $action, 'admin_request' => array_intersect_key( $request, array_flip( array( 'expires_at', 'reason', 'requested_by', 'generation', 'years' ) ) ) );
		$order = null;
		try {
			$order = new WC_Order();
			$order->set_customer_id( $reserved['customer_id'] );
			$order->set_currency( $reserved['currency'] );
			$order->set_address( $source->get_address( 'billing' ), 'billing' );
			$order->set_address( $source->get_address( 'shipping' ), 'shipping' );
			$order->set_status( 'pending' );
			$order->set_date_paid( null );
			$order->set_created_via( 'din-package-renewal' );
			$order->set_prices_include_tax( wc_prices_include_tax() );
			$order->update_meta_data( 'is_vat_exempt', 'yes' === $source->get_meta( 'is_vat_exempt' ) ? 'yes' : 'no' );
			$order->update_meta_data( '_din_packages_version', DIN_PACKAGES_VERSION );
			$order->update_meta_data( '_din_package_admin_request', $purchase );
			$order->update_meta_data( '_din_package_request_key', $request['key'] );
			$order->update_meta_data( '_din_package_source_order_id', (int) $source_id );
			if ( ! $order->save() || ! $order->get_id() ) {
				throw new RuntimeException( 'The payment order could not be saved.' );
			}
			$attached = self::record_order( $id, $request, $order->get_id(), 'creating' );
			if ( is_wp_error( $attached ) ) {
				throw new RuntimeException( $attached->get_error_message() );
			}
			$amount = wc_get_price_excluding_tax( $product, array( 'qty' => 1, 'price' => (float) $product->get_price() * $option['multiplier'], 'order' => $order ) );
			if ( ! is_numeric( $amount ) || ! is_finite( (float) $amount ) || $amount <= 0 ) {
				throw new RuntimeException( 'The renewal price could not be calculated.' );
			}
			$item = new WC_Order_Item_Product();
			$item->set_product( $product );
			$item->set_quantity( 1 );
			$item->set_subtotal( $amount );
			$item->set_total( $amount );
			$item->update_meta_data( '_din_package_purchase', $purchase );
			$item->update_meta_data( 'New expiry', $request['expires_at'] ? ( new DateTimeImmutable( '@' . $request['expires_at'] ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' ) : 'Lifetime (no expiry)' );
			$item->update_meta_data( 'Reason', $reason );
			$item->update_meta_data( 'Duration', 'renew_custom' === $action ? $years . ' Years' : $option['label'] );
			$order->add_item( $item );
			$order->calculate_totals( true );
			$order->save();
			$stored = wc_get_order( $order->get_id() );
			if ( ! $stored ) {
				throw new RuntimeException( 'The saved payment order could not be read.' );
			}
			$stored->get_data_store()->read( $stored );
			$stored->read_meta_data( true );
			if ( ! $stored->has_status( 'pending' ) || $stored->get_date_paid() || ! self::valid_order( $stored, $reserved, $request ) ) {
				throw new RuntimeException( 'The saved payment order failed validation. Review it before retrying.' );
			}
			$ready = self::record_order( $id, $request, $order->get_id(), 'ready' );
			if ( is_wp_error( $ready ) ) {
				throw new RuntimeException( $ready->get_error_message() );
			}
			$order = $stored;
		} catch ( Throwable $error ) {
			self::failed( $id, $request, $order, $error->getMessage() );
			return new WP_Error( 'package_request_failed', 'The payment request could not be completed. ' . $error->getMessage() . ' No service change was granted. Reload and review the retained order before retrying.' );
		}
		// This is outside creation: mail failures never roll back or recreate a ready payment order.
		$sent = null;
		$invoice = null;
		$observe = static function ( $success, $email_id, $email ) use ( &$sent, &$invoice ) {
			if ( 'customer_invoice' === $email_id && $email === $invoice ) {
				$sent = (bool) $success;
			}
		};
		add_action( 'woocommerce_email_sent', $observe, 10, 3 );
		try {
			$emails = WC()->mailer()->get_emails();
			if ( empty( $emails['WC_Email_Customer_Invoice'] ) || ! $order->get_billing_email() ) {
				throw new RuntimeException( 'Customer invoice email or recipient is unavailable.' );
			}
			$invoice = $emails['WC_Email_Customer_Invoice'];
			$invoice->trigger( $order->get_id(), $order );
			if ( true !== $sent ) {
				throw new RuntimeException( 'The email transport did not confirm sending the invoice.' );
			}
		} catch ( Throwable $error ) {
			self::log( 'Payment order #' . $order->get_id() . ' is ready but its invoice email failed. Resend the customer invoice from Order actions. ' . $error->getMessage(), $order );
		} finally {
			remove_action( 'woocommerce_email_sent', $observe, 10 );
		}
		return $order;
	}

	private static function valid_order( $order, $p, $request ) {
		$purchase = array( 'package_id' => (int) $p['id'], 'action' => $request['action'], 'admin_request' => array_intersect_key( $request, array_flip( array( 'expires_at', 'reason', 'requested_by', 'generation', 'years' ) ) ) );
		$items = array_values( $order->get_items() );
		$product_id = $p[ 'lifetime' === $request['action'] ? 'lifetime_product_id' : 'annual_product_id' ];
		return (int) $order->get_customer_id() === (int) $p['customer_id']
			&& $order->get_currency() === $p['currency'] && $order->get_total_refunded() <= 0
			&& is_numeric( $order->get_total() ) && is_finite( (float) $order->get_total() ) && $order->get_total() > 0
			&& $order->get_meta( '_din_package_admin_request' ) === $purchase && $order->get_meta( '_din_package_request_key' ) === $request['key']
			&& count( $items ) === 1 && $items[0]->get_meta( '_din_package_purchase' ) === $purchase
			&& 1.0 === (float) $items[0]->get_quantity() && (int) $items[0]->get_order_id() === (int) $order->get_id()
			&& (int) ( $items[0]->get_variation_id() ?: $items[0]->get_product_id() ) === (int) $product_id
			&& is_numeric( $items[0]->get_total() ) && is_finite( (float) $items[0]->get_total() ) && $items[0]->get_total() > 0;
	}

	private static function source( $p, $source_id, $admin_id ) {
		if ( (int) $p['order_id'] !== (int) $source_id ) {
			return new WP_Error( 'package_source', 'This package does not belong to the edited order.' );
		}
		if ( 'annual' !== $p['period'] || empty( $p['started_at'] ) || $p['expires_at'] <= 0 || ! empty( $p['stopped_at'] ) || ! empty( $p['review'] ) ) {
			return new WP_Error( 'package_request_state', 'Only an activated Annual package without a stop or pending review can receive a payment request.' );
		}
		try {
			$source = wc_get_order( $source_id );
			if ( ! $source || ! user_can( $admin_id, 'edit_shop_order', $source_id ) ) {
				return new WP_Error( 'package_access', 'Source order access denied.' );
			}
			$source->get_data_store()->read( $source );
			$source->read_meta_data( true );
			$item = $source->get_item( $p['order_item_id'] );
			if ( ! $source->has_status( 'completed' ) || ! $p['customer_id'] || (int) $source->get_customer_id() !== (int) $p['customer_id'] || ! $item || (int) $item->get_order_id() !== (int) $source_id || $p['unit'] < 1 || $p['unit'] > $item->get_quantity() || (int) $p['product_id'] !== (int) ( $item->get_variation_id() ?: $item->get_product_id() ) || $p['currency'] !== $source->get_currency() || $source->get_total_refunded() > 0 || ! DIN_Packages::valid_proofs( $source ) ) {
				return new WP_Error( 'package_source', 'Review the source order status, owner, items, currency, refunds and proof before requesting payment.' );
			}
			return $source;
		} catch ( Throwable $error ) {
			return new WP_Error( 'package_source', 'The source order could not be verified. Reload the order and try again.' );
		}
	}

	private static function record_order( $id, $request, $order_id, $state ) {
		return DIN_Packages::mutate( $id, static function ( $p ) use ( $request, $order_id, $state ) {
			if ( ( $p['payment_request']['token'] ?? '' ) !== $request['token'] || (int) $p['generation'] !== $request['generation'] || ! in_array( (int) $p['payment_request']['order_id'], array( 0, (int) $order_id ), true ) ) {
				return new WP_Error( 'package_request_changed', 'The package changed while its payment request was being created.' );
			}
			$source = self::source( $p, $p['order_id'], $request['requested_by'] );
			if ( is_wp_error( $source ) ) {
				return $source;
			}
			$p['payment_request']['order_id'] = (int) $order_id;
			$p['payment_request']['state'] = $state;
			return $p;
		} );
	}

	private static function failed( $id, $request, $order, $message ) {
		if ( $order && $order->get_id() ) {
			try {
				$order->get_data_store()->read( $order );
				if ( $order->has_status( 'pending' ) && ! $order->get_date_paid() ) {
					$order->set_status( 'failed' );
					$order->save();
				}
				DIN_Packages::mutate( $id, static function ( $p ) use ( $request, $order ) {
					// Do not repair an uncertain missing order-ID write automatically: retain the reservation for manual review.
					if ( ( $p['payment_request']['token'] ?? '' ) === $request['token'] && (int) $p['payment_request']['order_id'] === (int) $order->get_id() ) {
						$p['payment_request']['state'] = 'failed';
					}
					return $p;
				} );
			} catch ( Throwable $error ) {
				$message .= ' Failure recovery also needs manual review: ' . $error->getMessage();
			}
		}
		self::log( 'Package #' . $id . ' payment request ' . $request['key'] . ' failed; retained order #' . ( $order ? $order->get_id() : 0 ) . '. ' . $message, $order );
	}

	private static function log( $message, $order ) {
		try {
			if ( $order && $order->get_id() ) {
				$order->add_order_note( $message );
			}
			wc_get_logger()->error( $message, array( 'source' => 'din-packages' ) );
		} catch ( Throwable $error ) {
			// Logging cannot roll back a retained order or make a retry safe.
		}
	}
}
