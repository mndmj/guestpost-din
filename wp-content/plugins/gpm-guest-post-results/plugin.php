<?php
/**
 * Plugin Name: GPM Guest Post Results
 * Description: Menyimpan hasil guest post pada order WooCommerce.
 * Version: 1.2.1
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: gpm-guest-post-results
 */

defined( "ABSPATH" ) || exit();

function gpm_guest_post_declare_hpos_compatibility() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            "custom_order_tables",
            __FILE__,
            true,
        );
    }
}
add_action(
    "before_woocommerce_init",
    "gpm_guest_post_declare_hpos_compatibility",
);

/** The site's verified service catalog. Names/headings may change; product IDs do not. */
function gpm_package_service( $product_id ) {
    $services = [ 91 => 'guestpost', 90 => 'guestpost', 83 => 'link_insertion', 51 => 'link_insertion' ];
    return is_scalar( $product_id ) ? ( $services[ (int) $product_id ] ?? '' ) : '';
}

function gpm_order_needs_guest_post_result( $order ) {
    if ( ! ( $order instanceof WC_Order ) ) {
        return false;
    }
    foreach ( $order->get_items() as $item ) {
        // Renewal/upgrade payment orders use the original publication and proof.
        $purchase = $item->get_meta( '_din_package_purchase' );
        $id = is_array( $purchase ) ? ( $purchase['package_id'] ?? null ) : null;
        if ( ( is_int( $id ) || is_string( $id ) ) && ctype_digit( (string) $id ) && (int) $id > 0 && in_array( $purchase['action'] ?? '', [ 'lifetime', 'renew_1', 'renew_2', 'renew_custom' ], true ) && 1.0 === (float) $item->get_quantity() ) {
            continue;
        }
        if ( 'guestpost' === gpm_package_service( $item->get_product_id() ) ) {
            return true;
        }
    }
    return false;
}

function gpm_render_guest_post_result_fields( $order ) {
    if ( ! gpm_order_needs_guest_post_result( $order ) ) {
        return;
    }

    $link_status = $order->get_meta( "_gpm_link_status" ) ?: "live";

    echo '<div class="gpm-order-result-fields">';
    echo "<h4>" .
        esc_html__( "Guest Post Result", "gpm-guest-post-results" ) .
        "</h4>";

    echo '<p>' . esc_html__( 'Published URL and Publication Date are required when completing this Guestpost order. Drafts may remain empty.', 'gpm-guest-post-results' ) . '</p>';
    wp_nonce_field( "gpm_save_guest_post_result", "gpm_guest_post_result_nonce" );
    $required = $order->has_status( 'completed' ) ? [ 'required' => 'required' ] : [];

    woocommerce_wp_text_input( [
        "id" => "_gpm_published_url",
        "label" => __( "Published URL", "gpm-guest-post-results" ),
        "type" => "url",
        "value" => $order->get_meta( "_gpm_published_url" ),
        "wrapper_class" => "form-field-wide",
        "placeholder" => "https://publisher.com/article",
        "custom_attributes" => $required + [ 'maxlength' => 2048 ],
        "description" => __( 'Use a full HTTP/HTTPS URL with a publisher domain, without login credentials, IP addresses or spaces.', 'gpm-guest-post-results' ),
    ] );

    woocommerce_wp_text_input( [
        "id" => "_gpm_published_at",
        "label" => __( "Publication Date", "gpm-guest-post-results" ),
        "type" => "date",
        "value" => $order->get_meta( "_gpm_published_at" ),
        "wrapper_class" => "form-field-wide",
        "custom_attributes" => $required + [ 'max' => current_datetime()->format( 'Y-m-d' ) ],
        "description" => __( 'Use the actual publication date, today or earlier in the site timezone.', 'gpm-guest-post-results' ),
    ] );

    woocommerce_wp_select( [
        "id" => "_gpm_link_status",
        "label" => __( "Link Status", "gpm-guest-post-results" ),
        "value" => $link_status,
        "wrapper_class" => "form-field-wide",
        "options" => [
            "live" => __( "Live", "gpm-guest-post-results" ),
            "removed" => __( "Removed", "gpm-guest-post-results" ),
        ],
    ] );

    echo "</div>";
    ?>
    <script>
        jQuery(function ($) {
            const fields = $('#_gpm_published_url, #_gpm_published_at');
            function syncRequired() {
                fields.prop('required', $('#order_status').val() === 'wc-completed');
            }
            $('#order_status').on('change.gpmGuestPostResult', syncRequired);
            syncRequired();
        });
    </script>
    <?php
}
add_action(
    "woocommerce_admin_order_data_after_order_details",
    "gpm_render_guest_post_result_fields",
);

function gpm_validate_guest_post_result( $input, $required ) {
    foreach ( [ '_gpm_published_url', '_gpm_published_at', '_gpm_link_status' ] as $field ) {
        if ( ! isset( $input[ $field ] ) || ! is_string( $input[ $field ] ) ) {
            return new WP_Error( 'gpm_result_input', __( 'Guest Post Result: invalid or missing form fields. Reload the order and try again.', 'gpm-guest-post-results' ) );
        }
    }
    $url = trim( $input['_gpm_published_url'] );
    $day = $input['_gpm_published_at'];
    $status = $input['_gpm_link_status'];
    if ( $required && ( '' === $url || '' === $day ) ) {
        return new WP_Error( 'gpm_result_required', __( 'Guest Post Result: Published URL and Publication Date are required before completing the order.', 'gpm-guest-post-results' ) );
    }
    if ( '' !== $url ) {
        $parts = wp_parse_url( $url );
        $host = is_array( $parts ) ? ( $parts['host'] ?? '' ) : '';
        // Publisher domains only: raw/numeric IP aliases are not publication URLs.
        $public_host = filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) && preg_match( '/\.[a-z][a-z0-9-]+$/iD', $host );
        if ( strlen( $url ) > 2048 || preg_match( '/[\x00-\x1f\x7f]/', $input['_gpm_published_url'] ) || preg_match( '/\s|%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! $public_host || ! in_array( strtolower( $parts['scheme'] ?? '' ), [ 'http', 'https' ], true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
            return new WP_Error( 'gpm_result_url', __( 'Guest Post Result: enter a full HTTP/HTTPS Published URL with a publisher domain, without credentials, IP addresses, spaces or control characters.', 'gpm-guest-post-results' ) );
        }
    }
    if ( '' !== $day ) {
        $date = preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $day ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() ) : false;
        if ( ! $date || $date->format( 'Y-m-d' ) !== $day || $date->getTimestamp() <= 0 || $day > current_datetime()->format( 'Y-m-d' ) ) {
            return new WP_Error( 'gpm_result_date', __( 'Guest Post Result: enter a real Publication Date, today or earlier in the site timezone.', 'gpm-guest-post-results' ) );
        }
    }
    if ( ! in_array( $status, [ 'live', 'removed' ], true ) ) {
        return new WP_Error( 'gpm_result_status', __( 'Guest Post Result: Link Status must be Live or Removed.', 'gpm-guest-post-results' ) );
    }
    return [ '_gpm_published_url' => esc_url_raw( $url, [ 'http', 'https' ] ), '_gpm_published_at' => $day, '_gpm_link_status' => $status ];
}

/** Request-local state: invalid publication input must also stop package approval at 80. */
function gpm_guest_post_result_save_blocked( $order_id ) {
    return ! empty( $GLOBALS['gpm_guest_post_result_blocked'][ (int) $order_id ] );
}

function gpm_save_guest_post_result_fields( $order_id, $unused_order ) {
    // Resolve WooCommerce's order model; never trust POST's original status or the hook argument.
    $order = wc_get_order( $order_id );
    $GLOBALS['gpm_guest_post_result_blocked'][ (int) $order_id ] = false;
    if ( ! gpm_order_needs_guest_post_result( $order ) ) {
        return;
    }
    $nonce = $_POST['gpm_guest_post_result_nonce'] ?? '';
    if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order->get_id() ) || ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'gpm_save_guest_post_result' ) ) {
        $result = new WP_Error( 'gpm_result_permission', __( 'Guest Post Result was not saved. Check your permissions and reload the order before trying again.', 'gpm-guest-post-results' ) );
    } elseif ( isset( $_POST['order_status'] ) && ! is_string( $_POST['order_status'] ) ) {
        $result = new WP_Error( 'gpm_result_order_status', __( 'Guest Post Result: invalid order status. Reload the order and try again.', 'gpm-guest-post-results' ) );
    } else {
        // Match native WooCommerce normalization so whitespace/HTML cannot bypass completion requirements.
        $target = wc_clean( wp_unslash( $_POST['order_status'] ?? '' ) );
        $required = '' !== $target ? in_array( $target, [ 'wc-completed', 'completed' ], true ) : $order->has_status( 'completed' );
        $result = gpm_validate_guest_post_result( wp_unslash( $_POST ), $required );
    }
    if ( ! is_wp_error( $result ) ) {
        try {
            foreach ( $result as $key => $value ) {
                $order->update_meta_data( $key, $value );
            }
            // Save metadata before WooCommerce's status save40 so its Completed email uses these values.
            $order->save_meta_data();
            return;
        } catch (Exception $error) {
            $result = new WP_Error( 'gpm_result_save', __( 'Guest Post Result could not be saved. Reload the order and try again.', 'gpm-guest-post-results' ) );
        }
    }
    $GLOBALS['gpm_guest_post_result_blocked'][ (int) $order_id ] = true;
    $_POST['order_status'] = 'wc-' . $order->get_status();
    WC_Admin_Meta_Boxes::add_error( $result->get_error_message() );
}
add_action(
    "woocommerce_process_shop_order_meta",
    "gpm_save_guest_post_result_fields",
    35,
    2,
);

/** Add this order's publication link to its buyer's attachment table. */
function gpm_buyer_order_publication_rows( $rows, $order ) {
    $buyer_id = get_current_user_id();
    if ( is_admin() || ! $buyer_id || ! is_wc_endpoint_url( 'view-order' ) ) {
        return $rows;
    }

    if ( ! ( $order instanceof WC_Order ) || (int) $order->get_customer_id() !== (int) $buyer_id || ! $order->has_status( 'completed' ) ) {
        return $rows;
    }

    $url = $order->get_meta( '_gpm_published_url' );
    $parts = is_string( $url ) ? wp_parse_url( $url ) : false;
    if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ) {
        return $rows;
    }
    $url = esc_url( $url, array( 'http', 'https' ) );
    if ( '' === $url ) {
        return $rows;
    }

    $removed = 'removed' === $order->get_meta( '_gpm_link_status' );
    ob_start();
    ?>
    <tr class="gpm-order-publication">
        <td><strong><?php echo esc_html__( 'Published Post', 'gpm-guest-post-results' ); ?></strong><?php if ( ! $removed ) : ?><br><?php echo esc_html( $parts['host'] ); ?><?php endif; ?>
        </td>
        <td>—</td>
        <td>—</td>
        <td>
            <?php if ( $removed ) : ?>
                <span
                    class="gpm-guest-result__status gpm-order-publication__removed"><?php echo esc_html__( 'Removed', 'gpm-guest-post-results' ); ?></span>
            <?php else : ?>
                <a class="woocommerce-button button" href="<?php echo $url; ?>" target="_blank"
                    rel="noopener noreferrer external"><?php echo esc_html__( 'View Published Post', 'gpm-guest-post-results' ); ?></a>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    return $rows . ob_get_clean();
}
add_filter( 'din_order_attach_extra_rows', 'gpm_buyer_order_publication_rows', 10, 2 );

/**
 * Menambahkan kolom Post sebelum kolom Order.
 */
function gpm_add_post_order_column( $columns ) {
    $new_columns = [];

    foreach ( $columns as $column_name => $column_label ) {
        if ( "order_number" === $column_name ) {
            $new_columns["gpm_post"] = __( "Post", "gpm-guest-post-results" );
        }

        $new_columns[ $column_name ] = $column_label;
    }

    return $new_columns;
}

add_filter(
    "manage_woocommerce_page_wc-orders_columns",
    "gpm_add_post_order_column",
    20,
);

add_filter( "manage_edit-shop_order_columns", "gpm_add_post_order_column", 20 );

/**
 * Menampilkan Heading Post pada daftar order HPOS.
 */
function gpm_render_post_order_column( $column_name, $order ) {
    if ( "gpm_post" !== $column_name || ! ( $order instanceof WC_Order ) ) {
        return;
    }

    $heading_post = trim( (string) $order->get_customer_note() );

    echo "" !== $heading_post ? esc_html( $heading_post ) : "&mdash;";
}

add_action(
    "manage_woocommerce_page_wc-orders_custom_column",
    "gpm_render_post_order_column",
    10,
    2,
);

/**
 * Menampilkan Heading Post pada daftar order legacy.
 */
function gpm_render_post_order_column_legacy( $column_name, $order_id ) {
    if ( "gpm_post" !== $column_name ) {
        return;
    }

    gpm_render_post_order_column( $column_name, wc_get_order( $order_id ) );
}

add_action(
    "manage_shop_order_posts_custom_column",
    "gpm_render_post_order_column_legacy",
    10,
    2,
);

/**
 * Memastikan perubahan hanya berjalan pada halaman order admin.
 */
function gpm_is_order_admin_screen() {
    $screen = function_exists( "get_current_screen" )
        ? get_current_screen()
        : null;

    return $screen &&
        in_array(
            $screen->id,
            [ "woocommerce_page_wc-orders", "shop_order" ],
            true,
        );
}

/**
 * Mengubah label customer note menjadi Heading Post.
 */
function gpm_rename_customer_note_admin_label(
    $translated_text,
    $original_text,
    $domain
) {
    if ( "woocommerce" !== $domain || ! gpm_is_order_admin_screen() ) {
        return $translated_text;
    }

    $labels = [
        "Customer provided note:" => "Heading Post:",
        "Customer provided note" => "Heading Post",
        "Customer notes about the order" => "Enter the post heading",
    ];

    return $labels[ $original_text ] ?? $translated_text;
}

add_filter( "gettext", "gpm_rename_customer_note_admin_label", 20, 3 );

/**
 * Styling Heading Post pada halaman order admin.
 */
function gpm_render_heading_post_admin_styles() {
    if ( ! gpm_is_order_admin_screen() ) {
        return;
    } ?>
    <style id="gpm-heading-post-admin-styles">
        #order_data .gpm-order-result-fields {
            clear: both;
            padding-top: 24px;
        }

        /* Tampilan Heading Post saat order tidak sedang diedit. */
        #order_data .order_data_column .address p.order_note {
            margin: 16px 0 0;
            padding: 12px 14px !important;
            overflow-wrap: anywhere;
            color: #1d2327;
            line-height: 1.55;
            background: #f0f7f4;
            border: 1px solid #c7ded4;
            border-left: 4px solid #2f7d61;
            border-radius: 6px;
        }

        #order_data .order_data_column .address p.order_note strong {
            display: block;
            margin-bottom: 4px;
            color: #1f664f;
            font-size: 12px;
            line-height: 1.4;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* Label Heading Post saat tombol edit ditekan. */
        #order_data .order_data_column .edit_address label[for="excerpt"] {
            color: #1f664f;
            font-weight: 600;
        }

        /* Input Heading Post saat order sedang diedit. */
        #order_data .order_data_column .edit_address textarea#excerpt {
            box-sizing: border-box;
            width: 100%;
            min-height: 80px;
            padding: 10px 12px;
            border: 1px solid #8c8f94;
            border-radius: 6px;
            resize: vertical;
        }

        #order_data .order_data_column .edit_address textarea#excerpt:focus {
            border-color: #2f7d61;
            outline: 2px solid #2f7d61;
            outline-offset: 1px;
            box-shadow: none;
        }
    </style>
    <?php
}

add_action( "admin_head", "gpm_render_heading_post_admin_styles", 20 );
