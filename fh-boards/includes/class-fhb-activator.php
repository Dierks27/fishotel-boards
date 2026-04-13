<?php
/**
 * FHB_Activator – Plugin activation / deactivation.
 *
 * Creates custom DB tables on activation and cleans up on deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Activator {

    /**
     * Runs on plugin activation.
     *
     * Creates the fhb_thread_visits and fhb_notifications custom tables
     * and schedules the notification cron event.
     */
    public static function activate() {
        self::create_tables();
        self::schedule_cron();
        flush_rewrite_rules();
    }

    /**
     * Runs on plugin deactivation.
     *
     * Removes the scheduled cron event.
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled( 'fhb_process_notifications' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'fhb_process_notifications' );
        }
    }

    /**
     * Create custom database tables using dbDelta.
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $thread_visits_table = $wpdb->prefix . 'fhb_thread_visits';
        $notifications_table = $wpdb->prefix . 'fhb_notifications';

        $sql_thread_visits = "CREATE TABLE {$thread_visits_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            topic_id BIGINT(20) UNSIGNED NOT NULL,
            last_visit DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY user_topic (user_id, topic_id),
            KEY topic_id (topic_id)
        ) {$charset_collate};";

        $sql_notifications = "CREATE TABLE {$notifications_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            topic_id BIGINT(20) UNSIGNED NOT NULL,
            last_sent DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY user_topic (user_id, topic_id),
            KEY topic_id (topic_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( $sql_thread_visits );
        dbDelta( $sql_notifications );

        update_option( 'fhb_db_version', '1.0.0' );
    }

    /**
     * Schedule the WP Cron event for processing notifications.
     */
    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'fhb_process_notifications' ) ) {
            wp_schedule_event( time(), 'hourly', 'fhb_process_notifications' );
        }
    }

    /**
     * Helper: get the thread visits table name.
     */
    public static function get_thread_visits_table() {
        global $wpdb;
        return $wpdb->prefix . 'fhb_thread_visits';
    }

    /**
     * Helper: get the notifications table name.
     */
    public static function get_notifications_table() {
        global $wpdb;
        return $wpdb->prefix . 'fhb_notifications';
    }
}
