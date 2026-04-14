<?php
/**
 * FHB_Shortcode – [fh_boards] shortcode handler.
 *
 * Routing (4 levels):
 *   /fh-boards/                        → Subject list
 *   /fh-boards/?fhb_subject=123        → Topics in that subject
 *   /fh-boards/?fhb_topic_cat=456      → Threads in that topic
 *   /fh-boards/?fhb_topic=789          → Single thread (conversation)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FHB_Shortcode {

    public static function init() {
        add_shortcode( 'fh_boards', array( __CLASS__, 'render' ) );
    }

    public static function render( $atts ) {
        ob_start();

        if ( ! is_user_logged_in() ) {
            include FHB_PLUGIN_DIR . 'templates/login-required.php';
            return ob_get_clean();
        }

        $topic_id     = isset( $_GET['fhb_topic'] ) ? absint( $_GET['fhb_topic'] ) : 0;
        $topic_cat_id = isset( $_GET['fhb_topic_cat'] ) ? absint( $_GET['fhb_topic_cat'] ) : 0;
        $subject_id   = isset( $_GET['fhb_subject'] ) ? absint( $_GET['fhb_subject'] ) : 0;

        if ( $topic_id ) {
            self::render_single_thread( $topic_id );
        } elseif ( $topic_cat_id ) {
            self::render_thread_list( $topic_cat_id );
        } elseif ( $subject_id ) {
            self::render_topic_list( $subject_id );
        } else {
            self::render_subject_list();
        }

        return ob_get_clean();
    }

    /** Level 1: Subject list (main board page). */
    private static function render_subject_list() {
        $subjects = get_posts( array(
            'post_type'      => FHB_Constants::POST_TYPE_SUBJECT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        include FHB_PLUGIN_DIR . 'templates/subject-list.php';
    }

    /** Level 2: Topics within a subject. */
    private static function render_topic_list( $subject_id ) {
        $subject = get_post( $subject_id );

        if ( ! $subject || FHB_Constants::POST_TYPE_SUBJECT !== $subject->post_type || 'publish' !== $subject->post_status ) {
            echo '<p class="fhb-error">Subject not found.</p>';
            return;
        }

        $topic_cats = get_posts( array(
            'post_type'      => FHB_Constants::POST_TYPE_TOPIC_CAT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_key'       => FHB_Constants::META_SUBJECT_ID,
            'meta_value'     => $subject_id,
        ) );

        include FHB_PLUGIN_DIR . 'templates/topic-list.php';
    }

    /** Level 3: Threads within a topic. */
    private static function render_thread_list( $topic_cat_id ) {
        $topic_cat = get_post( $topic_cat_id );

        if ( ! $topic_cat || FHB_Constants::POST_TYPE_TOPIC_CAT !== $topic_cat->post_type || 'publish' !== $topic_cat->post_status ) {
            echo '<p class="fhb-error">Topic not found.</p>';
            return;
        }

        $subject_id = get_post_meta( $topic_cat_id, FHB_Constants::META_SUBJECT_ID, true );
        $subject    = $subject_id ? get_post( $subject_id ) : null;
        $paged      = isset( $_GET['fhb_paged'] ) ? absint( $_GET['fhb_paged'] ) : 1;

        $threads = new WP_Query( array(
            'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'meta_key'       => FHB_Constants::META_LAST_ACTIVITY,
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'   => FHB_Constants::META_TOPIC_CAT_ID,
                    'value' => $topic_cat_id,
                ),
            ),
        ) );

        include FHB_PLUGIN_DIR . 'templates/thread-list.php';
    }

    /** Level 4: Single thread with replies. */
    private static function render_single_thread( $topic_id ) {
        $topic = get_post( $topic_id );

        if ( ! $topic || FHB_Constants::POST_TYPE_TOPIC !== $topic->post_type || 'publish' !== $topic->post_status ) {
            echo '<p class="fhb-error">Thread not found.</p>';
            return;
        }

        self::record_visit( $topic_id );

        $replies = new WP_Query( array(
            'post_type'      => FHB_Constants::POST_TYPE_REPLY,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => FHB_Constants::META_TOPIC_ID,
            'meta_value'     => $topic_id,
            'orderby'        => 'date',
            'order'          => 'ASC',
        ) );

        $user_id       = get_current_user_id();
        $is_subscribed = self::is_subscribed( $user_id, $topic_id );
        $notifs_on     = get_user_meta( $user_id, FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS, true ) === '1';
        $is_closed     = FHB_Constants::is_topic_closed( $topic_id );

        include FHB_PLUGIN_DIR . 'templates/single-topic.php';
    }

    /** Record a thread visit for the current user. */
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
            $user_id, $topic_id, $now, $now
        ) );
    }

    /** Check if a user is subscribed to a thread. */
    public static function is_subscribed( $user_id, $topic_id ) {
        $subscribers = FHB_Constants::get_subscribers( $topic_id );
        return in_array( $user_id, $subscribers, true );
    }
}
