<?php
/**
 * FHB_Constants – Centralised constants for FH Boards.
 *
 * Single source of truth for post types, meta keys, capabilities,
 * and other magic strings used across the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Constants {

    // Post types.
    const POST_TYPE_SUBJECT   = 'fhb_subject';
    const POST_TYPE_TOPIC_CAT = 'fhb_topic_cat';
    const POST_TYPE_TOPIC     = 'fhb_topic';
    const POST_TYPE_REPLY     = 'fhb_reply';

    // Post meta keys.
    const META_SUBJECT_ID           = '_fhb_subject_id';
    const META_TOPIC_CAT_ID         = '_fhb_topic_cat_id';
    const META_REPLY_COUNT          = '_fhb_reply_count';
    const META_TOPIC_COUNT          = '_fhb_topic_count';
    const META_THREAD_COUNT         = '_fhb_thread_count';
    const META_LAST_ACTIVITY        = '_fhb_last_activity';
    const META_SUBSCRIBERS          = '_fhb_subscribers';
    const META_CLOSED               = '_fhb_closed';
    const META_TOPIC_ID             = '_fhb_topic_id';
    const META_PENDING_NOTIFICATION = '_fhb_pending_notification';

    // User meta keys.
    const USERMETA_EMAIL_NOTIFICATIONS = 'fhb_email_notifications';

    // Nonce.
    const NONCE_ACTION = 'fhb_nonce';

    // Capability.
    const ADMIN_CAP = 'manage_options';

    // Cron hook.
    const CRON_HOOK = 'fhb_process_notifications';

    // DB table suffixes (appended to $wpdb->prefix).
    const TABLE_THREAD_VISITS = 'fhb_thread_visits';
    const TABLE_NOTIFICATIONS = 'fhb_notifications';

    // Notification throttle (days).
    const NOTIFICATION_THROTTLE_DAYS = 3;

    /* ------------------------------------------------------------------
     * Convenience helpers (used in 6+ places each).
     * ----------------------------------------------------------------*/

    /**
     * Check whether a topic is closed.
     */
    public static function is_topic_closed( $topic_id ) {
        return get_post_meta( $topic_id, self::META_CLOSED, true ) === '1';
    }

    /**
     * Get the subscriber list for a topic (always returns an array).
     */
    public static function get_subscribers( $topic_id ) {
        $subs = get_post_meta( $topic_id, self::META_SUBSCRIBERS, true );
        return is_array( $subs ) ? $subs : array();
    }

    /**
     * Check whether the current user can edit a given post.
     */
    public static function user_can_edit( $post ) {
        if ( is_int( $post ) ) {
            $post = get_post( $post );
        }
        if ( ! $post ) {
            return false;
        }
        return (int) $post->post_author === get_current_user_id()
            || current_user_can( self::ADMIN_CAP );
    }
}
