<?php
/**
 * Admin view – Manual notification trigger.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
$count   = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;

// Get all topics that have subscribers.
$topics = get_posts( array(
    'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
) );
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards &mdash; Send Notification</h1>

    <?php if ( $message === 'notification_sent' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Notification sent to <?php echo esc_html( $count ); ?> subscriber(s).</p>
        </div>
    <?php endif; ?>

    <p>Manually send a notification email to all subscribers of a topic. This bypasses the normal throttling rules.</p>

    <?php if ( ! empty( $topics ) ) : ?>
        <form method="post" class="fhb-admin-notify-form">
            <?php wp_nonce_field( 'fhb_admin_send_notification', 'fhb_admin_nonce' ); ?>
            <input type="hidden" name="fhb_admin_action" value="send_notification" />
            <table class="form-table">
                <tr>
                    <th><label for="fhb_notify_topic">Select Topic</label></th>
                    <td>
                        <select name="topic_id" id="fhb_notify_topic" required>
                            <option value="">-- Select a topic --</option>
                            <?php foreach ( $topics as $topic ) : ?>
                                <?php
                                $subscribers = FHB_Constants::get_subscribers( $topic->ID );
                                $sub_count   = count( $subscribers );
                                ?>
                                <option value="<?php echo esc_attr( $topic->ID ); ?>">
                                    <?php echo esc_html( $topic->post_title ); ?> (<?php echo $sub_count; ?> subscriber<?php echo $sub_count !== 1 ? 's' : ''; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Send Notification Now', 'primary', 'submit', true, array( 'onclick' => "return confirm('Send notification to all subscribers of this topic?');" ) ); ?>
        </form>
    <?php else : ?>
        <p>No topics found.</p>
    <?php endif; ?>
</div>
