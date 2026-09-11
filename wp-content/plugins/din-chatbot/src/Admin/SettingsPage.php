<?php

namespace DinStudio\DinChatbot\Admin;

use DinStudio\DinChatbot\Frontend;

final class SettingsPage {
    private const CAPABILITY = 'manage_din_chatbot';
    private const SETTINGS_OPTION = 'din_chatbot_settings';
    private const APPEARANCE_OPTION = 'din_chatbot_appearance';
    private const ICONS = [ 'chat', 'spark', 'question' ];

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_pages' ] );
        add_action( 'admin_init', [ $this, 'register_options' ] );
        add_action( 'admin_notices', [ $this, 'woocommerce_missing_notice' ] );
        add_action( 'admin_notices', [ $this, 'contact_missing_notice' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_preview_assets' ] );
        add_filter( 'option_page_capability_din_chatbot_settings_group', [ $this, 'option_page_capability' ] );
        add_filter( 'option_page_capability_din_chatbot_appearance_group', [ $this, 'option_page_capability' ] );
    }

    /** @return array{enabled:int,contact_url:string,excluded_page_ids:array<int,int>,buyer_inactivity_minutes:int,operator_unavailable_message:string,session_ended_message:string,notification_sound:int} */
    public static function settings_defaults(): array {
        return [
            'enabled'                      => 1,
            'contact_url'                  => '',
            'excluded_page_ids'            => [],
            'buyer_inactivity_minutes'     => 10,
            'operator_unavailable_message' => 'No operator is available right now.',
            'session_ended_message'        => 'This chat session has ended.',
            'notification_sound'           => 0,
        ];
    }

    /** @return array{fab_icon:string,primary_color:string,modal_title:string,welcome_message:string,bottom_offset:int,right_offset:int} */
    public static function appearance_defaults(): array {
        return [
            'fab_icon'        => 'chat',
            'primary_color'   => '#3DB0AA',
            'modal_title'     => 'Font Advisor',
            'welcome_message' => '<p>How can we help you find the right font?</p>',
            'bottom_offset'   => 24,
            'right_offset'    => 24,
        ];
    }

    /** @return array{enabled:int,contact_url:string,excluded_page_ids:array<int,int>,buyer_inactivity_minutes:int,operator_unavailable_message:string,session_ended_message:string,notification_sound:int} */
    public static function settings(): array {
        return self::normalize_settings_option( get_option( self::SETTINGS_OPTION, false ) );
    }

    /** @return array{enabled:int,contact_url:string,excluded_page_ids:array<int,int>,buyer_inactivity_minutes:int,operator_unavailable_message:string,session_ended_message:string,notification_sound:int} */
    public static function normalize_settings_option( mixed $stored ): array {
        return is_array( $stored ) ? self::sanitize_settings( $stored ) : self::settings_defaults();
    }

    /** @return array{fab_icon:string,primary_color:string,modal_title:string,welcome_message:string,bottom_offset:int,right_offset:int} */
    public static function appearance(): array {
        $stored = get_option( self::APPEARANCE_OPTION, [] );
        return self::sanitize_appearance( is_array( $stored ) ? $stored : [] );
    }

    /** @param array<string,mixed> $input @return array{enabled:int,contact_url:string,excluded_page_ids:array<int,int>,buyer_inactivity_minutes:int,operator_unavailable_message:string,session_ended_message:string,notification_sound:int} */
    public static function sanitize_settings( array $input ): array {
        $defaults = self::settings_defaults();
        $ids = $input['excluded_page_ids'] ?? [];
        if ( is_string( $ids ) ) {
            $ids = preg_split( '/\s*,\s*/', trim( $ids ) ) ?: [];
        }
        $safe_ids = [];
        foreach ( is_array( $ids ) ? $ids : [] as $id ) {
            if ( ( is_int( $id ) && $id > 0 ) || ( is_string( $id ) && preg_match( '/^[1-9][0-9]*$/D', $id ) ) ) {
                $safe_ids[] = absint( $id );
            }
        }
        $safe_ids = array_values( array_unique( $safe_ids ) );

        $minutes_raw = $input['buyer_inactivity_minutes'] ?? $defaults['buyer_inactivity_minutes'];
        $minutes = self::bounded_integer( $minutes_raw, 1, 60, $defaults['buyer_inactivity_minutes'] );
        if ( $minutes_raw === 0 || '0' === $minutes_raw || ( is_numeric( $minutes_raw ) && (int) $minutes_raw < 1 ) ) {
            $minutes = $defaults['buyer_inactivity_minutes'];
        }
        $contact = $input['contact_url'] ?? '';
        $operator = $input['operator_unavailable_message'] ?? $defaults['operator_unavailable_message'];
        $ended = $input['session_ended_message'] ?? $defaults['session_ended_message'];

        return [
            'enabled'                      => self::checkbox( $input['enabled'] ?? 0 ),
            'contact_url'                  => self::contact_url( $contact ),
            'excluded_page_ids'            => $safe_ids,
            'buyer_inactivity_minutes'     => $minutes,
            'operator_unavailable_message' => sanitize_text_field( is_string( $operator ) ? $operator : '' ),
            'session_ended_message'        => sanitize_text_field( is_string( $ended ) ? $ended : '' ),
            'notification_sound'           => self::checkbox( $input['notification_sound'] ?? $defaults['notification_sound'] ),
        ];
    }

    /** @param array<string,mixed> $input @return array{fab_icon:string,primary_color:string,modal_title:string,welcome_message:string,bottom_offset:int,right_offset:int} */
    public static function sanitize_appearance( array $input ): array {
        $defaults = self::appearance_defaults();
        $icon = $input['fab_icon'] ?? $defaults['fab_icon'];
        $color = $input['primary_color'] ?? $defaults['primary_color'];
        $title = $input['modal_title'] ?? $defaults['modal_title'];
        $welcome = $input['welcome_message'] ?? $defaults['welcome_message'];

        $color = is_string( $color ) ? sanitize_hex_color( $color ) : null;
        $safe_title = sanitize_text_field( is_string( $title ) ? $title : '' );
        return [
            'fab_icon'        => is_string( $icon ) && in_array( $icon, self::ICONS, true ) ? $icon : $defaults['fab_icon'],
            'primary_color'   => is_string( $color ) ? $color : $defaults['primary_color'],
            'modal_title'     => '' !== $safe_title ? $safe_title : $defaults['modal_title'],
            'welcome_message' => wp_kses_post( is_string( $welcome ) ? $welcome : '' ),
            'bottom_offset'   => self::bounded_integer( $input['bottom_offset'] ?? $defaults['bottom_offset'], 0, 80, $defaults['bottom_offset'] ),
            'right_offset'    => self::bounded_integer( $input['right_offset'] ?? $defaults['right_offset'], 0, 80, $defaults['right_offset'] ),
        ];
    }

    public static function foreground_for_primary( string $color ): string {
        $hex = ltrim( $color, '#' );
        if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
            return '#ffffff';
        }
        $channels = [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
        $linear = array_map( static fn( int $channel ): float => ( $channel / 255 ) <= 0.04045 ? ( $channel / 255 ) / 12.92 : ( ( $channel / 255 + 0.055 ) / 1.055 ) ** 2.4, $channels );
        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
        return $luminance > 0.179 ? '#111827' : '#ffffff';
    }

    public function add_pages(): void {
        add_submenu_page( 'edit.php?post_type=din_chatbot_rule', __( 'Appearance', 'din-chatbot' ), __( 'Appearance', 'din-chatbot' ), self::CAPABILITY, 'din-chatbot-appearance', [ $this, 'render_appearance' ] );
        add_submenu_page( 'edit.php?post_type=din_chatbot_rule', __( 'Settings', 'din-chatbot' ), __( 'Settings', 'din-chatbot' ), self::CAPABILITY, 'din-chatbot-settings', [ $this, 'render_settings' ] );
    }

    public function register_options(): void {
        register_setting( 'din_chatbot_settings_group', self::SETTINGS_OPTION, [ 'sanitize_callback' => [ self::class, 'sanitize_settings' ] ] );
        register_setting( 'din_chatbot_appearance_group', self::APPEARANCE_OPTION, [ 'sanitize_callback' => [ self::class, 'sanitize_appearance' ] ] );
    }

    public function woocommerce_missing_notice(): void {
        if ( class_exists( 'WooCommerce' ) || ! current_user_can( self::CAPABILITY ) ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Din Chatbot frontend is disabled because WooCommerce is inactive.', 'din-chatbot' ) . '</p></div>';
    }

    public function contact_missing_notice(): void {
        if ( ! current_user_can( self::CAPABILITY ) || '' !== self::settings()['contact_url'] ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Din Chatbot Contact Us URL is not configured.', 'din-chatbot' ) . '</p></div>';
    }

    public function option_page_capability(): string {
        return self::CAPABILITY;
    }

    public function enqueue_preview_assets( string $hook_suffix ): void {
        if ( 'din_chatbot_rule_page_din-chatbot-appearance' !== $hook_suffix ) {
            return;
        }
        wp_enqueue_style( 'din-chatbot-admin-preview', plugins_url( 'assets/admin-preview.css', DIN_CHATBOT_FILE ), [], '0.1.0' );
    }

    public function render_settings(): void {
        $this->require_capability();
        $settings = self::settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Chatbot Settings', 'din-chatbot' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'din_chatbot_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e( 'Enable chatbot', 'din-chatbot' ); ?></th><td><input name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[enabled]" type="hidden" value="0" /><label><input name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[enabled]" type="checkbox" value="1" <?php checked( $settings['enabled'], 1 ); ?> /> <?php esc_html_e( 'Show the Compact Advisor on public pages.', 'din-chatbot' ); ?></label></td></tr>
                        
                    <tr><th scope="row"><label for="din-chatbot-excluded-pages"><?php esc_html_e( 'Excluded page IDs', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-excluded-pages" class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[excluded_page_ids]" type="text" value="<?php echo esc_attr( implode( ', ', $settings['excluded_page_ids'] ) ); ?>" /><p class="description"><?php esc_html_e( 'Comma-separated public page IDs.', 'din-chatbot' ); ?></p></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-inactivity"><?php esc_html_e( 'Buyer inactivity (minutes)', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-inactivity" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[buyer_inactivity_minutes]" type="number" min="1" max="60" value="<?php echo esc_attr( (string) $settings['buyer_inactivity_minutes'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-unavailable"><?php esc_html_e( 'Operator unavailable message', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-unavailable" class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[operator_unavailable_message]" type="text" value="<?php echo esc_attr( $settings['operator_unavailable_message'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-ended"><?php esc_html_e( 'Session ended message', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-ended" class="regular-text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[session_ended_message]" type="text" value="<?php echo esc_attr( $settings['session_ended_message'] ); ?>" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'Notification sound', 'din-chatbot' ); ?></th><td><label><input name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[notification_sound]" type="checkbox" value="1" <?php checked( $settings['notification_sound'], 1 ); ?> /> <?php esc_html_e( 'Enable a browser notification sound after user interaction.', 'din-chatbot' ); ?></label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_appearance(): void {
        $this->require_capability();
        $appearance = self::appearance();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Chatbot Appearance', 'din-chatbot' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'din_chatbot_appearance_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="din-chatbot-icon"><?php esc_html_e( 'FAB icon', 'din-chatbot' ); ?></label></th><td><select id="din-chatbot-icon" name="<?php echo esc_attr( self::APPEARANCE_OPTION ); ?>[fab_icon]"><?php foreach ( self::ICONS as $icon ) : ?><option value="<?php echo esc_attr( $icon ); ?>" <?php selected( $appearance['fab_icon'], $icon ); ?>><?php echo esc_html( ucfirst( $icon ) ); ?></option><?php endforeach; ?></select></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-primary"><?php esc_html_e( 'Primary color', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-primary" name="<?php echo esc_attr( self::APPEARANCE_OPTION ); ?>[primary_color]" type="color" value="<?php echo esc_attr( $appearance['primary_color'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-title"><?php esc_html_e( 'Modal title', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-title" class="regular-text" name="<?php echo esc_attr( self::APPEARANCE_OPTION ); ?>[modal_title]" type="text" value="<?php echo esc_attr( $appearance['modal_title'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-welcome"><?php esc_html_e( 'Welcome message', 'din-chatbot' ); ?></label></th><td><textarea id="din-chatbot-welcome" class="large-text" rows="4" name="<?php echo esc_attr( self::APPEARANCE_OPTION ); ?>[welcome_message]"><?php echo esc_textarea( $appearance['welcome_message'] ); ?></textarea></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-bottom"><?php esc_html_e( 'Bottom offset (px)', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-bottom" name="<?php echo esc_attr( self::APPEARANCE_OPTION ); ?>[bottom_offset]" type="number" min="0" max="80" value="<?php echo esc_attr( (string) $appearance['bottom_offset'] ); ?>" /></td></tr>
                    <tr><th scope="row"><label for="din-chatbot-right"><?php esc_html_e( 'Right offset (px)', 'din-chatbot' ); ?></label></th><td><input id="din-chatbot-right" name="<?php echo esc_attr( self::APPEARANCE_OPTION ); ?>[right_offset]" type="number" min="0" max="80" value="<?php echo esc_attr( (string) $appearance['right_offset'] ); ?>" /></td></tr>
                </table>
                <h2><?php esc_html_e( 'Preview', 'din-chatbot' ); ?></h2>
                <?php $preview_style = sprintf( '--din-chatbot-primary:%1$s;--din-chatbot-primary-foreground:%2$s;--din-chatbot-bottom:%3$dpx;--din-chatbot-right:%4$dpx;', $appearance['primary_color'], self::foreground_for_primary( $appearance['primary_color'] ), $appearance['bottom_offset'], $appearance['right_offset'] ); ?>
                <div class="din-chatbot-preview-wrap" aria-label="<?php esc_attr_e( 'Appearance previews', 'din-chatbot' ); ?>">
                    <?php foreach ( [ 'desktop', 'mobile' ] as $device ) : ?>
                        <div class="din-chatbot-preview din-chatbot-preview--<?php echo esc_attr( $device ); ?>" style="<?php echo esc_attr( $preview_style ); ?>">
                            <button class="din-chatbot-preview__fab" type="button" tabindex="-1" aria-hidden="true" data-din-chatbot-preview-icon="<?php echo esc_attr( $appearance['fab_icon'] ); ?>"><?php echo Frontend::icon_markup( $appearance['fab_icon'] ); ?></button>
                            <section class="din-chatbot-preview__dialog"><header><strong><?php echo esc_html( $appearance['modal_title'] ); ?></strong></header><div class="din-chatbot-preview__transcript"><?php echo wp_kses_post( $appearance['welcome_message'] ); ?></div><div class="din-chatbot-preview__suggestions"><button type="button" tabindex="-1"><?php esc_html_e( 'Browse font styles', 'din-chatbot' ); ?></button></div><div class="din-chatbot-preview__composer"><label><?php esc_html_e( 'Ask about fonts or licenses', 'din-chatbot' ); ?></label><input type="text" disabled /></div></section>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function checkbox( mixed $value ): int {
        return ( 1 === $value || '1' === $value || true === $value ) ? 1 : 0;
    }

    private static function contact_url( mixed $value ): string {
        $url = is_string( $value ) ? esc_url_raw( $value ) : '';
        $scheme = is_string( $url ) ? strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) : '';
        return in_array( $scheme, [ 'http', 'https', 'mailto' ], true ) ? $url : '';
    }

    private static function bounded_integer( mixed $value, int $minimum, int $maximum, int $fallback ): int {
        if ( ! is_int( $value ) && ( ! is_string( $value ) || ! preg_match( '/^-?[0-9]+$/D', $value ) ) ) {
            return $fallback;
        }
        return min( $maximum, max( $minimum, (int) $value ) );
    }

    private function require_capability(): void {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_die( esc_html__( 'Forbidden.', 'din-chatbot' ), '', [ 'response' => 403 ] );
        }
    }
}
