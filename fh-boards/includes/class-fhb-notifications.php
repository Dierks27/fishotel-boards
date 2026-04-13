<?php
/**
 * FHB_Notifications – WP Cron notification processing and throttling.
 *
 * Logic per subscriber:
 *   - Has the user visited the thread since the last notification? → send.
 *   - Has it been 3+ days since the last notification for this thread? → send.
 *   - Otherwise → skip.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Notifications {

    public static function init() {
        add_action( 'fhb_process_notifications', array( __CLASS__, 'process_queue' ) );
    }

    /**
     * Cron callback: find all topics with pending notifications and process them.
     */
    public static function process_queue() {
        $topics = get_posts( array(
            'post_type'      => 'fhb_topic',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_fhb_pending_notification',
            'meta_value'     => '1',
            'fields'         => 'ids',
        ) );

        foreach ( $topics as $topic_id ) {
            self::process_topic( $topic_id );
            delete_post_meta( $topic_id, '_fhb_pending_notification' );
        }
    }

    /**
     * Process notifications for a single topic.
     */
    public static function process_topic( $topic_id ) {
        $subscribers = get_post_meta( $topic_id, '_fhb_subscribers', true );
        if ( ! is_array( $subscribers ) || empty( $subscribers ) ) {
            return;
        }

        $topic = get_post( $topic_id );
        if ( ! $topic ) {
            return;
        }

        foreach ( $subscribers as $user_id ) {
            // Skip the author of the latest reply (they don't need a notification).
            $latest_reply = get_posts( array(
                'post_type'      => 'fhb_reply',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'meta_key'       => '_fhb_topic_id',
                'meta_value'     => $topic_id,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ) );

            if ( ! empty( $latest_reply ) ) {
                $reply_author = (int) get_post_field( 'post_author', $latest_reply[0] );
                if ( $reply_author === (int) $user_id ) {
                    continue;
                }
            }

            // Check if user still has email notifications enabled.
            if ( get_user_meta( $user_id, 'fhb_email_notifications', true ) !== '1' ) {
                continue;
            }

            if ( self::should_send( $user_id, $topic_id ) ) {
                self::send_email( $user_id, $topic_id, $topic );
                self::record_sent( $user_id, $topic_id );
            }
        }
    }

    /**
     * Determine if a notification should be sent.
     *
     * YES if the user visited since last notification.
     * YES if 3+ days since last notification.
     * NO otherwise.
     */
    private static function should_send( $user_id, $topic_id ) {
        global $wpdb;

        $visits_table = FHB_Activator::get_thread_visits_table();
        $notifs_table = FHB_Activator::get_notifications_table();

        // Get the last notification sent time.
        $last_sent = $wpdb->get_var( $wpdb->prepare(
            "SELECT last_sent FROM {$notifs_table} WHERE user_id = %d AND topic_id = %d",
            $user_id,
            $topic_id
        ) );

        // Never sent before → send.
        if ( ! $last_sent || $last_sent === '0000-00-00 00:00:00' ) {
            return true;
        }

        // Has the user visited since the last notification?
        $last_visit = $wpdb->get_var( $wpdb->prepare(
            "SELECT last_visit FROM {$visits_table} WHERE user_id = %d AND topic_id = %d",
            $user_id,
            $topic_id
        ) );

        if ( $last_visit && strtotime( $last_visit ) > strtotime( $last_sent ) ) {
            return true;
        }

        // Has it been 3+ days since the last notification?
        $days_since = ( time() - strtotime( $last_sent ) ) / DAY_IN_SECONDS;
        if ( $days_since >= 3 ) {
            return true;
        }

        return false;
    }

    /**
     * Send the notification email.
     */
    private static function send_email( $user_id, $topic_id, $topic ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $topic_title = $topic->post_title;
        $site_name   = get_bloginfo( 'name' );
        $subject     = sprintf( '[%s] New reply in: %s', $site_name, $topic_title );

        // Build a link to the topic. We look for a page with [fh_boards].
        $board_page = self::get_board_page_url();
        $topic_url  = $board_page ? add_query_arg( 'fhb_topic', $topic_id, $board_page ) : home_url();

        $message  = sprintf( "Hi %s,\n\n", $user->display_name );
        $message .= sprintf( "There's a new reply in the topic \"%s\" on the %s boards.\n\n", $topic_title, $site_name );
        $message .= sprintf( "View the topic: %s\n\n", $topic_url );
        $message .= "You are receiving this because you subscribed to notifications for this topic.\n";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

        wp_mail( $user->user_email, $subject, $message, $headers );
    }

    /**
     * Record the notification send time.
     */
    private static function record_sent( $user_id, $topic_id ) {
        global $wpdb;

        $table = FHB_Activator::get_notifications_table();
        $now   = current_time( 'mysql', true );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (user_id, topic_id, last_sent)
             VALUES (%d, %d, %s)
             ON DUPLICATE KEY UPDATE last_sent = %s",
            $user_id,
            $topic_id,
            $now,
            $now
        ) );
    }

    /**
     * Find the URL of the page containing the [fh_boards] shortcode.
     */
    private static function get_board_page_url() {
        static $url = null;
        if ( $url !== null ) {
            return $url;
        }

        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page'
             AND post_status = 'publish'
             AND post_content LIKE '%[fh_boards]%'
             LIMIT 1"
        );

        $url = $page_id ? get_permalink( $page_id ) : '';
        return $url;
    }

    /**
     * Send a manual notification to all subscribers of a topic (admin use).
     */
    public static function send_manual_notification( $topic_id ) {
        $topic = get_post( $topic_id );
        if ( ! $topic || 'fhb_topic' !== $topic->post_type ) {
            return false;
        }

        $subscribers = get_post_meta( $topic_id, '_fhb_subscribers', true );
        if ( ! is_array( $subscribers ) || empty( $subscribers ) ) {
            return false;
        }

        $sent = 0;
        foreach ( $subscribers as $user_id ) {
            if ( get_user_meta( $user_id, 'fhb_email_notifications', true ) === '1' ) {
                self::send_email( $user_id, $topic_id, $topic );
                self::record_sent( $user_id, $topic_id );
                $sent++;
            }
        }

        return $sent;
    }
}
