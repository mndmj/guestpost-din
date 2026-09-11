<?php

namespace DinStudio\DinChatbot;

final class Installer {
    public static function activate(): void {
        self::add_capabilities();
        self::install();

        if ( ! wp_next_scheduled( 'din_chatbot_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'din_chatbot_cleanup' );
        }
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate  = $wpdb->get_charset_collate();
        $sessions_table   = $wpdb->prefix . 'din_chatbot_sessions';
        $unanswered_table = $wpdb->prefix . 'din_chatbot_unanswered';

        $sessions_sql = "CREATE TABLE {$sessions_table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            public_token_hash char(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'automated',
            topic_id bigint unsigned NOT NULL DEFAULT 0,
            last_rule_id bigint unsigned NOT NULL DEFAULT 0,
            repeat_count smallint unsigned NOT NULL DEFAULT 0,
            no_match_count smallint unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_token_hash (public_token_hash),
            KEY status_updated (status, updated_at)
        ) ENGINE=InnoDB {$charset_collate};";

        $unanswered_sql = "CREATE TABLE {$unanswered_table} (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            normalized_hash char(64) NOT NULL,
            topic_id bigint unsigned NOT NULL DEFAULT 0,
            question_text text NOT NULL,
            occurrence_count bigint unsigned NOT NULL DEFAULT 1,
            first_seen_at datetime NOT NULL,
            last_seen_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY normalized_topic (normalized_hash, topic_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        dbDelta( $sessions_sql );
        dbDelta( $unanswered_sql );

        $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $sessions_table ) );
        if ( ! is_string( $engine ) || 'INNODB' !== strtoupper( $engine ) ) {
            throw new \RuntimeException( 'Din Chatbot sessions table requires InnoDB.' );
        }

        update_option( 'din_chatbot_schema_version', 1 );
    }

    private static function add_capabilities(): void {
        foreach ( [ 'administrator', 'shop_manager' ] as $role_name ) {
            $role = get_role( $role_name );

            if ( null === $role ) {
                continue;
            }

            $role->add_cap( 'manage_din_chatbot' );
            $role->add_cap( 'operate_din_chatbot' );
        }
    }
}
