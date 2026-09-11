<?php

namespace DinStudio\DinChatbot;

final class SessionRepository {
    /** @return array{id:int,token:string} */
    public function create(): array {
        global $wpdb;

        $table = $wpdb->prefix . 'din_chatbot_sessions';
        for ( $attempt = 0; $attempt < 3; $attempt++ ) {
            $token = bin2hex( random_bytes( 32 ) );
            $hash  = hash( 'sha256', $token );
            $now   = gmdate( 'Y-m-d H:i:s' );
            $saved = $wpdb->insert(
                $table,
                [
                    'public_token_hash' => $hash,
                    'status'            => 'automated',
                    'topic_id'          => 0,
                    'last_rule_id'      => 0,
                    'repeat_count'      => 0,
                    'no_match_count'    => 0,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ],
                [ '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ]
            );

            if ( 1 === $saved ) {
                return [ 'id' => (int) $wpdb->insert_id, 'token' => $token ];
            }
        }

        throw new \RuntimeException( 'Could not create chatbot session.' );
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|null */
    public function find_by_token( string $token ): ?array {
        if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
            return null;
        }

        $hash = hash( 'sha256', $token );
        $row  = $this->row_by_hash( $hash );
        if ( ! is_object( $row ) || ! hash_equals( (string) $row->public_token_hash, $hash ) ) {
            return null;
        }

        return $this->public_row( $row );
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|null */
    public function find_by_id( int $session_id ): ?array {
        global $wpdb;

        if ( $session_id < 1 ) {
            return null;
        }

        $table = $wpdb->prefix . 'din_chatbot_sessions';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, topic_id, last_rule_id, repeat_count, no_match_count FROM {$table} WHERE id = %d", $session_id ) );
        return is_object( $row ) ? $this->public_row( $row ) : null;
    }

    /**
     * Atomically advances only the free-text matching state.
     *
     * @param 'matched'|'none' $outcome
     * @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|null
     */
    public function transition_free_text( int $session_id, string $outcome, int $rule_id = 0 ): ?array {
        if ( $session_id < 1 || ! in_array( $outcome, [ 'matched', 'none' ], true ) || ( 'matched' === $outcome && $rule_id < 1 ) ) {
            return null;
        }
        return $this->locked_update(
            $session_id,
            static function ( object $row ) use ( $outcome, $rule_id ): array {
                if ( 'automated' !== (string) $row->status ) {
                    return [];
                }
                if ( 'none' === $outcome ) {
                    $no_match_count = (int) $row->no_match_count + 1;
                    return [
                        'status'         => $no_match_count >= 2 ? 'handoff_eligible' : 'automated',
                        'last_rule_id'   => 0,
                        'repeat_count'   => 0,
                        'no_match_count' => $no_match_count,
                    ];
                }
                $repeat_count = (int) $row->last_rule_id === $rule_id ? (int) $row->repeat_count + 1 : 1;
                return [
                    'status'         => $repeat_count >= 2 ? 'handoff_eligible' : 'automated',
                    'last_rule_id'   => $rule_id,
                    'repeat_count'   => $repeat_count,
                    'no_match_count' => 0,
                ];
            }
        );
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|null */
    public function clarify( int $session_id ): ?array {
        return $this->locked_update(
            $session_id,
            static function ( object $row ): array {
                return 'automated' === (string) $row->status ? [ 'last_rule_id' => 0, 'repeat_count' => 0, 'no_match_count' => 0 ] : [];
            }
        );
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|null */
    public function authorize_automated_action( int $session_id, int $topic_id = 0 ): ?array {
        return $this->locked_update(
            $session_id,
            static function ( object $row ) use ( $topic_id ): array {
                if ( 'automated' !== (string) $row->status ) {
                    return [];
                }
                return $topic_id > 0 ? [ 'topic_id' => $topic_id ] : [];
            }
        );
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}}|null */
    public function restart( int $session_id ): ?array {
        return $this->locked_update(
            $session_id,
            static fn ( object $row ): array => [
                'status'         => 'automated',
                'topic_id'       => 0,
                'last_rule_id'   => 0,
                'repeat_count'   => 0,
                'no_match_count' => 0,
            ]
        );
    }

    /** @return object|null */
    private function row_by_hash( string $hash ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'din_chatbot_sessions';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, public_token_hash, status, topic_id, last_rule_id, repeat_count, no_match_count FROM {$table} WHERE public_token_hash = %s", $hash ) );
        return is_object( $row ) ? $row : null;
    }

    /** @return array{id:int,state:array{status:string,topic_id:int,last_rule_id:int,repeat_count:int,no_match_count:int}} */
    private function public_row( object $row ): array {
        return [
            'id'    => (int) $row->id,
            'state' => [
                'status'         => (string) $row->status,
                'topic_id'       => (int) $row->topic_id,
                'last_rule_id'   => (int) $row->last_rule_id,
                'repeat_count'   => (int) $row->repeat_count,
                'no_match_count' => (int) $row->no_match_count,
            ],
        ];
    }

    /** @param callable(object):array<string, int|string> $changes */
    private function locked_update( int $session_id, callable $changes ): ?array {
        global $wpdb;
        if ( $session_id < 1 ) {
            return null;
        }

        $table = $wpdb->prefix . 'din_chatbot_sessions';
        if ( ! $this->is_innodb( $table ) ) {
            throw new \RuntimeException( 'Chatbot sessions require InnoDB.' );
        }
        if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
            throw new \RuntimeException( 'Could not start chatbot session transaction.' );
        }
        $started = true;
        try {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, topic_id, last_rule_id, repeat_count, no_match_count FROM {$table} WHERE id = %d FOR UPDATE", $session_id ) );
            if ( ! is_object( $row ) ) {
                $this->rollback_checked();
                $started = false;
                return null;
            }
            $next = $changes( $row );
            if ( ! empty( $next ) ) {
                $next['updated_at'] = gmdate( 'Y-m-d H:i:s' );
                if ( false === $wpdb->update( $table, $next, [ 'id' => $session_id ] ) ) {
                    throw new \RuntimeException( 'Could not update chatbot session state.' );
                }
                foreach ( $next as $column => $value ) {
                    $row->{$column} = $value;
                }
            }
            if ( false === $wpdb->query( 'COMMIT' ) ) {
                throw new \RuntimeException( 'Could not commit chatbot session transaction.' );
            }
            $started = false;
            return $this->public_row( $row );
        } catch ( \Throwable $error ) {
            if ( $started ) {
                try {
                    $this->rollback_checked();
                } catch ( \Throwable $rollback_error ) {
                    throw new \RuntimeException( 'Could not roll back chatbot session transaction.', 0, $rollback_error );
                }
            }
            throw $error;
        }
    }

    private function is_innodb( string $table ): bool {
        global $wpdb;
        $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
        return is_string( $engine ) && 'INNODB' === strtoupper( $engine );
    }

    private function rollback_checked(): void {
        global $wpdb;
        if ( false === $wpdb->query( 'ROLLBACK' ) ) {
            throw new \RuntimeException( 'Could not roll back chatbot session transaction.' );
        }
    }
}
