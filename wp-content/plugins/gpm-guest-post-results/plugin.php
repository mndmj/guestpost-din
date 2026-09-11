<?php
/**
 * Plugin Name: GPM Guest Post Results
 * Description: Menyimpan hasil guest post pada order WooCommerce.
 * Version: 1.2.0
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

function gpm_render_guest_post_result_fields( $order ) {
    if ( ! ( $order instanceof WC_Order ) ) {
        return;
    }

    $link_status = $order->get_meta( "_gpm_link_status" ) ?: "live";

    echo '<div class="gpm-order-result-fields">';
    echo "<h4>" .
        esc_html__( "Guest Post Result", "gpm-guest-post-results" ) .
        "</h4>";

    wp_nonce_field( "gpm_save_guest_post_result", "gpm_guest_post_result_nonce" );

    woocommerce_wp_text_input( [
        "id" => "_gpm_published_url",
        "label" => __( "Published URL", "gpm-guest-post-results" ),
        "type" => "url",
        "value" => $order->get_meta( "_gpm_published_url" ),
        "wrapper_class" => "form-field-wide",
        "placeholder" => "https://publisher.com/article",
    ] );

    woocommerce_wp_text_input( [
        "id" => "_gpm_anchor_text",
        "label" => __( "Anchor Text", "gpm-guest-post-results" ),
        "type" => "text",
        "value" => $order->get_meta( "_gpm_anchor_text" ),
        "wrapper_class" => "form-field-wide",
    ] );

    woocommerce_wp_text_input( [
        "id" => "_gpm_published_at",
        "label" => __( "Publication Date", "gpm-guest-post-results" ),
        "type" => "date",
        "value" => $order->get_meta( "_gpm_published_at" ),
        "wrapper_class" => "form-field-wide",
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
}
add_action(
    "woocommerce_admin_order_data_after_order_details",
    "gpm_render_guest_post_result_fields",
);

function gpm_save_guest_post_result_fields( $order_id, $order ) {
    if (
        ! isset( $_POST["gpm_guest_post_result_nonce"] ) ||
        ! wp_verify_nonce(
            sanitize_text_field(
                wp_unslash( $_POST["gpm_guest_post_result_nonce"] ),
            ),
            "gpm_save_guest_post_result",
        )
    ) {
        return;
    }

    if ( ! current_user_can( "edit_shop_orders" ) ) {
        return;
    }

    if ( ! ( $order instanceof WC_Order ) ) {
        $order = wc_get_order( $order_id );
    }

    if ( ! $order ) {
        return;
    }

    $published_url = isset( $_POST["_gpm_published_url"] )
        ? esc_url_raw( wp_unslash( $_POST["_gpm_published_url"] ), [
            "http",
            "https",
        ] )
        : "";

    $anchor_text = isset( $_POST["_gpm_anchor_text"] )
        ? sanitize_text_field( wp_unslash( $_POST["_gpm_anchor_text"] ) )
        : "";

    $published_at = isset( $_POST["_gpm_published_at"] )
        ? sanitize_text_field( wp_unslash( $_POST["_gpm_published_at"] ) )
        : "";

    if ( "" !== $published_at ) {
        $date = DateTime::createFromFormat( "!Y-m-d", $published_at );

        if ( ! $date || $date->format( "Y-m-d" ) !== $published_at ) {
            $published_at = "";
        }
    }

    $link_status = isset( $_POST["_gpm_link_status"] )
        ? sanitize_key( wp_unslash( $_POST["_gpm_link_status"] ) )
        : "live";

    if ( ! in_array( $link_status, [ "live", "removed" ], true ) ) {
        $link_status = "live";
    }

    $order->update_meta_data( "_gpm_published_url", $published_url );
    $order->update_meta_data( "_gpm_anchor_text", $anchor_text );
    $order->update_meta_data( "_gpm_published_at", $published_at );
    $order->update_meta_data( "_gpm_link_status", $link_status );
    $order->save();
}
add_action(
    "woocommerce_process_shop_order_meta",
    "gpm_save_guest_post_result_fields",
    60,
    2,
);

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
    $domain,
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
