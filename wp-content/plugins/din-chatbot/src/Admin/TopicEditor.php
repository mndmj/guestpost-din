<?php

namespace DinStudio\DinChatbot\Admin;

final class TopicEditor {
    private const TAXONOMY = 'din_chatbot_topic';

    public function register(): void {
        add_action( self::TAXONOMY . '_add_form_fields', [ $this, 'render_add_fields' ] );
        add_action( self::TAXONOMY . '_edit_form_fields', [ $this, 'render_edit_fields' ], 10, 2 );
        add_action( 'created_' . self::TAXONOMY, [ $this, 'save' ] );
        add_action( 'edited_' . self::TAXONOMY, [ $this, 'save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * @param array<mixed> $items
     * @return array<int, array{label: string, type: string, target_id: int}>
     */
    public function sanitize_suggestions( array $items ): array {
        $suggestions = [];
        $types       = [ 'topic', 'rule', 'restart', 'contact', 'operator' ];

        foreach ( $items as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $label_raw  = $item['label'] ?? null;
            $type_raw   = $item['type'] ?? null;
            $target_raw = $item['target_id'] ?? null;
            if ( ! is_string( $label_raw ) || ! is_string( $type_raw ) ) {
                continue;
            }

            $label = trim( sanitize_text_field( $label_raw ) );
            $type  = sanitize_key( $type_raw );

            if ( '' === $label || ! in_array( $type, $types, true ) ) {
                continue;
            }

            $target_id = self::nonnegative_integer( $target_raw );
            if ( null === $target_id ) {
                continue;
            }
            if ( in_array( $type, [ 'topic', 'rule' ], true ) && $target_id < 1 ) {
                continue;
            }

            if ( in_array( $type, [ 'restart', 'contact', 'operator' ], true ) ) {
                $target_id = 0;
            }

            $suggestions[] = [
                'label'     => $label,
                'type'      => $type,
                'target_id' => $target_id,
            ];
        }

        return $suggestions;
    }

    public function render_add_fields(): void {
        wp_nonce_field( 'din_chatbot_topic_meta', 'din_chatbot_topic_meta_nonce' );
        $this->render_add_field_markup( [] );
    }

    public function render_edit_fields( \WP_Term $term ): void {
        wp_nonce_field( 'din_chatbot_topic_meta', 'din_chatbot_topic_meta_nonce' );
        $this->render_edit_field_markup(
            [
                'active'          => (int) get_term_meta( $term->term_id, '_din_active', true ),
                'order'           => (int) get_term_meta( $term->term_id, '_din_order', true ),
                'opening_message' => (string) get_term_meta( $term->term_id, '_din_opening_message', true ),
                'suggestions'     => get_term_meta( $term->term_id, '_din_suggestions', true ),
            ]
        );
    }

    public function save( int $term_id ): void {
        if ( ! current_user_can( 'manage_din_chatbot' ) || empty( $_POST['din_chatbot_topic_meta_nonce'] ) || ! is_string( $_POST['din_chatbot_topic_meta_nonce'] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['din_chatbot_topic_meta_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'din_chatbot_topic_meta' ) ) {
            return;
        }

        $input = isset( $_POST['din_topic'] ) && is_array( $_POST['din_topic'] ) ? wp_unslash( $_POST['din_topic'] ) : [];
        $input = is_array( $input ) ? $input : [];

        $active      = isset( $input['active'] ) && ( '1' === $input['active'] || 1 === $input['active'] ) ? 1 : 0;
        $order       = self::nonnegative_integer( $input['order'] ?? null );
        $order       = null === $order ? 0 : $order;
        $opening_raw = $input['opening_message'] ?? '';
        $opening     = wp_kses_post( is_string( $opening_raw ) ? $opening_raw : '' );
        $suggestions = $this->sanitize_suggestions( is_array( $input['suggestions'] ?? null ) ? $input['suggestions'] : [] );

        update_term_meta( $term_id, '_din_active', $active );
        update_term_meta( $term_id, '_din_order', $order );
        update_term_meta( $term_id, '_din_opening_message', $opening );
        update_term_meta( $term_id, '_din_suggestions', $suggestions );
    }

    public function enqueue_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen || self::TAXONOMY !== $screen->taxonomy ) {
            return;
        }

        wp_enqueue_style( 'din-chatbot-admin', plugins_url( 'assets/admin.css', DIN_CHATBOT_FILE ), [], '0.1.0' );
        wp_enqueue_script( 'din-chatbot-admin', plugins_url( 'assets/admin.js', DIN_CHATBOT_FILE ), [], '0.1.0', true );
    }

    /** @param array<string, mixed> $values */
    private function render_add_field_markup( array $values ): void {
        $active      = ! empty( $values['active'] );
        $order       = (int) ( $values['order'] ?? 0 );
        $opening     = (string) ( $values['opening_message'] ?? '' );
        $suggestions = is_array( $values['suggestions'] ?? null ) ? $values['suggestions'] : [];
        ?>
        <div class="form-field">
            <label for="din-topic-active"><?php esc_html_e( 'Active', 'din-chatbot' ); ?></label>
            <label><input id="din-topic-active" name="din_topic[active]" type="checkbox" value="1" <?php checked( $active ); ?> /> <?php esc_html_e( 'Use this topic in the chatbot.', 'din-chatbot' ); ?></label>
        </div>
        <div class="form-field">
            <label for="din-topic-order"><?php esc_html_e( 'Order', 'din-chatbot' ); ?></label>
            <input id="din-topic-order" name="din_topic[order]" type="number" min="0" value="<?php echo esc_attr( (string) $order ); ?>" />
        </div>
        <div class="form-field">
            <label for="din-topic-opening-message"><?php esc_html_e( 'Opening message', 'din-chatbot' ); ?></label>
            <textarea id="din-topic-opening-message" name="din_topic[opening_message]" rows="4"><?php echo esc_textarea( $opening ); ?></textarea>
        </div>
        <div class="form-field din-repeater" data-din-repeater>
            <label><?php esc_html_e( 'Suggestions', 'din-chatbot' ); ?></label>
            <div class="din-repeater__rows" data-din-rows>
                <?php foreach ( $suggestions as $index => $suggestion ) : ?>
                    <?php $this->render_suggestion_row( 'din_topic[suggestions][' . (int) $index . ']', is_array( $suggestion ) ? $suggestion : [] ); ?>
                <?php endforeach; ?>
            </div>
            <template data-din-template><?php $this->render_suggestion_row( 'din_topic[suggestions][__INDEX__]', [] ); ?></template>
            <button type="button" class="button" data-din-add><?php esc_html_e( 'Add suggestion', 'din-chatbot' ); ?></button>
        </div>
        <?php
    }

    /** @param array<string, mixed> $values */
    private function render_edit_field_markup( array $values ): void {
        $active      = ! empty( $values['active'] );
        $order       = (int) ( $values['order'] ?? 0 );
        $opening     = is_string( $values['opening_message'] ?? null ) ? $values['opening_message'] : '';
        $suggestions = is_array( $values['suggestions'] ?? null ) ? $values['suggestions'] : [];
        ?>
        <tr class="form-field">
            <th scope="row"><label for="din-topic-active"><?php esc_html_e( 'Active', 'din-chatbot' ); ?></label></th>
            <td><label><input id="din-topic-active" name="din_topic[active]" type="checkbox" value="1" <?php checked( $active ); ?> /> <?php esc_html_e( 'Use this topic in the chatbot.', 'din-chatbot' ); ?></label></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="din-topic-order"><?php esc_html_e( 'Order', 'din-chatbot' ); ?></label></th>
            <td><input id="din-topic-order" name="din_topic[order]" type="number" min="0" value="<?php echo esc_attr( (string) $order ); ?>" /></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="din-topic-opening-message"><?php esc_html_e( 'Opening message', 'din-chatbot' ); ?></label></th>
            <td><textarea id="din-topic-opening-message" name="din_topic[opening_message]" rows="4"><?php echo esc_textarea( $opening ); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><?php esc_html_e( 'Suggestions', 'din-chatbot' ); ?></th>
            <td class="din-repeater" data-din-repeater>
                <div class="din-repeater__rows" data-din-rows>
                    <?php foreach ( $suggestions as $index => $suggestion ) : ?>
                        <?php $this->render_suggestion_row( 'din_topic[suggestions][' . (int) $index . ']', is_array( $suggestion ) ? $suggestion : [] ); ?>
                    <?php endforeach; ?>
                </div>
                <template data-din-template><?php $this->render_suggestion_row( 'din_topic[suggestions][__INDEX__]', [] ); ?></template>
                <button type="button" class="button" data-din-add><?php esc_html_e( 'Add suggestion', 'din-chatbot' ); ?></button>
            </td>
        </tr>
        <?php
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

    /** @param array<string, mixed> $suggestion */
    private function render_suggestion_row( string $name, array $suggestion ): void {
        $label     = is_string( $suggestion['label'] ?? null ) ? $suggestion['label'] : '';
        $type      = is_string( $suggestion['type'] ?? null ) ? $suggestion['type'] : 'topic';
        $target_id = is_int( $suggestion['target_id'] ?? null ) ? $suggestion['target_id'] : 0;
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
