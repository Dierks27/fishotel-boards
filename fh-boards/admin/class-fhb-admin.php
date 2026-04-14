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
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * Register the top-level admin menu and sub-pages.
     */
    public static function register_menu() {
        add_menu_page(
            'FH Boards',
            'FH Boards',
            FHB_Constants::ADMIN_CAP,
            'fh-boards',
            array( __CLASS__, 'page_topics' ),
            'dashicons-format-chat',
            30
        );

        add_submenu_page(
            'fh-boards',
            'Topics',
            'Topics',
            FHB_Constants::ADMIN_CAP,
            'fh-boards',
            array( __CLASS__, 'page_topics' )
        );

        add_submenu_page(
            'fh-boards',
            'Replies',
            'Replies',
            FHB_Constants::ADMIN_CAP,
            'fh-boards-replies',
            array( __CLASS__, 'page_replies' )
        );

        add_submenu_page(
            'fh-boards',
            'Subscribers',
            'Subscribers',
            FHB_Constants::ADMIN_CAP,
            'fh-boards-subscribers',
            array( __CLASS__, 'page_subscribers' )
        );

        add_submenu_page(
            'fh-boards',
            'Send Notification',
            'Send Notification',
            FHB_Constants::ADMIN_CAP,
            'fh-boards-notify',
            array( __CLASS__, 'page_notify' )
        );

        add_submenu_page(
            'fh-boards',
            'Settings',
            'Settings',
            FHB_Constants::ADMIN_CAP,
            'fh-boards-settings',
            array( __CLASS__, 'page_settings' )
        );
    }

    /**
     * Handle admin POST actions (delete, close, notify).
     */
    public static function handle_actions() {
        if ( ! isset( $_POST['fhb_admin_action'] ) || ! current_user_can( FHB_Constants::ADMIN_CAP ) ) {
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
     * Shared helpers
     * ----------------------------------------------------------------*/

    /**
     * Extract an integer from a POST field.
     */
    private static function get_post_int( $key ) {
        return isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
    }

    /**
     * Redirect to an admin page with a status message.
     */
    private static function redirect_with_message( $page, $message, $extra = array() ) {
        $args = array_merge( array( 'page' => $page, 'message' => $message ), $extra );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Get all reply IDs for a given topic.
     */
    private static function get_topic_reply_ids( $topic_id, $status = 'any' ) {
        return get_posts( array(
            'post_type'      => FHB_Constants::POST_TYPE_REPLY,
            'post_status'    => $status,
            'posts_per_page' => -1,
            'meta_key'       => FHB_Constants::META_TOPIC_ID,
            'meta_value'     => $topic_id,
            'fields'         => 'ids',
        ) );
    }

    /* ------------------------------------------------------------------
     * Admin actions
     * ----------------------------------------------------------------*/

    private static function action_delete_topic() {
        $topic_id = self::get_post_int( 'topic_id' );
        if ( ! $topic_id ) {
            return;
        }

        // Delete all replies for this topic.
        $replies = self::get_topic_reply_ids( $topic_id );
        foreach ( $replies as $reply_id ) {
            wp_delete_post( $reply_id, true );
        }

        // Clean up notification/visit records.
        self::clean_topic_db_records( $topic_id );

        wp_delete_post( $topic_id, true );

        self::redirect_with_message( 'fh-boards', 'topic_deleted' );
    }

    private static function action_close_topic() {
        $topic_id = self::get_post_int( 'topic_id' );
        if ( $topic_id ) {
            update_post_meta( $topic_id, FHB_Constants::META_CLOSED, '1' );
        }

        self::redirect_with_message( 'fh-boards', 'topic_closed' );
    }

    private static function action_reopen_topic() {
        $topic_id = self::get_post_int( 'topic_id' );
        if ( $topic_id ) {
            delete_post_meta( $topic_id, FHB_Constants::META_CLOSED );
        }

        self::redirect_with_message( 'fh-boards', 'topic_reopened' );
    }

    private static function action_delete_reply() {
        $reply_id = self::get_post_int( 'reply_id' );
        if ( ! $reply_id ) {
            return;
        }

        $topic_id = get_post_meta( $reply_id, FHB_Constants::META_TOPIC_ID, true );
        wp_delete_post( $reply_id, true );

        // Recalculate reply count.
        if ( $topic_id ) {
            $count = count( self::get_topic_reply_ids( $topic_id, 'publish' ) );
            update_post_meta( $topic_id, FHB_Constants::META_REPLY_COUNT, $count );
        }

        self::redirect_with_message( 'fh-boards-replies', 'reply_deleted' );
    }

    private static function action_send_notification() {
        $topic_id = self::get_post_int( 'topic_id' );
        if ( ! $topic_id ) {
            return;
        }

        $sent = FHB_Notifications::send_manual_notification( $topic_id );

        self::redirect_with_message( 'fh-boards-notify', 'notification_sent', array( 'count' => $sent ) );
    }

    /* ------------------------------------------------------------------
     * Other helpers
     * ----------------------------------------------------------------*/

    private static function clean_topic_db_records( $topic_id ) {
        global $wpdb;

        $visits_table = FHB_Activator::get_thread_visits_table();
        $notifs_table = FHB_Activator::get_notifications_table();

        $wpdb->delete( $visits_table, array( 'topic_id' => $topic_id ), array( '%d' ) );
        $wpdb->delete( $notifs_table, array( 'topic_id' => $topic_id ), array( '%d' ) );
    }

    /* ------------------------------------------------------------------
     * Settings registration
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

    public static function page_settings() {
        include FHB_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
