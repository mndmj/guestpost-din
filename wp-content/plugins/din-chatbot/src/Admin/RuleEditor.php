<?php

namespace DinStudio\DinChatbot\Admin;

final class RuleEditor {
    private const POST_TYPE = 'din_chatbot_rule';
    private const TAXONOMY  = 'din_chatbot_topic';

    private bool $invalid_new_rule_topic = false;

    public function register(): void {
        add_action( 'add_meta_boxes_' . self::POST_TYPE, [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'wp_insert_post_data', [ $this, 'prevent_invalid_new_rule' ], 10, 2 );
        add_filter( 'redirect_post_location', [ $this, 'add_topic_error_to_redirect' ], 10, 2 );
        add_action( 'admin_notices', [ $this, 'render_topic_error' ] );
        add_filter( 'default_title', [ $this, 'prefill_unanswered_question' ], 10, 2 );
    }

    public function prefill_unanswered_question( string $title, \WP_Post $post ): string {
        if ( self::POST_TYPE !== $post->post_type || empty( $_GET['din_chatbot_prefill'] ) || ! is_string( $_GET['din_chatbot_prefill'] ) ) {
            return $title;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['din_chatbot_prefill'] ) );
        $text  = get_transient( UnansweredPage::prefill_key( $token ) );

        return is_string( $text ) ? $text : $title;
    }

    /**
     * @param array<mixed> $items
     * @return array<int, string>
     */
    public function sanitize_keywords( array $items ): array {
        $keywords = [];

        foreach ( $items as $item ) {
            if ( ! is_string( $item ) ) {
                continue;
            }

            $keyword = trim( sanitize_text_field( $item ) );
            if ( '' === $keyword ) {
                continue;
            }

            $keywords[] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $keyword ) : strtolower( $keyword );
        }

        return $keywords;
    }

    public function add_meta_boxes(): void {
        remove_meta_box( self::TAXONOMY . 'div', self::POST_TYPE, 'side' );
        add_meta_box( 'din-chatbot-rule-settings', __( 'Rule settings', 'din-chatbot' ), [ $this, 'render_settings' ], self::POST_TYPE, 'normal', 'high' );
        add_meta_box( 'din-chatbot-rule-topic', __( 'Topic', 'din-chatbot' ), [ $this, 'render_topic' ], self::POST_TYPE, 'side', 'high' );
    }

    public function render_settings( \WP_Post $post ): void {
        wp_nonce_field( 'din_chatbot_rule_meta', 'din_chatbot_rule_meta_nonce' );
        $active      = (int) get_post_meta( $post->ID, '_din_active', true );
        $priority    = (int) get_post_meta( $post->ID, '_din_priority', true );
        $examples    = get_post_meta( $post->ID, '_din_examples', true );
        $keywords    = get_post_meta( $post->ID, '_din_keywords', true );
        $answer      = (string) get_post_meta( $post->ID, '_din_answer', true );
        $variation   = get_post_meta( $post->ID, '_din_variation_ids', true );
        $suggestions = get_post_meta( $post->ID, '_din_suggestions', true );

        $examples    = is_array( $examples ) ? $examples : [];
        $keywords    = is_array( $keywords ) ? $keywords : [];
        $variation   = is_array( $variation ) ? $variation : [];
        $suggestions = is_array( $suggestions ) ? $suggestions : [];
        ?>
        <p><label><input name="din_rule[active]" type="checkbox" value="1" <?php checked( $active ); ?> /> <?php esc_html_e( 'Active', 'din-chatbot' ); ?></label></p>
        <p><label for="din-rule-priority"><?php esc_html_e( 'Priority', 'din-chatbot' ); ?></label><br />
        <input id="din-rule-priority" name="din_rule[priority]" type="number" min="0" value="<?php echo esc_attr( (string) $priority ); ?>" /></p>
        <?php $this->render_text_repeater( __( 'Examples', 'din-chatbot' ), 'din_rule[examples][]', $examples, __( 'Add example', 'din-chatbot' ) ); ?>
        <?php $this->render_text_repeater( __( 'Keywords', 'din-chatbot' ), 'din_rule[keywords][]', $keywords, __( 'Add keyword', 'din-chatbot' ) ); ?>
        <p><label for="din-rule-answer"><?php esc_html_e( 'Answer', 'din-chatbot' ); ?></label><br />
        <textarea class="large-text" id="din-rule-answer" name="din_rule[answer]" rows="6"><?php echo esc_textarea( $answer ); ?></textarea></p>
        <?php $this->render_variation_selector( $variation ); ?>
        <?php $this->render_suggestions( $suggestions ); ?>
        <?php
    }

    public function render_topic( \WP_Post $post ): void {
        $terms    = get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ] );
        $assigned = wp_get_object_terms( $post->ID, self::TAXONOMY, [ 'fields' => 'ids' ] );
        $selected = is_wp_error( $assigned ) || empty( $assigned ) ? 0 : (int) $assigned[0];
        ?>
        <p><label for="din-rule-topic-id"><?php esc_html_e( 'Select one topic', 'din-chatbot' ); ?></label></p>
        <select class="widefat" id="din-rule-topic-id" name="din_rule[topic_id]" required>
            <option value="0"><?php esc_html_e( 'Select a topic', 'din-chatbot' ); ?></option>
            <?php if ( ! is_wp_error( $terms ) ) : ?>
                <?php foreach ( $terms as $term ) : ?>
                    <option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( $selected, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <?php
    }

    public function save( int $post_id ): void {
        if ( $this->is_ignored_save( $post_id ) || empty( $_POST['din_chatbot_rule_meta_nonce'] ) || ! is_string( $_POST['din_chatbot_rule_meta_nonce'] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['din_chatbot_rule_meta_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'din_chatbot_rule_meta' ) ) {
            return;
        }

        $input = isset( $_POST['din_rule'] ) && is_array( $_POST['din_rule'] ) ? wp_unslash( $_POST['din_rule'] ) : [];
        $input = is_array( $input ) ? $input : [];
        $topic = $this->valid_topic( $input['topic_id'] ?? null );
        if ( ! $topic || is_wp_error( $topic ) ) {
            return;
        }

        $active      = isset( $input['active'] ) && ( '1' === $input['active'] || 1 === $input['active'] ) ? 1 : 0;
        $priority    = self::nonnegative_integer( $input['priority'] ?? null );
        $priority    = null === $priority ? 0 : $priority;
        $examples    = $this->sanitize_text_items( is_array( $input['examples'] ?? null ) ? $input['examples'] : [] );
        $keywords    = $this->sanitize_keywords( is_array( $input['keywords'] ?? null ) ? $input['keywords'] : [] );
        $answer_raw  = $input['answer'] ?? '';
        $answer      = wp_kses_post( is_string( $answer_raw ) ? $answer_raw : '' );
        $variations  = $this->sanitize_variation_ids( is_array( $input['variation_ids'] ?? null ) ? $input['variation_ids'] : [] );
        $suggestions = ( new TopicEditor() )->sanitize_suggestions( is_array( $input['suggestions'] ?? null ) ? $input['suggestions'] : [] );

        update_post_meta( $post_id, '_din_active', $active );
        update_post_meta( $post_id, '_din_priority', $priority );
        update_post_meta( $post_id, '_din_examples', $examples );
        update_post_meta( $post_id, '_din_keywords', $keywords );
        update_post_meta( $post_id, '_din_answer', $answer );
        update_post_meta( $post_id, '_din_variation_ids', $variations );
        update_post_meta( $post_id, '_din_suggestions', $suggestions );
        wp_set_object_terms( $post_id, [ (int) $topic->term_id ], self::TAXONOMY, false );
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $postarr */
    public function prevent_invalid_new_rule( array $data, array $postarr ): array {
        $status = $data['post_status'] ?? '';
        if ( self::POST_TYPE !== ( $data['post_type'] ?? '' ) || ! empty( $postarr['ID'] ) || ! in_array( $status, [ 'publish', 'private', 'future' ], true ) ) {
            return $data;
        }

        $this->invalid_new_rule_topic = false;
        $input                        = isset( $_POST['din_rule'] ) && is_array( $_POST['din_rule'] ) ? wp_unslash( $_POST['din_rule'] ) : null;
        if ( ! is_array( $input ) || ! $this->valid_topic( $input['topic_id'] ?? null ) ) {
            $this->invalid_new_rule_topic = true;
            $data['post_status']          = 'draft';
        }

        return $data;
    }

    public function add_topic_error_to_redirect( string $location, int $post_id ): string {
        if ( ! $this->invalid_new_rule_topic || self::POST_TYPE !== get_post_type( $post_id ) ) {
            return $location;
        }

        return add_query_arg( 'din_chatbot_topic_error', '1', $location );
    }

    public function render_topic_error(): void {
        if ( empty( $_GET['din_chatbot_topic_error'] ) || '1' !== $_GET['din_chatbot_topic_error'] ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html__( 'A Rule needs exactly one valid Topic. The Rule was saved as a draft.', 'din-chatbot' ) . '</p></div>';
    }

    public function enqueue_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen || self::POST_TYPE !== $screen->post_type || ! in_array( $screen->base, [ 'post', 'post-new' ], true ) ) {
            return;
        }

        wp_enqueue_style( 'din-chatbot-admin', plugins_url( 'assets/admin.css', DIN_CHATBOT_FILE ), [], '0.1.0' );
        wp_enqueue_script( 'din-chatbot-admin', plugins_url( 'assets/admin.js', DIN_CHATBOT_FILE ), [], '0.1.0', true );
        wp_localize_script(
            'din-chatbot-admin',
            'dinChatbotVariationSearch',
            [
                'url'   => esc_url_raw( rest_url( 'din-chatbot/v1/variations' ) ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            ]
        );
    }

    private function is_ignored_save( int $post_id ): bool {
        return wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id );
    }

    /** @param array<mixed> $items */
    private function sanitize_text_items( array $items ): array {
        $sanitized = [];
        foreach ( $items as $item ) {
            if ( ! is_string( $item ) ) {
                continue;
            }

            $value = trim( sanitize_text_field( $item ) );
            if ( '' !== $value ) {
                $sanitized[] = $value;
            }
        }

        return $sanitized;
    }

    /** @param array<mixed> $items */
    private function sanitize_variation_ids( array $items ): array {
        $ids = [];
        foreach ( $items as $item ) {
            $id = self::positive_integer( $item );
            if ( null !== $id ) {
                $ids[] = $id;
            }
        }

        return array_values( array_unique( $ids ) );
    }

    private function valid_topic( mixed $value ): ?\WP_Term {
        $topic_id = self::positive_integer( $value );
        if ( null === $topic_id ) {
            return null;
        }

        $term = get_term( $topic_id, self::TAXONOMY );
        return $term instanceof \WP_Term ? $term : null;
    }

    private static function positive_integer( mixed $value ): ?int {
        if ( is_int( $value ) ) {
            return $value > 0 ? $value : null;
        }

        if ( ! is_string( $value ) || ! preg_match( '/^[1-9][0-9]*$/D', $value ) ) {
            return null;
        }

        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private static function nonnegative_integer( mixed $value ): ?int {
        if ( is_int( $value ) ) {
            return $value >= 0 ? $value : null;
        }

        if ( ! is_string( $value ) || ! preg_match( '/^(?:0|[1-9][0-9]*)$/D', $value ) ) {
            return null;
        }

        return (int) $value;
    }

    /** @param array<mixed> $items */
    private function render_text_repeater( string $label, string $name, array $items, string $add_label, string $type = 'text' ): void {
        ?>
        <div class="din-repeater" data-din-repeater>
            <p><strong><?php echo esc_html( $label ); ?></strong></p>
            <div class="din-repeater__rows" data-din-rows>
                <?php foreach ( $items as $item ) : ?>
                    <?php $this->render_text_row( $name, (string) $item, $type ); ?>
                <?php endforeach; ?>
            </div>
            <template data-din-template><?php $this->render_text_row( $name, '', $type ); ?></template>
            <button type="button" class="button" data-din-add><?php echo esc_html( $add_label ); ?></button>
        </div>
        <?php
    }

    private function render_text_row( string $name, string $value, string $type ): void {
        ?>
        <div class="din-repeater__row">
            <input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>" <?php echo 'number' === $type ? 'min="1"' : ''; ?> value="<?php echo esc_attr( $value ); ?>" />
            <button type="button" class="button-link" data-din-up aria-label="<?php esc_attr_e( 'Move item up', 'din-chatbot' ); ?>">&#8593;</button>
            <button type="button" class="button-link" data-din-down aria-label="<?php esc_attr_e( 'Move item down', 'din-chatbot' ); ?>">&#8595;</button>
            <button type="button" class="button-link-delete" data-din-remove><?php esc_html_e( 'Remove', 'din-chatbot' ); ?></button>
        </div>
        <?php
    }

    /** @param array<mixed> $variation_ids */
    private function render_variation_selector( array $variation_ids ): void {
        $variation_ids = $this->sanitize_variation_ids( $variation_ids );
        $cards         = ( new \DinStudio\DinChatbot\VariationProvider() )->cards( $variation_ids, max( 1, count( $variation_ids ) ) );
        $cards_by_id   = [];
        foreach ( $cards as $card ) {
            $cards_by_id[ $card['variation_id'] ] = $card;
        }
        ?>
        <div class="din-variation-selector" data-din-variation-selector>
            <p><strong><?php esc_html_e( 'WooCommerce variations', 'din-chatbot' ); ?></strong></p>
            <label for="din-variation-search"><?php esc_html_e( 'Search product variations', 'din-chatbot' ); ?></label>
            <input class="regular-text" id="din-variation-search" type="search" data-din-variation-search aria-describedby="din-variation-status" autocomplete="off" />
            <p id="din-variation-status" data-din-variation-status role="status" aria-live="polite"><?php esc_html_e( 'Type at least 2 characters to search.', 'din-chatbot' ); ?></p>
            <ul data-din-variation-results aria-label="<?php esc_attr_e( 'Variation search results', 'din-chatbot' ); ?>"></ul>
            <p><strong><?php esc_html_e( 'Selected variations', 'din-chatbot' ); ?></strong></p>
            <ul data-din-variation-selected aria-label="<?php esc_attr_e( 'Selected variations', 'din-chatbot' ); ?>">
                <?php foreach ( $variation_ids as $variation_id ) : ?>
                    <?php $card = $cards_by_id[ $variation_id ] ?? null; ?>
                    <li data-din-variation-id="<?php echo esc_attr( (string) $variation_id ); ?>">
                        <input name="din_rule[variation_ids][]" type="hidden" value="<?php echo esc_attr( (string) $variation_id ); ?>" />
                        <?php if ( is_array( $card ) ) : ?>
                            <span><?php echo esc_html( $card['name'] ); ?></span>
                        <?php else : ?>
                            <span><?php echo esc_html( sprintf( __( 'Variation #%d is currently unavailable.', 'din-chatbot' ), $variation_id ) ); ?></span>
                        <?php endif; ?>
                        <button type="button" class="button-link-delete" data-din-variation-remove><?php esc_html_e( 'Remove', 'din-chatbot' ); ?></button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /** @param array<mixed> $suggestions */
    private function render_suggestions( array $suggestions ): void {
        ?>
        <div class="din-repeater" data-din-repeater>
            <p><strong><?php esc_html_e( 'Suggestions', 'din-chatbot' ); ?></strong></p>
            <div class="din-repeater__rows" data-din-rows>
                <?php foreach ( $suggestions as $index => $suggestion ) : ?>
                    <?php $this->render_suggestion_row( 'din_rule[suggestions][' . (int) $index . ']', is_array( $suggestion ) ? $suggestion : [] ); ?>
                <?php endforeach; ?>
            </div>
            <template data-din-template><?php $this->render_suggestion_row( 'din_rule[suggestions][__INDEX__]', [] ); ?></template>
            <button type="button" class="button" data-din-add><?php esc_html_e( 'Add suggestion', 'din-chatbot' ); ?></button>
        </div>
        <?php
    }

    /** @param array<string, mixed> $suggestion */
    private function render_suggestion_row( string $name, array $suggestion ): void {
        $label     = (string) ( $suggestion['label'] ?? '' );
        $type      = (string) ( $suggestion['type'] ?? 'topic' );
        $target_id = (int) ( $suggestion['target_id'] ?? 0 );
        ?>
        <div class="din-repeater__row">
            <input aria-label="<?php esc_attr_e( 'Suggestion label', 'din-chatbot' ); ?>" name="<?php echo esc_attr( $name ); ?>[label]" type="text" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'Label', 'din-chatbot' ); ?>" />
            <select aria-label="<?php esc_attr_e( 'Suggestion type', 'din-chatbot' ); ?>" name="<?php echo esc_attr( $name ); ?>[type]">
                <?php foreach ( [ 'topic', 'rule', 'restart', 'contact', 'operator' ] as $option ) : ?>
                    <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $type, $option ); ?>><?php echo esc_html( ucfirst( $option ) ); ?></option>
                <?php endforeach; ?>
            </select>
            <input aria-label="<?php esc_attr_e( 'Suggestion target ID', 'din-chatbot' ); ?>" name="<?php echo esc_attr( $name ); ?>[target_id]" type="number" min="0" value="<?php echo esc_attr( (string) $target_id ); ?>" placeholder="<?php esc_attr_e( 'Target ID', 'din-chatbot' ); ?>" />
            <button type="button" class="button-link" data-din-up aria-label="<?php esc_attr_e( 'Move suggestion up', 'din-chatbot' ); ?>">&#8593;</button>
            <button type="button" class="button-link" data-din-down aria-label="<?php esc_attr_e( 'Move suggestion down', 'din-chatbot' ); ?>">&#8595;</button>
            <button type="button" class="button-link-delete" data-din-remove><?php esc_html_e( 'Remove', 'din-chatbot' ); ?></button>
        </div>
        <?php
    }
}
