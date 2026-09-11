<?php

namespace DinStudio\DinChatbot;

final class Matcher {
    private const POST_TYPE = 'din_chatbot_rule';
    private const TAXONOMY  = 'din_chatbot_topic';

    public function normalize( string $text ): string {
        $text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = mb_strtolower( $text, 'UTF-8' );
        $text = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $text ) ?? '';

        return trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
    }

    /**
     * @return array{status:string,rule_ids:int[],score:int}
     */
    public function match( string $text, int $topic_id = 0 ): array {
        $normalized = $this->normalize( $text );
        if ( '' === $normalized ) {
            return $this->none();
        }

        if ( $topic_id > 0 ) {
            $matches = $this->score_rules( $normalized, $this->active_rule_ids( $topic_id ) );
            if ( ! empty( $matches ) ) {
                return $this->select( $matches );
            }
        }

        $matches = $this->score_rules( $normalized, $this->active_rule_ids() );
        return empty( $matches ) ? $this->none() : $this->select( $matches );
    }

    /** @return int[] */
    private function active_rule_ids( int $topic_id = 0 ): array {
        $query = [
            'post_type'              => self::POST_TYPE,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'fields'                 => 'ids',
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => true,
            'meta_query'             => [
                [
                    'key'     => '_din_active',
                    'value'   => '1',
                    'compare' => '=',
                ],
            ],
        ];

        if ( $topic_id > 0 ) {
            $query['tax_query'] = [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => $topic_id,
                ],
            ];
        }

        return array_map( 'intval', get_posts( $query ) );
    }

    /**
     * @param int[] $rule_ids
     * @return array<int, array{score:int,priority:int}>
     */
    private function score_rules( string $text, array $rule_ids ): array {
        $matches = [];

        foreach ( $rule_ids as $rule_id ) {
            $examples = get_post_meta( $rule_id, '_din_examples', true );
            $keywords = get_post_meta( $rule_id, '_din_keywords', true );
            $examples = is_array( $examples ) ? $examples : [];
            $keywords = is_array( $keywords ) ? $keywords : [];

            if ( $this->has_exact_example( $text, $examples ) ) {
                $score = 1000;
            } else {
                $score = $this->keyword_score( $text, $keywords );
            }

            if ( $score > 0 ) {
                $matches[ $rule_id ] = [
                    'score'    => $score,
                    'priority' => (int) get_post_meta( $rule_id, '_din_priority', true ),
                ];
            }
        }

        return $matches;
    }

    /** @param array<mixed> $examples */
    private function has_exact_example( string $text, array $examples ): bool {
        foreach ( $examples as $example ) {
            if ( ! is_string( $example ) ) {
                continue;
            }

            $normalized = $this->normalize( $example );
            if ( '' !== $normalized && $text === $normalized ) {
                return true;
            }
        }

        return false;
    }

    /** @param array<mixed> $keywords */
    private function keyword_score( string $text, array $keywords ): int {
        $score   = 0;
        $phrases = [];

        foreach ( $keywords as $keyword ) {
            if ( ! is_string( $keyword ) ) {
                continue;
            }

            $phrase = $this->normalize( $keyword );
            if ( '' !== $phrase ) {
                $phrases[ $phrase ] = true;
            }
        }

        foreach ( array_keys( $phrases ) as $phrase ) {
            $pattern = '/(?=(?<!\\S)' . preg_quote( $phrase, '/' ) . '(?!\\S))/u';
            $count   = preg_match_all( $pattern, $text );
            if ( false !== $count ) {
                $score += $count * 100;
            }
        }

        return $score;
    }

    /**
     * @param array<int, array{score:int,priority:int}> $matches
     * @return array{status:string,rule_ids:int[],score:int}
     */
    private function select( array $matches ): array {
        $top_score = max( array_column( $matches, 'score' ) );
        $top       = array_filter( $matches, static fn( array $match ): bool => $top_score === $match['score'] );
        $priority  = max( array_column( $top, 'priority' ) );
        $rule_ids  = array_keys( array_filter( $top, static fn( array $match ): bool => $priority === $match['priority'] ) );
        sort( $rule_ids, SORT_NUMERIC );

        return [
            'status'   => count( $rule_ids ) > 1 ? 'tie' : 'matched',
            'rule_ids' => array_map( 'intval', $rule_ids ),
            'score'    => $top_score,
        ];
    }

    /** @return array{status:string,rule_ids:int[],score:int} */
    private function none(): array {
        return [
            'status'   => 'none',
            'rule_ids' => [],
            'score'    => 0,
        ];
    }
}
