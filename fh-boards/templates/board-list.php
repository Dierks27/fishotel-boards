<?php
/**
 * Template – Board listing (all topics).
 *
 * Variables available: $topics (WP_Query), $paged (int).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="fhb-board-wrap">
    <div class="fhb-board-header">
        <h2>FisHotel Boards</h2>
        <button class="fhb-btn fhb-new-topic-toggle" type="button">New Topic</button>
    </div>

    <div class="fhb-new-topic-form" style="display:none;">
        <?php include FHB_PLUGIN_DIR . 'templates/forms/new-topic.php'; ?>
    </div>

    <?php if ( $topics->have_posts() ) : ?>
        <div class="fhb-topic-list">
            <?php while ( $topics->have_posts() ) : $topics->the_post(); ?>
                <?php
                $topic_id     = get_the_ID();
                $reply_count  = absint( get_post_meta( $topic_id, '_fhb_reply_count', true ) );
                $last_activity = get_post_meta( $topic_id, '_fhb_last_activity', true );
                $is_closed    = get_post_meta( $topic_id, '_fhb_closed', true ) === '1';
                $author       = get_the_author();
                $board_url    = remove_query_arg( 'fhb_topic' );
                $topic_url    = add_query_arg( 'fhb_topic', $topic_id, $board_url );
                ?>
                <div class="fhb-topic-row<?php echo $is_closed ? ' fhb-closed' : ''; ?>">
                    <div class="fhb-topic-title">
                        <a href="<?php echo esc_url( $topic_url ); ?>">
                            <?php echo esc_html( get_the_title() ); ?>
                        </a>
                        <?php if ( $is_closed ) : ?>
                            <span class="fhb-badge fhb-badge-closed">Closed</span>
                        <?php endif; ?>
                    </div>
                    <div class="fhb-topic-meta">
                        <span class="fhb-topic-author">by <?php echo esc_html( $author ); ?></span>
                        <span class="fhb-topic-replies"><?php echo $reply_count; ?> <?php echo $reply_count === 1 ? 'reply' : 'replies'; ?></span>
                        <?php if ( $last_activity ) : ?>
                            <span class="fhb-topic-activity">Last activity: <?php echo esc_html( human_time_diff( strtotime( $last_activity ), current_time( 'timestamp', true ) ) ); ?> ago</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <?php if ( $topics->max_num_pages > 1 ) : ?>
            <div class="fhb-pagination">
                <?php if ( $paged > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'fhb_paged', $paged - 1 ) ); ?>" class="fhb-btn">&laquo; Previous</a>
                <?php endif; ?>
                <?php if ( $paged < $topics->max_num_pages ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'fhb_paged', $paged + 1 ) ); ?>" class="fhb-btn">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p class="fhb-no-topics">No topics yet. Be the first to start a conversation!</p>
    <?php endif; ?>
</div>
