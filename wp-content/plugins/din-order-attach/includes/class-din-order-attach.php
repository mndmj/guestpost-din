<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DIN_Order_Attach {
	private static $instance;

	private $booted = false;

	private function __construct() {}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot() {
		add_action( 'add_meta_boxes', array( $this, 'register_order_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'post_edit_form_tag', array( $this, 'render_form_enctype' ) );
		add_action( 'order_edit_form_tag', array( $this, 'render_form_enctype' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'process_order_upload' ), 50, 2 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'render_email_attachments' ), 10, 4 );
		add_action( 'admin_post_din_order_attach_delete', array( $this, 'handle_delete' ) );
		add_action( 'woocommerce_before_delete_order', array( $this, 'cleanup_deleted_order' ), 10, 2 );
		DIN_Order_Attach_Download::boot();
		$this->booted = true;
	}

	public function is_booted() {
		return $this->booted;
	}

	public static function get_delete_nonce_action( $order_id, $attachment_id ) {
		return 'din_order_attach_delete_' . absint( $order_id ) . '_' . sanitize_text_field( $attachment_id );
	}

	public static function get_delete_url( $order_id, $attachment_id ) {
		$url = add_query_arg(
			array(
				'action'        => 'din_order_attach_delete',
				'order_id'      => absint( $order_id ),
				'attachment_id' => sanitize_text_field( $attachment_id ),
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, self::get_delete_nonce_action( $order_id, $attachment_id ) );
	}

	public function register_order_meta_box() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box(
				'din-order-attach',
				__( 'DIN Order Attach', 'din-order-attach' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'normal',
				'default'
			);
		}
	}

	public function enqueue_admin_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		if ( ! in_array( $screen->id, array_unique( $screens ), true ) ) {
			return;
		}

		$css_file = dirname( DIN_ORDER_ATTACH_FILE ) . '/assets/admin-order.css';

		if (
			\Automattic\WooCommerce\Utilities\OrderUtil::is_order_edit_screen()
			&& is_readable( $css_file )
		) {
			wp_enqueue_style(
				'din-order-admin',
				plugins_url( 'assets/admin-order.css', DIN_ORDER_ATTACH_FILE ),
				array(),
				(string) filemtime( $css_file )
			);
		}

		wp_enqueue_script(
			'din-order-attach-preview',
			plugins_url( 'assets/admin-preview.js', DIN_ORDER_ATTACH_FILE ),
			array(),
			'1.6.0',
			true
		);
	}

	public function render_form_enctype( $post_or_order = null ) {
		$is_order = $post_or_order instanceof WC_Order;
		if ( ! $is_order && isset( $post_or_order->post_type ) ) {
			$is_order = in_array( $post_or_order->post_type, wc_get_order_types( 'order-meta-boxes' ), true );
		}

		if ( ! $is_order ) {
			return;
		}

		echo ' enctype="multipart/form-data"';
	}

	public function render_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID ?? 0 );
		if ( ! $order ) {
			return;
		}

		$records = ( new DIN_Order_Attach_Storage() )->get_order_files( $order );
		wp_nonce_field( 'din_order_attach_upload', 'din_order_attach_nonce' );
		?>
		<p>
			<label for="din-order-attach-files"><strong><?php esc_html_e( 'Add attachments', 'din-order-attach' ); ?></strong></label><br>
			<input id="din-order-attach-files" name="din_order_attach_files[]" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png,.zip" data-max-size="<?php echo esc_attr( DIN_Order_Attach_Storage::MAX_SIZE ); ?>" multiple>
		</p>
		<p class="description"><?php esc_html_e( 'Maximum 10 MB per file. Files are sent as one batch when the order is updated.', 'din-order-attach' ); ?></p>
		<div
			id="din-order-attach-alert"
			class="notice notice-error inline"
			role="alert"
			data-invalid-type-template="<?php echo esc_attr__( '%s was removed: file type is not allowed.', 'din-order-attach' ); ?>"
			data-too-large-template="<?php echo esc_attr__( '%s was removed: file exceeds 10 MB.', 'din-order-attach' ); ?>"
			hidden
		></div>
		<style>
			#din-order-attach-preview[hidden] { display: none; }
			#din-order-attach-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin: 12px 0; }
			.din-order-attach-preview__item { display: flex; flex-direction: column; margin: 0; padding: 8px; border: 1px solid #dcdcde; background: #fff; }
			.din-order-attach-preview__item img { display: block; width: 100%; height: 110px; object-fit: cover; }
			.din-order-attach-preview__file-type { display: flex; align-items: center; justify-content: center; height: 110px; background: #f0f0f1; color: #50575e; font-size: 18px; font-weight: 600; }
			.din-order-attach-preview__item figcaption { margin-top: 6px; overflow-wrap: anywhere; font-size: 12px; }
			.din-order-attach-preview__remove { align-self: flex-start; margin-top: 8px; }
		</style>
		<div id="din-order-attach-preview" class="din-order-attach-preview" data-remove-label="<?php echo esc_attr__( 'Remove', 'din-order-attach' ); ?>" aria-live="polite" hidden></div>
		<?php if ( $records ) : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'File', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Type', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Size', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Uploaded by', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Uploaded at', 'din-order-attach' ); ?></th>
					<th><?php esc_html_e( 'Action', 'din-order-attach' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $records as $record ) : ?>
					<?php $uploader = get_userdata( absint( $record['uploaded_by'] ?? 0 ) ); ?>
					<tr>
						<td><?php echo esc_html( $record['name'] ?? '' ); ?></td>
						<td><?php echo esc_html( $record['mime'] ?? '' ); ?></td>
						<td><?php echo esc_html( size_format( absint( $record['size'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( $uploader ? $uploader->display_name : '—' ); ?></td>
						<td><?php echo esc_html( empty( $record['uploaded_at'] ) ? '—' : wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record['uploaded_at'] ) ) ); ?></td>
						<td>
							<?php if ( ! empty( $record['id'] ) ) : ?>
								<a
									class="button"
									href="<?php echo esc_url( DIN_Order_Attach_Download::get_url( $order->get_id(), $record['id'] ) ); ?>"
									target="_blank"
									rel="noopener noreferrer"
								>
									<?php esc_html_e( 'View / Download', 'din-order-attach' ); ?>
								</a>
								<a class="button" href="<?php echo esc_url( self::get_delete_url( $order->get_id(), $record['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this attachment permanently?', 'din-order-attach' ) ); ?>');"><?php esc_html_e( 'Delete', 'din-order-attach' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	public function process_order_upload( $order_id, $post_or_order = null ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$files       = isset( $_FILES['din_order_attach_files'] ) && is_array( $_FILES['din_order_attach_files'] ) ? $_FILES['din_order_attach_files'] : array();
		$nonce_input = $_POST['din_order_attach_nonce'] ?? '';
		$nonce       = is_string( $nonce_input ) ? sanitize_text_field( wp_unslash( $nonce_input ) ) : '';

		return $this->save_uploaded_files( $order, $files, $nonce, current_user_can( 'manage_woocommerce' ) );
	}

	public function save_uploaded_files( $order, $files, $nonce, $authorized, $storage = null ) {
		if ( ! $authorized ) {
			return new WP_Error( 'din_order_attach_forbidden', __( 'You are not allowed to manage order attachments.', 'din-order-attach' ) );
		}

		$names = array_filter(
			(array) ( is_array( $files ) ? ( $files['name'] ?? array() ) : array() ),
			static function ( $name ) {
				return is_string( $name ) && '' !== trim( $name );
			}
		);
		if ( empty( $names ) ) {
			return false;
		}

		if ( ! wp_verify_nonce( $nonce, 'din_order_attach_upload' ) ) {
			$error = new WP_Error( 'din_order_attach_invalid_nonce', __( 'The attachment security check failed. Please reload the order and try again.', 'din-order-attach' ) );
			$this->add_admin_error( $error->get_error_message() );

			return $error;
		}

		$storage = $storage ?: new DIN_Order_Attach_Storage();
		$result  = $storage->store_for_order( $order, $files, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->add_admin_error( $result->get_error_message() );

			return $result;
		}

		if ( ! $order->get_user_id() ) {
			$this->add_admin_error( __( 'Attachments were saved, but no customer notification can be sent because this order has no buyer account.', 'din-order-attach' ) );
		} else {
			$order->add_order_note( __( 'Guest Post files for your order have been updated.', 'din-order-attach' ), true, true );
		}

		return $result;
	}

	public function delete_order_attachment( $order, $attachment_id, $nonce, $authorized, $storage = null ) {
		if ( ! $authorized ) {
			return new WP_Error( 'din_order_attach_forbidden', __( 'You are not allowed to manage order attachments.', 'din-order-attach' ) );
		}

		if ( ! is_object( $order ) || ! wp_verify_nonce( $nonce, self::get_delete_nonce_action( $order->get_id(), $attachment_id ) ) ) {
			return new WP_Error( 'din_order_attach_invalid_nonce', __( 'The attachment security check failed.', 'din-order-attach' ) );
		}

		$storage = $storage ?: new DIN_Order_Attach_Storage();
		return $storage->delete_order_file( $order, $attachment_id );
	}

	public function handle_delete() {
		$order_id_input      = $_GET['order_id'] ?? 0;
		$attachment_id_input = $_GET['attachment_id'] ?? '';
		$nonce_input         = $_GET['_wpnonce'] ?? '';
		$order_id            = is_scalar( $order_id_input ) ? absint( wp_unslash( $order_id_input ) ) : 0;
		$attachment_id       = is_string( $attachment_id_input ) ? sanitize_text_field( wp_unslash( $attachment_id_input ) ) : '';
		$nonce               = is_string( $nonce_input ) ? sanitize_text_field( wp_unslash( $nonce_input ) ) : '';
		$order               = wc_get_order( $order_id );
		$result              = $this->delete_order_attachment( $order, $attachment_id, $nonce, current_user_can( 'manage_woocommerce' ) );

		if ( is_wp_error( $result ) ) {
			$response = in_array( $result->get_error_code(), array( 'din_order_attach_forbidden', 'din_order_attach_invalid_nonce' ), true ) ? 403 : 500;
			wp_die(
				esc_html__( 'Attachment could not be deleted.', 'din-order-attach' ),
				esc_html__( 'Delete failed', 'din-order-attach' ),
				array( 'response' => $response )
			);
			return;
		}

		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}

	public function cleanup_deleted_order( $order_id, $order = null, $storage = null ) {
		$order = is_object( $order ) ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'din_order_attach_invalid_order', __( 'The order is invalid.', 'din-order-attach' ) );
		}

		$result = ( $storage ?: new DIN_Order_Attach_Storage() )->delete_order_files( $order );
		if ( is_wp_error( $result ) ) {
			wc_get_logger()->error(
				sprintf( 'DIN Order Attach cleanup failed for order %d.', absint( $order_id ) ),
				array( 'source' => 'din-order-attach' )
			);
		}

		return $result;
	}

	public function render_email_attachments( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! is_object( $order ) || ! isset( $email->id ) || 'customer_note' !== $email->id ) {
			return;
		}

		$records = ( new DIN_Order_Attach_Storage() )->get_order_files( $order );
		if ( ! $records ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Order attachments', 'din-order-attach' ) . "\n";
			foreach ( $records as $record ) {
				if ( empty( $record['id'] ) || empty( $record['name'] ) ) {
					continue;
				}
				echo '- ' . wp_strip_all_tags( $record['name'] ) . ': ' . esc_url_raw( DIN_Order_Attach_Download::get_url( $order->get_id(), $record['id'] ) ) . "\n";
			}

			return;
		}

		echo '<h2>' . esc_html__( 'Order attachments', 'din-order-attach' ) . '</h2><ul>';
		foreach ( $records as $record ) {
			if ( empty( $record['id'] ) || empty( $record['name'] ) ) {
				continue;
			}
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( DIN_Order_Attach_Download::get_url( $order->get_id(), $record['id'] ) ),
				esc_html( $record['name'] )
			);
		}
		echo '</ul>';
	}

	private function add_admin_error( $message ) {
		if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
			WC_Admin_Meta_Boxes::add_error( esc_html( $message ) );
		}
	}

	public static function declare_hpos_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				DIN_ORDER_ATTACH_FILE,
				true
			);
		}
	}

	public static function render_missing_woocommerce_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'DIN Order Attach requires WooCommerce to be active.', 'din-order-attach' )
		);
	}
}
