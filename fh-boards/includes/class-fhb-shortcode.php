<?php
/**
 * FHB_Shortcode – [fh_boards] shortcode handler.
 *
 * Routes to board list, single topic, or login-required template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Shortcode {

    public static function init() {
        add_shortcode( 'fh_boards', array( __CLASS__, 'render' ) );
    }

    /**
     * Main shortcode callback.
     */
    public static function render( $atts ) {
        ob_start();

        if ( ! is_user_logged_in() ) {
            include FHB_PLUGIN_DIR . 'templates/login-required.php';
            return ob_get_clean();
        }

        $topic_id = isset( $_GET['fhb_topic'] ) ? absint( $_GET['fhb_topic'] ) : 0;

        if ( $topic_id ) {
            self::render_single_topic( $topic_id );
        } else {
            self::render_board_list();
        }

        return ob_get_clean();
    }

    /**
     * Render the board list (all topics).
     */
    private static function render_board_list() {
        $paged = isset( $_GET['fhb_paged'] ) ? absint( $_GET['fhb_paged'] ) : 1;

        $topics = new WP_Query( array(
            'post_type'      => 'fhb_topic',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'meta_key'       => '_fhb_last_activity',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        ) );

        include FHB_PLUGIN_DIR . 'templates/board-list.php';
    }

    /**
     * Render a single topic with its replies.
     */
    private static function render_single_topic( $topic_id ) {
        $topic = get_post( $topic_id );

        if ( ! $topic || 'fhb_topic' !== $topic->post_type || 'publish' !== $topic->post_status ) {
            echo '<p class="fhb-error">Topic not found.</p>';
            return;
        }

        // Record this visit for the current user.
        self::record_visit( $topic_id );

        $replies = new WP_Query( array(
            'post_type'      => 'fhb_reply',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_fhb_topic_id',
            'meta_value'     => $topic_id,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );

        // Check subscription status.
        $user_id       = get_current_user_id();
        $is_subscribed = self::is_subscribed( $user_id, $topic_id );
        $notifs_on     = get_user_meta( $user_id, 'fhb_email_notifications', true ) === '1';
        $is_closed     = get_post_meta( $topic_id, '_fhb_closed', true ) === '1';

        include FHB_PLUGIN_DIR . 'templates/single-topic.php';
    }

    /**
     * Record a thread visit for the current user.
     */
    private static function record_visit( $topic_id ) {
        global $wpdb;

        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        $table = FHB_Activator::get_thread_visits_table();
        $now   = current_time( 'mysql', true );

        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (user_id, topic_id, last_visit)
             VALUES (%d, %d, %s)
             ON DUPLICATE KEY UPDATE last_visit = %s",
            $user_id,
            $topic_id,
            $now,
            $now
        ) );
    }

    /**
     * Check if a user is subscribed to a topic.
     */
    public static function is_subscribed( $user_id, $topic_id ) {
        $subscribers = get_post_meta( $topic_id, '_fhb_subscribers', true );
        if ( ! is_array( $subscribers ) ) {
            return false;
        }
        return in_array( $user_id, $subscribers, true );
    }
}
