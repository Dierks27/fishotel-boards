<?php
/**
 * Admin view – Threads list (user-created discussions).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

$paged   = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$threads = new WP_Query( array(
    'post_type'      => FHB_Constants::POST_TYPE_TOPIC,
    'post_status'    => 'any',
    'posts_per_page' => 20,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
) );
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards &mdash; Threads</h1>

    <?php if ( $message === 'thread_deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Thread deleted.</p></div>
    <?php elseif ( $message === 'thread_closed' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Thread closed.</p></div>
    <?php elseif ( $message === 'thread_reopened' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Thread reopened.</p></div>
    <?php endif; ?>

    <?php if ( $threads->have_posts() ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th style="width:25%;">Title</th>
                    <th style="width:15%;">Topic</th>
                    <th style="width:10%;">Author</th>
                    <th style="width:10%;">Replies</th>
                    <th style="width:8%;">Status</th>
                    <th style="width:12%;">Date</th>
                    <th style="width:15%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ( $threads->have_posts() ) : $threads->the_post(); ?>
                    <?php
                    $thread_id   = get_the_ID();
                    $reply_count = absint( get_post_meta( $thread_id, FHB_Constants::META_REPLY_COUNT, true ) );
                    $is_closed   = FHB_Constants::is_topic_closed( $thread_id );
                    $tc_id       = get_post_meta( $thread_id, FHB_Constants::META_TOPIC_CAT_ID, true );
                    $topic_name  = $tc_id ? get_the_title( $tc_id ) : '(none)';
                    ?>
                    <tr>
                        <td><?php echo esc_html( $thread_id ); ?></td>
                        <td><?php echo esc_html( get_the_title() ); ?></td>
                        <td><?php echo esc_html( $topic_name ); ?></td>
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
                                    <?php wp_nonce_field( 'fhb_admin_reopen_thread', 'fhb_admin_nonce' ); ?>
                                    <input type="hidden" name="fhb_admin_action" value="reopen_thread" />
                                    <input type="hidden" name="thread_id" value="<?php echo esc_attr( $thread_id ); ?>" />
                                    <button type="submit" class="button button-small">Reopen</button>
                                </form>
                            <?php else : ?>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'fhb_admin_close_thread', 'fhb_admin_nonce' ); ?>
                                    <input type="hidden" name="fhb_admin_action" value="close_thread" />
                                    <input type="hidden" name="thread_id" value="<?php echo esc_attr( $thread_id ); ?>" />
                                    <button type="submit" class="button button-small">Close</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this thread and all its replies?');">
                                <?php wp_nonce_field( 'fhb_admin_delete_thread', 'fhb_admin_nonce' ); ?>
                                <input type="hidden" name="fhb_admin_action" value="delete_thread" />
                                <input type="hidden" name="thread_id" value="<?php echo esc_attr( $thread_id ); ?>" />
                                <button type="submit" class="button button-small button-link-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php if ( $threads->max_num_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo paginate_links( array(
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $threads->max_num_pages,
                    ) ); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p>No threads found.</p>
    <?php endif; ?>
</div>
