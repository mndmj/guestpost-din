<?php

namespace DinStudio\DinChatbot;

final class VariationProvider implements CardProvider {
    public function register(): void {
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
    }

    public function register_rest_route(): void {
        register_rest_route(
            'din-chatbot/v1',
            '/variations',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'rest_search' ],
                'permission_callback' => static fn (): bool => current_user_can( 'manage_din_chatbot' ),
                'args'                => [
                    'term' => [
                        'sanitize_callback' => 'sanitize_text_field',
                        'default'            => '',
                    ],
                    'page' => [
                        'sanitize_callback' => 'absint',
                        'default'            => 1,
                    ],
                ],
            ]
        );
    }

    /** @return \WP_REST_Response */
    public function rest_search( \WP_REST_Request $request ): \WP_REST_Response {
        $term = sanitize_text_field( (string) $request->get_param( 'term' ) );
        $page = max( 1, absint( $request->get_param( 'page' ) ) );

        return rest_ensure_response( $this->search( $term, $page ) );
    }

    /**
     * @return array{items:array<int, array{variation_id:int,product_id:int,label:string,price_html:string,status:string}>,page:int,per_page:int,total:int,total_pages:int}
     */
    public function search( string $term, int $page ): array {
        $page  = max( 1, $page );
        $query = new \WP_Query(
            [
                'post_type'              => 'product_variation',
                'post_status'            => [ 'publish', 'private', 'draft', 'pending', 'future' ],
                'posts_per_page'         => 20,
                'paged'                  => $page,
                's'                      => sanitize_text_field( $term ),
                'fields'                 => 'ids',
                'no_found_rows'          => false,
                'ignore_sticky_posts'    => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        $items = [];
        foreach ( $query->posts as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation instanceof \WC_Product_Variation ) {
                continue;
            }

            $parent = wc_get_product( $variation->get_parent_id() );
            if ( ! $parent instanceof \WC_Product ) {
                continue;
            }

            $items[] = [
                'variation_id' => $variation->get_id(),
                'product_id'   => $parent->get_id(),
                'label'        => $this->label( $variation, $parent ),
                'price_html'   => $variation->get_price_html(),
                'status'       => $variation->get_status(),
            ];
        }

        return [
            'items'       => $items,
            'page'        => $page,
            'per_page'    => 20,
            'total'       => (int) $query->found_posts,
            'total_pages' => max( 1, (int) $query->max_num_pages ),
        ];
    }

    /**
     * @param array<mixed> $variation_ids
     * @return array<int, array{variation_id:int,product_id:int,name:string,attributes:string,price_html:string,url:string}>
     */
    public function cards( array $variation_ids, int $limit = 3 ): array {
        if ( $limit < 1 ) {
            return [];
        }

        $cards = [];
        $seen  = [];
        foreach ( $variation_ids as $requested_id ) {
            $variation_id = is_numeric( $requested_id ) ? absint( $requested_id ) : 0;
            if ( $variation_id < 1 || isset( $seen[ $variation_id ] ) ) {
                continue;
            }
            $seen[ $variation_id ] = true;

            $variation = wc_get_product( $variation_id );
            if ( ! $variation instanceof \WC_Product_Variation || ! $variation->is_visible() || ! $variation->is_purchasable() ) {
                continue;
            }

            $parent = wc_get_product( $variation->get_parent_id() );
            if ( ! $parent instanceof \WC_Product || 'publish' !== $parent->get_status() ) {
                continue;
            }

            $attributes = $this->formatted_attributes( $variation, $parent );
            $url        = add_query_arg( $variation->get_variation_attributes(), get_permalink( $parent->get_id() ) );
            $cards[]    = [
                'variation_id' => $variation->get_id(),
                'product_id'   => $parent->get_id(),
                'name'         => $this->label( $variation, $parent ),
                'attributes'   => $attributes,
                'price_html'   => $variation->get_price_html(),
                'url'          => $url,
            ];

            if ( count( $cards ) >= $limit ) {
                break;
            }
        }

        return $cards;
    }

    private function label( \WC_Product_Variation $variation, \WC_Product $parent ): string {
        $attributes = $this->formatted_attributes( $variation, $parent );
        return '' === $attributes ? $parent->get_name() : $parent->get_name() . ' – ' . $attributes;
    }

    private function formatted_attributes( \WC_Product_Variation $variation, \WC_Product $parent ): string {
        $parts = [];
        foreach ( $variation->get_variation_attributes() as $attribute_key => $attribute_value ) {
            if ( '' === $attribute_value ) {
                continue;
            }

            $attribute_name = str_replace( 'attribute_', '', $attribute_key );
            $label          = wc_attribute_label( $attribute_name, $parent );
            $value          = $attribute_value;
            if ( taxonomy_exists( $attribute_name ) ) {
                $term = get_term_by( 'slug', $attribute_value, $attribute_name );
                if ( $term instanceof \WP_Term ) {
                    $value = $term->name;
                }
            }

            $parts[] = $label . ': ' . $value;
        }

        return implode( ', ', $parts );
    }
}
