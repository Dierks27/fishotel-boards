<?php
/**
 * Template – Topic listing within a subject.
 *
 * Variables available: $subject (WP_Post), $topic_cats (array of WP_Post).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = remove_query_arg( array( 'fhb_subject', 'fhb_topic_cat', 'fhb_topic', 'fhb_paged' ) );
?>
<div class="fhb-board-wrap">
    <div class="fhb-back-link">
        <a href="<?php echo esc_url( $base_url ); ?>">&laquo; Back to Boards</a>
    </div>

    <div class="fhb-board-header">
        <h2><?php echo esc_html( $subject->post_title ); ?></h2>
    </div>
    <?php if ( $subject->post_content ) : ?>
        <p class="fhb-subject-desc"><?php echo esc_html( $subject->post_content ); ?></p>
    <?php endif; ?>

    <div class="fhb-search-wrap">
        <input type="text" class="fhb-search-input" placeholder="Search threads and replies..." autocomplete="off" />
        <button type="button" class="fhb-search-clear" style="display:none;">&times;</button>
        <div class="fhb-search-loading" style="display:none;">Searching...</div>
    </div>
    <div class="fhb-search-results" style="display:none;"></div>

    <?php if ( ! empty( $topic_cats ) ) : ?>
        <div class="fhb-subject-list">
            <?php foreach ( $topic_cats as $tc ) : ?>
                <?php
                $thread_count  = absint( get_post_meta( $tc->ID, FHB_Constants::META_THREAD_COUNT, true ) );
                $last_activity = get_post_meta( $tc->ID, FHB_Constants::META_LAST_ACTIVITY, true );
                $tc_url        = add_query_arg( 'fhb_topic_cat', $tc->ID, $base_url );
                ?>
                <a href="<?php echo esc_url( $tc_url ); ?>" class="fhb-subject-card">
                    <div class="fhb-subject-title"><?php echo esc_html( $tc->post_title ); ?></div>
                    <?php if ( $tc->post_content ) : ?>
                        <div class="fhb-subject-description"><?php echo esc_html( wp_trim_words( $tc->post_content, 20, '&hellip;' ) ); ?></div>
                    <?php endif; ?>
                    <div class="fhb-subject-meta">
                        <span><?php echo $thread_count; ?> <?php echo $thread_count === 1 ? 'thread' : 'threads'; ?></span>
                        <?php if ( $last_activity ) : ?>
                            <span>Last activity: <?php echo esc_html( human_time_diff( strtotime( $last_activity ), current_time( 'timestamp', true ) ) ); ?> ago</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p class="fhb-no-topics">No topics yet. An admin needs to create some first.</p>
    <?php endif; ?>
</div>
