<?php

namespace DinStudio\DinChatbot;

final class Plugin {
    public static function boot(): void {
        add_action( 'plugins_loaded', [ self::class, 'on_plugins_loaded' ] );
    }

    public static function on_plugins_loaded(): void {
        $settings_page = new Admin\SettingsPage();
        $settings_page->register();

        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        $content = new Content();
        add_action( 'init', [ $content, 'register' ] );

        $topic_editor = new Admin\TopicEditor();
        $topic_editor->register();

        $rule_editor = new Admin\RuleEditor();
        $rule_editor->register();

        $variation_provider = new VariationProvider();
        $variation_provider->register();

        $unanswered_repository = new UnansweredRepository();
        $unanswered_page       = new Admin\UnansweredPage( $unanswered_repository );
        $unanswered_page->register();
        add_action( 'din_chatbot_cleanup', [ $unanswered_repository, 'delete_expired' ] );

        $sessions = new SessionRepository();
        $chat     = new AutomatedChatService( $sessions, new Matcher(), $unanswered_repository, $variation_provider );
        ( new RestController( $sessions, $chat ) )->register();

        ( new Frontend() )->register();

        if ( (int) get_option( 'din_chatbot_schema_version' ) < 1 ) {
            Installer::install();
        }
    }
}
