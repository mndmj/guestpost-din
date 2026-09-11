<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);
?>

<section class="gpm-welcome-card" aria-labelledby="gpm-welcome-title">
	<div class="gpm-welcome-card__identity">
		<?php
		echo get_avatar(
			$current_user->ID,
			72,
			'',
			$current_user->display_name,
			array(
				'class' => 'gpm-welcome-card__avatar',
			)
		);
		?>

		<div>
			<p class="gpm-welcome-card__label">
				Welcome back,
			</p>

			<h2 id="gpm-welcome-title" class="gpm-welcome-card__name">
				<?php echo esc_html( $current_user->display_name ); ?>
			</h2>
		</div>
	</div>

	<a href="<?php echo esc_url( wc_logout_url() ); ?>">
		<div class="gpm-welcome-card__logout">Logout</div>
	</a>
</section>

<?php
$customer_id = $current_user->ID;

$count_orders_by_status = static function ( array $statuses ) use ( $customer_id ) {
	$result = wc_get_orders(
		array(
			'customer_id' => $customer_id,
			'status' => $statuses,
			'limit' => 1,
			'paginate' => true,
			'return' => 'ids',
		)
	);

	return isset( $result->total ) ? (int) $result->total : 0;
};

$summary_cards = array(
	array(
		'label' => 'Total Order',
		'count' => (int) wc_get_customer_order_count( $customer_id ),
		'type' => 'total',
	),
	array(
		'label' => 'Waiting Payment',
		'count' => $count_orders_by_status( array( 'pending', 'on-hold' ) ),
		'type' => 'payment',
	),
	array(
		'label' => 'On Process',
		'count' => $count_orders_by_status( array( 'processing' ) ),
		'type' => 'processing',
	),
	array(
		'label' => 'Finish',
		'count' => $count_orders_by_status( array( 'completed' ) ),
		'type' => 'completed',
	),
);
?>

<section class="gpm-summary" aria-label="Order summary">
	<?php foreach ( $summary_cards as $card ) : ?>
		<a class="gpm-summary-card gpm-summary-card--<?php echo esc_attr( sanitize_html_class( $card['type'] ) ); ?>"
			href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
			<span class="gpm-summary-card__label">
				<?php echo esc_html( $card['label'] ); ?>
			</span>

			<strong class="gpm-summary-card__count">
				<?php echo esc_html( $card['count'] ); ?>
			</strong>

			<span class="gpm-summary-card__action">
				See Details →
			</span>
		</a>
	<?php endforeach; ?>
</section>

<?php
$action_orders = array_filter(
	wc_get_orders(
		array(
			'customer_id' => get_current_user_id(),
			'status' => array( 'pending', 'failed' ),
			'limit' => 3,
			'orderby' => 'date',
			'order' => 'DESC',
		)
	),
	static function ( $order ) {
		return $order instanceof WC_Order && $order->needs_payment();
	}
);
?>

<?php if ( $action_orders ) : ?>
	<section class="gpm-action-required" aria-labelledby="gpm-action-title">
		<header class="gpm-panel-header">
			<div>
				<span class="gpm-panel-header__eyebrow">ACTION REQUIRED</span>
				<h2 id="gpm-action-title">Action Required</h2>
			</div>
		</header>

		<div class="gpm-action-list">
			<?php foreach ( $action_orders as $action_order ) : ?>
				<?php
				$status = $action_order->get_status();
				$message = 'failed' === $status
					? 'Payment failed, please try again.'
					: 'Complete payment for this order.';
				?>

				<article class="gpm-action-item gpm-action-item--<?php echo esc_attr( $status ); ?>">
					<div class="gpm-action-item__content">
						<span class="gpm-action-item__status">
							<?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
						</span>

						<h3><?php echo esc_html( $message ); ?></h3>

						<p>
							Order #<?php echo esc_html( $action_order->get_order_number() ); ?>
							&middot;
							<?php echo wp_kses_post( $action_order->get_formatted_order_total() ); ?>
						</p>
					</div>

					<div class="gpm-action-item__buttons">
						<a href="<?php echo esc_url( $action_order->get_checkout_payment_url() ); ?>">
							<div class="gpm-action-button gpm-action-button--pay">
								Payment Now
							</div>
						</a>

						<a href="<?php echo esc_url( $action_order->get_view_order_url() ); ?>">
							<div class="gpm-action-button gpm-action-button--view">
								Check Order
							</div>
						</a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
$progress_steps = array(
	'Order Received',
	'Awaiting Payment',
	'Processing',
	'Completed',
);

$status_step_map = array(
	'pending' => 0,
	'on-hold' => 1,
	'processing' => 2,
	'completed' => 3,
);

$progress_orders = wc_get_orders(
	array(
		'customer_id' => $current_user->ID,
		'status' => array_keys( $status_step_map ),
		'limit' => 1,
		'orderby' => 'date',
		'order' => 'DESC',
	)
);

$invoice_order = $progress_orders[0] ?? false;
// A newer Completed order must not hide an unfinished order's progress.
$progress_orders = wc_get_orders(
	array(
		'customer_id' => $current_user->ID,
		'status' => array( 'pending', 'on-hold', 'processing' ),
		'limit' => 1,
		'orderby' => 'date',
		'order' => 'DESC',
	)
);
$progress_order = $progress_orders[0] ?? false;
$current_status = $progress_order ? $progress_order->get_status() : '';
$current_step = $status_step_map[ $current_status ] ?? 0;

$history_orders = wc_get_orders(
	array(
		'customer_id' => $current_user->ID,
		'limit' => 5,
		'orderby' => 'date',
		'order' => 'DESC',
	)
);

$running_packages = array();
if ( class_exists( 'DIN_Packages_Customer' ) ) {
	// This template owns the shared slot; leave other dashboard callbacks intact.
	remove_action( 'woocommerce_account_dashboard', array( 'DIN_Packages_Customer', 'dashboard' ) );
	if ( ! $progress_order ) {
		$running_packages = DIN_Packages_Customer::running_packages( $customer_id );
	}
}
do_action( 'woocommerce_account_dashboard' );
?>

<?php if ( $running_packages ) : ?>
	<?php DIN_Packages_Customer::dashboard( $running_packages ); ?>
<?php else : ?>
<section class="gpm-order-progress" aria-labelledby="gpm-progress-title">
	<header class="gpm-panel-header">
		<div>
			<span class="gpm-panel-header__eyebrow">
				Latest Order
			</span>

			<h2 id="gpm-progress-title">
				Order Progress
			</h2>
		</div>

		<?php if ( $progress_order ) : ?>
			<a class="gpm-panel-header__link" href="<?php echo esc_url( $progress_order->get_view_order_url() ); ?>">
				Order #<?php echo esc_html( $progress_order->get_order_number() ); ?>
			</a>
		<?php endif; ?>
	</header>

	<?php if ( $progress_order ) : ?>
		<ol class="gpm-stepper">
			<?php foreach ( $progress_steps as $step_index => $step_label ) : ?>
				<?php
				if ( 'completed' === $current_status || $step_index < $current_step ) {
					$step_state = 'completed';
				} elseif ( $step_index === $current_step ) {
					$step_state = 'current';
				} else {
					$step_state = 'upcoming';
				}
				?>

				<li class="gpm-stepper__step gpm-stepper__step--<?php echo esc_attr( $step_state ); ?>" <?php echo 'current' === $step_state ? 'aria-current="step"' : ''; ?>>
					<span class="gpm-stepper__marker">
						<?php
						echo 'completed' === $step_state
							? '✓'
							: esc_html( $step_index + 1 );
						?>
					</span>

					<span class="gpm-stepper__label">
						<?php echo esc_html( $step_label ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php else : ?>
		<p class="gpm-panel-empty">
			No active orders yet.
		</p>
	<?php endif; ?>
</section>
<?php endif; ?>

<section class="gpm-order-history" aria-labelledby="gpm-history-title">
	<header class="gpm-panel-header">
		<div>
			<span class="gpm-panel-header__eyebrow">
				RECENT ACTIVITY
			</span>

			<h2 id="gpm-history-title">
				Recent Orders
			</h2>
		</div>

		<a class="gpm-panel-header__link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">
			View All
		</a>
	</header>

	<?php if ( $history_orders ) : ?>
		<div class="gpm-history-list">
			<?php foreach ( $history_orders as $history_order ) : ?>
				<?php
				$history_status = $history_order->get_status();
				$order_date = $history_order->get_date_created();
				?>

				<article class="gpm-history-item">
					<div class="gpm-history-item__identity">
						<strong>
							Order #<?php echo esc_html( $history_order->get_order_number() ); ?>
						</strong>

						<?php if ( $order_date ) : ?>
							<time datetime="<?php echo esc_attr( $order_date->date( 'c' ) ); ?>">
								<?php echo esc_html( wc_format_datetime( $order_date ) ); ?>
							</time>
						<?php endif; ?>
					</div>

					<span
						class="gpm-history-status gpm-history-status--<?php echo esc_attr( sanitize_html_class( $history_status ) ); ?>">
						<?php echo esc_html( wc_get_order_status_name( $history_status ) ); ?>
					</span>

					<strong class="gpm-history-item__total">
						<?php echo wp_kses_post( $history_order->get_formatted_order_total() ); ?>
					</strong>

					<a class="gpm-history-item__link" href="<?php echo esc_url( $history_order->get_view_order_url() ); ?>">
						View
					</a>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="gpm-panel-empty">
			No cancelled, failed, or refunded orders.
		</p>
	<?php endif; ?>
</section>

<?php if ( $invoice_order ) : ?>
	<?php
	$invoice_date = $invoice_order->get_date_created();
	$payment_method = $invoice_order->get_payment_method_title();
	?>

	<section class="gpm-invoice-card" aria-labelledby="gpm-invoice-title">
		<header class="gpm-panel-header">
			<div>
				<span class="gpm-panel-header__eyebrow">
					BILLING SUMMARY
				</span>

				<h2 id="gpm-invoice-title">
					Invoice
				</h2>
			</div>

			<span
				class="gpm-history-status gpm-history-status--<?php echo esc_attr( sanitize_html_class( $invoice_order->get_status() ) ); ?>">
				<?php echo esc_html( wc_get_order_status_name( $invoice_order->get_status() ) ); ?>
			</span>
		</header>

		<dl class="gpm-invoice-card__details">
			<div class="gpm-invoice-card__field">
				<dt>Order Number</dt>
				<dd>#<?php echo esc_html( $invoice_order->get_order_number() ); ?></dd>
			</div>

			<div class="gpm-invoice-card__field">
				<dt>Date</dt>
				<dd>
					<?php echo $invoice_date ? esc_html( wc_format_datetime( $invoice_date ) ) : '&mdash;'; ?>
				</dd>
			</div>

			<div class="gpm-invoice-card__field">
				<dt>Payment Method</dt>
				<dd><?php echo esc_html( $payment_method ?: 'Not available' ); ?></dd>
			</div>

			<div class="gpm-invoice-card__field">
				<dt>Total</dt>
				<dd><?php echo wp_kses_post( $invoice_order->get_formatted_order_total() ); ?></dd>
			</div>
		</dl>

		<div class="gpm-invoice-card__actions">
			<?php if ( $invoice_order->needs_payment() ) : ?>
				<a class="gpm-invoice-card__button gpm-invoice-card__button--pay"
					href="<?php echo esc_url( $invoice_order->get_checkout_payment_url() ); ?>">
					Pay Now
				</a>
			<?php endif; ?>

			<a href="<?php echo esc_url( $invoice_order->get_view_order_url() ); ?>">
				<div class="gpm-invoice-card__button gpm-invoice-card__button--view">View Invoice</div>
			</a>
		</div>
	</section>
<?php endif; ?>

<?php
$guest_post_orders = wc_get_orders(
	array(
		'customer_id' => get_current_user_id(),
		'status' => 'completed',
		'limit' => 1,
		'orderby' => 'date',
		'order' => 'DESC',
		'meta_query' => array(
			array(
				'key' => '_gpm_published_url',
				'value' => '',
				'compare' => '!=',
			),
		),
	)
);

$guest_post_order = $guest_post_orders[0] ?? false;
?>

<?php if ( $guest_post_order ) : ?>
	<?php
	$published_url = $guest_post_order->get_meta( '_gpm_published_url' );
	$anchor_text = $guest_post_order->get_meta( '_gpm_anchor_text' );
	$published_at = $guest_post_order->get_meta( '_gpm_published_at' );
	$link_status = $guest_post_order->get_meta( '_gpm_link_status' );

	$link_status = in_array(
		$link_status,
		array( 'live', 'removed' ),
		true
	) ? $link_status : 'live';

	$publisher_domain = wp_parse_url( $published_url, PHP_URL_HOST );
	$published_time = $published_at ? strtotime( $published_at ) : false;
	$display_date = $published_time
		? wp_date( get_option( 'date_format' ), $published_time )
		: '—';
	?>

	<section class="gpm-guest-result gpm-guest-result--<?php echo esc_attr( $link_status ); ?>"
		data-status="<?php echo esc_attr( $link_status ); ?>" aria-labelledby="gpm-guest-result-title">
		<header class="gpm-panel-header">
			<div>
				<span class="gpm-panel-header__eyebrow">
					PUBLICATION RESULT
				</span>

				<h2 id="gpm-guest-result-title">
					Results Guest Post
				</h2>
			</div>

			<span class="gpm-guest-result__status">
				<?php echo esc_html( ucfirst( $link_status ) ); ?>
			</span>
		</header>

		<div class="gpm-guest-result__details">
			<div class="gpm-guest-result__field">
				<span>Publisher</span>
				<strong>
					<?php echo esc_html( $publisher_domain ?: '—' ); ?>
				</strong>
			</div>

			<div class="gpm-guest-result__field">
				<span>Anchor Text</span>
				<strong>
					<?php echo esc_html( $anchor_text ?: '—' ); ?>
				</strong>
			</div>

			<div class="gpm-guest-result__field">
				<span>Publication Date</span>
				<strong><?php echo esc_html( $display_date ); ?></strong>
			</div>

			<div class="gpm-guest-result__field">
				<span>Order</span>
				<strong>
					#<?php echo esc_html( $guest_post_order->get_order_number() ); ?>
				</strong>
			</div>
		</div>

		<?php if ( 'live' === $link_status ) : ?>
			<div class="gpm-quest-result__action">
				<a data-published-link href="<?php echo esc_url( $published_url ); ?>" target="_blank"
					rel="noopener noreferrer external">
					<div class="gpm-guest-result__button">
						View Published Post
					</div>
				</a>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php
$recent_reports = array();

if (
	class_exists( 'DIN_Order_Attach_Storage' ) &&
	class_exists( 'DIN_Order_Attach_Download' )
) {
	// ponytail: cukup untuk buyer biasa; tambahkan cache hanya jika akun memiliki ratusan order.
	$report_orders = wc_get_orders(
		array(
			'customer_id' => get_current_user_id(),
			'limit' => -1,
			'return' => 'objects',
			'meta_query' => array(
				array(
					'key' => DIN_Order_Attach_Storage::META_KEY,
					'compare' => 'EXISTS',
				),
			),
		)
	);

	foreach ( $report_orders as $report_order ) {
		$records = $report_order->get_meta(
			DIN_Order_Attach_Storage::META_KEY
		);

		if ( ! is_array( $records ) ) {
			continue;
		}

		foreach ( $records as $record ) {
			if (
				! is_array( $record ) ||
				empty( $record['id'] ) ||
				empty( $record['name'] ) ||
				! is_scalar( $record['id'] ) ||
				! is_scalar( $record['name'] )
			) {
				continue;
			}

			$file_name = sanitize_file_name( (string) $record['name'] );

			if ( '' === $file_name ) {
				continue;
			}

			$uploaded_at = isset( $record['uploaded_at'] ) &&
				is_string( $record['uploaded_at'] )
				? $record['uploaded_at']
				: '';

			$timestamp = $uploaded_at ? strtotime( $uploaded_at ) : false;

			$recent_reports[] = array(
				'id' => sanitize_text_field( (string) $record['id'] ),
				'name' => $file_name,
				'mime' => sanitize_mime_type( (string) ( $record['mime'] ?? '' ) ),
				'size' => absint( $record['size'] ?? 0 ),
				'timestamp' => false !== $timestamp ? $timestamp : 0,
				'order_id' => $report_order->get_id(),
				'order_number' => $report_order->get_order_number(),
			);
		}
	}

	usort(
		$recent_reports,
		static function ( $first, $second ) {
			return $second['timestamp'] <=> $first['timestamp'];
		}
	);

	$recent_reports = array_slice( $recent_reports, 0, 5 );
}
?>

<section class="gpm-download-report" aria-labelledby="gpm-download-report-title">
	<header class="gpm-panel-header">
		<div>
			<span class="gpm-panel-header__eyebrow">
				DOWNLOADS &amp; REPORTS
			</span>

			<h2 id="gpm-download-report-title">
				Download dan Report
			</h2>
		</div>
	</header>

	<?php if ( $recent_reports ) : ?>
		<div class="gpm-report-list">
			<?php foreach ( $recent_reports as $report ) : ?>
				<?php
				$file_type = strtoupper(
					pathinfo( $report['name'], PATHINFO_EXTENSION )
				);

				$download_url = DIN_Order_Attach_Download::get_url(
					$report['order_id'],
					$report['id']
				);

				$is_image = in_array(
					$report['mime'],
					array( 'image/jpeg', 'image/png' ),
					true
				);

				$is_image = in_array(
					$report['mime'],
					array( 'image/jpeg', 'image/png' ),
					true
				);
				?>

				<article class="gpm-report-item" data-uploaded-at="<?php echo esc_attr( $report['timestamp'] ); ?>"
					data-file-mime="<?php echo esc_attr( $report['mime'] ); ?>">
					<div class="gpm-report-item__identity">
						<div class="gpm-report-item__preview">
							<?php if ( $is_image ) : ?>
								<a href="<?php echo esc_url( $download_url ); ?>" target="_blank" rel="noopener noreferrer"
									aria-label="<?php echo esc_attr( 'View ' . $report['name'] ); ?>">
									<img class="gpm-report-item__thumbnail" src="<?php echo esc_url( $download_url ); ?>" alt=""
										loading="lazy" width="64" height="64">
								</a>
							<?php else : ?>
								<span class="gpm-report-item__type">
									<?php echo esc_html( $file_type ?: 'FILE' ); ?>
								</span>
							<?php endif; ?>
						</div>

						<div>
							<h3><?php echo esc_html( $report['name'] ); ?></h3>

							<p>
								Order #<?php echo esc_html( $report['order_number'] ); ?>
								&bull;

								<?php
								echo esc_html(
									$report['timestamp']
									? wp_date(
										get_option( 'date_format' ),
										$report['timestamp']
									)
									: '—'
								);
								?>

								&bull;
								<?php echo esc_html( size_format( $report['size'] ) ); ?>
							</p>
						</div>
					</div>

					<div class="gpm-report-item__actions">
						<a data-report-action data-report-view href="<?php echo esc_url( $download_url ); ?>" target="_blank"
							rel="noopener noreferrer" aria-label="<?php echo esc_attr( 'View ' . $report['name'] ); ?>">
							<div class="gpm-report-item__button gpm-report-item__button--view">
								View
							</div>
						</a>

						<a data-report-download href="<?php echo esc_url( $download_url ); ?>"
							download="<?php echo esc_attr( $report['name'] ); ?>"
							aria-label="<?php echo esc_attr( 'Download ' . $report['name'] ); ?>">
							<div class="gpm-report-item__button gpm-report-item__button--download">Download</div>
						</a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="gpm-panel-empty">
			No reports are available yet.
		</p>
	<?php endif; ?>
</section>

<p>
	<?php
	/* translators: 1: Orders URL 2: Address URL 3: Account URL. */
	$dashboard_desc = __( 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">billing address</a>, and <a href="%3$s">edit your password and account details</a>.', 'woocommerce' );
	if ( wc_shipping_enabled() ) {
		/* translators: 1: Orders URL 2: Addresses URL 3: Account URL. */
		$dashboard_desc = __( 'From your account dashboard you can view your <a href="%1$s">recent orders</a>, manage your <a href="%2$s">shipping and billing addresses</a>, and <a href="%3$s">edit your password and account details</a>.', 'woocommerce' );
	}
	printf(
		wp_kses( $dashboard_desc, $allowed_html ),
		esc_url( wc_get_endpoint_url( 'orders' ) ),
		esc_url( wc_get_endpoint_url( 'edit-address' ) ),
		esc_url( wc_get_endpoint_url( 'edit-account' ) )
	);
	?>

	<?php
	$latest_notifications = function_exists(
		'gpm_get_customer_notifications'
	)
		? gpm_get_customer_notifications( get_current_user_id(), 5 )
		: array();
	?>

<section class="gpm-latest-notifications" aria-labelledby="gpm-latest-notifications-title">
	<header class="gpm-panel-header">
		<div>
			<span class="gpm-panel-header__eyebrow">
				LATEST UPDATES
			</span>

			<h2 id="gpm-latest-notifications-title">
				News Notification
			</h2>
		</div>

		<a class="gpm-panel-header__link"
			href="<?php echo esc_url( wc_get_account_endpoint_url( 'notifications' ) ); ?>">
			View All
		</a>
	</header>

	<?php gpm_render_notification_list( $latest_notifications ); ?>
</section>
</p>

<?php
/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_before_my_account' );

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_after_my_account' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
