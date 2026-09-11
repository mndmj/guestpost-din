<?php
/**
 * Minimal integration smoke test for DIN Order Attach.
 *
 * Run with:
 * studio wp eval-file wp-content/plugins/din-order-attach/tests/smoke.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	throw new RuntimeException( 'WordPress must be loaded.' );
}

function din_order_attach_expect( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$plugin_file = dirname( __DIR__ ) . '/din-order-attach.php';

din_order_attach_expect( file_exists( $plugin_file ), 'Plugin bootstrap file is missing.' );

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugin_data = get_plugin_data( $plugin_file, false, false );

din_order_attach_expect( 'DIN Order Attach' === $plugin_data['Name'], 'Plugin header name is invalid.' );
din_order_attach_expect( '1.6.1' === $plugin_data['Version'], 'Plugin version must be 1.6.1.' );
din_order_attach_expect( ! file_exists( dirname( __DIR__ ) . '/uninstall.php' ), 'MVP uninstall must preserve attachment data.' );
din_order_attach_expect( is_plugin_active( 'din-order-attach/din-order-attach.php' ), 'Plugin is not active.' );
din_order_attach_expect( defined( 'DIN_ORDER_ATTACH_FILE' ), 'Plugin file constant is missing.' );
din_order_attach_expect( class_exists( 'DIN_Order_Attach' ), 'Main plugin class is missing.' );
din_order_attach_expect( class_exists( 'DIN_Order_Attach_Download' ), 'Download class is missing.' );

if ( ! class_exists( 'WooCommerce' ) ) {
	din_order_attach_expect( ! DIN_Order_Attach::instance()->is_booted(), 'Plugin booted without WooCommerce.' );
	din_order_attach_expect(
		false !== has_action( 'admin_notices', array( 'DIN_Order_Attach', 'render_missing_woocommerce_notice' ) ),
		'Missing WooCommerce notice hook is not registered.'
	);

	echo "DIN Order Attach dependency guard: OK\n";
	return;
}

din_order_attach_expect( DIN_Order_Attach::instance() === DIN_Order_Attach::instance(), 'Plugin must initialize once.' );
din_order_attach_expect( DIN_Order_Attach::instance()->is_booted(), 'Plugin did not boot with WooCommerce active.' );
din_order_attach_expect( get_role( 'administrator' )->has_cap( 'manage_woocommerce' ), 'Administrator cannot manage WooCommerce.' );
din_order_attach_expect( get_role( 'shop_manager' )->has_cap( 'manage_woocommerce' ), 'Shop Manager cannot manage WooCommerce.' );
din_order_attach_expect( ! get_role( 'customer' )->has_cap( 'manage_woocommerce' ), 'Customer must not manage order attachments.' );
din_order_attach_expect(
	false !== has_action( 'before_woocommerce_init', array( 'DIN_Order_Attach', 'declare_hpos_compatibility' ) ),
	'HPOS compatibility declaration hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'add_meta_boxes', array( DIN_Order_Attach::instance(), 'register_order_meta_box' ) ),
	'Order meta box hook is missing.'
);
din_order_attach_expect(
	method_exists( DIN_Order_Attach::instance(), 'enqueue_admin_assets' )
	&& false !== has_action( 'admin_enqueue_scripts', array( DIN_Order_Attach::instance(), 'enqueue_admin_assets' ) ),
	'Order image-preview asset hook is missing.'
);
if ( method_exists( DIN_Order_Attach::instance(), 'enqueue_admin_assets' ) ) {
	$original_screen = get_current_screen();
	wp_dequeue_script( 'din-order-attach-preview' );
	set_current_screen( 'dashboard' );
	DIN_Order_Attach::instance()->enqueue_admin_assets();
	din_order_attach_expect( ! wp_script_is( 'din-order-attach-preview', 'enqueued' ), 'Preview asset must not load outside order screens.' );

	set_current_screen( wc_get_page_screen_id( 'shop-order' ) );
	DIN_Order_Attach::instance()->enqueue_admin_assets();
	$preview_script = wp_scripts()->registered['din-order-attach-preview'] ?? null;
	din_order_attach_expect(
		wp_script_is( 'din-order-attach-preview', 'enqueued' )
		&& $preview_script
		&& '1.6.0' === $preview_script->ver
		&& false !== strpos( $preview_script->src, '/assets/admin-preview.js' ),
		'Preview asset must load from the plugin on HPOS order screens.'
	);
	wp_dequeue_script( 'din-order-attach-preview' );
	set_current_screen( $original_screen ? $original_screen->id : 'front' );
}
din_order_attach_expect(
	false !== has_action( 'post_edit_form_tag', array( DIN_Order_Attach::instance(), 'render_form_enctype' ) ),
	'Legacy order form enctype hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'order_edit_form_tag', array( DIN_Order_Attach::instance(), 'render_form_enctype' ) ),
	'HPOS order form enctype hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'woocommerce_process_shop_order_meta', array( DIN_Order_Attach::instance(), 'process_order_upload' ) ),
	'Order upload processing hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'woocommerce_email_after_order_table', array( DIN_Order_Attach::instance(), 'render_email_attachments' ) ),
	'Customer-note email attachment hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'woocommerce_order_details_after_order_table', array( 'DIN_Order_Attach_Download', 'render_order_attachments' ) ),
	'Buyer order attachment hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'admin_post_din_order_attach_download', array( 'DIN_Order_Attach_Download', 'handle_download' ) ),
	'Authenticated download hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'admin_post_nopriv_din_order_attach_download', array( 'DIN_Order_Attach_Download', 'redirect_to_login' ) ),
	'Guest login redirect hook is missing.'
);
din_order_attach_expect(
	false !== has_action( 'admin_post_din_order_attach_delete', array( DIN_Order_Attach::instance(), 'handle_delete' ) ),
	'Authenticated delete hook is missing.'
);
din_order_attach_expect(
	false === has_action( 'admin_post_nopriv_din_order_attach_delete' ),
	'Guest delete endpoint must not be registered.'
);
din_order_attach_expect(
	false !== has_action( 'woocommerce_before_delete_order', array( DIN_Order_Attach::instance(), 'cleanup_deleted_order' ) ),
	'Permanent order cleanup hook is missing.'
);
din_order_attach_expect(
	false === has_action( 'woocommerce_before_trash_order', array( DIN_Order_Attach::instance(), 'cleanup_deleted_order' ) ),
	'Moving an order to Trash must not trigger attachment cleanup.'
);
ob_start();
DIN_Order_Attach::instance()->render_form_enctype( new WC_Order() );
$form_enctype = ob_get_clean();
din_order_attach_expect( false !== strpos( $form_enctype, 'enctype="multipart/form-data"' ), 'Order form enctype is invalid.' );

ob_start();
DIN_Order_Attach::instance()->render_form_enctype( (object) array( 'post_type' => 'post' ) );
$non_order_enctype = ob_get_clean();
din_order_attach_expect( '' === $non_order_enctype, 'Non-order edit forms must not be modified.' );

ob_start();
DIN_Order_Attach::instance()->render_meta_box( new WC_Order() );
$meta_box = ob_get_clean();
din_order_attach_expect( false !== strpos( $meta_box, 'name="din_order_attach_files[]"' ), 'Multi-file input is missing.' );
din_order_attach_expect( false !== strpos( $meta_box, ' multiple' ), 'File input does not allow multiple files.' );
din_order_attach_expect( false !== strpos( $meta_box, '.pdf,.doc,.docx,.xls,.xlsx,.jpg,.png,.zip' ), 'File input allowlist is invalid.' );
din_order_attach_expect( false !== strpos( $meta_box, 'data-max-size="10485760"' ), 'Realtime file-size limit is missing.' );
din_order_attach_expect( false !== strpos( $meta_box, 'name="din_order_attach_nonce"' ), 'Upload nonce is missing.' );
din_order_attach_expect(
	false !== strpos( $meta_box, 'id="din-order-attach-alert"' )
	&& false !== strpos( $meta_box, 'role="alert"' )
	&& false !== strpos( $meta_box, 'data-invalid-type-template="%s was removed: file type is not allowed."' )
	&& false !== strpos( $meta_box, 'data-too-large-template="%s was removed: file exceeds 10 MB."' ),
	'Realtime file validation alert is missing or incomplete.'
);
din_order_attach_expect(
	false !== strpos( $meta_box, 'id="din-order-attach-preview"' )
	&& false !== strpos( $meta_box, 'class="din-order-attach-preview"' )
	&& false !== strpos( $meta_box, 'data-remove-label="Remove"' )
	&& false !== strpos( $meta_box, 'aria-live="polite"' )
	&& false !== strpos( $meta_box, ' hidden' ),
	'Selected-image preview region is missing or inaccessible.'
);

$render_order = new WC_Order();
$render_order->update_meta_data(
	DIN_Order_Attach_Storage::META_KEY,
	array(
		array(
			'id'          => 'old-file-id',
			'name'        => 'proof.pdf',
			'mime'        => 'application/pdf',
			'size'        => 1024,
			'path'        => '0/private-old-name.pdf',
			'uploaded_by' => 0,
			'uploaded_at' => '2026-08-28T00:00:00+00:00',
		),
		array(
			'id'          => 'latest-file-id',
			'name'        => 'latest-proof.png',
			'mime'        => 'image/png',
			'size'        => 2048,
			'path'        => '0/private-latest-name.png',
			'uploaded_by' => 0,
			'uploaded_at' => '2026-08-28T00:01:00+00:00',
		),
	)
);
ob_start();
DIN_Order_Attach::instance()->render_meta_box( $render_order );
$populated_meta_box = ob_get_clean();
din_order_attach_expect( false !== strpos( $populated_meta_box, 'widefat striped' ), 'Active attachment table is missing.' );
din_order_attach_expect( false !== strpos( $populated_meta_box, 'proof.pdf' ), 'Active attachment name is missing.' );
din_order_attach_expect( false !== strpos( $populated_meta_box, 'application/pdf' ), 'Active attachment MIME is missing.' );
din_order_attach_expect(
	false !== strpos( $populated_meta_box, 'View / Download' )
	&& false !== strpos( $populated_meta_box, 'old-file-id' ),
	'Internal order panel must provide a protected download action.'
);
din_order_attach_expect(
	false !== strpos( $populated_meta_box, 'action=din_order_attach_delete' )
	&& false !== strpos( $populated_meta_box, '_wpnonce=' )
	&& false !== strpos( $populated_meta_box, 'return confirm' ),
	'Internal delete action must include a nonce and native confirmation.'
);

$customer_note_email = (object) array( 'id' => 'customer_note' );
ob_start();
DIN_Order_Attach::instance()->render_email_attachments( $render_order, false, false, $customer_note_email );
$html_email_attachments = ob_get_clean();
din_order_attach_expect(
	false !== strpos( $html_email_attachments, 'proof.pdf' )
	&& false !== strpos( $html_email_attachments, 'latest-proof.png' )
	&& false !== strpos( $html_email_attachments, 'old-file-id' )
	&& false !== strpos( $html_email_attachments, 'latest-file-id' ),
	'Customer-note HTML email must list every active attachment.'
);
din_order_attach_expect( false === strpos( $html_email_attachments, 'private-old-name.pdf' ), 'Email must not expose private storage paths.' );

ob_start();
DIN_Order_Attach::instance()->render_email_attachments( $render_order, false, true, $customer_note_email );
$plain_email_attachments = ob_get_clean();
din_order_attach_expect(
	false !== strpos( $plain_email_attachments, 'proof.pdf' )
	&& false !== strpos( $plain_email_attachments, 'latest-proof.png' )
	&& false !== strpos( $plain_email_attachments, 'old-file-id' )
	&& false !== strpos( $plain_email_attachments, 'latest-file-id' ),
	'Customer-note plain-text email must list every active attachment.'
);

ob_start();
DIN_Order_Attach::instance()->render_email_attachments( $render_order, false, false, (object) array( 'id' => 'customer_processing_order' ) );
$other_email_attachments = ob_get_clean();
din_order_attach_expect( '' === $other_email_attachments, 'Non-customer-note emails must not include order attachments.' );

$feature_compatibility = Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_features_for_plugin(
	plugin_basename( DIN_ORDER_ATTACH_FILE )
);

din_order_attach_expect(
	in_array( 'custom_order_tables', $feature_compatibility['compatible'], true ),
	'HPOS compatibility was not declared.'
);

ob_start();
DIN_Order_Attach::render_missing_woocommerce_notice();
$dependency_notice = ob_get_clean();

din_order_attach_expect( false !== strpos( $dependency_notice, 'WooCommerce' ), 'Dependency notice is unclear.' );

din_order_attach_expect( class_exists( 'DIN_Order_Attach_Storage' ), 'Storage class is missing.' );

$download_url = DIN_Order_Attach_Download::get_url( 42, 'attachment-uuid' );
din_order_attach_expect(
	false !== strpos( $download_url, 'action=din_order_attach_download' )
	&& false !== strpos( $download_url, 'order_id=42' )
	&& false !== strpos( $download_url, 'attachment_id=attachment-uuid' )
	&& false === strpos( $download_url, 'path=' ),
	'Download URL must contain only the action, order ID, and attachment ID.'
);
$login_url = urldecode( DIN_Order_Attach_Download::get_login_url( 42, 'attachment-uuid' ) );
din_order_attach_expect(
	false !== strpos( $login_url, 'wp-login.php' )
	&& false !== strpos( $login_url, 'redirect_to=' )
	&& false !== strpos( $login_url, 'action=din_order_attach_download' )
	&& false !== strpos( $login_url, 'order_id=42' )
	&& false !== strpos( $login_url, 'attachment_id=attachment-uuid' ),
	'Guest login URL must return the buyer to the protected download.'
);

$redirect_location = '';
$stop_redirect      = static function ( $location ) use ( &$redirect_location ) {
	$redirect_location = $location;
	throw new RuntimeException( 'Stop test redirect.' );
};
$previous_get = $_GET;
$_GET         = array(
	'order_id'      => '42',
	'attachment_id' => 'attachment-uuid',
);
add_filter( 'wp_redirect', $stop_redirect, 1 );
try {
	DIN_Order_Attach_Download::redirect_to_login();
} catch ( RuntimeException $error ) {
	// The filter stops execution before the endpoint calls exit.
} finally {
	remove_filter( 'wp_redirect', $stop_redirect, 1 );
	$_GET = $previous_get;
}
din_order_attach_expect(
	false !== strpos( urldecode( $redirect_location ), 'wp-login.php' )
	&& false !== strpos( urldecode( $redirect_location ), 'attachment_id=attachment-uuid' ),
	'Guest endpoint did not redirect to login without streaming a file.'
);

$die_response = null;
$stop_die     = static function () use ( &$die_response ) {
	return static function ( $message, $title, $args ) use ( &$die_response ) {
		$die_response = array(
			'message' => wp_strip_all_tags( $message ),
			'response' => $args['response'] ?? null,
		);
		throw new RuntimeException( 'Stop test wp_die.' );
	};
};
$previous_get = $_GET;
$_GET         = array(
	'order_id'      => '0',
	'attachment_id' => 'unknown',
);
add_filter( 'wp_die_handler', $stop_die, PHP_INT_MAX );
try {
	DIN_Order_Attach_Download::handle_download();
} catch ( RuntimeException $error ) {
	// The test handler stops wp_die before process termination.
} finally {
	remove_filter( 'wp_die_handler', $stop_die, PHP_INT_MAX );
	$_GET = $previous_get;
}
din_order_attach_expect(
	is_array( $die_response )
	&& 404 === $die_response['response']
	&& 'Attachment unavailable.' === $die_response['message'],
	'Invalid download endpoint requests must return a generic 404.'
);

$delete_die_response = null;
$stop_delete_die     = static function () use ( &$delete_die_response ) {
	return static function ( $message, $title, $args ) use ( &$delete_die_response ) {
		$delete_die_response = array(
			'message'  => wp_strip_all_tags( $message ),
			'response' => $args['response'] ?? null,
		);
		throw new RuntimeException( 'Stop test delete wp_die.' );
	};
};
$previous_get = $_GET;
$_GET         = array(
	'order_id'      => '0',
	'attachment_id' => 'unknown',
	'_wpnonce'      => 'invalid',
);
add_filter( 'wp_die_handler', $stop_delete_die, PHP_INT_MAX );
try {
	DIN_Order_Attach::instance()->handle_delete();
} catch ( RuntimeException $error ) {
	// The test handler stops wp_die before process termination.
} finally {
	remove_filter( 'wp_die_handler', $stop_delete_die, PHP_INT_MAX );
	$_GET = $previous_get;
}
din_order_attach_expect(
	is_array( $delete_die_response )
	&& 403 === $delete_die_response['response']
	&& 'Attachment could not be deleted.' === $delete_die_response['message'],
	'Unauthorized delete endpoint requests must return a generic 403.'
);

class DIN_Order_Attach_Test_Download_Order {
	public $id = 42;
	public $user_id = 10;
	public $meta = array();

	public function get_id() {
		return $this->id;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}
}

$download_order = new DIN_Order_Attach_Test_Download_Order();
$download_order->meta[ DIN_Order_Attach_Storage::META_KEY ] = array(
	array(
		'id'          => 'attachment-uuid',
		'name'        => 'proof.pdf',
		'path'        => 'tests/fixtures/proof.pdf',
		'mime'        => 'application/pdf',
		'size'        => 123,
		'uploaded_by' => 1,
		'uploaded_at' => '2026-08-28T00:00:00+00:00',
	),
);
$download_storage = new DIN_Order_Attach_Storage( dirname( __DIR__ ) );
$download_service = new DIN_Order_Attach_Download( $download_storage );
$owner_download    = $download_service->authorize( $download_order, 'attachment-uuid', 10, false );
$staff_download    = $download_service->authorize( $download_order, 'attachment-uuid', 99, true );
$other_download    = $download_service->authorize( $download_order, 'attachment-uuid', 11, false );
$guest_download    = $download_service->authorize( $download_order, 'attachment-uuid', 0, false );
$unknown_download  = $download_service->authorize( $download_order, 'unknown-id', 10, false );

din_order_attach_expect( is_array( $owner_download ) && 'attachment-uuid' === $owner_download['record']['id'], 'Order owner cannot download their attachment.' );
din_order_attach_expect( is_array( $staff_download ) && 'attachment-uuid' === $staff_download['record']['id'], 'WooCommerce order manager cannot download an attachment.' );
din_order_attach_expect( is_wp_error( $other_download ) && 'din_order_attach_unavailable' === $other_download->get_error_code(), 'Another buyer was not denied generically.' );
din_order_attach_expect( is_wp_error( $guest_download ) && 'din_order_attach_unavailable' === $guest_download->get_error_code(), 'Guest authorization must not expose a file.' );
din_order_attach_expect( is_wp_error( $unknown_download ) && $other_download->get_error_code() === $unknown_download->get_error_code(), 'Unknown and unauthorized attachments must use the same generic response.' );

$inline_headers = $download_service->get_response_headers( $owner_download );
din_order_attach_expect(
	'application/pdf' === $inline_headers['Content-Type']
	&& 'nosniff' === $inline_headers['X-Content-Type-Options']
	&& (string) filesize( $owner_download['path'] ) === $inline_headers['Content-Length']
	&& 0 === strpos( $inline_headers['Content-Disposition'], 'inline;' )
	&& false !== strpos( $inline_headers['Cache-Control'], 'no-cache' ),
	'PDF download headers are not safe for inline streaming.'
);

$attachment_payload             = $owner_download;
$attachment_payload['record']['name'] = 'spreadsheet.xlsx';
$attachment_payload['record']['mime'] = 'application/zip';
$attachment_headers            = $download_service->get_response_headers( $attachment_payload );
din_order_attach_expect( 0 === strpos( $attachment_headers['Content-Disposition'], 'attachment;' ), 'Non-inline formats must use attachment disposition.' );

$image_payload = $owner_download;
$image_payload['record']['mime'] = 'image/jpeg';
$jpeg_headers = $download_service->get_response_headers( $image_payload );
$image_payload['record']['mime'] = 'image/png';
$png_headers = $download_service->get_response_headers( $image_payload );
din_order_attach_expect(
	0 === strpos( $jpeg_headers['Content-Disposition'], 'inline;' )
	&& 0 === strpos( $png_headers['Content-Disposition'], 'inline;' ),
	'JPG and PNG files must use inline disposition.'
);

ob_start();
$streamed_bytes = $download_service->stream( $owner_download );
$streamed_file  = ob_get_clean();
din_order_attach_expect(
	file_get_contents( $owner_download['path'] ) === $streamed_file
	&& strlen( $streamed_file ) === $streamed_bytes,
	'Authorized file was not streamed without buffering it into application memory.'
);

$download_order->meta[ DIN_Order_Attach_Storage::META_KEY ][0]['path'] = '../outside.pdf';
$traversal_download = $download_service->authorize( $download_order, 'attachment-uuid', 10, false );
din_order_attach_expect( is_wp_error( $traversal_download ) && 'din_order_attach_unavailable' === $traversal_download->get_error_code(), 'Traversal metadata must be denied generically.' );

$download_order->meta[ DIN_Order_Attach_Storage::META_KEY ][0]['path'] = 'tests/fixtures/proof.pdf';
$download_order->meta[ DIN_Order_Attach_Storage::META_KEY ][0]['mime'] = "text/html\r\nX-Evil: yes";
$unsafe_mime_download = $download_service->authorize( $download_order, 'attachment-uuid', 10, false );
din_order_attach_expect( is_wp_error( $unsafe_mime_download ) && 'din_order_attach_unavailable' === $unsafe_mime_download->get_error_code(), 'Unsafe MIME metadata must be denied generically.' );
$download_order->meta[ DIN_Order_Attach_Storage::META_KEY ][0]['mime'] = 'application/pdf';
$download_order->meta[ DIN_Order_Attach_Storage::META_KEY ][] = array(
	'id'          => 'second-uuid',
	'name'        => 'second-proof.png',
	'path'        => 'tests/fixtures/proof.pdf',
	'mime'        => 'image/png',
	'size'        => 456,
	'uploaded_by' => 1,
	'uploaded_at' => '2026-08-28T00:01:00+00:00',
);

ob_start();
DIN_Order_Attach_Download::render_order_attachments( $download_order, 10, false, $download_storage );
$owner_attachment_list = ob_get_clean();
din_order_attach_expect(
	false !== strpos( $owner_attachment_list, 'proof.pdf' )
	&& false !== strpos( $owner_attachment_list, 'second-proof.png' )
	&& false !== strpos( $owner_attachment_list, 'attachment-uuid' )
	&& false !== strpos( $owner_attachment_list, 'second-uuid' )
	&& false !== strpos( $owner_attachment_list, 'View / Download' ),
	'Buyer dashboard must list every active attachment with protected actions.'
);

ob_start();
DIN_Order_Attach_Download::render_order_attachments( $download_order, 11, false, $download_storage );
$other_attachment_list = ob_get_clean();
din_order_attach_expect( '' === $other_attachment_list, 'Another buyer must not see the order attachment list.' );

ob_start();
DIN_Order_Attach_Download::render_order_attachments( $download_order, 99, true, $download_storage );
$staff_attachment_list = ob_get_clean();
din_order_attach_expect( false !== strpos( $staff_attachment_list, 'proof.pdf' ), 'WooCommerce order manager cannot view the attachment list.' );

class DIN_Order_Attach_Test_Delete_Order {
	public $id = 777;
	public $user_id = 10;
	public $meta = array();
	public $notes = array();
	public $save_count = 0;

	public function get_id() {
		return $this->id;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}

	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	public function save_meta_data() {
		++$this->save_count;
	}
}

$delete_root       = wp_normalize_path( sys_get_temp_dir() ) . '/din-order-attach-test-' . wp_generate_uuid4();
$delete_source_one = wp_tempnam( 'din-order-attach-delete-one' );
$delete_source_two = wp_tempnam( 'din-order-attach-delete-two' );
try {
	$pdf_fixture = file_get_contents( __DIR__ . '/fixtures/proof.pdf' );
	file_put_contents( $delete_source_one, $pdf_fixture );
	file_put_contents( $delete_source_two, $pdf_fixture );
	$delete_storage = new DIN_Order_Attach_Storage(
		$delete_root,
		static function ( $source, $target ) {
			return rename( $source, $target );
		}
	);
	$delete_records = $delete_storage->store_batch(
		777,
		array(
			'name'     => array( 'delete-one.pdf', 'keep-two.pdf' ),
			'tmp_name' => array( $delete_source_one, $delete_source_two ),
			'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
			'size'     => array( filesize( $delete_source_one ), filesize( $delete_source_two ) ),
		),
		1
	);
	$delete_order = new DIN_Order_Attach_Test_Delete_Order();
	$delete_order->meta[ DIN_Order_Attach_Storage::META_KEY ] = $delete_records;
	$deleted_record = $delete_storage->delete_order_file( $delete_order, $delete_records[0]['id'] );
	$remaining_records = $delete_storage->get_order_files( $delete_order );

	din_order_attach_expect( is_array( $deleted_record ) && $delete_records[0]['id'] === $deleted_record['id'], 'Target attachment was not deleted.' );
	din_order_attach_expect( 1 === count( $remaining_records ) && $delete_records[1]['id'] === $remaining_records[0]['id'], 'Deleting one attachment changed the wrong metadata.' );
	din_order_attach_expect( ! file_exists( $delete_root . '/' . $delete_records[0]['path'] ), 'Deleted attachment file still exists.' );
	din_order_attach_expect( file_exists( $delete_root . '/' . $delete_records[1]['path'] ), 'Unrelated attachment file was deleted.' );
	din_order_attach_expect( empty( $delete_order->notes ), 'Deleting an attachment must not create a customer notification.' );
	$old_link_result = ( new DIN_Order_Attach_Download( $delete_storage ) )->authorize( $delete_order, $delete_records[0]['id'], 10, false );
	din_order_attach_expect( is_wp_error( $old_link_result ), 'Deleted attachment link remained authorized.' );

	din_order_attach_expect( file_exists( $delete_root . '/' . $delete_records[1]['path'] ), 'Moving an order to Trash deleted its attachment.' );
	do_action( 'deactivate_din-order-attach/din-order-attach.php' );
	din_order_attach_expect( file_exists( $delete_root . '/' . $delete_records[1]['path'] ), 'Plugin deactivation deleted an attachment.' );
	din_order_attach_expect( 1 === count( $delete_storage->get_order_files( $delete_order ) ), 'Plugin deactivation discarded attachment metadata.' );

	$cleanup_result = DIN_Order_Attach::instance()->cleanup_deleted_order( 777, $delete_order, $delete_storage );
	din_order_attach_expect( true === $cleanup_result, 'Permanent order cleanup did not complete.' );
	din_order_attach_expect( ! file_exists( $delete_root . '/' . $delete_records[1]['path'] ), 'Permanent order cleanup left an attachment file.' );
	din_order_attach_expect( ! is_dir( $delete_root . '/777' ), 'Permanent order cleanup left an empty order directory.' );
} finally {
	foreach ( array( $delete_source_one, $delete_source_two ) as $temporary_file ) {
		if ( file_exists( $temporary_file ) ) {
			wp_delete_file( $temporary_file );
		}
	}
	foreach ( glob( $delete_root . '/*', GLOB_ONLYDIR ) ?: array() as $temporary_directory ) {
		foreach ( glob( $temporary_directory . '/*' ) ?: array() as $temporary_file ) {
			wp_delete_file( $temporary_file );
		}
		rmdir( $temporary_directory );
	}
	if ( is_dir( $delete_root ) ) {
		rmdir( $delete_root );
	}
}

$failed_delete_root   = wp_normalize_path( sys_get_temp_dir() ) . '/din-order-attach-test-' . wp_generate_uuid4();
$failed_delete_source = wp_tempnam( 'din-order-attach-delete-failure' );
try {
	file_put_contents( $failed_delete_source, file_get_contents( __DIR__ . '/fixtures/proof.pdf' ) );
	$failed_delete_setup = new DIN_Order_Attach_Storage(
		$failed_delete_root,
		static function ( $source, $target ) {
			return rename( $source, $target );
		}
	);
	$failed_delete_records = $failed_delete_setup->store_batch(
		778,
		array(
			'name'     => array( 'must-remain.pdf' ),
			'tmp_name' => array( $failed_delete_source ),
			'error'    => array( UPLOAD_ERR_OK ),
			'size'     => array( filesize( $failed_delete_source ) ),
		),
		1
	);
	$failed_delete_order = new DIN_Order_Attach_Test_Delete_Order();
	$failed_delete_order->id = 778;
	$failed_delete_order->meta[ DIN_Order_Attach_Storage::META_KEY ] = $failed_delete_records;
	$failed_delete_storage = new DIN_Order_Attach_Storage(
		$failed_delete_root,
		null,
		static function () {
			return false;
		}
	);
	$failed_delete_result = $failed_delete_storage->delete_order_file( $failed_delete_order, $failed_delete_records[0]['id'] );

	din_order_attach_expect( is_wp_error( $failed_delete_result ), 'A physical delete failure must reject the operation.' );
	din_order_attach_expect( $failed_delete_records === $failed_delete_storage->get_order_files( $failed_delete_order ), 'Physical delete failure discarded attachment metadata.' );
	din_order_attach_expect( file_exists( $failed_delete_root . '/' . $failed_delete_records[0]['path'] ), 'Physical delete failure removed the attachment file.' );

	$cleanup_logs = array();
	$capture_cleanup_log = static function ( $message, $level, $context ) use ( &$cleanup_logs ) {
		if ( 'din-order-attach' === ( $context['source'] ?? '' ) ) {
			$cleanup_logs[] = compact( 'message', 'level', 'context' );
			return null;
		}

		return $message;
	};
	add_filter( 'woocommerce_logger_log_message', $capture_cleanup_log, 10, 3 );
	$cleanup_failure = DIN_Order_Attach::instance()->cleanup_deleted_order( 778, $failed_delete_order, $failed_delete_storage );
	remove_filter( 'woocommerce_logger_log_message', $capture_cleanup_log, 10 );

	din_order_attach_expect( is_wp_error( $cleanup_failure ), 'Permanent cleanup must report a physical delete failure.' );
	din_order_attach_expect( file_exists( $failed_delete_root . '/' . $failed_delete_records[0]['path'] ), 'Failed permanent cleanup removed the attachment unexpectedly.' );
	din_order_attach_expect( ! empty( $cleanup_logs ), 'Permanent cleanup failure was not logged.' );
	din_order_attach_expect( 'error' === $cleanup_logs[0]['level'] && false !== strpos( $cleanup_logs[0]['message'], '778' ), 'Cleanup log does not identify the order generically.' );
	din_order_attach_expect( false === strpos( $cleanup_logs[0]['message'], $failed_delete_root ), 'Cleanup log exposed the private storage path.' );
} finally {
	if ( file_exists( $failed_delete_source ) ) {
		wp_delete_file( $failed_delete_source );
	}
	foreach ( glob( $failed_delete_root . '/*', GLOB_ONLYDIR ) ?: array() as $temporary_directory ) {
		foreach ( glob( $temporary_directory . '/*' ) ?: array() as $temporary_file ) {
			wp_delete_file( $temporary_file );
		}
		rmdir( $temporary_directory );
	}
	if ( is_dir( $failed_delete_root ) ) {
		rmdir( $failed_delete_root );
	}
}

class DIN_Order_Attach_Test_Delete_Storage {
	public $calls = 0;

	public function delete_order_file( $order, $attachment_id ) {
		++$this->calls;
		return array(
			'id'            => $attachment_id,
			'name'          => 'proof.pdf',
			'path'          => '777/private.pdf',
			'mime'          => 'application/pdf',
			'size'          => 100,
			'uploaded_by'   => 1,
			'uploaded_at'   => '2026-08-28T00:00:00+00:00',
		);
	}
}

$delete_handler_order   = new DIN_Order_Attach_Test_Delete_Order();
$delete_handler_storage = new DIN_Order_Attach_Test_Delete_Storage();
$delete_nonce           = wp_create_nonce( DIN_Order_Attach::get_delete_nonce_action( $delete_handler_order->get_id(), 'attachment-uuid' ) );
$forbidden_delete       = DIN_Order_Attach::instance()->delete_order_attachment( $delete_handler_order, 'attachment-uuid', $delete_nonce, false, $delete_handler_storage );
din_order_attach_expect( is_wp_error( $forbidden_delete ) && 0 === $delete_handler_storage->calls, 'Unauthorized delete reached storage.' );

$invalid_nonce_delete = DIN_Order_Attach::instance()->delete_order_attachment( $delete_handler_order, 'attachment-uuid', 'invalid', true, $delete_handler_storage );
din_order_attach_expect( is_wp_error( $invalid_nonce_delete ) && 0 === $delete_handler_storage->calls, 'Invalid delete nonce reached storage.' );

$valid_delete = DIN_Order_Attach::instance()->delete_order_attachment( $delete_handler_order, 'attachment-uuid', $delete_nonce, true, $delete_handler_storage );
din_order_attach_expect( is_array( $valid_delete ) && 1 === $delete_handler_storage->calls, 'Authorized attachment delete was not processed once.' );
din_order_attach_expect( empty( $delete_handler_order->notes ), 'Authorized delete must not create a customer notification.' );

$storage      = new DIN_Order_Attach_Storage();
$storage_root = trailingslashit( wp_normalize_path( $storage->get_root() ) );
$public_root  = trailingslashit( wp_normalize_path( ABSPATH ) );

din_order_attach_expect(
	0 !== strpos( strtolower( $storage_root ), strtolower( $public_root ) ),
	'Default storage must be outside ABSPATH.'
);

$allowed_candidates = array(
	'proof.pdf'  => 'application/pdf',
	'proof.doc'  => 'application/msword',
	'proof.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'proof.xls'  => 'application/vnd.ms-excel',
	'proof.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	'proof.jpg'  => 'image/jpeg',
	'proof.png'  => 'image/png',
	'proof.zip'  => 'application/zip',
);

foreach ( $allowed_candidates as $filename => $mime ) {
	din_order_attach_expect(
		true === $storage->validate_candidate( $filename, 1024, $mime ),
		'Allowed file was rejected: ' . $filename
	);
}

din_order_attach_expect(
	is_wp_error( $storage->validate_candidate( 'shell.php', 1024, 'application/x-httpd-php' ) ),
	'Executable extension must be rejected.'
);
din_order_attach_expect(
	is_wp_error( $storage->validate_candidate( 'fake.jpg', 1024, 'application/pdf' ) ),
	'Extension and MIME mismatch must be rejected.'
);
din_order_attach_expect(
	true === $storage->validate_candidate( 'limit.pdf', 10 * MB_IN_BYTES, 'application/pdf' ),
	'File at 10 MB must be accepted.'
);
din_order_attach_expect(
	is_wp_error( $storage->validate_candidate( 'too-large.pdf', ( 10 * MB_IN_BYTES ) + 1, 'application/pdf' ) ),
	'File larger than 10 MB must be rejected.'
);
din_order_attach_expect(
	is_wp_error( $storage->validate_candidate( 'empty.pdf', 0, 'application/pdf' ) ),
	'Empty files must be rejected.'
);
din_order_attach_expect(
	is_wp_error( $storage->validate_candidate( 'broken.pdf', 1024, 'application/pdf', UPLOAD_ERR_PARTIAL ) ),
	'PHP upload errors must be rejected.'
);

$normalized = $storage->normalize_files(
	array(
		'name'     => array( 'one.pdf', 'two.pdf' ),
		'type'     => array( 'application/pdf', 'application/pdf' ),
		'tmp_name' => array( 'tmp-one', 'tmp-two' ),
		'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
		'size'     => array( 10, 20 ),
	)
);

din_order_attach_expect( 2 === count( $normalized ), 'Multi-file input was not normalized.' );
din_order_attach_expect( 'two.pdf' === $normalized[1]['name'], 'Normalized file fields do not stay aligned.' );
din_order_attach_expect( array() === $storage->normalize_files( 'invalid' ), 'Malformed file payload must normalize to an empty batch.' );

$test_root    = wp_normalize_path( sys_get_temp_dir() ) . '/din-order-attach-test-' . wp_generate_uuid4();
$source_one   = wp_tempnam( 'din-order-attach-one' );
$source_two   = wp_tempnam( 'din-order-attach-two' );
$pdf_contents = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";

file_put_contents( $source_one, $pdf_contents );
file_put_contents( $source_two, $pdf_contents );

$move_file = static function ( $source, $target ) {
	return rename( $source, $target );
};
$batch_storage = new DIN_Order_Attach_Storage( $test_root, $move_file );
$records       = $batch_storage->store_batch(
	123,
	array(
		'name'     => array( 'client-proof-one.pdf', 'client-proof-two.pdf' ),
		'tmp_name' => array( $source_one, $source_two ),
		'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
		'size'     => array( filesize( $source_one ), filesize( $source_two ) ),
	),
	7
);

din_order_attach_expect( is_array( $records ) && 2 === count( $records ), 'Valid batch was not stored completely.' );

foreach ( $records as $record ) {
	din_order_attach_expect( 7 === $record['uploaded_by'], 'Uploader ID was not recorded.' );
	din_order_attach_expect( false === strpos( basename( $record['path'] ), 'client-proof' ), 'Physical filename exposes the input name.' );
	din_order_attach_expect( file_exists( $test_root . '/' . $record['path'] ), 'Stored file is missing.' );
}

$rollback_root = wp_normalize_path( sys_get_temp_dir() ) . '/din-order-attach-test-' . wp_generate_uuid4();
$rollback_one  = wp_tempnam( 'din-order-attach-rollback-one' );
$rollback_two  = wp_tempnam( 'din-order-attach-rollback-two' );
$move_count    = 0;

file_put_contents( $rollback_one, $pdf_contents );
file_put_contents( $rollback_two, $pdf_contents );

$fail_second_move = static function ( $source, $target ) use ( &$move_count ) {
	++$move_count;
	return 2 === $move_count ? false : rename( $source, $target );
};
$rollback_storage = new DIN_Order_Attach_Storage( $rollback_root, $fail_second_move );
$rollback_result  = $rollback_storage->store_batch(
	456,
	array(
		'name'     => array( 'one.pdf', 'two.pdf' ),
		'tmp_name' => array( $rollback_one, $rollback_two ),
		'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
		'size'     => array( filesize( $rollback_one ), filesize( $rollback_two ) ),
	),
	7
);

din_order_attach_expect( is_wp_error( $rollback_result ), 'A partial move must fail the batch.' );
din_order_attach_expect( empty( glob( $rollback_root . '/456/*' ) ), 'Partial batch files were not rolled back.' );

$invalid_root  = wp_normalize_path( sys_get_temp_dir() ) . '/din-order-attach-test-' . wp_generate_uuid4();
$invalid_one   = wp_tempnam( 'din-order-attach-invalid-one' );
$invalid_two   = wp_tempnam( 'din-order-attach-invalid-two' );
$invalid_moves = 0;

file_put_contents( $invalid_one, $pdf_contents );
file_put_contents( $invalid_two, '<?php echo "no";' );

$count_move = static function () use ( &$invalid_moves ) {
	++$invalid_moves;
	return true;
};
$invalid_storage = new DIN_Order_Attach_Storage( $invalid_root, $count_move );
$invalid_result  = $invalid_storage->store_batch(
	789,
	array(
		'name'     => array( 'valid.pdf', 'invalid.php' ),
		'tmp_name' => array( $invalid_one, $invalid_two ),
		'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
		'size'     => array( filesize( $invalid_one ), filesize( $invalid_two ) ),
	),
	7
);

din_order_attach_expect( is_wp_error( $invalid_result ), 'Invalid batch item must reject the batch.' );
din_order_attach_expect( 0 === $invalid_moves, 'Batch files moved before all items passed validation.' );
din_order_attach_expect( false !== strpos( $invalid_result->get_error_message(), 'invalid.php' ), 'Batch error does not identify the affected file.' );

class DIN_Order_Attach_Test_Order {
	public $meta = array();
	public $save_count = 0;
	public $id = 321;
	public $user_id = 10;
	public $notes = array();

	public function get_id() {
		return $this->id;
	}

	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function add_order_note( $note, $is_customer_note = 0, $added_by_user = false ) {
		$this->notes[] = array(
			'note'          => $note,
			'customer'      => (bool) $is_customer_note,
			'added_by_user' => (bool) $added_by_user,
		);
	}

	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	public function save_meta_data() {
		++$this->save_count;
	}
}

final class DIN_Order_Attach_Test_Admin_Storage {
	public $calls = 0;
	public $files;

	public function store_for_order( $order, $files, $user_id ) {
		++$this->calls;
		$this->files = $files;

		return array( array( 'id' => 'new-file' ) );
	}
}

$admin_order   = new DIN_Order_Attach_Test_Order();
$admin_storage = new DIN_Order_Attach_Test_Admin_Storage();
$admin_files   = array(
	'name'     => array( 'one.pdf', 'two.pdf' ),
	'tmp_name' => array( 'tmp-one', 'tmp-two' ),
	'error'    => array( UPLOAD_ERR_OK, UPLOAD_ERR_OK ),
	'size'     => array( 10, 20 ),
);
$valid_nonce   = wp_create_nonce( 'din_order_attach_upload' );

$unauthorized_result = DIN_Order_Attach::instance()->save_uploaded_files( $admin_order, $admin_files, $valid_nonce, false, $admin_storage );
din_order_attach_expect( is_wp_error( $unauthorized_result ), 'Unauthorized user was not rejected.' );
din_order_attach_expect( 0 === $admin_storage->calls, 'Unauthorized upload reached storage.' );

WC_Admin_Meta_Boxes::$meta_box_errors = array();
$invalid_nonce_result                 = DIN_Order_Attach::instance()->save_uploaded_files( $admin_order, $admin_files, 'invalid', true, $admin_storage );
din_order_attach_expect( is_wp_error( $invalid_nonce_result ), 'Invalid nonce was not rejected.' );
din_order_attach_expect( 0 === $admin_storage->calls, 'Invalid nonce reached storage.' );
din_order_attach_expect( 1 === count( WC_Admin_Meta_Boxes::$meta_box_errors ), 'Invalid nonce did not create an admin error.' );
WC_Admin_Meta_Boxes::$meta_box_errors = array();

$no_file_result = DIN_Order_Attach::instance()->save_uploaded_files(
	$admin_order,
	array( 'name' => array( '' ), 'error' => array( UPLOAD_ERR_NO_FILE ) ),
	'',
	true,
	$admin_storage
);
din_order_attach_expect( false === $no_file_result, 'Order update without files must be a no-op.' );
din_order_attach_expect( 0 === $admin_storage->calls && 0 === $admin_order->save_count && empty( $admin_order->notes ), 'No-file update changed the order.' );

$malformed_result = DIN_Order_Attach::instance()->save_uploaded_files(
	$admin_order,
	array( 'name' => array( array( 'nested-name' ) ) ),
	$valid_nonce,
	true,
	$admin_storage
);
din_order_attach_expect( false === $malformed_result, 'Malformed file payload must be ignored safely.' );
din_order_attach_expect( 0 === $admin_storage->calls, 'Malformed file payload reached storage.' );

$stored_result = DIN_Order_Attach::instance()->save_uploaded_files( $admin_order, $admin_files, $valid_nonce, true, $admin_storage );
din_order_attach_expect( is_array( $stored_result ), 'Valid admin batch was not accepted.' );
din_order_attach_expect( 1 === $admin_storage->calls, 'Multi-file input was not forwarded as one batch.' );
din_order_attach_expect( $admin_files === $admin_storage->files, 'Multi-file input changed before reaching storage.' );
$customer_note_created = 1 === count( $admin_order->notes )
	&& true === $admin_order->notes[0]['customer']
	&& true === $admin_order->notes[0]['added_by_user']
	&& false !== strpos( $admin_order->notes[0]['note'], 'Guest Post' );

WC_Admin_Meta_Boxes::$meta_box_errors = array();
$admin_order->user_id                  = 0;
$admin_order->notes                    = array();
DIN_Order_Attach::instance()->save_uploaded_files( $admin_order, $admin_files, $valid_nonce, true, $admin_storage );
din_order_attach_expect( 1 === count( WC_Admin_Meta_Boxes::$meta_box_errors ), 'Order without a buyer account must create an admin warning.' );
din_order_attach_expect( empty( $admin_order->notes ), 'Order without a buyer account must not create a customer note.' );
WC_Admin_Meta_Boxes::$meta_box_errors = array();

$test_order = new DIN_Order_Attach_Test_Order();
$batch_storage->save_order_files( $test_order, $records );

din_order_attach_expect( 1 === $test_order->save_count, 'Order metadata was not persisted through the CRUD API.' );
din_order_attach_expect( $records === $batch_storage->get_order_files( $test_order ), 'Attachment records did not survive metadata round-trip.' );

$resolved_path = $batch_storage->resolve_path( $records[0]['path'] );
din_order_attach_expect( ! is_wp_error( $resolved_path ) && file_exists( $resolved_path ), 'A valid stored path could not be resolved.' );
din_order_attach_expect( is_wp_error( $batch_storage->resolve_path( '../outside.pdf' ) ), 'Path traversal outside storage must be rejected.' );
din_order_attach_expect( is_wp_error( $batch_storage->resolve_path( $test_root . '/123/file.pdf' ) ), 'Absolute metadata paths must be rejected.' );

final class DIN_Order_Attach_Failing_Order extends DIN_Order_Attach_Test_Order {
	public function save_meta_data() {
		throw new RuntimeException( 'Simulated metadata failure.' );
	}
}

$metadata_root    = wp_normalize_path( sys_get_temp_dir() ) . '/din-order-attach-test-' . wp_generate_uuid4();
$metadata_source  = wp_tempnam( 'din-order-attach-metadata' );
$metadata_storage = new DIN_Order_Attach_Storage( $metadata_root, $move_file );
$failing_order    = new DIN_Order_Attach_Failing_Order();
$existing_records = array( array( 'id' => 'existing' ) );

$failing_order->meta[ DIN_Order_Attach_Storage::META_KEY ] = $existing_records;
file_put_contents( $metadata_source, $pdf_contents );

$metadata_result = $metadata_storage->store_for_order(
	$failing_order,
	array(
		'name'     => array( 'metadata.pdf' ),
		'tmp_name' => array( $metadata_source ),
		'error'    => array( UPLOAD_ERR_OK ),
		'size'     => array( filesize( $metadata_source ) ),
	),
	7
);

din_order_attach_expect( is_wp_error( $metadata_result ), 'Metadata failure must reject the batch.' );
din_order_attach_expect( $existing_records === $failing_order->meta[ DIN_Order_Attach_Storage::META_KEY ], 'Metadata failure did not restore the previous records.' );
din_order_attach_expect( empty( glob( $metadata_root . '/321/*' ) ), 'Metadata failure did not roll back the new files.' );

foreach ( array( $source_one, $source_two, $rollback_one, $rollback_two, $invalid_one, $invalid_two, $metadata_source ) as $temporary_file ) {
	if ( file_exists( $temporary_file ) ) {
		wp_delete_file( $temporary_file );
	}
}

foreach ( array( $test_root, $rollback_root, $invalid_root, $metadata_root ) as $temporary_root ) {
	foreach ( glob( $temporary_root . '/*', GLOB_ONLYDIR ) ?: array() as $temporary_directory ) {
		foreach ( glob( $temporary_directory . '/*' ) ?: array() as $temporary_file ) {
			wp_delete_file( $temporary_file );
		}
		rmdir( $temporary_directory );
	}
	if ( is_dir( $temporary_root ) ) {
		rmdir( $temporary_root );
	}
}

din_order_attach_expect( $customer_note_created, 'A valid batch must create exactly one customer note.' );

echo "DIN Order Attach smoke: OK\n";
