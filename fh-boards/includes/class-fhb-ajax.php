<?php
/**
 * FHB_Ajax – AJAX handlers for topics, replies, and subscriptions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Ajax {

    public static function init() {
        // Logged-in only actions.
        add_action( 'wp_ajax_fhb_new_topic', array( __CLASS__, 'new_topic' ) );
        add_action( 'wp_ajax_fhb_new_reply', array( __CLASS__, 'new_reply' ) );
        add_action( 'wp_ajax_fhb_edit_post', array( __CLASS__, 'edit_post' ) );
        add_action( 'wp_ajax_fhb_search', array( __CLASS__, 'search' ) );
        add_action( 'wp_ajax_fhb_subscribe', array( __CLASS__, 'subscribe' ) );
        add_action( 'wp_ajax_fhb_unsubscribe', array( __CLASS__, 'unsubscribe' ) );
        add_action( 'wp_ajax_fhb_enable_notifications', array( __CLASS__, 'enable_notifications' ) );
    }

    /* ------------------------------------------------------------------
     * Shared helpers
     * ----------------------------------------------------------------*/

    /**
     * Verify nonce and login for every AJAX request.
     */
    private static function verify_request() {
        check_ajax_referer( FHB_Constants::NONCE_ACTION, 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }
    }

    /**
     * Send an error if any of the given values are empty.
     */
    private static function require_fields( $fields, $message ) {
        foreach ( $fields as $value ) {
            if ( empty( $value ) ) {
                wp_send_json_error( array( 'message' => $message ) );
            }
        }
    }

    /**
     * Build a short text snippet around a search query match.
     */
    private static function extract_snippet( $content, $query, $context = 40, $length = 100 ) {
        $text = strip_tags( $content );
        $pos  = mb_stripos( $text, $query );

        if ( false === $pos ) {
            return '';
        }

        $start   = max( 0, $pos - $context );
        $snippet = mb_substr( $text, $start, $length );
        if ( $start > 0 ) {
            $snippet = '...' . $snippet;
        }
        return trim( $snippet ) . '...';
    }

    /**
     * Add a user to a topic's subscriber list.
     */
    private static function add_subscriber( $topic_id, $user_id ) {
        $subscribers = FHB_Constants::get_subscribers( $topic_id );
        if ( ! in_array( $user_id, $subscribers, true ) ) {
            $subscribers[] = $user_id;
            update_post_meta( $topic_id, FHB_Constants::META_SUBSCRIBERS, $subscribers );
        }
    }

    /* ------------------------------------------------------------------
     * Create a new topic.
     * ----------------------------------------------------------------*/
    public static function new_topic() {
        self::verify_request();

        $title   = isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : '';
        $content = isset( $_POST['topic_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic_content'] ) ) : '';

        self::require_fields( array( $title, $content ), 'Title and message are required.' );

        $now = current_time( 'mysql', true );

        $topic_id = wp_insert_post( array(
            'post_type'    => FHB_Constants::POST_TYPE_TOPIC,
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ), true );

        if ( is_wp_error( $topic_id ) ) {
            wp_send_json_error( array( 'message' => 'Could not create topic.' ) );
        }

        update_post_meta( $topic_id, FHB_Constants::META_REPLY_COUNT, 0 );
        update_post_meta( $topic_id, FHB_Constants::META_LAST_ACTIVITY, $now );
        update_post_meta( $topic_id, FHB_Constants::META_SUBSCRIBERS, array() );

        wp_send_json_success( array(
            'message'  => 'Topic created.',
            'topic_id' => $topic_id,
        ) );
    }

    /* ------------------------------------------------------------------
     * Post a reply.
     * ----------------------------------------------------------------*/
    public static function new_reply() {
        self::verify_request();

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $content  = isset( $_POST['reply_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply_content'] ) ) : '';

        self::require_fields( array( $topic_id, $content ), 'Topic ID and reply content are required.' );

        // Verify topic exists and is open.
        $topic = get_post( $topic_id );
        if ( ! $topic || FHB_Constants::POST_TYPE_TOPIC !== $topic->post_type ) {
            wp_send_json_error( array( 'message' => 'Topic not found.' ) );
        }

        if ( FHB_Constants::is_topic_closed( $topic_id ) ) {
            wp_send_json_error( array( 'message' => 'This topic is closed.' ) );
        }

        $reply_id = wp_insert_post( array(
            'post_type'    => FHB_Constants::POST_TYPE_REPLY,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ), true );

        if ( is_wp_error( $reply_id ) ) {
            wp_send_json_error( array( 'message' => 'Could not post reply.' ) );
        }

        update_post_meta( $reply_id, FHB_Constants::META_TOPIC_ID, $topic_id );

        // Update topic meta.
        $now = current_time( 'mysql', true );
        $count = absint( get_post_meta( $topic_id, FHB_Constants::META_REPLY_COUNT, true ) );
        update_post_meta( $topic_id, FHB_Constants::META_REPLY_COUNT, $count + 1 );
        update_post_meta( $topic_id, FHB_Constants::META_LAST_ACTIVITY, $now );

        // Queue notifications for this topic (processed by cron).
        update_post_meta( $topic_id, FHB_Constants::META_PENDING_NOTIFICATION, '1' );

        // Build reply HTML for live-append.
        $author_id   = get_current_user_id();
        $author_name = get_the_author_meta( 'display_name', $author_id );
        $date        = get_the_date( '', $reply_id );
        $time        = get_the_time( '', $reply_id );

        $html  = '<div class="fhb-post fhb-reply-post" data-post-id="' . esc_attr( $reply_id ) . '">';
        $html .= '<div class="fhb-post-author">';
        $html .= '<span class="fhb-fish-icon">&#x1F41F;</span>';
        $html .= '<strong>' . esc_html( $author_name ) . '</strong>';
        $html .= '<span class="fhb-post-date">' . esc_html( $date ) . ' at ' . esc_html( $time ) . '</span>';
        $html .= '</div>';
        $html .= '<div class="fhb-post-content">' . wp_kses_post( wpautop( $content ) ) . '</div>';
        $html .= '<div class="fhb-post-actions"><button type="button" class="fhb-edit-btn">Edit</button></div>';
        $html .= '</div>';

        wp_send_json_success( array(
            'message'  => 'Reply posted.',
            'reply_id' => $reply_id,
            'html'     => $html,
        ) );
    }

    /* ------------------------------------------------------------------
     * Edit a post (topic or reply) inline.
     * ----------------------------------------------------------------*/
    public static function edit_post() {
        self::verify_request();

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

        self::require_fields( array( $post_id, $content ), 'Post ID and content are required.' );

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, array( FHB_Constants::POST_TYPE_TOPIC, FHB_Constants::POST_TYPE_REPLY ), true ) ) {
            wp_send_json_error( array( 'message' => 'Post not found.' ) );
        }

        // Only the author or an admin can edit.
        if ( ! FHB_Constants::user_can_edit( $post ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to edit this post.' ) );
        }

        $now = current_time( 'mysql' );
        $result = wp_update_post( array(
            'ID'                => $post_id,
            'post_content'      => $content,
            'post_modified'     => $now,
            'post_modified_gmt' => current_time( 'mysql', true ),
        ), true );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => 'Could not update post.' ) );
        }

        $edited_stamp = '<span class="fhb-edited-stamp">(edited ' . esc_html( mysql2date( get_option( 'date_format' ), $now ) ) . ')</span>';

        wp_send_json_success( array(
            'message'      => 'Post updated.',
            'html'         => wp_kses_post( wpautop( $content ) ),
            'edited_stamp' => $edited_stamp,
        ) );
    }

    /* ------------------------------------------------------------------
     * Search topics (titles, topic content, and reply content).
     * ----------------------------------------------------------------*/
    public static function search() {
        self::verify_request();

        $query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

        if ( mb_strlen( $query ) < 2 ) {
            wp_send_json_error( array( 'message' => 'Query too short.' ) );
        }

        $found_topic_ids = array();

        // 1. Search topic titles and content.
        $topic_results = new WP_Query( array(
            'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
            'post_status'    => 'publish',
            's'              => $query,
            'posts_per_page' => 20,
            'fields'         => 'ids',
        ) );
        foreach ( $topic_results->posts as $tid ) {
            $found_topic_ids[ $tid ] = true;
        }

        // 2. Search reply content and map back to parent topics.
        $reply_results = new WP_Query( array(
            'post_type'      => FHB_Constants::POST_TYPE_REPLY,
            'post_status'    => 'publish',
            's'              => $query,
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ) );
        foreach ( $reply_results->posts as $rid ) {
            $parent_tid = get_post_meta( $rid, FHB_Constants::META_TOPIC_ID, true );
            if ( $parent_tid && 'publish' === get_post_status( $parent_tid ) ) {
                $found_topic_ids[ (int) $parent_tid ] = true;
            }
        }

        // Build the response from unique topic IDs.
        $topic_ids = array_keys( $found_topic_ids );
        $topics    = array();

        if ( ! empty( $topic_ids ) ) {
            $ordered = new WP_Query( array(
                'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
                'post_status'    => 'publish',
                'post__in'       => $topic_ids,
                'posts_per_page' => 20,
                'orderby'        => 'post__in',
            ) );

            while ( $ordered->have_posts() ) {
                $ordered->the_post();
                $tid = get_the_ID();

                // Try to find a matching snippet from topic content.
                $snippet  = self::extract_snippet( get_the_content(), $query );
                $reply_id = 0;

                // If no match in topic content, check replies.
                if ( empty( $snippet ) ) {
                    $matching_replies = new WP_Query( array(
                        'post_type'      => FHB_Constants::POST_TYPE_REPLY,
                        'post_status'    => 'publish',
                        's'              => $query,
                        'posts_per_page' => 1,
                        'meta_key'       => FHB_Constants::META_TOPIC_ID,
                        'meta_value'     => $tid,
                    ) );
                    if ( $matching_replies->have_posts() ) {
                        $matching_replies->the_post();
                        $reply_id = get_the_ID();
                        $snippet  = self::extract_snippet( get_the_content(), $query );
                    }
                    // Restore outer loop post.
                    $ordered->the_post();
                }

                $topics[] = array(
                    'post_id'      => $tid,
                    'title'        => get_the_title(),
                    'author_name'  => get_the_author(),
                    'date'         => get_the_date(),
                    'reply_count'  => absint( get_post_meta( $tid, FHB_Constants::META_REPLY_COUNT, true ) ),
                    'is_closed'    => FHB_Constants::is_topic_closed( $tid ),
                    'snippet'      => $snippet,
                    'reply_id'     => $reply_id,
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( array( 'topics' => $topics ) );
    }

    /* ------------------------------------------------------------------
     * Subscribe to a topic.
     * ----------------------------------------------------------------*/
    public static function subscribe() {
        self::verify_request();

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $user_id  = get_current_user_id();

        if ( ! $topic_id ) {
            wp_send_json_error( array( 'message' => 'Invalid topic.' ) );
        }

        // Check if user has email notifications enabled.
        $notifs_on = get_user_meta( $user_id, FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS, true ) === '1';

        if ( ! $notifs_on ) {
            wp_send_json_error( array(
                'message'      => 'You have notifications turned off. Would you like to turn them on?',
                'needs_opt_in' => true,
            ) );
        }

        self::add_subscriber( $topic_id, $user_id );

        wp_send_json_success( array( 'message' => 'You are now subscribed to this topic.' ) );
    }

    /* ------------------------------------------------------------------
     * Unsubscribe from a topic.
     * ----------------------------------------------------------------*/
    public static function unsubscribe() {
        self::verify_request();

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $user_id  = get_current_user_id();

        if ( ! $topic_id ) {
            wp_send_json_error( array( 'message' => 'Invalid topic.' ) );
        }

        $subscribers = FHB_Constants::get_subscribers( $topic_id );
        $subscribers = array_values( array_diff( $subscribers, array( $user_id ) ) );
        update_post_meta( $topic_id, FHB_Constants::META_SUBSCRIBERS, $subscribers );

        wp_send_json_success( array( 'message' => 'You have been unsubscribed.' ) );
    }

    /* ------------------------------------------------------------------
     * Enable notifications and subscribe in one step.
     * ----------------------------------------------------------------*/
    public static function enable_notifications() {
        self::verify_request();

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $user_id  = get_current_user_id();

        if ( ! $topic_id ) {
            wp_send_json_error( array( 'message' => 'Invalid topic.' ) );
        }

        // Turn on email notifications for this user.
        update_user_meta( $user_id, FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS, '1' );

        // Subscribe them.
        self::add_subscriber( $topic_id, $user_id );

        wp_send_json_success( array( 'message' => 'Notifications enabled and you are now subscribed.' ) );
    }
}
