<?php

namespace DinStudio\DinChatbot\Admin;

use DinStudio\DinChatbot\UnansweredRepository;

if ( ! class_exists( '\\WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class UnansweredPage {
    private const CAPABILITY = 'manage_din_chatbot';

    public function __construct( private UnansweredRepository $repository ) {}

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_page' ] );
        add_action( 'admin_post_din_chatbot_create_rule', [ $this, 'handle_create_rule' ] );
        add_action( 'admin_post_din_chatbot_delete_unanswered', [ $this, 'handle_delete' ] );
        add_action( 'admin_post_din_chatbot_delete_all_unanswered', [ $this, 'handle_delete_all' ] );
    }

    public function add_page(): void {
        add_submenu_page( 'edit.php?post_type=din_chatbot_rule', __( 'Unanswered', 'din-chatbot' ), __( 'Unanswered', 'din-chatbot' ), self::CAPABILITY, 'din-chatbot-unanswered', [ $this, 'render' ] );
    }

    public function render(): void {
        $this->require_manage_capability();
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

        $table = new UnansweredListTable( $this->repository );
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Unanswered chatbot questions', 'din-chatbot' ); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('Delete all unanswered questions? This cannot be undone.');">
                <input type="hidden" name="action" value="din_chatbot_delete_all_unanswered" />
                <?php wp_nonce_field( 'din_chatbot_delete_all_unanswered' ); ?>
                <?php submit_button( __( 'Delete All', 'din-chatbot' ), 'delete', 'submit', false ); ?>
            </form>
            <?php $table->display(); ?>
        </div>
        <?php
    }

    public function handle_create_rule(): void {
        $this->require_manage_capability();
        $id    = $this->request_id( $_GET );
        $token = isset( $_GET['din_chatbot_action_token'] ) && is_string( $_GET['din_chatbot_action_token'] ) ? sanitize_text_field( wp_unslash( $_GET['din_chatbot_action_token'] ) ) : '';
        $row = $this->repository->find( $id );
        if ( null === $row ) {
            wp_die( esc_html__( 'Unanswered question not found.', 'din-chatbot' ), '', [ 'response' => 404 ] );
        }
        if ( ! $this->consume_create_rule_token( $token, $id ) ) {
            wp_die( esc_html__( 'Forbidden.', 'din-chatbot' ), '', [ 'response' => 403 ] );
        }

        $prefill_token = bin2hex( random_bytes( 24 ) );
        set_transient( $this->prefill_key( $prefill_token ), (string) $row->question_text, 15 * MINUTE_IN_SECONDS );
        wp_safe_redirect( add_query_arg( [ 'post_type' => 'din_chatbot_rule', 'din_chatbot_prefill' => $prefill_token ], admin_url( 'post-new.php' ) ) );
        exit;
    }

    public function handle_delete(): void {
        $this->require_manage_capability();
        $this->require_post();
        $id = $this->request_id( $_POST );
        check_admin_referer( 'din_chatbot_delete_unanswered_' . $id );
        $this->repository->delete( $id );
        $this->redirect_to_queue();
    }

    public function handle_delete_all(): void {
        $this->require_manage_capability();
        $this->require_post();
        check_admin_referer( 'din_chatbot_delete_all_unanswered' );
        $this->repository->delete_all();
        $this->redirect_to_queue();
    }

    public static function prefill_key( string $token ): string {
        return 'din_chatbot_rule_prefill_' . get_current_user_id() . '_' . $token;
    }

    public static function create_action_token( int $row_id ): string {
        $token = bin2hex( random_bytes( 24 ) );
        set_transient(
            self::action_token_key( $token ),
            [
                'user_id'    => get_current_user_id(),
                'row_id'     => $row_id,
                'expires_at' => time() + ( 15 * MINUTE_IN_SECONDS ),
            ],
            15 * MINUTE_IN_SECONDS
        );

        return $token;
    }

    public static function action_token_key( string $token ): string {
        return 'din_chatbot_rule_action_' . hash( 'sha256', $token );
    }

    public function consume_create_rule_token( string $token, int $row_id ): bool {
        if ( ! preg_match( '/\A[a-f0-9]{48}\z/D', $token ) || $row_id < 1 ) {
            return false;
        }

        $key   = self::action_token_key( $token );
        $token = get_transient( $key );
        if ( ! is_array( $token ) || (int) ( $token['expires_at'] ?? 0 ) < time() ) {
            if ( is_array( $token ) ) {
                delete_transient( $key );
            }

            return false;
        }

        if ( (int) ( $token['user_id'] ?? 0 ) !== get_current_user_id() || (int) ( $token['row_id'] ?? 0 ) !== $row_id ) {
            return false;
        }

        return delete_transient( $key );
    }

    private function require_manage_capability(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Forbidden.', 'din-chatbot' ), '', [ 'response' => 403 ] );
        }
    }

    /** @param array<string, mixed> $input */
    private function request_id( array $input ): int {
        $id = $input['unanswered_id'] ?? 0;
        return is_string( $id ) || is_int( $id ) ? absint( $id ) : 0;
    }

    private function require_post(): void {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
            wp_die( esc_html__( 'Method not allowed.', 'din-chatbot' ), '', [ 'response' => 405 ] );
        }
    }

    private function redirect_to_queue(): void {
        wp_safe_redirect( admin_url( 'edit.php?post_type=din_chatbot_rule&page=din-chatbot-unanswered' ) );
        exit;
    }
}

final class UnansweredListTable extends \WP_List_Table {
    public function __construct( private UnansweredRepository $repository ) {
        parent::__construct( [ 'singular' => 'unanswered question', 'plural' => 'unanswered questions', 'ajax' => false ] );
    }

    /** @return array<string, string> */
    public function get_columns(): array {
        return [
            'question_text'    => __( 'Question', 'din-chatbot' ),
            'topic'            => __( 'Topic', 'din-chatbot' ),
            'first_seen_at'    => __( 'First seen', 'din-chatbot' ),
            'last_seen_at'     => __( 'Last seen', 'din-chatbot' ),
            'occurrence_count' => __( 'Count', 'din-chatbot' ),
            'expires_at'       => __( 'Expires', 'din-chatbot' ),
        ];
    }

    public function prepare_items(): void {
        global $wpdb;

        $per_page = 20;
        $page     = $this->get_pagenum();
        $table    = $wpdb->prefix . 'din_chatbot_unanswered';
        $total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $offset   = ( $page - 1 ) * $per_page;
        $this->items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY last_seen_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
        $this->set_pagination_args( [ 'total_items' => $total, 'per_page' => $per_page ] );
    }

    /** @param object $item */
    public function column_question_text( $item ): string {
        $id      = (int) $item->id;
        $create  = add_query_arg( [ 'action' => 'din_chatbot_create_rule', 'unanswered_id' => $id, 'din_chatbot_action_token' => UnansweredPage::create_action_token( $id ) ], admin_url( 'admin-post.php' ) );
        $delete  = wp_nonce_field( 'din_chatbot_delete_unanswered_' . $id, '_wpnonce', true, false );
        $delete .= '<input type="hidden" name="action" value="din_chatbot_delete_unanswered" /><input type="hidden" name="unanswered_id" value="' . esc_attr( (string) $id ) . '" />';
        $delete .= '<button type="submit" class="button-link-delete" onclick="return window.confirm(\'Delete this unanswered question? This cannot be undone.\');">' . esc_html__( 'Delete', 'din-chatbot' ) . '</button>';

        return esc_html( (string) $item->question_text )
            . '<div class="row-actions"><span class="create"><a href="' . esc_url( $create ) . '">' . esc_html__( 'Create Rule', 'din-chatbot' ) . '</a> | </span>'
            . '<span class="delete"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">' . $delete . '</form></span></div>';
    }

    /** @param object $item */
    public function column_topic( $item ): string {
        $term = get_term( (int) $item->topic_id, 'din_chatbot_topic' );
        return $term instanceof \WP_Term ? esc_html( $term->name ) : esc_html__( 'No topic', 'din-chatbot' );
    }

    /** @param object $item @param string $column_name */
    public function column_default( $item, $column_name ): string {
        if ( 'occurrence_count' === $column_name ) {
            return esc_html( number_format_i18n( (int) $item->occurrence_count ) );
        }

        return esc_html( (string) $item->{$column_name} );
    }
}
