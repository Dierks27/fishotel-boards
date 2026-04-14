<?php
/**
 * Template – Subject listing (main board page).
 *
 * Variables available: $subjects (array of WP_Post).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$base_url = remove_query_arg( array( 'fhb_subject', 'fhb_topic', 'fhb_paged' ) );
?>
<div class="fhb-board-wrap">
    <div class="fhb-board-header">
        <h2>FisHotel Boards</h2>
    </div>

    <div class="fhb-search-wrap">
        <input type="text" class="fhb-search-input" placeholder="Search topics and replies..." autocomplete="off" />
        <button type="button" class="fhb-search-clear" style="display:none;">&times;</button>
        <div class="fhb-search-loading" style="display:none;">Searching...</div>
    </div>
    <div class="fhb-search-results" style="display:none;"></div>

    <?php if ( ! empty( $subjects ) ) : ?>
        <div class="fhb-subject-list">
            <?php foreach ( $subjects as $subject ) : ?>
                <?php
                $topic_count   = absint( get_post_meta( $subject->ID, FHB_Constants::META_TOPIC_COUNT, true ) );
                $last_activity = get_post_meta( $subject->ID, FHB_Constants::META_LAST_ACTIVITY, true );
                $subject_url   = add_query_arg( 'fhb_subject', $subject->ID, $base_url );
                $description   = $subject->post_content;
                ?>
                <a href="<?php echo esc_url( $subject_url ); ?>" class="fhb-subject-card">
                    <div class="fhb-subject-title"><?php echo esc_html( $subject->post_title ); ?></div>
                    <?php if ( $description ) : ?>
                        <div class="fhb-subject-description"><?php echo esc_html( wp_trim_words( $description, 20, '&hellip;' ) ); ?></div>
                    <?php endif; ?>
                    <div class="fhb-subject-meta">
                        <span class="fhb-subject-topic-count"><?php echo $topic_count; ?> <?php echo $topic_count === 1 ? 'topic' : 'topics'; ?></span>
                        <?php if ( $last_activity ) : ?>
                            <span class="fhb-subject-activity">Last activity: <?php echo esc_html( human_time_diff( strtotime( $last_activity ), current_time( 'timestamp', true ) ) ); ?> ago</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p class="fhb-no-topics">No subjects yet. An admin needs to create some first.</p>
    <?php endif; ?>
</div>
