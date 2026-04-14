<?php
/**
 * Admin view – Subscribers per thread.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get all topics that have subscribers.
$topics = get_posts( array(
    'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards &mdash; Subscribers</h1>

    <?php if ( ! empty( $topics ) ) : ?>
        <?php foreach ( $topics as $topic ) : ?>
            <?php
            $subscribers = FHB_Constants::get_subscribers( $topic->ID );
            if ( empty( $subscribers ) ) {
                continue;
            }
            ?>
            <div class="fhb-admin-subscriber-block">
                <h3><?php echo esc_html( $topic->post_title ); ?> <small>(#<?php echo esc_html( $topic->ID ); ?>)</small></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:10%;">User ID</th>
                            <th style="width:30%;">Name</th>
                            <th style="width:30%;">Email</th>
                            <th style="width:15%;">Notifs Enabled</th>
                            <th style="width:15%;">Subscribed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $subscribers as $user_id ) : ?>
                            <?php
                            $user = get_userdata( $user_id );
                            if ( ! $user ) {
                                continue;
                            }
                            $notifs_on = get_user_meta( $user_id, FHB_Constants::USERMETA_EMAIL_NOTIFICATIONS, true ) === '1';
                            ?>
                            <tr>
                                <td><?php echo esc_html( $user_id ); ?></td>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td>
                                    <?php if ( $notifs_on ) : ?>
                                        <span style="color:#46b450;">Yes</span>
                                    <?php else : ?>
                                        <span style="color:#dc3232;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>Yes</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p>No topics with subscribers found.</p>
    <?php endif; ?>
</div>
