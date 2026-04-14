<?php
/**
 * Template – Topic listing within a subject.
 *
 * Variables available: $subject (WP_Post), $topics (WP_Query), $paged (int).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$board_url = remove_query_arg( array( 'fhb_subject', 'fhb_topic', 'fhb_paged' ) );
?>
<div class="fhb-board-wrap">
    <div class="fhb-back-link">
        <a href="<?php echo esc_url( $board_url ); ?>">&laquo; Back to Boards</a>
    </div>

    <div class="fhb-board-header">
        <h2><?php echo esc_html( $subject->post_title ); ?></h2>
        <button class="fhb-btn fhb-new-topic-toggle" type="button">New Topic</button>
    </div>
    <?php if ( $subject->post_content ) : ?>
        <p class="fhb-subject-desc"><?php echo esc_html( $subject->post_content ); ?></p>
    <?php endif; ?>

    <div class="fhb-new-topic-form" style="display:none;">
        <?php include FHB_PLUGIN_DIR . 'templates/forms/new-topic.php'; ?>
    </div>

    <div class="fhb-search-wrap">
        <input type="text" class="fhb-search-input" placeholder="Search topics and replies..." autocomplete="off" />
        <button type="button" class="fhb-search-clear" style="display:none;">&times;</button>
        <div class="fhb-search-loading" style="display:none;">Searching...</div>
    </div>
    <div class="fhb-search-results" style="display:none;"></div>

    <?php if ( $topics->have_posts() ) : ?>
        <div class="fhb-topic-list">
            <?php while ( $topics->have_posts() ) : $topics->the_post(); ?>
                <?php
                $topic_id      = get_the_ID();
                $reply_count   = absint( get_post_meta( $topic_id, FHB_Constants::META_REPLY_COUNT, true ) );
                $last_activity = get_post_meta( $topic_id, FHB_Constants::META_LAST_ACTIVITY, true );
                $is_closed     = FHB_Constants::is_topic_closed( $topic_id );
                $author        = get_the_author();
                $topic_url     = add_query_arg( 'fhb_topic', $topic_id, remove_query_arg( array( 'fhb_topic', 'fhb_paged' ) ) );
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
