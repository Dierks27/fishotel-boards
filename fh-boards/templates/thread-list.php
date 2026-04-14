<?php
/**
 * Template – Thread listing within a topic.
 *
 * Variables available: $topic_cat (WP_Post), $subject (WP_Post|null),
 *                      $threads (WP_Query), $paged (int).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url    = remove_query_arg( array( 'fhb_subject', 'fhb_topic_cat', 'fhb_topic', 'fhb_paged' ) );
$subject_id  = get_post_meta( $topic_cat->ID, FHB_Constants::META_SUBJECT_ID, true );
$back_url    = $subject_id ? add_query_arg( 'fhb_subject', $subject_id, $base_url ) : $base_url;
$back_label  = $subject ? $subject->post_title : 'Boards';
?>
<div class="fhb-board-wrap">
    <div class="fhb-back-link">
        <a href="<?php echo esc_url( $back_url ); ?>">&laquo; Back to <?php echo esc_html( $back_label ); ?></a>
    </div>

    <div class="fhb-board-header">
        <h2><?php echo esc_html( $topic_cat->post_title ); ?></h2>
        <button class="fhb-btn fhb-new-topic-toggle" type="button">New Thread</button>
    </div>
    <?php if ( $topic_cat->post_content ) : ?>
        <p class="fhb-subject-desc"><?php echo esc_html( $topic_cat->post_content ); ?></p>
    <?php endif; ?>

    <div class="fhb-new-topic-form" style="display:none;">
        <?php include FHB_PLUGIN_DIR . 'templates/forms/new-topic.php'; ?>
    </div>

    <div class="fhb-search-wrap">
        <input type="text" class="fhb-search-input" placeholder="Search threads and replies..." autocomplete="off" />
        <button type="button" class="fhb-search-clear" style="display:none;">&times;</button>
        <div class="fhb-search-loading" style="display:none;">Searching...</div>
    </div>
    <div class="fhb-search-results" style="display:none;"></div>

    <?php if ( $threads->have_posts() ) : ?>
        <div class="fhb-topic-list">
            <?php while ( $threads->have_posts() ) : $threads->the_post(); ?>
                <?php
                $thread_id     = get_the_ID();
                $reply_count   = absint( get_post_meta( $thread_id, FHB_Constants::META_REPLY_COUNT, true ) );
                $last_activity = get_post_meta( $thread_id, FHB_Constants::META_LAST_ACTIVITY, true );
                $is_closed     = FHB_Constants::is_topic_closed( $thread_id );
                $author        = get_the_author();
                $thread_url    = add_query_arg( 'fhb_topic', $thread_id, remove_query_arg( array( 'fhb_topic', 'fhb_paged' ) ) );
                ?>
                <div class="fhb-topic-row<?php echo $is_closed ? ' fhb-closed' : ''; ?>">
                    <div class="fhb-topic-title">
                        <a href="<?php echo esc_url( $thread_url ); ?>">
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

        <?php if ( $threads->max_num_pages > 1 ) : ?>
            <div class="fhb-pagination">
                <?php if ( $paged > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'fhb_paged', $paged - 1 ) ); ?>" class="fhb-btn">&laquo; Previous</a>
                <?php endif; ?>
                <?php if ( $paged < $threads->max_num_pages ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'fhb_paged', $paged + 1 ) ); ?>" class="fhb-btn">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p class="fhb-no-topics">No threads yet. Be the first to start a conversation!</p>
    <?php endif; ?>
</div>
