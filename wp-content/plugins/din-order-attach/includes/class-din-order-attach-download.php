<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DIN_Order_Attach_Download {
	private $storage;

	public function __construct( $storage = null ) {
		$this->storage = $storage ?: new DIN_Order_Attach_Storage();
	}

	public static function boot() {
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'render_order_attachments' ) );
		add_action( 'admin_post_din_order_attach_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_din_order_attach_download', array( __CLASS__, 'redirect_to_login' ) );
	}

	public static function get_url( $order_id, $attachment_id ) {
		return add_query_arg(
			array(
				'action'        => 'din_order_attach_download',
				'order_id'      => absint( $order_id ),
				'attachment_id' => sanitize_text_field( $attachment_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function get_login_url( $order_id, $attachment_id ) {
		return wp_login_url( self::get_url( $order_id, $attachment_id ) );
	}

	public function authorize( $order, $attachment_id, $user_id, $can_manage ) {
		if ( ! $this->can_access_order( $order, $user_id, $can_manage ) ) {
			return $this->unavailable();
		}

		foreach ( $this->storage->get_order_files( $order ) as $record ) {
			if ( empty( $record['id'] ) || (string) $record['id'] !== (string) $attachment_id ) {
				continue;
			}

			$path = $this->storage->resolve_path( $record['path'] ?? '' );
			if ( is_wp_error( $path ) ) {
				return $this->unavailable();
			}
			$inspection = $this->storage->inspect_stored_file( $record, $path );
			if ( is_wp_error( $inspection ) ) {
				return $this->unavailable();
			}
			$record['mime'] = $inspection['mime'];
			$record['size'] = $inspection['size'];

			return array(
				'record' => $record,
				'path'   => $path,
			);
		}

		return $this->unavailable();
	}

	public function get_response_headers( $authorized ) {
		$record = $authorized['record'];
		$name   = sanitize_file_name( $record['name'] ?? '' ) ?: 'attachment';
		$mime   = sanitize_mime_type( $record['mime'] ?? '' ) ?: 'application/octet-stream';
		$inline = in_array( $mime, array( 'application/pdf', 'image/jpeg', 'image/png' ), true );

		return array(
			'Content-Type'              => $mime,
			'Content-Length'            => (string) filesize( $authorized['path'] ),
			'Content-Disposition'       => ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode( $name ),
			'X-Content-Type-Options'    => 'nosniff',
			'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0',
			'Pragma'                    => 'no-cache',
			'Expires'                   => '0',
		);
	}

	public function stream( $authorized ) {
		foreach ( $this->get_response_headers( $authorized ) as $name => $value ) {
			header( $name . ': ' . $value );
		}

		return readfile( $authorized['path'] );
	}

	public static function render_order_attachments( $order, $user_id = null, $can_manage = null, $storage = null ) {
		$service    = new self( $storage );
		$user_id    = null === $user_id ? get_current_user_id() : absint( $user_id );
		$can_manage = null === $can_manage ? current_user_can( 'manage_woocommerce' ) : (bool) $can_manage;
		if ( ! $service->can_access_order( $order, $user_id, $can_manage ) ) {
			return;
		}

		$records = $service->storage->get_order_files( $order );
		if ( ! $records ) {
			return;
		}
		?>
		<section class="woocommerce-order-details din-order-attachments">
			<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Order attachments', 'din-order-attach' ); ?></h2>
			<table class="woocommerce-table shop_table">
				<thead><tr>
					<th><?php esc_html_e( 'File', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Size', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Uploaded at', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Action', 'din-order-attach' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $records as $record ) : ?>
					<?php if ( empty( $record['id'] ) || empty( $record['name'] ) ) { continue; } ?>
					<tr>
						<td><?php echo esc_html( $record['name'] ); ?></td>
						<td><?php echo esc_html( size_format( absint( $record['size'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( empty( $record['uploaded_at'] ) ? '—' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record['uploaded_at'] ) ) ); ?></td>
						<td>
							<a
								class="woocommerce-button button"
								href="<?php echo esc_url( self::get_url( $order->get_id(), $record['id'] ) ); ?>"
								target="_blank"
								rel="noopener noreferrer"
							>
								<?php esc_html_e( 'View / Download', 'din-order-attach' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	public static function handle_download() {
		list( $order_id, $attachment_id ) = self::get_request_values();
		$service    = new self();
		$authorized = $service->authorize(
			wc_get_order( $order_id ),
			$attachment_id,
			get_current_user_id(),
			current_user_can( 'manage_woocommerce' )
		);

		if ( is_wp_error( $authorized ) ) {
			wp_die(
				esc_html__( 'Attachment unavailable.', 'din-order-attach' ),
				esc_html__( 'Download unavailable', 'din-order-attach' ),
				array( 'response' => 404 )
			);
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$service->stream( $authorized );
		exit;
	}

	public static function redirect_to_login() {
		list( $order_id, $attachment_id ) = self::get_request_values();

		wp_safe_redirect( self::get_login_url( $order_id, $attachment_id ) );
		exit;
	}

	private static function get_request_values() {
		$order_id_input      = $_GET['order_id'] ?? 0;
		$attachment_id_input = $_GET['attachment_id'] ?? '';

		return array(
			is_scalar( $order_id_input ) ? absint( wp_unslash( $order_id_input ) ) : 0,
			is_string( $attachment_id_input ) ? sanitize_text_field( wp_unslash( $attachment_id_input ) ) : '',
		);
	}

	private function can_access_order( $order, $user_id, $can_manage ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_user_id' ) ) {
			return false;
		}

		$user_id  = absint( $user_id );
		$owner_id = absint( $order->get_user_id() );

		return (bool) $can_manage || ( $user_id && $user_id === $owner_id );
	}

	private function unavailable() {
		return new WP_Error( 'din_order_attach_unavailable', __( 'Attachment unavailable.', 'din-order-attach' ) );
	}
}
