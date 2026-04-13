<?php
/**
 * Admin view – Topics list.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$topics = new WP_Query( array(
    'post_type'      => 'fhb_topic',
    'post_status'    => 'any',
    'posts_per_page' => 20,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards &mdash; Topics</h1>

    <?php if ( $message === 'topic_deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Topic deleted.</p></div>
    <?php elseif ( $message === 'topic_closed' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Topic closed.</p></div>
    <?php elseif ( $message === 'topic_reopened' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Topic reopened.</p></div>
    <?php endif; ?>

    <?php if ( $topics->have_posts() ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th style="width:35%;">Title</th>
                    <th style="width:15%;">Author</th>
                    <th style="width:10%;">Replies</th>
                    <th style="width:10%;">Status</th>
                    <th style="width:15%;">Date</th>
                    <th style="width:10%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ( $topics->have_posts() ) : $topics->the_post(); ?>
                    <?php
                    $topic_id    = get_the_ID();
                    $reply_count = absint( get_post_meta( $topic_id, '_fhb_reply_count', true ) );
                    $is_closed   = get_post_meta( $topic_id, '_fhb_closed', true ) === '1';
                    ?>
                    <tr>
                        <td><?php echo esc_html( $topic_id ); ?></td>
                        <td><?php echo esc_html( get_the_title() ); ?></td>
                        <td><?php echo esc_html( get_the_author() ); ?></td>
                        <td><?php echo esc_html( $reply_count ); ?></td>
                        <td>
                            <?php if ( $is_closed ) : ?>
                                <span style="color:#dc3232;">Closed</span>
                            <?php else : ?>
                                <span style="color:#46b450;">Open</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( get_the_date() ); ?></td>
                        <td class="fhb-admin-actions">
                            <?php if ( $is_closed ) : ?>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'fhb_admin_reopen_topic', 'fhb_admin_nonce' ); ?>
                                    <input type="hidden" name="fhb_admin_action" value="reopen_topic" />
                                    <input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic_id ); ?>" />
                                    <button type="submit" class="button button-small">Reopen</button>
                                </form>
                            <?php else : ?>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'fhb_admin_close_topic', 'fhb_admin_nonce' ); ?>
                                    <input type="hidden" name="fhb_admin_action" value="close_topic" />
                                    <input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic_id ); ?>" />
                                    <button type="submit" class="button button-small">Close</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this topic and all its replies?');">
                                <?php wp_nonce_field( 'fhb_admin_delete_topic', 'fhb_admin_nonce' ); ?>
                                <input type="hidden" name="fhb_admin_action" value="delete_topic" />
                                <input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic_id ); ?>" />
                                <button type="submit" class="button button-small button-link-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php if ( $topics->max_num_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( array(
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $topics->max_num_pages,
                    ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p>No topics found.</p>
    <?php endif; ?>
</div>
