<?php

namespace DinStudio\DinChatbot;

final class AutomatedChatService {
    public function __construct(
        private SessionRepository $sessions,
        private ?Matcher $matcher = null,
        private ?UnansweredRepository $unanswered = null,
        private ?CardProvider $variations = null
    ) {
        $this->matcher     ??= new Matcher();
        $this->unanswered  ??= new UnansweredRepository();
        $this->variations  ??= new VariationProvider();
    }

    /** @return array{status:string,answer:string,suggestions:array<int,array{label:string,type:string,target_id:int}>,cards:array<int,array<string,mixed>>} */
    public function message( int $session_id, string $text ): array {
        $session = $this->sessions->find_by_id( $session_id );
        if ( null === $session ) {
            throw new \InvalidArgumentException( 'Unknown chatbot session.' );
        }
        if ( 'automated' !== $session['state']['status'] ) {
            return $this->handoff();
        }

        $match = $this->matcher->match( $text, $session['state']['topic_id'] );
        if ( 'none' === $match['status'] ) {
            $next = $this->sessions->transition_free_text( $session_id, 'none' );
            if ( null === $next ) {
                throw new \RuntimeException( 'Could not update chatbot session.' );
            }
            $this->unanswered->record( $text, $session['state']['topic_id'] );
            return 'handoff_eligible' === $next['state']['status'] ? $this->handoff() : $this->response( 'no_match' );
        }

        if ( 'tie' === $match['status'] ) {
            $next = $this->sessions->clarify( $session_id );
            if ( null === $next ) {
                throw new \RuntimeException( 'Could not update chatbot session.' );
            }
            if ( 'automated' !== $next['state']['status'] ) {
                return $this->handoff();
            }
            return $this->response( 'clarification', '', $this->clarification_suggestions( $match['rule_ids'] ) );
        }

        $rule_id = (int) $match['rule_ids'][0];
        $next    = $this->sessions->transition_free_text( $session_id, 'matched', $rule_id );
        if ( null === $next ) {
            throw new \RuntimeException( 'Could not update chatbot session.' );
        }
        if ( 'handoff_eligible' === $next['state']['status'] ) {
            $this->unanswered->record( $text, $session['state']['topic_id'] );
            return $this->handoff();
        }

        return $this->rule_response( $rule_id );
    }

    /** @return array{status:string,answer:string,suggestions:array<int,array{label:string,type:string,target_id:int}>,cards:array<int,array<string,mixed>>} */
    public function action( int $session_id, string $type, int $target_id = 0 ): array {
        if ( 'restart' === $type ) {
            return $this->restart( $session_id );
        }
        if ( 'contact' === $type ) {
            if ( null === $this->sessions->authorize_automated_action( $session_id ) ) {
                throw new \InvalidArgumentException( 'Unknown chatbot session.' );
            }
            return $this->response( 'contact' );
        }
        if ( 'topic' === $type ) {
            $topic = $this->active_topic( $target_id );
            if ( null === $topic ) {
                throw new \InvalidArgumentException( 'Unknown chatbot Topic.' );
            }
            $state = $this->sessions->authorize_automated_action( $session_id, $target_id );
            if ( null === $state ) {
                throw new \InvalidArgumentException( 'Unknown chatbot session.' );
            }
            if ( 'automated' !== $state['state']['status'] ) {
                return $this->handoff();
            }
            return $this->response(
                'topic',
                wp_kses_post( (string) get_term_meta( $target_id, '_din_opening_message', true ) ),
                $this->safe_suggestions( get_term_meta( $target_id, '_din_suggestions', true ) )
            );
        }
        if ( 'rule' === $type && null !== $this->active_rule( $target_id ) ) {
            $topics = wp_get_object_terms( $target_id, 'din_chatbot_topic', [ 'fields' => 'ids' ] );
            $state = $this->sessions->authorize_automated_action( $session_id, is_wp_error( $topics ) || empty( $topics ) ? 0 : (int) $topics[0] );
            if ( null === $state ) {
                throw new \InvalidArgumentException( 'Unknown chatbot session.' );
            }
            if ( 'automated' !== $state['state']['status'] ) {
                return $this->handoff();
            }
            return $this->rule_response( $target_id );
        }

        throw new \InvalidArgumentException( 'Unsupported chatbot action.' );
    }

    /** @return array{status:string,answer:string,suggestions:array<int,array{label:string,type:string,target_id:int}>,cards:array<int,array<string,mixed>>} */
    public function restart( int $session_id ): array {
        if ( null === $this->sessions->restart( $session_id ) ) {
            throw new \InvalidArgumentException( 'Unknown chatbot session.' );
        }
        return $this->response( 'automated' );
    }

    /** @return array{status:string,answer:string,suggestions:array<int,array{label:string,type:string,target_id:int}>,cards:array<int,array<string,mixed>>} */
    private function rule_response( int $rule_id ): array {
        if ( null === $this->active_rule( $rule_id ) ) {
            throw new \InvalidArgumentException( 'Unknown chatbot Rule.' );
        }
        $answer     = wp_kses_post( (string) get_post_meta( $rule_id, '_din_answer', true ) );
        $suggestions = $this->safe_suggestions( get_post_meta( $rule_id, '_din_suggestions', true ) );
        $variation_ids = get_post_meta( $rule_id, '_din_variation_ids', true );
        return $this->response( 'matched', $answer, $suggestions, $this->variations->cards( is_array( $variation_ids ) ? $variation_ids : [], 3 ) );
    }

    /** @param int[] $rule_ids @return array<int,array{label:string,type:string,target_id:int}> */
    private function clarification_suggestions( array $rule_ids ): array {
        $suggestions = [];
        foreach ( $rule_ids as $rule_id ) {
            if ( null !== $this->active_rule( $rule_id ) ) {
                $suggestions[] = [ 'label' => sanitize_text_field( get_the_title( $rule_id ) ), 'type' => 'rule', 'target_id' => (int) $rule_id ];
            }
        }
        return $suggestions;
    }

    /** @param mixed $stored @return array<int,array{label:string,type:string,target_id:int}> */
    private function safe_suggestions( mixed $stored ): array {
        if ( ! is_array( $stored ) ) {
            return [];
        }
        $safe = [];
        foreach ( $stored as $suggestion ) {
            if ( ! is_array( $suggestion ) || ! is_string( $suggestion['label'] ?? null ) || ! is_string( $suggestion['type'] ?? null ) ) {
                continue;
            }
            $type = $suggestion['type'];
            $label = sanitize_text_field( $suggestion['label'] );
            $target_id = isset( $suggestion['target_id'] ) ? (int) $suggestion['target_id'] : 0;
            if ( '' === $label || ! in_array( $type, [ 'topic', 'rule', 'restart', 'contact' ], true ) ) {
                continue;
            }
            if ( 'topic' === $type && null === $this->active_topic( $target_id ) ) {
                continue;
            }
            if ( 'rule' === $type && null === $this->active_rule( $target_id ) ) {
                continue;
            }
            if ( in_array( $type, [ 'restart', 'contact' ], true ) ) {
                $target_id = 0;
            }
            $safe[] = [ 'label' => $label, 'type' => $type, 'target_id' => $target_id ];
        }
        return $safe;
    }

    private function active_topic( int $topic_id ): ?\WP_Term {
        $topic = $topic_id > 0 ? get_term( $topic_id, 'din_chatbot_topic' ) : null;
        return $topic instanceof \WP_Term && 1 === (int) get_term_meta( $topic_id, '_din_active', true ) ? $topic : null;
    }

    private function active_rule( int $rule_id ): ?\WP_Post {
        $rule = $rule_id > 0 ? get_post( $rule_id ) : null;
        return $rule instanceof \WP_Post && 'din_chatbot_rule' === $rule->post_type && 'publish' === $rule->post_status && 1 === (int) get_post_meta( $rule_id, '_din_active', true ) ? $rule : null;
    }

    /** @param array<int,array{label:string,type:string,target_id:int}> $suggestions @param array<int,array<string,mixed>> $cards @return array{status:string,answer:string,suggestions:array<int,array{label:string,type:string,target_id:int}>,cards:array<int,array<string,mixed>>} */
    private function response( string $status, string $answer = '', array $suggestions = [], array $cards = [] ): array {
        return [ 'status' => $status, 'answer' => $answer, 'suggestions' => $suggestions, 'cards' => $this->safe_cards( $cards ) ];
    }

    /** @param array<int,array<string,mixed>> $cards @return array<int,array{variation_id:int,product_id:int,name:string,attributes:string,price_html:string,url:string}> */
    private function safe_cards( array $cards ): array {
        $safe = [];
        foreach ( $cards as $card ) {
            if ( ! is_array( $card ) || ! isset( $card['variation_id'], $card['product_id'], $card['name'], $card['attributes'], $card['price_html'], $card['url'] ) ) {
                continue;
            }
            $variation_id = absint( $card['variation_id'] );
            $product_id = absint( $card['product_id'] );
            if ( $variation_id < 1 || $product_id < 1 ) {
                continue;
            }
            $safe[] = [
                'variation_id' => $variation_id,
                'product_id'   => $product_id,
                'name'         => sanitize_text_field( (string) $card['name'] ),
                'attributes'   => sanitize_text_field( (string) $card['attributes'] ),
                'price_html'   => wp_kses_post( (string) $card['price_html'] ),
                'url'          => esc_url_raw( (string) $card['url'] ),
            ];
            if ( count( $safe ) >= 3 ) {
                break;
            }
        }
        return $safe;
    }

    /** @return array{status:string,answer:string,suggestions:array<int,array{label:string,type:string,target_id:int}>,cards:array<int,array<string,mixed>>} */
    private function handoff(): array {
        return $this->response( 'handoff_eligible', '', [
            [ 'label' => 'Restart chat', 'type' => 'restart', 'target_id' => 0 ],
            [ 'label' => 'Contact Us', 'type' => 'contact', 'target_id' => 0 ],
        ] );
    }
}
