<?php

namespace DinStudio\DinChatbot;

final class Content {
    public function register(): void {
        register_post_type(
            'din_chatbot_rule',
            [
                'labels' => [
                    'name'          => __( 'Chatbot', 'din-chatbot' ),
                    'singular_name' => __( 'Rule', 'din-chatbot' ),
                    'menu_name'     => __( 'Chatbot', 'din-chatbot' ),
                    'add_new'       => __( 'Add Rule', 'din-chatbot' ),
                    'add_new_item'  => __( 'Add New Rule', 'din-chatbot' ),
                ],
                'public'       => false,
                'show_ui'      => true,
                'show_in_menu' => true,
                'supports'     => [ 'title', 'editor' ],
                'capabilities' => [
                    'edit_post'          => 'manage_din_chatbot',
                    'read_post'          => 'manage_din_chatbot',
                    'delete_post'        => 'manage_din_chatbot',
                    'edit_posts'         => 'manage_din_chatbot',
                    'edit_others_posts'  => 'manage_din_chatbot',
                    'publish_posts'      => 'manage_din_chatbot',
                    'read_private_posts' => 'manage_din_chatbot',
                    'delete_posts'       => 'manage_din_chatbot',
                    'delete_private_posts' => 'manage_din_chatbot',
                    'delete_published_posts' => 'manage_din_chatbot',
                    'delete_others_posts' => 'manage_din_chatbot',
                    'edit_private_posts' => 'manage_din_chatbot',
                    'edit_published_posts' => 'manage_din_chatbot',
                    'create_posts'       => 'manage_din_chatbot',
                ],
                'map_meta_cap' => false,
            ]
        );

        register_taxonomy(
            'din_chatbot_topic',
            [ 'din_chatbot_rule' ],
            [
                'labels' => [
                    'name'          => __( 'Topics', 'din-chatbot' ),
                    'singular_name' => __( 'Topic', 'din-chatbot' ),
                ],
                'public'       => false,
                'show_ui'      => true,
                'show_in_menu' => true,
                'capabilities' => [
                    'manage_terms' => 'manage_din_chatbot',
                    'edit_terms'   => 'manage_din_chatbot',
                    'delete_terms' => 'manage_din_chatbot',
                    'assign_terms' => 'manage_din_chatbot',
                ],
            ]
        );
    }
}
