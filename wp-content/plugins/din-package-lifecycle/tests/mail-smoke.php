<?php
/** Standalone, no WordPress bootstrap, database writes, scheduler jobs, or real mail. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$jobs = $sent = $logs = $hooks = array();
$transport_ok = true;
$transport_throw = false;
$before_send = null;
$source_order = array( 'customer_id' => 7, 'status' => 'completed' );
$cached_order = null;
$source_reads = 0;
$source_read_error = false;
$composed = 0;

class WP_Error {
	public function __construct( public $code, public $message = '' ) {}
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function __ ( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, $domain = '' ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function wp_generate_uuid4() { return uniqid( '', true ); }
function get_userdata( $id ) { return $id === 7 ? (object) array( 'user_email' => 'test@example.invalid' ) : false; }
function wc_get_order( $id ) {
	if ( 12 !== $id ) { return false; }
	if ( $GLOBALS['cached_order'] ) { return $GLOBALS['cached_order']; }
	if ( ! $GLOBALS['source_order'] ) { return false; }
	$GLOBALS['cached_order'] = new class( $GLOBALS['source_order'] ) {
		public function __construct( public $data ) {}
		public function get_customer_id() { return $this->data['customer_id']; }
		public function has_status( $status ) { return $this->data['status'] === $status; }
		public function get_data_store() {
			return new class {
				public function read( $order ) {
					++$GLOBALS['source_reads'];
					if ( ! $GLOBALS['source_order'] || $GLOBALS['source_read_error'] ) { throw new RuntimeException( 'Order cannot be read' ); }
					$order->data = $GLOBALS['source_order'];
				}
			};
		}
	};
	return $GLOBALS['cached_order'];
}
function is_email( $text ) { return filter_var( $text, FILTER_VALIDATE_EMAIL ); }
function wp_timezone() { return new DateTimeZone( 'Asia/Jakarta' ); }
function get_option( $key ) { return $key === 'date_format' ? 'Y-m-d' : 'H:i'; }
function wp_date( $format, $time, $zone = null ) { return ( new DateTimeImmutable( '@' . $time ) )->setTimezone( $zone ?? wp_timezone() )->format( $format ); }
function wc_get_account_endpoint_url( $endpoint ) { return 'https://example.invalid/my-account/' . $endpoint . '/'; }
function wp_login_url( $redirect ) { return 'https://example.invalid/wp-login.php?redirect_to=' . rawurlencode( $redirect ); }
function wc_get_logger() { return new class { public function error( $message, $context ) { $GLOBALS['logs'][] = $message; } }; }
function WC() {
	return new class {
		public function mailer() {
			return new class {
				public function wrap_message( $heading, $message ) {
					++$GLOBALS['composed'];
					if ( $GLOBALS['before_send'] ) { ( $GLOBALS['before_send'] )(); }
					return '<header>' . $heading . '</header>' . $message;
				}
				public function send( $to, $subject, $body, $headers = '' ) {
					$GLOBALS['sent'][] = array( $to, $subject, $body );
					if ( $GLOBALS['transport_throw'] ) { throw new RuntimeException( 'Ambiguous transport failure' ); }
					return $GLOBALS['transport_ok'];
				}
			};
		}
	};
}
class DIN_Packages {
	public static $rows = array();
	public static $next_error = null;
	public static function get( $id ) { return self::$rows[ $id ] ?? null; }
	public static function mutate( $id, $callback ) {
		if ( self::$next_error ) { $error = self::$next_error; self::$next_error = null; return $error; }
		$p = $callback( self::get( $id ) );
		if ( ! is_wp_error( $p ) ) { self::$rows[ $id ] = $p; }
		return $p;
	}
	public static function all_after( $id, $limit = 100 ) {
		return array_slice( array_values( array_filter( self::$rows, fn( $p ) => $p['id'] > $id ) ), 0, $limit );
	}
}
class Fake_Action {
	public function __construct( public $time, public $hook, public $args, public $group ) {}
	public function get_args() { return $this->args; }
}
function as_has_scheduled_action( $hook, $args = null, $group = '' ) {
	foreach ( $GLOBALS['jobs'] as $job ) {
		if ( $job->hook === $hook && $job->group === $group && ( $args === null || $args === $job->args ) ) { return true; }
	}
	return false;
}
function as_schedule_single_action( $time, $hook, $args = array(), $group = '', $unique = false ) {
	if ( $unique && as_has_scheduled_action( $hook, $args, $group ) ) { return 0; }
	$GLOBALS['jobs'][] = new Fake_Action( $time, $hook, $args, $group );
	return count( $GLOBALS['jobs'] );
}
function as_schedule_recurring_action( $time, $interval, $hook, $args = array(), $group = '', $unique = false ) {
	return as_schedule_single_action( $time, $hook, $args, $group, $unique );
}
function as_get_scheduled_actions( $query ) {
	return array_filter( $GLOBALS['jobs'], fn( $j ) => $j->hook === $query['hook'] && $j->group === $query['group'] );
}
function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
	$GLOBALS['jobs'] = array_values( array_filter( $GLOBALS['jobs'], fn( $j ) => ! ( $j->hook === $hook && $j->group === $group && $j->args === $args ) ) );
}
function check_mail( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } }
function fixture_mail( $expires ) {
	return array( 'id' => 1, 'revision' => 1, 'customer_id' => 7, 'order_id' => 12, 'product_name' => '<Test>', 'heading' => 'Heading & Post', 'period' => 'annual', 'started_at' => time() - DAY_IN_SECONDS, 'expires_at' => $expires, 'review' => '', 'emails' => array(), 'generation' => 1 );
}

$source = dirname( __DIR__ ) . '/includes/class-din-packages-mail.php';
if ( is_file( $source ) ) { require $source; }
check_mail( class_exists( 'DIN_Packages_Mail' ), 'The package email scheduler is not implemented.' );
DIN_Packages::$rows[1] = fixture_mail( time() + 20 * DAY_IN_SECONDS );
DIN_Packages_Mail::boot();
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( count( $jobs ) === 3, 'Annual schedules H-14, H-7, and expiry.' );
check_mail( $jobs[0]->time === DIN_Packages::get( 1 )['expires_at'] - 14 * DAY_IN_SECONDS, 'Reminder scheduled H-14.' );
check_mail( $jobs[1]->args === array( 1, 1, 'reminder_7', 1 ) && $jobs[1]->time === DIN_Packages::get( 1 )['expires_at'] - 7 * DAY_IN_SECONDS, 'Independent H-7 event uses its exact due date.' );
check_mail( $jobs[2]->args === array( 1, 1, 'expired', 1 ) && $jobs[2]->time === DIN_Packages::get( 1 )['expires_at'], 'Expiry event uses the exact end date.' );
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( count( $jobs ) === 3, 'Repeated scheduling does not duplicate jobs.' );

$relevant = new ReflectionMethod( DIN_Packages_Mail::class, 'relevant' );
$boundary_package = fixture_mail( 2000000000 );
foreach ( array(
	array( 'reminder', 1998790399, false ),
	array( 'reminder', 1998790400, true ),
	array( 'reminder', 1999395199, true ),
	array( 'reminder', 1999395200, false ),
	array( 'reminder', 1999999999, false ),
	array( 'reminder', 2000000000, false ),
	array( 'reminder_7', 1999395199, false ),
	array( 'reminder_7', 1999395200, true ),
	array( 'reminder_7', 1999999999, true ),
	array( 'reminder_7', 2000000000, false ),
	array( 'expired', 1999999999, false ),
	array( 'expired', 2000000000, true ),
	array( 'expired', 2000000001, true ),
) as [ $event, $now, $expected ] ) {
	check_mail( $relevant->invoke( null, $boundary_package, 1, $event, $now ) === $expected, "Exact event window: $event at $now." );
}

DIN_Packages::$rows[1]['expires_at'] = time() + 10 * DAY_IN_SECONDS;
DIN_Packages_Mail::send( 1, 1, 'reminder', 1 );
DIN_Packages_Mail::send( 1, 1, 'reminder', 1 );
check_mail( count( $sent ) === 1, 'Duplicate callback sends only one reminder.' );
check_mail( $source_reads >= 2, 'Both claim and transport guards refresh the cached order through its data store.' );
check_mail( str_contains( $sent[0][2], '&lt;Test&gt;' ) && str_contains( $sent[0][2], 'Heading &amp; Post' ), 'Email escapes product and heading.' );
check_mail( str_contains( $sent[0][2], 'wp-login.php' ), 'Email links to account login.' );
check_mail( DIN_Packages::get( 1 )['emails']['1:reminder']['state'] === 'sent', 'Successful transport is persisted.' );
check_mail( $sent[0][0] === 'test@example.invalid', 'Reminder recipient is the account owner.' );
check_mail( str_contains( $sent[0][1], 'within 14 days' ) && str_contains( $sent[0][2], 'within 14 days' ), 'Late H-14 subject and content state the reminder window, not an exact countdown.' );
$legacy_reminder = DIN_Packages::get( 1 )['emails']['1:reminder'];
DIN_Packages::$rows[1]['expires_at'] = time() + 5 * DAY_IN_SECONDS;
DIN_Packages_Mail::send( 1, 1, 'reminder', 1 );
DIN_Packages_Mail::send( 1, 1, 'reminder_7', 1 );
DIN_Packages_Mail::send( 1, 1, 'reminder_7', 1 );
check_mail( count( $sent ) === 2, 'Previously sent H-14 is retained and independent H-7 sends exactly once.' );
check_mail( DIN_Packages::get( 1 )['emails']['1:reminder'] === $legacy_reminder && DIN_Packages::get( 1 )['emails']['1:reminder_7']['state'] === 'sent', 'Generation-event keys preserve existing H-14 history alongside H-7.' );
check_mail( $sent[1][0] === 'test@example.invalid' && str_contains( $sent[1][2], 'wp-login.php' ), 'H-7 uses the same account recipient and login route.' );
check_mail( str_contains( $sent[1][1], 'within 7 days' ) && str_contains( $sent[1][2], 'within 7 days' ) && ! str_contains( $sent[1][2], 'within 14 days' ), 'Late H-7 content is distinct and does not claim an exact seven days remaining.' );
$expected_date = ( new DateTimeImmutable( '@' . DIN_Packages::get( 1 )['expires_at'] ) )->setTimezone( new DateTimeZone( 'Asia/Jakarta' ) )->format( 'Y-m-d H:i' );
check_mail( str_contains( $sent[1][2], $expected_date ), 'H-7 preserves the exact end date in the site time zone.' );
DIN_Packages::$rows[1]['expires_at'] = time() - 1;
DIN_Packages_Mail::send( 1, 1, 'expired', 1 );
DIN_Packages_Mail::send( 1, 1, 'expired', 1 );
check_mail( count( $sent ) === 3 && DIN_Packages::get( 1 )['emails']['1:expired']['state'] === 'sent', 'Expiry sends once independently of both sent reminders.' );
check_mail( str_contains( $sent[2][1], 'has expired' ) && str_contains( $sent[2][2], 'has expired' ) && ! str_contains( $sent[2][2], 'within' ), 'Expiry content uses the expired branch, not a reminder countdown.' );

$jobs = $sent = array();
DIN_Packages::$rows[1] = fixture_mail( time() + 5 * DAY_IN_SECONDS );
$queue_started = time();
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( count( $jobs ) === 2 && $jobs[0]->args === array( 1, 1, 'reminder_7', 1 ) && $jobs[1]->args === array( 1, 1, 'expired', 1 ), 'Late scheduling queues only H-7 and expiry, without duplicate or obsolete H-14 jobs.' );
check_mail( $jobs[0]->time >= $queue_started + 1 && $jobs[0]->time <= time() + 1, 'An overdue H-7 reminder is queued promptly.' );
DIN_Packages_Mail::send( 1, 1, 'reminder', 1 );
check_mail( ! $sent && ! DIN_Packages::get( 1 )['emails'], 'An already queued H-14 callback cannot claim or send in the H-7 window.' );
DIN_Packages_Mail::send( ...$jobs[0]->args );
check_mail( count( $sent ) === 1 && DIN_Packages::get( 1 )['emails']['1:reminder_7']['state'] === 'sent', 'Late queued H-7 sends normally.' );

$sent = array();
DIN_Packages::$rows[1] = fixture_mail( time() + 5 * DAY_IN_SECONDS );
foreach ( array( 'unknown', null, array(), new stdClass() ) as $invalid_event ) {
	DIN_Packages_Mail::send( 1, 1, $invalid_event );
}
check_mail( ! $sent && ! DIN_Packages::get( 1 )['emails'], 'Malformed or unknown events neither crash nor claim an email.' );

foreach ( array( 'failed', 'uncertain' ) as $legacy_state ) {
	$jobs = $sent = array();
	DIN_Packages::$rows[1] = fixture_mail( time() + 5 * DAY_IN_SECONDS );
	$legacy_email = array( 'state' => $legacy_state, 'attempt' => 3, 'retry_at' => time() + HOUR_IN_SECONDS );
	DIN_Packages::$rows[1]['emails']['1:reminder'] = $legacy_email;
	DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
	DIN_Packages_Mail::send( 1, 1, 'reminder_7', 1 );
	check_mail( count( $sent ) === 1 && DIN_Packages::get( 1 )['emails']['1:reminder'] === $legacy_email, "H-7 remains independent of a legacy H-14 $legacy_state outcome." );
}

$jobs = $sent = array();
$transport_ok = false;
DIN_Packages::$rows[1] = fixture_mail( time() + 10 * DAY_IN_SECONDS );
DIN_Packages_Mail::send( 1, 1, 'reminder', 1 );
DIN_Packages::$rows[1]['expires_at'] = time() + 5 * DAY_IN_SECONDS;
DIN_Packages::$rows[1]['emails']['1:reminder']['retry_at'] = 0;
$failed_h14 = DIN_Packages::get( 1 )['emails']['1:reminder'];
$transport_ok = true;
DIN_Packages_Mail::send( ...$jobs[0]->args );
check_mail( count( $sent ) === 1 && DIN_Packages::get( 1 )['emails']['1:reminder'] === $failed_h14, 'An H-14 retry crossing the H-7 cutoff does not send or consume an attempt.' );
DIN_Packages_Mail::send( 1, 1, 'reminder_7', 1 );
check_mail( count( $sent ) === 2 && DIN_Packages::get( 1 )['emails']['1:reminder_7']['attempt'] === 1, 'H-7 starts its own attempt after an obsolete H-14 retry.' );

foreach ( array( 'reminder' => 10, 'reminder_7' => 5 ) as $event => $days ) {
	$key = '1:' . $event;
	$sent = $logs = array();
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	DIN_Packages::$next_error = new WP_Error( 'package_storage', 'Unavailable test storage' );
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( ! $sent && count( $logs ) === 1, 'A failed claim never sends and logs the storage failure.' );

	$sent = array();
	DIN_Packages::$rows[1] = fixture_mail( time() - 1 );
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( ! $sent, 'Delayed H-14 reminder does not send after expiry.' );
	DIN_Packages_Mail::send( 1, 1, 'expired', 1 );
	DIN_Packages_Mail::send( 1, 1, 'expired', 1 );
	check_mail( count( $sent ) === 1, 'Expired notice sends once.' );

	foreach ( array( 'owner', 'deleted', 'pending', 'on-hold', 'cancelled', 'refunded' ) as $case ) {
		$sent = array();
		$composed = 0;
		$source_order = array( 'customer_id' => 'owner' === $case ? 99 : 7, 'status' => 'owner' === $case ? 'completed' : $case );
		if ( 'deleted' === $case ) { $source_order = null; }
		DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
		DIN_Packages_Mail::send( 1, 1, $event, 1 );
		check_mail( ! $sent && 0 === $composed, "Invalid source order cannot compose or send an email: $case." );
	}
	$source_order = array( 'customer_id' => 7, 'status' => 'completed' );

	$sent = array();
	$composed = 0;
	$source_read_error = true;
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( ! $sent && 0 === $composed, 'A source data-store read failure blocks composition and transport.' );
	$source_read_error = false;

	foreach ( array( 'owner', 'deleted', 'pending' ) as $case ) {
		$sent = array();
		$source_order = array( 'customer_id' => 7, 'status' => 'completed' );
		DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
		$before_send = function () use ( $case ) {
			$GLOBALS['source_order'] = 'deleted' === $case ? null : array( 'customer_id' => 'owner' === $case ? 99 : 7, 'status' => 'pending' === $case ? 'pending' : 'completed' );
		};
		DIN_Packages_Mail::send( 1, 1, $event, 1 );
		check_mail( ! $sent, "Immediate transport guard refreshes the source order: $case." );
	}
	$before_send = null;
	$source_order = array( 'customer_id' => 7, 'status' => 'completed' );

	foreach ( array( 'stale', 'lifetime', 'review', 'pending', 'early' ) as $case ) {
		$sent = array();
		DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
		if ( $case === 'stale' ) { DIN_Packages::$rows[1]['generation'] = 2; }
		if ( $case === 'lifetime' ) { DIN_Packages::$rows[1]['period'] = 'lifetime'; }
		if ( $case === 'review' ) { DIN_Packages::$rows[1]['review'] = 'Refund'; }
		if ( $case === 'pending' ) { DIN_Packages::$rows[1]['started_at'] = 0; }
		if ( $case === 'early' ) { DIN_Packages::$rows[1]['expires_at'] = time() + 20 * DAY_IN_SECONDS; }
		DIN_Packages_Mail::send( 1, 1, $event, 1 );
		check_mail( ! $sent, "No irrelevant email: $case." );
	}

	$jobs = $sent = array();
	$transport_ok = false;
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	for ( $attempt = 1; $attempt <= 4; $attempt++ ) {
		if ( isset( DIN_Packages::$rows[1]['emails'][$key] ) ) { DIN_Packages::$rows[1]['emails'][$key]['retry_at'] = 0; }
		DIN_Packages_Mail::send( 1, 1, $event, $attempt );
		if ( $attempt === 1 ) {
			DIN_Packages_Mail::send( 1, 1, $event, 1 );
			DIN_Packages_Mail::send( 1, 1, $event, 2 );
			check_mail( count( $sent ) === 1, 'A duplicate failed job or premature retry does not send.' );
		}
	}
	check_mail( count( $sent ) === 3 && count( $jobs ) === 2, 'Failed delivery retries with maximum three total attempts.' );
	check_mail( ! DIN_Packages_Mail::has_sending( DIN_Packages::get( 1 ) ), 'Completed failure releases lease.' );
	check_mail( count( $logs ) >= 3, 'Failures logged for operations.' );

	$jobs = $sent = array();
	$transport_throw = true;
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	DIN_Packages_Mail::send( 1, 1, $event, 2 );
	check_mail( count( $sent ) === 1 && ! $jobs, 'Ambiguous transport exceptions do not trigger an automatic duplicate.' );
	check_mail( DIN_Packages::get( 1 )['emails'][$key]['state'] === 'uncertain', 'Transport exception is recorded for review.' );
	$transport_throw = false;

	$sent = array();
	$transport_ok = true;
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	$before_send = function () { DIN_Packages::$rows[1]['generation'] = 2; };
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( ! $sent, 'Generation is re-read immediately before transport.' );
	$before_send = null;

	$sent = array();
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	$before_send = function () use ( $event ) {
		DIN_Packages::$rows[1]['expires_at'] = 'reminder' === $event ? time() + 5 * DAY_IN_SECONDS : time() - 1;
	};
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( ! $sent && DIN_Packages::get( 1 )['emails'][ $key ]['state'] === 'skipped', "A window closing during composition blocks transport: $event." );
	$before_send = null;

	$sent = array();
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	$before_send = function () use ( $key ) {
		DIN_Packages::$rows[1]['emails'][$key]['state'] = 'uncertain';
		DIN_Packages::$rows[1]['emails']['1:expired'] = array( 'state' => 'sending', 'lease_until' => time() + 60 );
	};
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( ! $sent, 'A different live lease cannot authorize an uncertain claim.' );
	$before_send = null;

	$sent = array();
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	$before_send = function () use ( $event ) {
		DIN_Packages_Mail::send( 1, 1, $event, 1 );
		DIN_Packages::$rows[1]['heading'] = 'Updated heading';
	};
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( count( $sent ) === 1, 'A simultaneous callback cannot reuse an active claim.' );
	check_mail( DIN_Packages::get( 1 )['heading'] === 'Updated heading', 'Email outcome preserves other concurrent package changes.' );
	$before_send = null;

	$jobs = $sent = array();
	DIN_Packages::$rows[1] = fixture_mail( time() + $days * DAY_IN_SECONDS );
	DIN_Packages::$rows[1]['emails'][$key] = array( 'state' => 'sending', 'token' => 'dead-worker', 'attempt' => 1, 'lease_until' => time() - 1 );
	DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
	DIN_Packages_Mail::send( 1, 1, $event, 1 );
	check_mail( ! $sent, 'Ambiguous crashed delivery is not sent twice.' );
	check_mail( DIN_Packages::get( 1 )['emails'][$key]['state'] === 'uncertain', 'Expired send lease needs manual delivery review.' );
}

$jobs = array();
DIN_Packages::$rows[1] = fixture_mail( time() + 20 * DAY_IN_SECONDS );
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
DIN_Packages::$rows[1]['period'] = 'lifetime';
DIN_Packages::$rows[1]['generation'] = 2;
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( ! $jobs, 'Upgrade cancels queued expiry jobs.' );

$jobs = array();
DIN_Packages::$rows[1] = fixture_mail( time() + 20 * DAY_IN_SECONDS );
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
as_schedule_single_action( time() + 1, 'din_packages_send_email', array( 2, 1, 'reminder', 1 ), 'din-package-lifecycle-2', true );
DIN_Packages::$rows[1]['generation'] = 2;
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( count( $jobs ) === 4, 'Renewal replaces old-generation jobs and preserves a different package group.' );
check_mail( ! array_filter( $jobs, fn( $j ) => $j->args[0] === 1 && $j->args[1] === 1 ), 'Every old-generation package job is cancelled.' );
$new_generation_jobs = array_values( array_map( fn( $j ) => $j->args, array_filter( $jobs, fn( $j ) => $j->args[0] === 1 ) ) );
check_mail( $new_generation_jobs === array( array( 1, 2, 'reminder', 1 ), array( 1, 2, 'reminder_7', 1 ), array( 1, 2, 'expired', 1 ) ), 'Renewal rebuilds all three distinct events for the new generation.' );
as_schedule_single_action( time() + 1, 'din_packages_send_email', array( 1, 3, 'reminder', 1 ), 'din-package-lifecycle-1', true );
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( count( $jobs ) === 5, 'Scheduling an older snapshot cannot cancel a newer generation job.' );

$sent = array();
DIN_Packages::$rows[1] = fixture_mail( time() + 5 * DAY_IN_SECONDS );
DIN_Packages::$rows[1]['generation'] = 2;
DIN_Packages::$rows[1]['emails']['1:reminder_7'] = array( 'state' => 'sent', 'attempt' => 1 );
DIN_Packages_Mail::send( 1, 2, 'reminder_7', 1 );
DIN_Packages_Mail::send( 1, 2, 'reminder_7', 1 );
check_mail( count( $sent ) === 1 && DIN_Packages::get( 1 )['emails']['2:reminder_7']['state'] === 'sent' && DIN_Packages::get( 1 )['emails']['1:reminder_7'] === array( 'state' => 'sent', 'attempt' => 1 ), 'New-generation H-7 sends once without overwriting the old-generation history.' );

$jobs = array();
DIN_Packages_Mail::ensure_reconcile();
DIN_Packages_Mail::ensure_reconcile();
check_mail( count( $jobs ) === 1, 'Reconciliation recurring registration is idempotent.' );

$jobs = array();
for ( $id = 1; $id <= 101; $id++ ) {
	DIN_Packages::$rows[ $id ] = fixture_mail( time() + 20 * DAY_IN_SECONDS );
	DIN_Packages::$rows[ $id ]['id'] = $id;
}
DIN_Packages_Mail::reconcile();
check_mail( count( $jobs ) === 301, 'Reconciliation bounds the first page and chains the next cursor.' );
DIN_Packages_Mail::reconcile( 100 );
check_mail( count( $jobs ) === 304, 'Reconciliation repairs schedules on the next page.' );
$jobs = $sent = array();
DIN_Packages::$rows = array( 1 => fixture_mail( time() + 20 * DAY_IN_SECONDS ) );
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( count( $jobs ) === 3, 'All three events are queued before stopping.' );
DIN_Packages::$rows[1]['stopped_at'] = time();
DIN_Packages::$rows[1]['generation'] = 2;
DIN_Packages_Mail::schedule( DIN_Packages::get( 1 ) );
check_mail( ! $jobs, 'Stopping cancels reminders and does not schedule new generation jobs.' );
foreach ( array( 'reminder' => 10, 'reminder_7' => 5, 'expired' => -1 ) as $event => $days ) {
	DIN_Packages::$rows[1]['expires_at'] = time() + $days * DAY_IN_SECONDS;
	DIN_Packages_Mail::send( 1, 1, $event );
	DIN_Packages_Mail::send( 1, 2, $event );
	check_mail( ! $sent, "Neither old nor current generation may email a stopped package: $event." );
}
echo "PASS: H-14/H-7/expiry windows, independent delivery, race guards, cancellation, reconciliation, and stopped package suppression.\n";
