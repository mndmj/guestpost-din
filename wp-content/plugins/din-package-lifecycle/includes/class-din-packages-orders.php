<?php
defined( 'ABSPATH' ) || exit;

final class DIN_Packages_Orders {
	private static $indexes = array();

	public static function boot() {
		add_filter( 'woocommerce_my_account_my_orders_query', array( __CLASS__, 'orders_query' ), 20 );
		add_action( 'woocommerce_my_account_my_orders_column_order-number', array( __CLASS__, 'order_number' ) );
		add_action( 'woocommerce_my_account_my_orders_column_order-total', array( __CLASS__, 'order_total' ) );
		add_filter( 'woocommerce_order_details_status', array( __CLASS__, 'detail_status' ), 20, 2 );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'detail' ), 5 );
		add_action( 'woocommerce_order_details_before_order_table', array( __CLASS__, 'original_receipt' ) );
	}

	private static function buyer() {
		return ! is_admin() && is_account_page() ? (int) get_current_user_id() : 0;
	}

	private static function purchase( $item ) {
		$purchase = $item->get_meta( '_din_package_purchase' );
		$id = is_array( $purchase ) ? ( $purchase['package_id'] ?? null ) : null;
		if ( ! ( is_int( $id ) || is_string( $id ) ) || ! ctype_digit( (string) $id ) || (int) $id < 1 || ! in_array( $purchase['action'] ?? '', array( 'lifetime', 'renew_1', 'renew_2' ), true ) || (float) $item->get_quantity() !== 1.0 ) {
			return null;
		}
		return array( 'package_id' => (int) $id, 'action' => $purchase['action'] );
	}

	private static function index() {
		$buyer = self::buyer();
		$index = array( 'groups' => array(), 'parents' => array(), 'hidden' => array() );
		if ( ! $buyer ) {
			return $index;
		}
		if ( isset( self::$indexes[ $buyer ] ) ) {
			return self::$indexes[ $buyer ];
		}
		$orders = $packages = array();
		// ponytail: scan this buyer's history once per request; index source-order metadata if histories grow large.
		for ( $page = 1; ; ++$page ) {
			$batch = wc_get_orders( array( 'customer_id' => $buyer, 'type' => 'shop_order', 'page' => $page, 'limit' => 100, 'orderby' => 'ID', 'order' => 'DESC' ) );
			foreach ( $batch as $order ) {
				if ( (int) $order->get_customer_id() === $buyer ) {
					$orders[ $order->get_id() ] = $order;
				}
			}
			if ( count( $batch ) < 100 ) {
				break;
			}
		}
		for ( $page = 1; ; ++$page ) {
			$batch = DIN_Packages::for_customer( $buyer, $page, 100 );
			foreach ( $batch as $package ) {
				$source_id = (int) $package['order_id'];
				if ( (int) $package['customer_id'] !== $buyer || ! isset( $orders[ $source_id ] ) ) {
					continue;
				}
				$packages[ $package['id'] ] = $package;
				if ( ! isset( $index['groups'][ $source_id ] ) ) {
					$index['groups'][ $source_id ] = array( 'order' => $orders[ $source_id ], 'packages' => array(), 'transactions' => array() );
				}
				$index['groups'][ $source_id ]['packages'][ $package['id'] ] = $package;
			}
			if ( count( $batch ) < 100 ) {
				break;
			}
		}
		foreach ( $orders as $order_id => $order ) {
			$items = $order->get_items();
			$all_linked = ! empty( $items );
			$matches = array();
			foreach ( $items as $item ) {
				$purchase = self::purchase( $item );
				$package = $packages[ $purchase['package_id'] ?? 0 ] ?? null;
				$action = $purchase['action'] ?? '';
				if ( ! $package || (int) $package['order_id'] === (int) $order_id || (int) $package[ 'lifetime' === $action ? 'lifetime_product_id' : 'annual_product_id' ] !== (int) ( $item->get_variation_id() ?: $item->get_product_id() ) ) {
					$all_linked = false;
					continue;
				}
				$matches[ $package['order_id'] ][] = array( 'package' => $package, 'item_id' => $item->get_id(), 'action' => $action );
			}
			$hidden = $all_linked && count( $matches ) === 1 && ! isset( $index['groups'][ $order_id ] );
			if ( $hidden ) {
				$index['hidden'][] = (int) $order_id;
			}
			if ( $matches ) {
				$index['parents'][ $order_id ] = array_keys( $matches );
			}
			foreach ( $matches as $source_id => $links ) {
				$index['groups'][ $source_id ]['transactions'][ $order_id ] = array( 'order' => $order, 'links' => $links, 'standalone' => ! $hidden );
			}
		}
		self::$indexes[ $buyer ] = $index;
		return $index;
	}

	public static function orders_query( $args ) {
		$buyer = self::buyer();
		$requested = $args['customer'] ?? $args['customer_id'] ?? 0;
		if ( ! $buyer || ! is_scalar( $requested ) || (int) $requested !== $buyer ) {
			return $args;
		}
		$args['exclude'] = array_values( array_unique( array_merge( (array) ( $args['exclude'] ?? array() ), self::index()['hidden'] ) ) );
		return $args;
	}

	public static function group( $order_id ) {
		return self::index()['groups'][ (int) $order_id ] ?? null;
	}

	private static function applied( $link ) {
		foreach ( $link['package']['history'] ?? array() as $event ) {
			if ( ( $event['action'] ?? '' ) === $link['action'] && (int) ( $event['item_id'] ?? 0 ) === (int) $link['item_id'] ) {
				return true;
			}
		}
		return false;
	}

	public static function badges( $group ) {
		if ( ! $group ) {
			return '';
		}
		$upgraded = $pending = array();
		foreach ( $group['packages'] as $package ) {
			foreach ( $package['history'] ?? array() as $event ) {
				if ( 'lifetime' === ( $event['action'] ?? '' ) ) {
					$upgraded[ $package['id'] ] = true;
				}
			}
		}
		foreach ( $group['transactions'] as $transaction ) {
			if ( ! $transaction['order']->has_status( array( 'pending', 'on-hold', 'processing', 'completed' ) ) ) {
				continue;
			}
			foreach ( $transaction['links'] as $link ) {
				$package = $link['package'];
				if ( isset( $group['packages'][ $package['id'] ] ) && 'lifetime' === $link['action'] && 'annual' === $package['period'] && empty( $package['stopped_at'] ) && ! self::applied( $link ) ) {
					$pending[ $package['id'] ] = true;
				}
			}
		}
		$html = '';
		if ( $upgraded ) {
			$label = count( $group['packages'] ) === 1 ? 'UPGRADED TO LIFETIME' : sprintf( _n( '%d PACKAGE UPGRADED', '%d PACKAGES UPGRADED', count( $upgraded ), 'din-package-lifecycle' ), count( $upgraded ) );
			$html .= '<span class="din-order-badge din-order-badge--upgraded">' . esc_html( $label ) . '</span>';
		}
		if ( $pending ) {
			$html .= '<span class="din-order-badge din-order-badge--pending">UPGRADE PENDING</span>';
		}
		return $html;
	}

	public static function order_number( $order ) {
		if ( ! self::buyer() || (int) $order->get_customer_id() !== self::buyer() ) {
			return;
		}
		echo '<a href="' . esc_url( $order->get_view_order_url() ) . '" aria-label="' . esc_attr( sprintf( __( 'View order number %s', 'woocommerce' ), $order->get_order_number() ) ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
		echo self::badges( self::group( $order->get_id() ) );
	}

	public static function order_total( $order ) {
		if ( ! self::buyer() || (int) $order->get_customer_id() !== self::buyer() ) {
			return;
		}
		$count = $order->get_item_count() - $order->get_item_count_refunded();
		echo wp_kses_post( sprintf( _n( '%1$s for %2$s item', '%1$s for %2$s items', $count, 'woocommerce' ), $order->get_formatted_order_total(), $count ) );
		$group = self::group( $order->get_id() );
		if ( ! empty( $group['transactions'] ) ) {
			echo '<small class="din-order-note">Initial purchase; upgrades in history</small>';
		}
	}

	public static function detail_status( $text, $order ) {
		return is_wc_endpoint_url( 'view-order' ) ? $text . self::badges( self::group( $order->get_id() ) ) : $text;
	}

	private static function button( $url, $label ) {
		echo '<a class="din-order-link" href="' . esc_url( $url ) . '"><div class="din-order-button">' . esc_html( $label ) . '</div></a>';
	}

	private static function can_pay( $order ) {
		if ( ! $order->needs_payment() ) {
			return false;
		}
		foreach ( $order->get_items() as $item ) {
			if ( ! $item->get_meta( '_din_package_purchase' ) ) {
				continue;
			}
			$purchase = self::purchase( $item );
			if ( ! $purchase ) {
				return false;
			}
			$option = DIN_Packages::purchase_option( (int) $purchase['package_id'], $purchase['action'], self::buyer(), false );
			if ( is_wp_error( $option ) || (int) $option['product_id'] !== (int) ( $item->get_variation_id() ?: $item->get_product_id() ) ) {
				return false;
			}
		}
		return true;
	}

	public static function detail( $order_id ) {
		if ( ! is_wc_endpoint_url( 'view-order' ) ) {
			return;
		}
		$index = self::index();
		foreach ( $index['parents'][ $order_id ] ?? array() as $parent ) {
			$source = $index['groups'][ $parent ]['order'];
			echo '<p class="din-order-parent">Transaction for main order ';
			echo '<a href="' . esc_url( $source->get_view_order_url() ) . '">#' . esc_html( $source->get_order_number() ) . '</a></p>';
		}
		$group = $index['groups'][ $order_id ] ?? null;
		if ( ! $group ) {
			return;
		}
		$packages = $group['packages'];
		foreach ( $packages as $package_id => &$package ) {
			$product = wc_get_product( $package[ 'lifetime' === $package['period'] ? 'lifetime_product_id' : 'annual_product_id' ] );
			if ( $product ) {
				$package['product_name'] = $product->get_name();
			}
			$package['upgrade_badges'] = self::badges( array( 'packages' => array( $package_id => $package ), 'transactions' => $group['transactions'] ) );
			$package['evidence_url'] = $group['order']->get_view_order_url() . '#din-original-transaction';
		}
		unset( $package );
		DIN_Packages_Customer::render_packages( 1, count( $packages ) + 1, false, $packages );
		self::transactions( $group );
		self::timeline( $group['packages'] );
	}

	private static function transactions( $group ) {
		$root = $group['order'];
		$transactions = array( $root->get_id() => array( 'order' => $root, 'links' => array(), 'standalone' => false ) ) + $group['transactions'];
		uasort( $transactions, static function ( $left, $right ) {
			return ( $left['order']->get_date_created() ? $left['order']->get_date_created()->getTimestamp() : 0 ) <=> ( $right['order']->get_date_created() ? $right['order']->get_date_created()->getTimestamp() : 0 );
		} );
		echo '<section class="din-order-history"><h2>Transaction history</h2><p>Each payment remains a separate transaction. Amounts are not combined into a new charge.</p><div class="din-order-table-wrap" role="region" aria-label="Transaction history" tabindex="0"><table class="din-order-transactions"><thead><tr><th scope="col">Transaction</th><th scope="col">Package change</th><th scope="col">Payment status</th><th scope="col">Amount</th><th scope="col">Actions</th></tr></thead><tbody>';
		foreach ( $transactions as $transaction ) {
			$order = $transaction['order'];
			$original = $order->get_id() === $root->get_id();
			echo '<tr><th scope="row">#' . esc_html( $order->get_order_number() );
			if ( $order->get_date_created() ) {
				echo '<small class="din-order-note">' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</small>';
			}
			echo '</th><td>';
			if ( $original ) {
				echo 'Initial purchase';
			}
			foreach ( $transaction['links'] as $link ) {
				$label = array( 'lifetime' => 'Annual → Lifetime', 'renew_1' => 'Renewal: 1 year', 'renew_2' => 'Renewal: 2 years' )[ $link['action'] ];
				echo '<div>Package #' . esc_html( $link['package']['id'] ) . ': ' . esc_html( $label );
				$change = self::applied( $link ) ? 'Applied' : ( $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ? 'Not applied' : 'Awaiting payment / admin approval' );
				if ( ! empty( $link['package']['stopped_at'] ) ) {
					$change .= ' · Service stopped';
				}
				echo '<small class="din-order-note">' . esc_html( $change ) . '</small></div>';
			}
			if ( $transaction['standalone'] ) {
				echo '<small class="din-order-note">Shared transaction: total also includes other items or packages.</small>';
			}
			echo '</td><td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '<small class="din-order-note">' . esc_html( $order->get_payment_method_title() ) . '</small></td><td>' . wp_kses_post( $order->get_formatted_order_total() );
			if ( $order->get_total_refunded() > 0 ) {
				echo '<small class="din-order-note">Refunded: ' . wp_kses_post( wc_price( $order->get_total_refunded(), array( 'currency' => $order->get_currency() ) ) ) . '</small>';
			}
			echo '</td><td><div class="din-order-actions">';
			self::button( $original ? '#din-original-transaction' : $order->get_view_order_url(), $original ? 'View original receipt' : 'View transaction' );
			if ( self::can_pay( $order ) ) {
				$actions = array_unique( array_column( $transaction['links'], 'action' ) );
				self::button( $order->get_checkout_payment_url(), ! $transaction['standalone'] && array( 'lifetime' ) === array_values( $actions ) ? 'Pay Upgrade' : 'Pay transaction' );
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table></div></section>';
	}

	private static function timeline( $packages ) {
		$events = array();
		$labels = array( 'activate' => 'Service activated', 'lifetime' => 'Upgraded: Annual → Lifetime', 'renew_1' => 'Renewed for 1 year', 'renew_2' => 'Renewed for 2 years', 'stopped' => 'Service stopped' );
		foreach ( $packages as $package ) {
			foreach ( $package['history'] ?? array() as $event ) {
				if ( isset( $labels[ $event['action'] ?? '' ] ) ) {
					$events[] = array( 'package_id' => $package['id'], 'event' => $event, 'position' => count( $events ) );
				}
			}
		}
		usort( $events, static function ( $left, $right ) { return ( (int) ( $left['event']['at'] ?? 0 ) <=> (int) ( $right['event']['at'] ?? 0 ) ) ?: $left['position'] <=> $right['position']; } );
		echo '<section class="din-order-history"><h2>Service history</h2><ol class="din-order-timeline">';
		foreach ( $events as $entry ) {
			$event = $entry['event'];
			echo '<li><strong>Package #' . esc_html( $entry['package_id'] ) . ' — ' . esc_html( $labels[ $event['action'] ] ) . '</strong>';
			if ( ! empty( $event['at'] ) ) {
				echo '<small class="din-order-note">' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $event['at'] ) ) . '</small>';
			}
			if ( 'stopped' === $event['action'] && ! empty( $event['reason'] ) ) {
				echo '<div class="din-order-reason">' . nl2br( esc_html( $event['reason'] ) ) . '</div>';
			}
			echo '</li>';
		}
		if ( ! $events ) {
			echo '<li>Awaiting service activation.</li>';
		}
		echo '</ol></section>';
	}

	public static function original_receipt( $order ) {
		if ( is_wc_endpoint_url( 'view-order' ) && self::group( $order->get_id() ) ) {
			echo '<p id="din-original-transaction" class="din-order-receipt-note">Original transaction #' . esc_html( $order->get_order_number() ) . ' — the items and amounts below retain the original purchase record.</p>';
		}
	}
}
