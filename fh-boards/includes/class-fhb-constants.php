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
    const META_SORT_ORDER           = '_fhb_sort_order';
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

    // Option: capability or role slug required to use the front-end boards.
    // Blank (the default) keeps today's behaviour: any logged-in user.
    const OPTION_REQUIRED_CAP = 'fhb_required_capability';

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

    /**
     * Check whether a user may view and post on the front-end boards.
     *
     * WHY (boards-smtp-wpit-20): the forum used to be open to every logged-in
     * account, i.e. every WooCommerce customer. Jeff can opt in to restricting
     * it via FH Boards > Settings > Tester Access. The value may be a
     * capability (e.g. 'edit_posts') or a role slug (e.g. 'fhb_beta_tester').
     * With the setting blank this returns true for any logged-in user,
     * exactly as before. Administrators are never locked out.
     *
     * @param int $user_id Optional user ID; defaults to the current user.
     * @return bool
     */
    public static function user_can_access( $user_id = 0 ) {
        $user_id  = $user_id ? (int) $user_id : get_current_user_id();
        $required = trim( (string) get_option( self::OPTION_REQUIRED_CAP, '' ) );
        $allowed  = false;

        if ( $user_id ) {
            if ( '' === $required ) {
                $allowed = true;
            } else {
                $user    = get_userdata( $user_id );
                $allowed = $user && (
                    user_can( $user, $required )
                    || in_array( $required, (array) $user->roles, true )
                    || user_can( $user, self::ADMIN_CAP )
                );
            }
        }

        /**
         * Filter whether a user may access the boards.
         *
         * @param bool   $allowed  Computed result.
         * @param int    $user_id  User being checked (0 = not logged in).
         * @param string $required Configured capability/role slug ('' = none).
         */
        return (bool) apply_filters( 'fhb_user_can_access', $allowed, $user_id, $required );
    }
}
