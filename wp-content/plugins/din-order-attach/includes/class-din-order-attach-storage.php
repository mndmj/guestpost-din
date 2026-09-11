<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DIN_Order_Attach_Storage {
	const META_KEY = '_din_order_attach_files';
	const MAX_SIZE = 10485760;

	private $root;
	private $move_file;
	private $delete_file;

	public function __construct( $root = null, $move_file = null, $delete_file = null ) {
		if ( null === $root ) {
			if ( defined( 'DIN_ORDER_ATTACH_STORAGE_ROOT' ) ) {
				$root = DIN_ORDER_ATTACH_STORAGE_ROOT;
			} else {
				$base = dirname( untrailingslashit( wp_normalize_path( ABSPATH ) ) );
				$root = $base . '/din-order-attach-private-' . substr( hash( 'sha256', home_url( '/' ) ), 0, 12 );
			}
		}

		$this->root        = untrailingslashit( wp_normalize_path( $root ) );
		$this->move_file   = $move_file;
		$this->delete_file = $delete_file;
	}

	public function get_root() {
		return $this->root;
	}

	public function validate_candidate( $filename, $size, $mime, $upload_error = UPLOAD_ERR_OK ) {
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			return new WP_Error( 'din_order_attach_upload_error', __( 'The upload did not complete.', 'din-order-attach' ) );
		}

		if ( $size < 1 || $size > self::MAX_SIZE ) {
			return new WP_Error( 'din_order_attach_invalid_size', __( 'The file must be between 1 byte and 10 MB.', 'din-order-attach' ) );
		}

		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$mime      = strtolower( trim( strtok( $mime, ';' ) ) );
		$allowed   = $this->allowed_mimes();

		if ( ! isset( $allowed[ $extension ] ) || ! in_array( $mime, $allowed[ $extension ], true ) ) {
			return new WP_Error( 'din_order_attach_invalid_type', __( 'This file type is not allowed.', 'din-order-attach' ) );
		}

		return true;
	}

	public function normalize_files( $files ) {
		if ( ! is_array( $files ) || empty( $files['name'] ) ) {
			return array();
		}

		if ( ! is_array( $files['name'] ) ) {
			return array( $files );
		}

		$normalized = array();
		foreach ( array_keys( $files['name'] ) as $index ) {
			$normalized[] = array(
				'name'     => $files['name'][ $index ] ?? '',
				'tmp_name' => $files['tmp_name'][ $index ] ?? '',
				'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $files['size'][ $index ] ?? 0,
			);
		}

		return $normalized;
	}

	public function store_batch( $order_id, $files, $user_id ) {
		$files = $this->normalize_files( $files );
		if ( empty( $files ) ) {
			return new WP_Error( 'din_order_attach_no_files', __( 'No files were selected.', 'din-order-attach' ) );
		}

		$validated = array();
		foreach ( $files as $file ) {
			$tmp_name = $file['tmp_name'];
			if ( ! is_file( $tmp_name ) || ! is_readable( $tmp_name ) ) {
				return $this->file_error(
					$file['name'],
					new WP_Error( 'din_order_attach_invalid_upload', __( 'The uploaded file cannot be read.', 'din-order-attach' ) )
				);
			}

			$mime = $this->detect_mime( $tmp_name );
			if ( is_wp_error( $mime ) ) {
				return $this->file_error( $file['name'], $mime );
			}

			$size  = filesize( $tmp_name );
			$valid = $this->validate_candidate( $file['name'], $size, $mime, $file['error'] );
			if ( is_wp_error( $valid ) ) {
				return $this->file_error( $file['name'], $valid );
			}

			$validated[] = array(
				'name'     => sanitize_file_name( $file['name'] ),
				'tmp_name' => $tmp_name,
				'extension' => strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ),
				'mime'     => $mime,
				'size'     => $size,
			);
		}

		$root = $this->prepare_root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return new WP_Error( 'din_order_attach_invalid_order', __( 'The order is invalid.', 'din-order-attach' ) );
		}

		$order_directory = $root . '/' . $order_id;
		if ( ! wp_mkdir_p( $order_directory ) || ! is_writable( $order_directory ) ) {
			return new WP_Error( 'din_order_attach_storage_unavailable', __( 'Private storage is not writable.', 'din-order-attach' ) );
		}

		$records = array();
		$moved   = array();
		foreach ( $validated as $file ) {
			$id       = wp_generate_uuid4();
			$relative = $order_id . '/' . $id . '.' . $file['extension'];
			$target   = $root . '/' . $relative;
			$mover    = $this->move_file;
			$success  = $mover ? call_user_func( $mover, $file['tmp_name'], $target ) : move_uploaded_file( $file['tmp_name'], $target );

			if ( ! $success ) {
				foreach ( $moved as $created_file ) {
					wp_delete_file( $created_file );
				}
				if ( empty( glob( $order_directory . '/*' ) ) ) {
					rmdir( $order_directory );
				}

				return $this->file_error(
					$file['name'],
					new WP_Error( 'din_order_attach_move_failed', __( 'The file could not be stored.', 'din-order-attach' ) )
				);
			}

			$moved[]   = $target;
			$records[] = array(
				'id'          => $id,
				'name'        => $file['name'],
				'path'        => $relative,
				'mime'        => $file['mime'],
				'size'        => $file['size'],
				'uploaded_by' => absint( $user_id ),
				'uploaded_at' => gmdate( 'c' ),
			);
		}

		return $records;
	}

	public function get_order_files( $order ) {
		$records = $order->get_meta( self::META_KEY );
		return is_array( $records ) ? array_values( $records ) : array();
	}

	public function store_for_order( $order, $files, $user_id ) {
		$existing = $this->get_order_files( $order );
		$records  = $this->store_batch( $order->get_id(), $files, $user_id );
		if ( is_wp_error( $records ) ) {
			return $records;
		}

		try {
			$this->save_order_files( $order, array_merge( $existing, $records ) );
		} catch ( Throwable $error ) {
			$this->rollback_records( $records );
			$order->update_meta_data( self::META_KEY, $existing );

			return new WP_Error( 'din_order_attach_metadata_failed', __( 'The attachment batch could not be added to the order.', 'din-order-attach' ) );
		}

		return $records;
	}

	public function save_order_files( $order, $records ) {
		$order->update_meta_data( self::META_KEY, array_values( $records ) );
		$order->save_meta_data();
	}

	public function delete_order_file( $order, $attachment_id ) {
		$records   = $this->get_order_files( $order );
		$target    = null;
		$remaining = array();
		foreach ( $records as $record ) {
			if ( null === $target && isset( $record['id'] ) && (string) $record['id'] === (string) $attachment_id ) {
				$target = $record;
				continue;
			}
			$remaining[] = $record;
		}

		if ( null === $target ) {
			return new WP_Error( 'din_order_attach_unavailable', __( 'Attachment unavailable.', 'din-order-attach' ) );
		}

		$path = $this->resolve_path( $target['path'] ?? '' );
		if ( is_wp_error( $path ) ) {
			return new WP_Error( 'din_order_attach_delete_failed', __( 'The attachment could not be deleted.', 'din-order-attach' ) );
		}

		try {
			$this->save_order_files( $order, $remaining );
		} catch ( Throwable $error ) {
			$order->update_meta_data( self::META_KEY, $records );
			return new WP_Error( 'din_order_attach_metadata_failed', __( 'The attachment could not be deleted.', 'din-order-attach' ) );
		}

		$delete  = $this->delete_file;
		$deleted = $delete ? call_user_func( $delete, $path ) : wp_delete_file( $path );
		if ( ! $deleted ) {
			$this->save_order_files( $order, $records );
			return new WP_Error( 'din_order_attach_delete_failed', __( 'The attachment could not be deleted.', 'din-order-attach' ) );
		}

		$directory = dirname( $path );
		if ( is_dir( $directory ) && empty( glob( $directory . '/*' ) ) ) {
			rmdir( $directory );
		}

		return $target;
	}

	public function delete_order_files( $order ) {
		$directories = array();
		$failed      = false;
		foreach ( $this->get_order_files( $order ) as $record ) {
			$path = $this->resolve_path( $record['path'] ?? '' );
			if ( is_wp_error( $path ) ) {
				$failed = true;
				continue;
			}

			$delete = $this->delete_file;
			if ( ! ( $delete ? call_user_func( $delete, $path ) : wp_delete_file( $path ) ) ) {
				$failed = true;
				continue;
			}

			$directories[] = dirname( $path );
		}

		foreach ( array_unique( $directories ) as $directory ) {
			if ( is_dir( $directory ) && empty( glob( $directory . '/*' ) ) ) {
				rmdir( $directory );
			}
		}

		return $failed
			? new WP_Error( 'din_order_attach_cleanup_failed', __( 'Some order attachments could not be removed.', 'din-order-attach' ) )
			: true;
	}

	public function resolve_path( $relative_path ) {
		$relative_path = wp_normalize_path( $relative_path );
		if ( empty( $relative_path ) || preg_match( '#^(?:[a-z]:|/)#i', $relative_path ) || in_array( '..', explode( '/', $relative_path ), true ) ) {
			return new WP_Error( 'din_order_attach_invalid_path', __( 'The attachment path is invalid.', 'din-order-attach' ) );
		}

		$root   = realpath( $this->root );
		$target = $root ? realpath( $root . '/' . $relative_path ) : false;
		if ( ! $root || ! $target || ! is_file( $target ) || ! $this->is_inside( $target, $root ) ) {
			return new WP_Error( 'din_order_attach_invalid_path', __( 'The attachment path is invalid.', 'din-order-attach' ) );
		}

		return wp_normalize_path( $target );
	}

	public function inspect_stored_file( $record, $path ) {
		if ( ! is_array( $record ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'din_order_attach_invalid_file', __( 'The stored attachment is invalid.', 'din-order-attach' ) );
		}

		$mime = $this->detect_mime( $path );
		$size = filesize( $path );
		if ( is_wp_error( $mime ) || false === $size ) {
			return new WP_Error( 'din_order_attach_invalid_file', __( 'The stored attachment is invalid.', 'din-order-attach' ) );
		}

		$valid         = $this->validate_candidate( $record['name'] ?? '', $size, $mime );
		$metadata_mime = strtolower( trim( strtok( (string) ( $record['mime'] ?? '' ), ';' ) ) );
		if ( is_wp_error( $valid ) || $metadata_mime !== $mime ) {
			return new WP_Error( 'din_order_attach_invalid_file', __( 'The stored attachment is invalid.', 'din-order-attach' ) );
		}

		return array(
			'mime' => $mime,
			'size' => $size,
		);
	}

	private function prepare_root() {
		$root        = $this->root;
		$public_root = untrailingslashit( wp_normalize_path( ABSPATH ) );

		if ( empty( $root ) || dirname( $root ) === $root || preg_match( '#^[a-z]:/?$#i', $root ) ) {
			return new WP_Error( 'din_order_attach_invalid_root', __( 'The private storage path is invalid.', 'din-order-attach' ) );
		}

		$root_prefix   = strtolower( trailingslashit( $root ) );
		$public_prefix = strtolower( trailingslashit( $public_root ) );
		if ( $root_prefix === $public_prefix || 0 === strpos( $root_prefix, $public_prefix ) ) {
			return new WP_Error( 'din_order_attach_public_root', __( 'Private storage must be outside the public site directory.', 'din-order-attach' ) );
		}

		if ( ! wp_mkdir_p( $root ) || ! is_writable( $root ) ) {
			return new WP_Error( 'din_order_attach_storage_unavailable', __( 'Private storage is not writable.', 'din-order-attach' ) );
		}

		$canonical        = realpath( $root );
		$canonical_public = realpath( $public_root );
		if ( ! $canonical || ( $canonical_public && $this->is_inside( $canonical, $canonical_public ) ) ) {
			return new WP_Error( 'din_order_attach_invalid_root', __( 'The private storage path is invalid.', 'din-order-attach' ) );
		}

		return untrailingslashit( wp_normalize_path( $canonical ) );
	}

	private function detect_mime( $path ) {
		if ( ! class_exists( 'finfo' ) ) {
			return new WP_Error( 'din_order_attach_mime_unavailable', __( 'The server cannot inspect file types.', 'din-order-attach' ) );
		}

		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->file( $path );

		return $mime ? strtolower( $mime ) : new WP_Error( 'din_order_attach_mime_unknown', __( 'The file type could not be verified.', 'din-order-attach' ) );
	}

	private function is_inside( $path, $root ) {
		$path = strtolower( wp_normalize_path( $path ) );
		$root = strtolower( untrailingslashit( wp_normalize_path( $root ) ) );

		return $path === $root || 0 === strpos( $path, trailingslashit( $root ) );
	}

	private function rollback_records( $records ) {
		$directories = array();
		foreach ( $records as $record ) {
			$path = $this->resolve_path( $record['path'] );
			if ( is_wp_error( $path ) ) {
				continue;
			}

			$directories[] = dirname( $path );
			wp_delete_file( $path );
		}

		foreach ( array_unique( $directories ) as $directory ) {
			if ( is_dir( $directory ) && empty( glob( $directory . '/*' ) ) ) {
				rmdir( $directory );
			}
		}
	}

	private function file_error( $filename, $error ) {
		$name = sanitize_file_name( $filename );

		return new WP_Error(
			$error->get_error_code(),
			sprintf( '%1$s: %2$s', $name ?: __( 'Attachment', 'din-order-attach' ), $error->get_error_message() )
		);
	}

	private function allowed_mimes() {
		return array(
			'pdf'  => array( 'application/pdf' ),
			'doc'  => array( 'application/msword', 'application/cdfv2', 'application/x-ole-storage' ),
			'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ),
			'xls'  => array( 'application/vnd.ms-excel', 'application/cdfv2', 'application/x-ole-storage' ),
			'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip' ),
			'jpg'  => array( 'image/jpeg' ),
			'png'  => array( 'image/png' ),
			'zip'  => array( 'application/zip', 'application/x-zip-compressed' ),
		);
	}
}
