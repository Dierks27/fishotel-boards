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
     * Create a new topic.
     * ----------------------------------------------------------------*/
    public static function new_topic() {
        check_ajax_referer( 'fhb_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }

        $title   = isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : '';
        $content = isset( $_POST['topic_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic_content'] ) ) : '';

        if ( empty( $title ) || empty( $content ) ) {
            wp_send_json_error( array( 'message' => 'Title and message are required.' ) );
        }

        $now = current_time( 'mysql', true );

        $topic_id = wp_insert_post( array(
            'post_type'    => 'fhb_topic',
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ), true );

        if ( is_wp_error( $topic_id ) ) {
            wp_send_json_error( array( 'message' => 'Could not create topic.' ) );
        }

        update_post_meta( $topic_id, '_fhb_reply_count', 0 );
        update_post_meta( $topic_id, '_fhb_last_activity', $now );
        update_post_meta( $topic_id, '_fhb_subscribers', array() );

        wp_send_json_success( array(
            'message'  => 'Topic created.',
            'topic_id' => $topic_id,
        ) );
    }

    /* ------------------------------------------------------------------
     * Post a reply.
     * ----------------------------------------------------------------*/
    public static function new_reply() {
        check_ajax_referer( 'fhb_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $content  = isset( $_POST['reply_content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply_content'] ) ) : '';

        if ( ! $topic_id || empty( $content ) ) {
            wp_send_json_error( array( 'message' => 'Topic ID and reply content are required.' ) );
        }

        // Verify topic exists and is open.
        $topic = get_post( $topic_id );
        if ( ! $topic || 'fhb_topic' !== $topic->post_type ) {
            wp_send_json_error( array( 'message' => 'Topic not found.' ) );
        }

        if ( get_post_meta( $topic_id, '_fhb_closed', true ) === '1' ) {
            wp_send_json_error( array( 'message' => 'This topic is closed.' ) );
        }

        $reply_id = wp_insert_post( array(
            'post_type'    => 'fhb_reply',
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ), true );

        if ( is_wp_error( $reply_id ) ) {
            wp_send_json_error( array( 'message' => 'Could not post reply.' ) );
        }

        update_post_meta( $reply_id, '_fhb_topic_id', $topic_id );

        // Update topic meta.
        $now = current_time( 'mysql', true );
        $count = absint( get_post_meta( $topic_id, '_fhb_reply_count', true ) );
        update_post_meta( $topic_id, '_fhb_reply_count', $count + 1 );
        update_post_meta( $topic_id, '_fhb_last_activity', $now );

        // Queue notifications for this topic (processed by cron).
        update_post_meta( $topic_id, '_fhb_pending_notification', '1' );

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
        check_ajax_referer( 'fhb_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

        if ( ! $post_id || empty( $content ) ) {
            wp_send_json_error( array( 'message' => 'Post ID and content are required.' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, array( 'fhb_topic', 'fhb_reply' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Post not found.' ) );
        }

        // Only the author or an admin can edit.
        $current_user = get_current_user_id();
        if ( (int) $post->post_author !== $current_user && ! current_user_can( 'manage_options' ) ) {
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
     * Search topics.
     * ----------------------------------------------------------------*/
    public static function search() {
        check_ajax_referer( 'fhb_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }

        $query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

        if ( mb_strlen( $query ) < 2 ) {
            wp_send_json_error( array( 'message' => 'Query too short.' ) );
        }

        $results = new WP_Query( array(
            'post_type'      => 'fhb_topic',
            'post_status'    => 'publish',
            's'              => $query,
            'posts_per_page' => 20,
            'orderby'        => 'relevance',
        ) );

        $topics = array();
        if ( $results->have_posts() ) {
            while ( $results->have_posts() ) {
                $results->the_post();
                $tid = get_the_ID();
                $topics[] = array(
                    'post_id'      => $tid,
                    'title'        => get_the_title(),
                    'author_name'  => get_the_author(),
                    'date'         => get_the_date(),
                    'reply_count'  => absint( get_post_meta( $tid, '_fhb_reply_count', true ) ),
                    'is_closed'    => get_post_meta( $tid, '_fhb_closed', true ) === '1',
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
        check_ajax_referer( 'fhb_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $user_id  = get_current_user_id();

        if ( ! $topic_id ) {
            wp_send_json_error( array( 'message' => 'Invalid topic.' ) );
        }

        // Check if user has email notifications enabled.
        $notifs_on = get_user_meta( $user_id, 'fhb_email_notifications', true ) === '1';

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
        check_ajax_referer( 'fhb_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $user_id  = get_current_user_id();

        if ( ! $topic_id ) {
            wp_send_json_error( array( 'message' => 'Invalid topic.' ) );
        }

        $subscribers = get_post_meta( $topic_id, '_fhb_subscribers', true );
        if ( is_array( $subscribers ) ) {
            $subscribers = array_values( array_diff( $subscribers, array( $user_id ) ) );
            update_post_meta( $topic_id, '_fhb_subscribers', $subscribers );
        }

        wp_send_json_success( array( 'message' => 'You have been unsubscribed.' ) );
    }

    /* ------------------------------------------------------------------
     * Enable notifications and subscribe in one step.
     * ----------------------------------------------------------------*/
    public static function enable_notifications() {
        check_ajax_referer( 'fhb_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'You must be logged in.' ) );
        }

        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        $user_id  = get_current_user_id();

        if ( ! $topic_id ) {
            wp_send_json_error( array( 'message' => 'Invalid topic.' ) );
        }

        // Turn on email notifications for this user.
        update_user_meta( $user_id, 'fhb_email_notifications', '1' );

        // Subscribe them.
        self::add_subscriber( $topic_id, $user_id );

        wp_send_json_success( array( 'message' => 'Notifications enabled and you are now subscribed.' ) );
    }

    /* ------------------------------------------------------------------
     * Helper: add a user to a topic's subscriber list.
     * ----------------------------------------------------------------*/
    private static function add_subscriber( $topic_id, $user_id ) {
        $subscribers = get_post_meta( $topic_id, '_fhb_subscribers', true );
        if ( ! is_array( $subscribers ) ) {
            $subscribers = array();
        }
        if ( ! in_array( $user_id, $subscribers, true ) ) {
            $subscribers[] = $user_id;
            update_post_meta( $topic_id, '_fhb_subscribers', $subscribers );
        }
    }
}
