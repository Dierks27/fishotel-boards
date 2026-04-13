<?php
/**
 * Admin view – Replies list.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$filter_topic = isset( $_GET['topic_id'] ) ? absint( $_GET['topic_id'] ) : 0;

$args = array(
    'post_type'      => 'fhb_reply',
    'post_status'    => 'any',
    'posts_per_page' => 30,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
);

if ( $filter_topic ) {
    $args['meta_key']   = '_fhb_topic_id';
    $args['meta_value'] = $filter_topic;
}

$replies = new WP_Query( $args );
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards &mdash; Replies</h1>

    <?php if ( $message === 'reply_deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Reply deleted.</p></div>
    <?php endif; ?>

    <?php if ( $filter_topic ) : ?>
        <p>
            Showing replies for topic #<?php echo esc_html( $filter_topic ); ?>:
            <strong><?php echo esc_html( get_the_title( $filter_topic ) ); ?></strong>
            &mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=fh-boards-replies' ) ); ?>">Show all</a>
        </p>
    <?php endif; ?>

    <?php if ( $replies->have_posts() ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th style="width:25%;">Topic</th>
                    <th style="width:30%;">Content</th>
                    <th style="width:15%;">Author</th>
                    <th style="width:15%;">Date</th>
                    <th style="width:10%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ( $replies->have_posts() ) : $replies->the_post(); ?>
                    <?php
                    $reply_id   = get_the_ID();
                    $topic_id   = get_post_meta( $reply_id, '_fhb_topic_id', true );
                    $topic_title = $topic_id ? get_the_title( $topic_id ) : '(unknown)';
                    $excerpt    = wp_trim_words( get_the_content(), 20, '&hellip;' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $reply_id ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=fh-boards-replies&topic_id=' . $topic_id ) ); ?>">
                                <?php echo esc_html( $topic_title ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( $excerpt ); ?></td>
                        <td><?php echo esc_html( get_the_author() ); ?></td>
                        <td><?php echo esc_html( get_the_date() ); ?></td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this reply?');">
                                <?php wp_nonce_field( 'fhb_admin_delete_reply', 'fhb_admin_nonce' ); ?>
                                <input type="hidden" name="fhb_admin_action" value="delete_reply" />
                                <input type="hidden" name="reply_id" value="<?php echo esc_attr( $reply_id ); ?>" />
                                <button type="submit" class="button button-small button-link-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <?php if ( $replies->max_num_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( array(
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $replies->max_num_pages,
                    ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p>No replies found.</p>
    <?php endif; ?>
</div>
