<?php
/**
 * FHB_Post_Types – Registers fhb_topic and fhb_reply custom post types.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Post_Types {

    public static function register() {
        self::register_topic();
        self::register_reply();
    }

    private static function register_topic() {
        register_post_type( 'fhb_topic', array(
            'labels' => array(
                'name'               => 'Topics',
                'singular_name'      => 'Topic',
                'add_new'            => 'Add New Topic',
                'add_new_item'       => 'Add New Topic',
                'edit_item'          => 'Edit Topic',
                'view_item'          => 'View Topic',
                'search_items'       => 'Search Topics',
                'not_found'          => 'No topics found',
                'not_found_in_trash' => 'No topics found in Trash',
            ),
            'public'       => false,
            'show_ui'      => false,
            'show_in_rest' => false,
            'supports'     => array( 'title', 'editor', 'author' ),
            'has_archive'  => false,
            'rewrite'      => false,
        ) );
    }

    private static function register_reply() {
        register_post_type( 'fhb_reply', array(
            'labels' => array(
                'name'               => 'Replies',
                'singular_name'      => 'Reply',
                'add_new'            => 'Add New Reply',
                'add_new_item'       => 'Add New Reply',
                'edit_item'          => 'Edit Reply',
                'view_item'          => 'View Reply',
                'search_items'       => 'Search Replies',
                'not_found'          => 'No replies found',
                'not_found_in_trash' => 'No replies found in Trash',
            ),
            'public'       => false,
            'show_ui'      => false,
            'show_in_rest' => false,
            'supports'     => array( 'editor', 'author' ),
            'has_archive'  => false,
            'rewrite'      => false,
        ) );
    }
}
