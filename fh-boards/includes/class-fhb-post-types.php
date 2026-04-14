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

        // Prevent fhb_topic/fhb_reply from appearing in the main site search
        // (they have no public URLs, so results would be dead links).
        add_action( 'pre_get_posts', array( __CLASS__, 'exclude_from_site_search' ) );
    }

    private static function register_topic() {
        register_post_type( FHB_Constants::POST_TYPE_TOPIC, array(
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
            'public'              => false,
            'exclude_from_search' => false,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'supports'            => array( 'title', 'editor', 'author' ),
            'has_archive'         => false,
            'rewrite'             => false,
        ) );
    }

    private static function register_reply() {
        register_post_type( FHB_Constants::POST_TYPE_REPLY, array(
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
            'public'              => false,
            'exclude_from_search' => false,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'supports'            => array( 'editor', 'author' ),
            'has_archive'         => false,
            'rewrite'             => false,
        ) );
    }

    /**
     * Exclude board post types from the main WordPress site search.
     *
     * Since these post types have no public URLs, showing them in the
     * site-wide search would produce dead links.
     */
    public static function exclude_from_site_search( $query ) {
        if ( is_admin() || ! $query->is_search() || ! $query->is_main_query() ) {
            return;
        }

        $post_types = $query->get( 'post_type' );

        // Only modify the default search (when no specific post_type is set).
        if ( ! empty( $post_types ) && 'any' !== $post_types ) {
            return;
        }

        $public_types = get_post_types( array( 'public' => true ) );
        unset( $public_types[ FHB_Constants::POST_TYPE_TOPIC ] );
        unset( $public_types[ FHB_Constants::POST_TYPE_REPLY ] );

        $query->set( 'post_type', array_values( $public_types ) );
    }
}
