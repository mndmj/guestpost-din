<?php
/** Generation-aware package reminders using WooCommerce's existing queue and mail transport. */
defined( 'ABSPATH' ) || exit;

final class DIN_Packages_Mail {
	const HOOK = 'din_packages_send_email';
	const GROUP = 'din-package-lifecycle';
	const MAX_ATTEMPTS = 3;
	const LEASE_SECONDS = 900;
	// Keep the existing H-14 key so old queued jobs and delivery records still match.
	private const EVENT_DAYS = array( 'reminder' => 14, 'reminder_7' => 7, 'expired' => 0 );

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'ensure_reconcile' ), 20 );
		add_action( self::HOOK, array( __CLASS__, 'send' ), 10, 4 );
		add_action( 'din_packages_reconcile', array( __CLASS__, 'reconcile' ) );
		add_action( 'din_packages_reconcile_page', array( __CLASS__, 'reconcile' ) );
	}

	public static function ensure_reconcile() {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( 'din_packages_reconcile', array(), self::GROUP ) ) {
			$id = as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 'din_packages_reconcile', array(), self::GROUP, true );
			if ( ! $id ) {
				self::log( 0, 'Could not schedule the hourly package reconciliation.' );
			}
		}
	}

	public static function reconcile( $after_id = 0 ) {
		$packages = DIN_Packages::all_after( (int) $after_id, 100 );
		foreach ( $packages as $p ) {
			self::schedule( $p );
			$after_id = (int) $p['id'];
		}
		if ( count( $packages ) === 100 ) {
			$args = array( $after_id );
			if ( ! as_has_scheduled_action( 'din_packages_reconcile_page', $args, self::GROUP ) ) {
				$id = as_schedule_single_action( time() + 1, 'din_packages_reconcile_page', $args, self::GROUP, true );
				if ( ! $id ) {
					self::log( 0, 'Could not enqueue the next package reconciliation page.' );
				}
			}
		}
	}

	/** Called after activation, renewal, upgrade, or a review-state change is saved. */
	public static function schedule( $p ) {
		if ( empty( $p['id'] ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}
		$p = DIN_Packages::get( $p['id'] );
		if ( ! $p ) {
			return;
		}
		$p = self::settle_abandoned( $p );
		$group = self::GROUP . '-' . $p['id'];
		$generation = (int) $p['generation'];
		$annual = 'annual' === $p['period'] && ! empty( $p['started_at'] ) && ! empty( $p['expires_at'] ) && empty( $p['review'] ) && empty( $p['stopped_at'] );
		$actions = as_get_scheduled_actions( array( 'hook' => self::HOOK, 'group' => $group, 'status' => 'pending', 'per_page' => -1 ) );
		foreach ( $actions as $action ) {
			$args = $action->get_args();
			// A stale save must never cancel a newer generation's jobs.
			if ( isset( $args[1] ) && ( (int) $args[1] < $generation || ( ! $annual && (int) $args[1] === $generation ) ) ) {
				as_unschedule_all_actions( self::HOOK, $args, $group );
			}
		}
		if ( ! $annual ) {
			return;
		}
		$now = time();
		$expires = (int) $p['expires_at'];
		foreach ( self::EVENT_DAYS as $event => $days ) {
			$window_end = $expires - ( 'reminder' === $event ? 7 * DAY_IN_SECONDS : 0 );
			if ( $days && $now >= $window_end ) {
				continue;
			}
			$email = $p['emails'][ $generation . ':' . $event ] ?? array();
			if ( in_array( $email['state'] ?? '', array( 'sending', 'sent', 'uncertain' ), true ) ) {
				continue;
			}
			$attempt = (int) ( $email['attempt'] ?? 0 ) + 1;
			if ( $attempt > self::MAX_ATTEMPTS ) {
				continue;
			}
			$due = $expires - $days * DAY_IN_SECONDS;
			self::queue( $p['id'], $generation, $event, $attempt, max( $now + 1, $due, (int) ( $email['retry_at'] ?? 0 ) ) );
		}
	}

	/** Lifecycle writers call this inside their atomic mutation before changing entitlement. */
	public static function has_sending( $p ) {
		foreach ( $p['emails'] ?? array() as $email ) {
			if ( 'sending' === ( $email['state'] ?? '' ) && (int) ( $email['lease_until'] ?? 0 ) > time() ) {
				return true;
			}
		}
		return false;
	}

	public static function send( $id, $generation, $event, $attempt = 1 ) {
		$id = (int) $id;
		$generation = (int) $generation;
		$attempt = (int) $attempt;
		if ( $id < 1 || $generation < 1 || $attempt < 1 || $attempt > self::MAX_ATTEMPTS || ! is_string( $event ) || ! isset( self::EVENT_DAYS[ $event ] ) ) {
			return;
		}
		$key = $generation . ':' . $event;
		$token = wp_generate_uuid4();
		$now = time();
		$p = DIN_Packages::mutate( $id, static function ( $p ) use ( $generation, $event, $attempt, $key, $token, $now ) {
			$email = $p['emails'][ $key ] ?? array();
			if ( ! self::relevant( $p, $generation, $event, $now ) || in_array( $email['state'] ?? '', array( 'sending', 'sent', 'uncertain' ), true ) || $attempt !== (int) ( $email['attempt'] ?? 0 ) + 1 || (int) ( $email['retry_at'] ?? 0 ) > $now ) {
				return new WP_Error( 'din_email_skip', 'Email is stale, premature, already claimed, or already sent.' );
			}
			$p['emails'][ $key ] = array( 'state' => 'sending', 'attempt' => $attempt, 'token' => $token, 'lease_until' => $now + self::LEASE_SECONDS );
			return $p;
		} );
		if ( is_wp_error( $p ) ) {
			if ( 'din_email_skip' !== $p->get_error_code() ) {
				self::log( $id, 'Could not claim package email; hourly reconciliation will retry scheduling.' );
			}
			return;
		}

		$state = 'failed';
		try {
			$user = get_userdata( (int) $p['customer_id'] );
			if ( $user && is_email( $user->user_email ) ) {
				$subject = 'expired' === $event ? __( 'Your package has expired.', 'din-package-lifecycle' ) : sprintf( __( 'Your package expires within %d days.', 'din-package-lifecycle' ), self::EVENT_DAYS[ $event ] );
				$date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $p['expires_at'], wp_timezone() );
				$body = '<p>' . esc_html( $subject ) . '</p><p>' . esc_html( $p['product_name'] ) . '<br>' . esc_html( $p['heading'] ) . '<br>' . esc_html( sprintf( __( 'Order #%d', 'din-package-lifecycle' ), $p['order_id'] ) ) . '</p>';
				$body .= '<p>' . esc_html( sprintf( __( 'End date: %s (site time zone).', 'din-package-lifecycle' ), $date ) ) . '</p>';
				$body .= '<p><a href="' . esc_url( wp_login_url( wc_get_account_endpoint_url( 'din-packages' ) ) ) . '">' . esc_html__( 'Log in to your account to renew or upgrade your plan.', 'din-package-lifecycle' ) . '</a></p>';
				$mailer = WC()->mailer();
				$message = $mailer->wrap_message( $subject, $body );
				$fresh = DIN_Packages::get( $id );
				$claim = $fresh['emails'][ $key ] ?? array();
				if ( ! self::relevant( $fresh, $generation, $event, time() ) || ( $claim['token'] ?? '' ) !== $token || 'sending' !== ( $claim['state'] ?? '' ) || (int) ( $claim['lease_until'] ?? 0 ) <= time() ) {
					self::finish( $id, $key, $token, 'skipped', $attempt );
					return;
				}
				$state = $mailer->send( $user->user_email, $subject, $message ) ? 'sent' : 'failed';
			}
		} catch (Throwable $error) {
			// ponytail: SMTP cannot guarantee exactly-once; ambiguous transport needs manual review, not another email.
			$state = 'uncertain';
		}
		$saved = self::finish( $id, $key, $token, $state, $attempt );
		if ( 'sent' !== $state ) {
			self::log( $id, sprintf( 'Email %s generation %d attempt %d: %s.', $event, $generation, $attempt, $state ) );
		}
		if ( ! is_wp_error( $saved ) && 'failed' === $state && $attempt < self::MAX_ATTEMPTS ) {
			self::queue( $id, $generation, $event, $attempt + 1, $saved['emails'][ $key ]['retry_at'] );
		}
	}

	private static function relevant( $p, $generation, $event, $now ) {
		if ( ! $p || (int) $p['generation'] !== $generation || 'annual' !== $p['period'] || ! empty( $p['review'] ) || ! empty( $p['stopped_at'] ) || empty( $p['started_at'] ) || empty( $p['expires_at'] ) ) {
			return false;
		}
		// Re-read through WooCommerce both when claiming and immediately before transport.
		$order = wc_get_order( (int) $p['order_id'] );
		if ( ! $order ) {
			return false;
		}
		try {
			$order->get_data_store()->read( $order );
		} catch (Throwable $error) {
			return false;
		}
		if ( (int) $order->get_customer_id() !== (int) $p['customer_id'] || ! $order->has_status( 'completed' ) ) {
			return false;
		}
		$expires = (int) $p['expires_at'];
		if ( 'expired' === $event ) {
			return $now >= $expires;
		}
		$window_end = $expires - ( 'reminder' === $event ? 7 * DAY_IN_SECONDS : 0 );
		return $now >= $expires - self::EVENT_DAYS[ $event ] * DAY_IN_SECONDS && $now < $window_end;
	}

	private static function finish( $id, $key, $token, $state, $attempt ) {
		$now = time();
		$saved = DIN_Packages::mutate( $id, static function ( $p ) use ( $key, $token, $state, $attempt, $now ) {
			if ( ! $p || ( $p['emails'][ $key ]['token'] ?? '' ) !== $token ) {
				return new WP_Error( 'din_email_claim_lost', 'Email claim no longer belongs to this worker.' );
			}
			$p['emails'][ $key ]['state'] = $state;
			$p['emails'][ $key ]['lease_until'] = 0;
			$p['emails'][ $key ]['finished_at'] = $now;
			$p['emails'][ $key ]['retry_at'] = 'failed' === $state ? $now + 5 * MINUTE_IN_SECONDS * $attempt : 0;
			return $p;
		} );
		if ( is_wp_error( $saved ) ) {
			self::log( $id, 'Could not persist email delivery outcome; do not resend without checking transport logs.' );
		}
		return $saved;
	}

	private static function settle_abandoned( $p ) {
		foreach ( $p['emails'] ?? array() as $key => $email ) {
			if ( 'sending' !== ( $email['state'] ?? '' ) || (int) ( $email['lease_until'] ?? 0 ) > time() ) {
				continue;
			}
			$now = time();
			$saved = DIN_Packages::mutate( $p['id'], static function ( $current ) use ( $key, $email, $now ) {
				if ( ! $current || ( $current['emails'][ $key ] ?? array() ) !== $email ) {
					return new WP_Error( 'din_email_changed', 'Email changed during reconciliation.' );
				}
				$current['emails'][ $key ]['state'] = 'uncertain';
				$current['emails'][ $key ]['lease_until'] = 0;
				$current['emails'][ $key ]['finished_at'] = $now;
				return $current;
			} );
			if ( ! is_wp_error( $saved ) ) {
				$p = $saved;
				self::log( $p['id'], 'Email worker lease expired; delivery is uncertain and requires checking transport logs.' );
			}
		}
		return $p;
	}

	private static function queue( $id, $generation, $event, $attempt, $when ) {
		$args = array( (int) $id, (int) $generation, $event, (int) $attempt );
		$group = self::GROUP . '-' . $id;
		if ( ! as_has_scheduled_action( self::HOOK, $args, $group ) ) {
			$job = as_schedule_single_action( (int) $when, self::HOOK, $args, $group, true );
			if ( ! $job && ! as_has_scheduled_action( self::HOOK, $args, $group ) ) {
				self::log( $id, 'Could not enqueue package email; hourly reconciliation will retry scheduling.' );
			}
		}
	}

	private static function log( $id, $message ) {
		wc_get_logger()->error( 'Package ' . (int) $id . ': ' . $message, array( 'source' => self::GROUP ) );
	}
}
