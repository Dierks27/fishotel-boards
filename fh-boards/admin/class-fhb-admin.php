<?php
/**
 * FHB_Admin – Admin menu and page routing for FH Boards.
 *
 * Menu structure mirrors the board hierarchy:
 *   Subjects → Topics → Threads → Replies
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /* ------------------------------------------------------------------
     * Menu
     * ----------------------------------------------------------------*/

    public static function register_menu() {
        add_menu_page(
            'FH Boards',
            'FH Boards',
            FHB_Constants::ADMIN_CAP,
            'fh-boards',
            array( __CLASS__, 'page_subjects' ),
            'dashicons-format-chat',
            30
        );

        add_submenu_page( 'fh-boards', 'Subjects', 'Subjects', FHB_Constants::ADMIN_CAP, 'fh-boards', array( __CLASS__, 'page_subjects' ) );
        add_submenu_page( 'fh-boards', 'Topics', 'Topics', FHB_Constants::ADMIN_CAP, 'fh-boards-topics', array( __CLASS__, 'page_topics' ) );
        add_submenu_page( 'fh-boards', 'Threads', 'Threads', FHB_Constants::ADMIN_CAP, 'fh-boards-threads', array( __CLASS__, 'page_threads' ) );
        add_submenu_page( 'fh-boards', 'Replies', 'Replies', FHB_Constants::ADMIN_CAP, 'fh-boards-replies', array( __CLASS__, 'page_replies' ) );
        add_submenu_page( 'fh-boards', 'Subscribers', 'Subscribers', FHB_Constants::ADMIN_CAP, 'fh-boards-subscribers', array( __CLASS__, 'page_subscribers' ) );
        add_submenu_page( 'fh-boards', 'Send Notification', 'Notify', FHB_Constants::ADMIN_CAP, 'fh-boards-notify', array( __CLASS__, 'page_notify' ) );
        add_submenu_page( 'fh-boards', 'Settings', 'Settings', FHB_Constants::ADMIN_CAP, 'fh-boards-settings', array( __CLASS__, 'page_settings' ) );
    }

    /* ------------------------------------------------------------------
     * Action router
     * ----------------------------------------------------------------*/

    public static function handle_actions() {
        if ( ! isset( $_POST['fhb_admin_action'] ) || ! current_user_can( FHB_Constants::ADMIN_CAP ) ) {
            return;
        }

        $action = sanitize_text_field( $_POST['fhb_admin_action'] );

        if ( ! isset( $_POST['fhb_admin_nonce'] ) || ! wp_verify_nonce( $_POST['fhb_admin_nonce'], 'fhb_admin_' . $action ) ) {
            wp_die( 'Security check failed.' );
        }

        switch ( $action ) {
            case 'create_subject':       self::action_create_subject(); break;
            case 'delete_subject':       self::action_delete_subject(); break;
            case 'create_topic':         self::action_create_topic(); break;
            case 'delete_topic':         self::action_delete_topic(); break;
            case 'delete_thread':        self::action_delete_thread(); break;
            case 'close_thread':         self::action_close_thread(); break;
            case 'reopen_thread':        self::action_reopen_thread(); break;
            case 'delete_reply':         self::action_delete_reply(); break;
            case 'send_notification':    self::action_send_notification(); break;
        }
    }

    /* ------------------------------------------------------------------
     * Shared helpers
     * ----------------------------------------------------------------*/

    private static function get_post_int( $key ) {
        return isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
    }

    private static function redirect_with_message( $page, $message, $extra = array() ) {
        $args = array_merge( array( 'page' => $page, 'message' => $message ), $extra );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function get_thread_reply_ids( $thread_id, $status = 'any' ) {
        return get_posts( array(
            'post_type'      => FHB_Constants::POST_TYPE_REPLY,
            'post_status'    => $status,
            'posts_per_page' => -1,
            'meta_key'       => FHB_Constants::META_TOPIC_ID,
            'meta_value'     => $thread_id,
            'fields'         => 'ids',
        ) );
    }

    private static function clean_thread_db_records( $thread_id ) {
        global $wpdb;
        $wpdb->delete( FHB_Activator::get_thread_visits_table(), array( 'topic_id' => $thread_id ), array( '%d' ) );
        $wpdb->delete( FHB_Activator::get_notifications_table(), array( 'topic_id' => $thread_id ), array( '%d' ) );
    }

    /* ------------------------------------------------------------------
     * Subject actions
     * ----------------------------------------------------------------*/

    private static function action_create_subject() {
        $title       = isset( $_POST['subject_title'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_title'] ) ) : '';
        $description = isset( $_POST['subject_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['subject_description'] ) ) : '';

        if ( empty( $title ) ) {
            self::redirect_with_message( 'fh-boards', 'subject_error' );
        }

        $id = wp_insert_post( array(
            'post_type'    => FHB_Constants::POST_TYPE_SUBJECT,
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ) );

        if ( ! is_wp_error( $id ) ) {
            update_post_meta( $id, FHB_Constants::META_TOPIC_COUNT, 0 );
            update_post_meta( $id, FHB_Constants::META_LAST_ACTIVITY, current_time( 'mysql', true ) );
        }

        self::redirect_with_message( 'fh-boards', 'subject_created' );
    }

    private static function action_delete_subject() {
        $subject_id = self::get_post_int( 'subject_id' );
        if ( ! $subject_id ) {
            return;
        }

        // Delete all topic_cats in this subject.
        $topic_cats = get_posts( array(
            'post_type'      => FHB_Constants::POST_TYPE_TOPIC_CAT,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_key'       => FHB_Constants::META_SUBJECT_ID,
            'meta_value'     => $subject_id,
            'fields'         => 'ids',
        ) );

        foreach ( $topic_cats as $tc_id ) {
            self::delete_topic_cat_cascade( $tc_id );
        }

        wp_delete_post( $subject_id, true );
        self::redirect_with_message( 'fh-boards', 'subject_deleted' );
    }

    /* ------------------------------------------------------------------
     * Topic (category) actions
     * ----------------------------------------------------------------*/

    private static function action_create_topic() {
        $subject_id  = self::get_post_int( 'subject_id' );
        $title       = isset( $_POST['topic_title'] ) ? sanitize_text_field( wp_unslash( $_POST['topic_title'] ) ) : '';
        $description = isset( $_POST['topic_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['topic_description'] ) ) : '';

        if ( empty( $title ) || ! $subject_id ) {
            self::redirect_with_message( 'fh-boards-topics', 'topic_error' );
        }

        $id = wp_insert_post( array(
            'post_type'    => FHB_Constants::POST_TYPE_TOPIC_CAT,
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ) );

        if ( ! is_wp_error( $id ) ) {
            update_post_meta( $id, FHB_Constants::META_SUBJECT_ID, $subject_id );
            update_post_meta( $id, FHB_Constants::META_THREAD_COUNT, 0 );
            update_post_meta( $id, FHB_Constants::META_LAST_ACTIVITY, current_time( 'mysql', true ) );

            // Update subject's topic count.
            $count = absint( get_post_meta( $subject_id, FHB_Constants::META_TOPIC_COUNT, true ) );
            update_post_meta( $subject_id, FHB_Constants::META_TOPIC_COUNT, $count + 1 );
        }

        self::redirect_with_message( 'fh-boards-topics', 'topic_created' );
    }

    private static function action_delete_topic() {
        $tc_id = self::get_post_int( 'topic_cat_id' );
        if ( ! $tc_id ) {
            return;
        }

        $subject_id = get_post_meta( $tc_id, FHB_Constants::META_SUBJECT_ID, true );
        self::delete_topic_cat_cascade( $tc_id );

        // Update subject topic count.
        if ( $subject_id ) {
            $remaining = get_posts( array(
                'post_type'      => FHB_Constants::POST_TYPE_TOPIC_CAT,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'meta_key'       => FHB_Constants::META_SUBJECT_ID,
                'meta_value'     => $subject_id,
                'fields'         => 'ids',
            ) );
            update_post_meta( $subject_id, FHB_Constants::META_TOPIC_COUNT, count( $remaining ) );
        }

        self::redirect_with_message( 'fh-boards-topics', 'topic_deleted' );
    }

    /** Delete a topic_cat and all its threads + replies. */
    private static function delete_topic_cat_cascade( $tc_id ) {
        $threads = get_posts( array(
            'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_key'       => FHB_Constants::META_TOPIC_CAT_ID,
            'meta_value'     => $tc_id,
            'fields'         => 'ids',
        ) );

        foreach ( $threads as $thread_id ) {
            $replies = self::get_thread_reply_ids( $thread_id );
            foreach ( $replies as $reply_id ) {
                wp_delete_post( $reply_id, true );
            }
            self::clean_thread_db_records( $thread_id );
            wp_delete_post( $thread_id, true );
        }

        wp_delete_post( $tc_id, true );
    }

    /* ------------------------------------------------------------------
     * Thread actions (formerly "topic" actions)
     * ----------------------------------------------------------------*/

    private static function action_delete_thread() {
        $thread_id = self::get_post_int( 'thread_id' );
        if ( ! $thread_id ) {
            return;
        }

        $replies = self::get_thread_reply_ids( $thread_id );
        foreach ( $replies as $reply_id ) {
            wp_delete_post( $reply_id, true );
        }
        self::clean_thread_db_records( $thread_id );
        wp_delete_post( $thread_id, true );

        self::redirect_with_message( 'fh-boards-threads', 'thread_deleted' );
    }

    private static function action_close_thread() {
        $thread_id = self::get_post_int( 'thread_id' );
        if ( $thread_id ) {
            update_post_meta( $thread_id, FHB_Constants::META_CLOSED, '1' );
        }
        self::redirect_with_message( 'fh-boards-threads', 'thread_closed' );
    }

    private static function action_reopen_thread() {
        $thread_id = self::get_post_int( 'thread_id' );
        if ( $thread_id ) {
            delete_post_meta( $thread_id, FHB_Constants::META_CLOSED );
        }
        self::redirect_with_message( 'fh-boards-threads', 'thread_reopened' );
    }

    /* ------------------------------------------------------------------
     * Reply actions
     * ----------------------------------------------------------------*/

    private static function action_delete_reply() {
        $reply_id = self::get_post_int( 'reply_id' );
        if ( ! $reply_id ) {
            return;
        }

        $thread_id = get_post_meta( $reply_id, FHB_Constants::META_TOPIC_ID, true );
        wp_delete_post( $reply_id, true );

        if ( $thread_id ) {
            $count = count( self::get_thread_reply_ids( $thread_id, 'publish' ) );
            update_post_meta( $thread_id, FHB_Constants::META_REPLY_COUNT, $count );
        }

        self::redirect_with_message( 'fh-boards-replies', 'reply_deleted' );
    }

    /* ------------------------------------------------------------------
     * Notification actions
     * ----------------------------------------------------------------*/

    private static function action_send_notification() {
        $thread_id = self::get_post_int( 'topic_id' );
        if ( ! $thread_id ) {
            return;
        }
        $sent = FHB_Notifications::send_manual_notification( $thread_id );
        self::redirect_with_message( 'fh-boards-notify', 'notification_sent', array( 'count' => $sent ) );
    }

    /* ------------------------------------------------------------------
     * Settings
     * ----------------------------------------------------------------*/

    public static function register_settings() {
        register_setting( 'fhb_settings', 'fhb_delete_data_on_uninstall', array(
            'type'              => 'boolean',
            'default'           => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ) );
    }

    /* ------------------------------------------------------------------
     * Page callbacks
     * ----------------------------------------------------------------*/

    public static function page_subjects() {
        include FHB_PLUGIN_DIR . 'admin/views/subjects.php';
    }

    public static function page_topics() {
        include FHB_PLUGIN_DIR . 'admin/views/topics-admin.php';
    }

    public static function page_threads() {
        include FHB_PLUGIN_DIR . 'admin/views/threads.php';
    }

    public static function page_replies() {
        include FHB_PLUGIN_DIR . 'admin/views/replies.php';
    }

    public static function page_subscribers() {
        include FHB_PLUGIN_DIR . 'admin/views/subscribers.php';
    }

    public static function page_notify() {
        include FHB_PLUGIN_DIR . 'admin/views/notify.php';
    }

    public static function page_settings() {
        include FHB_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
