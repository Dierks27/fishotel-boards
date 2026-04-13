<?php
/**
 * FHB_Admin – Admin menu and page routing for FH Boards.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
    }

    /**
     * Register the top-level admin menu and sub-pages.
     */
    public static function register_menu() {
        add_menu_page(
            'FH Boards',
            'FH Boards',
            'manage_options',
            'fh-boards',
            array( __CLASS__, 'page_topics' ),
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            'fh-boards',
            'Topics',
            'Topics',
            'manage_options',
            'fh-boards',
            array( __CLASS__, 'page_topics' )
        );

        add_submenu_page(
            'fh-boards',
            'Replies',
            'Replies',
            'manage_options',
            'fh-boards-replies',
            array( __CLASS__, 'page_replies' )
        );

        add_submenu_page(
            'fh-boards',
            'Subscribers',
            'Subscribers',
            'manage_options',
            'fh-boards-subscribers',
            array( __CLASS__, 'page_subscribers' )
        );

        add_submenu_page(
            'fh-boards',
            'Send Notification',
            'Send Notification',
            'manage_options',
            'fh-boards-notify',
            array( __CLASS__, 'page_notify' )
        );
    }

    /**
     * Handle admin POST actions (delete, close, notify).
     */
    public static function handle_actions() {
        if ( ! isset( $_POST['fhb_admin_action'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $action = sanitize_text_field( $_POST['fhb_admin_action'] );

        // Verify nonce for all admin actions.
        if ( ! isset( $_POST['fhb_admin_nonce'] ) || ! wp_verify_nonce( $_POST['fhb_admin_nonce'], 'fhb_admin_' . $action ) ) {
            wp_die( 'Security check failed.' );
        }

        switch ( $action ) {
            case 'delete_topic':
                self::action_delete_topic();
                break;

            case 'close_topic':
                self::action_close_topic();
                break;

            case 'reopen_topic':
                self::action_reopen_topic();
                break;

            case 'delete_reply':
                self::action_delete_reply();
                break;

            case 'send_notification':
                self::action_send_notification();
                break;
        }
    }

    /* ------------------------------------------------------------------
     * Admin actions
     * ----------------------------------------------------------------*/

    private static function action_delete_topic() {
        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        if ( ! $topic_id ) {
            return;
        }

        // Delete all replies for this topic.
        $replies = get_posts( array(
            'post_type'      => 'fhb_reply',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_key'       => '_fhb_topic_id',
            'meta_value'     => $topic_id,
            'fields'         => 'ids',
        ) );
        foreach ( $replies as $reply_id ) {
            wp_delete_post( $reply_id, true );
        }

        // Clean up notification/visit records.
        self::clean_topic_db_records( $topic_id );

        wp_delete_post( $topic_id, true );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'fh-boards',
            'message' => 'topic_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function action_close_topic() {
        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        if ( $topic_id ) {
            update_post_meta( $topic_id, '_fhb_closed', '1' );
        }

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'fh-boards',
            'message' => 'topic_closed',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function action_reopen_topic() {
        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        if ( $topic_id ) {
            delete_post_meta( $topic_id, '_fhb_closed' );
        }

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'fh-boards',
            'message' => 'topic_reopened',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function action_delete_reply() {
        $reply_id = isset( $_POST['reply_id'] ) ? absint( $_POST['reply_id'] ) : 0;
        if ( ! $reply_id ) {
            return;
        }

        $topic_id = get_post_meta( $reply_id, '_fhb_topic_id', true );
        wp_delete_post( $reply_id, true );

        // Recalculate reply count.
        if ( $topic_id ) {
            $count = self::count_replies( $topic_id );
            update_post_meta( $topic_id, '_fhb_reply_count', $count );
        }

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'fh-boards-replies',
            'message' => 'reply_deleted',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function action_send_notification() {
        $topic_id = isset( $_POST['topic_id'] ) ? absint( $_POST['topic_id'] ) : 0;
        if ( ! $topic_id ) {
            return;
        }

        $sent = FHB_Notifications::send_manual_notification( $topic_id );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'fh-boards-notify',
            'message' => 'notification_sent',
            'count'   => $sent,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /* ------------------------------------------------------------------
     * Helper methods
     * ----------------------------------------------------------------*/

    private static function count_replies( $topic_id ) {
        $replies = get_posts( array(
            'post_type'      => 'fhb_reply',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_fhb_topic_id',
            'meta_value'     => $topic_id,
            'fields'         => 'ids',
        ) );
        return count( $replies );
    }

    private static function clean_topic_db_records( $topic_id ) {
        global $wpdb;

        $visits_table = FHB_Activator::get_thread_visits_table();
        $notifs_table = FHB_Activator::get_notifications_table();

        $wpdb->delete( $visits_table, array( 'topic_id' => $topic_id ), array( '%d' ) );
        $wpdb->delete( $notifs_table, array( 'topic_id' => $topic_id ), array( '%d' ) );
    }

    /* ------------------------------------------------------------------
     * Page callbacks
     * ----------------------------------------------------------------*/

    public static function page_topics() {
        include FHB_PLUGIN_DIR . 'admin/views/topics.php';
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
}
