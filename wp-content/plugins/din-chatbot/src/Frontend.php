<?php

namespace DinStudio\DinChatbot;

use DinStudio\DinChatbot\Admin\SettingsPage;

final class Frontend {
    private $woocommerce_active;

    public function __construct( ?callable $woocommerce_active = null ) {
        $this->woocommerce_active = $woocommerce_active ?? static fn (): bool => class_exists( 'WooCommerce' );
    }

    public function register(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_footer', [ $this, 'render' ], 5 );
    }

    public function should_render( int $page_id ): bool {
        if ( ! ( $this->woocommerce_active )() ) {
            return false;
        }
        $settings = SettingsPage::settings();
        return 1 === $settings['enabled'] && ! in_array( $page_id, $settings['excluded_page_ids'], true );
    }

    /** @return array<int,array{label:string,type:string,target_id:int}> */
    public function initial_suggestions(): array {
        $terms = get_terms( [ 'taxonomy' => 'din_chatbot_topic', 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return [];
        }
        $active = array_filter( $terms, static fn( mixed $term ): bool => $term instanceof \WP_Term && 1 === (int) get_term_meta( $term->term_id, '_din_active', true ) );
        usort( $active, static function ( \WP_Term $left, \WP_Term $right ): int {
            $order = (int) get_term_meta( $left->term_id, '_din_order', true ) <=> (int) get_term_meta( $right->term_id, '_din_order', true );
            return 0 !== $order ? $order : $left->term_id <=> $right->term_id;
        } );
        return array_map( static fn( \WP_Term $term ): array => [ 'label' => sanitize_text_field( $term->name ), 'type' => 'topic', 'target_id' => $term->term_id ], $active );
    }

    /** @return array{appearance:array{primaryColor:string,primaryForeground:string,title:string},contactUrl:string,initialSuggestions:array<int,array{label:string,type:string,target_id:int}>,restRoot:string,settings:array{notificationSound:bool}} */
    public function localized_config(): array {
        $appearance = SettingsPage::appearance();
        $settings = SettingsPage::settings();
        return [
            'appearance' => [
                'primaryColor'      => $appearance['primary_color'],
                'primaryForeground' => SettingsPage::foreground_for_primary( $appearance['primary_color'] ),
                'title'             => $appearance['modal_title'],
            ],
            'contactUrl'          => $settings['contact_url'],
            'initialSuggestions'  => $this->initial_suggestions(),
            'restRoot'            => esc_url_raw( rest_url( 'din-chatbot/v1' ) ),
            'settings'            => [ 'notificationSound' => 1 === $settings['notification_sound'] ],
        ];
    }

    public function enqueue_assets(): void {
        if ( is_admin() || ! $this->should_render( $this->current_page_id() ) ) {
            return;
        }
        wp_enqueue_style( 'din-chatbot-frontend', plugins_url( 'assets/frontend.css', DIN_CHATBOT_FILE ), [], '1.0.0' );
        wp_enqueue_script( 'din-chatbot-frontend', plugins_url( 'assets/frontend.js', DIN_CHATBOT_FILE ), [], '1.0.0', true );
        wp_add_inline_script( 'din-chatbot-frontend', 'window.DinChatbot = ' . wp_json_encode( $this->localized_config() ) . ';', 'before' );
    }

    public function render(): void {
        if ( is_admin() || ! $this->should_render( $this->current_page_id() ) ) {
            return;
        }
        $appearance = SettingsPage::appearance();
        $settings = SettingsPage::settings();
        $style = sprintf( '--din-chatbot-primary:%1$s;--din-chatbot-primary-foreground:%2$s;--din-chatbot-bottom:%3$dpx;--din-chatbot-right:%4$dpx;', $appearance['primary_color'], SettingsPage::foreground_for_primary( $appearance['primary_color'] ), $appearance['bottom_offset'], $appearance['right_offset'] );
        ?>
        <div class="din-chatbot" style="<?php echo esc_attr( $style ); ?>" data-din-chatbot>
            <button id="din-chatbot-fab" class="din-chatbot__fab" type="button" aria-label="<?php esc_attr_e( 'Open Font Advisor', 'din-chatbot' ); ?>" aria-expanded="false" aria-controls="din-chatbot-dialog"><?php echo self::icon_markup( $appearance['fab_icon'] ); ?></button>
            <section id="din-chatbot-dialog" class="din-chatbot__dialog" role="dialog" aria-modal="true" aria-labelledby="din-chatbot-title" hidden tabindex="-1">
                <div class="din-chatbot__header">
                    <div><h2 id="din-chatbot-title"><?php echo esc_html( $appearance['modal_title'] ); ?></h2><p class="din-chatbot__status" data-din-chatbot-status aria-live="polite"></p></div>
                    <div class="din-chatbot__controls"><button type="button" class="din-chatbot__button" data-din-chatbot-back hidden><?php esc_html_e( 'Back', 'din-chatbot' ); ?></button><button type="button" class="din-chatbot__button" data-din-chatbot-restart><?php esc_html_e( 'Restart', 'din-chatbot' ); ?></button><button type="button" class="din-chatbot__button" data-din-chatbot-close aria-label="<?php esc_attr_e( 'Close Font Advisor', 'din-chatbot' ); ?>"><?php esc_html_e( 'Close', 'din-chatbot' ); ?></button></div>
                </div>
                <div class="din-chatbot__transcript" data-din-chatbot-transcript aria-live="polite" aria-relevant="additions"><div class="din-chatbot__message din-chatbot__message--bot"><?php echo wp_kses_post( $appearance['welcome_message'] ); ?></div></div>
                <div class="din-chatbot__suggestions" data-din-chatbot-suggestions><?php foreach ( $this->initial_suggestions() as $suggestion ) : ?><button class="din-chatbot__suggestion" type="button" data-din-chatbot-action="<?php echo esc_attr( $suggestion['type'] ); ?>" data-din-chatbot-target="<?php echo esc_attr( (string) $suggestion['target_id'] ); ?>"><?php echo esc_html( $suggestion['label'] ); ?></button><?php endforeach; ?></div>
                <form class="din-chatbot__composer" data-din-chatbot-form><label for="din-chatbot-input"><?php esc_html_e( 'Ask about fonts or licenses', 'din-chatbot' ); ?></label><div><input id="din-chatbot-input" data-din-chatbot-input type="text" maxlength="1000" autocomplete="off" /><button class="din-chatbot__send" data-din-chatbot-send type="submit"><?php esc_html_e( 'Send', 'din-chatbot' ); ?></button></div></form>
            </section>
        </div>
        <?php
    }

    private function current_page_id(): int {
        return absint( get_queried_object_id() );
    }

    public static function icon_markup( string $icon ): string {
        return match ( $icon ) {
            'spark' => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="m12 2 1.9 6.1L20 10l-6.1 1.9L12 18l-1.9-6.1L4 10l6.1-1.9L12 2Zm7 12 .9 2.1L22 17l-2.1.9L19 20l-.9-2.1L16 17l2.1-.9L19 14Z"/></svg>',
            'question' => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 17a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Zm1.5-5.2v.7h-3v-1.2c0-2.4 3.2-2.3 3.2-4.2 0-.9-.7-1.5-1.7-1.5-1.1 0-1.8.7-1.9 1.8l-2.9-.4C7.5 6.5 9.4 5 12.1 5c2.8 0 4.7 1.6 4.7 4.1 0 2.8-3.3 3.1-3.3 4.7Z"/></svg>',
            default => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h16v11H8l-4 4V4Zm4 4v2h8V8H8Zm0 4v2h5v-2H8Z"/></svg>',
        };
    }
}
