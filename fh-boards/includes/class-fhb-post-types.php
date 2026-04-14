<?php
/**
 * FHB_Post_Types – Registers all FH Boards custom post types.
 *
 * Hierarchy: Subject → Topic → Thread → Reply
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Post_Types {

    public static function register() {
        self::register_subject();
        self::register_topic_cat();
        self::register_topic();
        self::register_reply();

        add_action( 'pre_get_posts', array( __CLASS__, 'exclude_from_site_search' ) );
    }

    /** Subject – top-level project/product (admin-created). */
    private static function register_subject() {
        register_post_type( FHB_Constants::POST_TYPE_SUBJECT, array(
            'labels' => array(
                'name'          => 'Subjects',
                'singular_name' => 'Subject',
                'not_found'     => 'No subjects found',
            ),
            'public'              => false,
            'exclude_from_search' => true,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'supports'            => array( 'title', 'editor' ),
            'has_archive'         => false,
            'rewrite'             => false,
        ) );
    }

    /** Topic – category within a subject (admin-created, e.g. "Bug Reports"). */
    private static function register_topic_cat() {
        register_post_type( FHB_Constants::POST_TYPE_TOPIC_CAT, array(
            'labels' => array(
                'name'          => 'Topics',
                'singular_name' => 'Topic',
                'not_found'     => 'No topics found',
            ),
            'public'              => false,
            'exclude_from_search' => true,
            'show_ui'             => false,
            'show_in_rest'        => false,
            'supports'            => array( 'title', 'editor' ),
            'has_archive'         => false,
            'rewrite'             => false,
        ) );
    }

    /** Thread – user-created discussion within a topic. */
    private static function register_topic() {
        register_post_type( FHB_Constants::POST_TYPE_TOPIC, array(
            'labels' => array(
                'name'               => 'Threads',
                'singular_name'      => 'Thread',
                'search_items'       => 'Search Threads',
                'not_found'          => 'No threads found',
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

    /** Reply – conversation within a thread. */
    private static function register_reply() {
        register_post_type( FHB_Constants::POST_TYPE_REPLY, array(
            'labels' => array(
                'name'               => 'Replies',
                'singular_name'      => 'Reply',
                'search_items'       => 'Search Replies',
                'not_found'          => 'No replies found',
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
     */
    public static function exclude_from_site_search( $query ) {
        if ( is_admin() || ! $query->is_search() || ! $query->is_main_query() ) {
            return;
        }

        $post_types = $query->get( 'post_type' );
        if ( ! empty( $post_types ) && 'any' !== $post_types ) {
            return;
        }

        $public_types = get_post_types( array( 'public' => true ) );
        unset( $public_types[ FHB_Constants::POST_TYPE_SUBJECT ] );
        unset( $public_types[ FHB_Constants::POST_TYPE_TOPIC_CAT ] );
        unset( $public_types[ FHB_Constants::POST_TYPE_TOPIC ] );
        unset( $public_types[ FHB_Constants::POST_TYPE_REPLY ] );

        $query->set( 'post_type', array_values( $public_types ) );
    }
}
