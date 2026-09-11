<?php

namespace DinStudio\DinChatbot;

final class UnansweredRepository {
    public function record( string $question, int $topic_id ): int {
        global $wpdb;

        if ( $topic_id < 0 || ( 0 !== $topic_id && ! $this->is_valid_topic( $topic_id ) ) ) {
            return 0;
        }

        $normalized = $this->normalize( $this->redact( $question ) );
        if ( $this->is_effectively_empty( $normalized ) ) {
            return 0;
        }

        $hash       = hash( 'sha256', $topic_id . "\n" . $normalized );
        $now        = gmdate( 'Y-m-d H:i:s' );
        $expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 90 * DAY_IN_SECONDS ) );
        $table      = $wpdb->prefix . 'din_chatbot_unanswered';

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (normalized_hash, topic_id, question_text, occurrence_count, first_seen_at, last_seen_at, expires_at)
            VALUES (%s, %d, %s, 1, %s, %s, %s)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), occurrence_count = occurrence_count + 1, last_seen_at = VALUES(last_seen_at), expires_at = VALUES(expires_at)",
            $hash,
            $topic_id,
            $normalized,
            $now,
            $now,
            $expires_at
        );

        $result = $wpdb->query( $sql );
        if ( false === $result || $wpdb->insert_id < 1 ) {
            throw new \RuntimeException( 'Could not record unanswered chatbot question.' );
        }

        return (int) $wpdb->insert_id;
    }

    public function find( int $id ): ?object {
        global $wpdb;

        if ( $id < 1 ) {
            return null;
        }

        $table = $wpdb->prefix . 'din_chatbot_unanswered';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

        return is_object( $row ) ? $row : null;
    }

    public function delete_expired(): int {
        global $wpdb;

        $table  = $wpdb->prefix . 'din_chatbot_unanswered';
        $result = $wpdb->query( "DELETE FROM {$table} WHERE expires_at < UTC_TIMESTAMP()" );

        if ( false === $result ) {
            throw new \RuntimeException( 'Could not delete expired unanswered chatbot questions.' );
        }

        return (int) $result;
    }

    public function delete( int $id ): bool {
        global $wpdb;

        return $id > 0 && 1 === $wpdb->delete( $wpdb->prefix . 'din_chatbot_unanswered', [ 'id' => $id ], [ '%d' ] );
    }

    public function delete_all(): int {
        global $wpdb;

        $table  = $wpdb->prefix . 'din_chatbot_unanswered';
        $result = $wpdb->query( "DELETE FROM {$table}" );

        if ( false === $result ) {
            throw new \RuntimeException( 'Could not delete unanswered chatbot questions.' );
        }

        return (int) $result;
    }

    public function normalize( string $text ): string {
        $text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
        $text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text ) ?? '';

        return trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
    }

    private function redact( string $question ): string {
        $question = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', ' ', $question ) ?? '';

        return preg_replace( '/(?<![\p{L}\p{N}_])(?:\+|00)(?:[\s().-]*\d){8,15}(?![\p{L}\p{N}_])/u', ' ', $question ) ?? '';
    }

    private function is_effectively_empty( string $text ): bool {
        return '' === $text || 1 === preg_match( '/^(?:redacted(?: (?:email|phone))?)(?:\s+redacted(?: (?:email|phone)?))*$/', $text );
    }

    private function is_valid_topic( int $topic_id ): bool {
        return get_term( $topic_id, 'din_chatbot_topic' ) instanceof \WP_Term;
    }
}
