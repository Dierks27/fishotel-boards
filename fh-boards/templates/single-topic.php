<?php
/**
 * Template – Single topic view with replies.
 *
 * Variables available: $topic (WP_Post), $replies (WP_Query),
 *                      $is_subscribed (bool), $notifs_on (bool), $is_closed (bool).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$board_url = remove_query_arg( array( 'fhb_topic', 'fhb_paged' ) );
?>
<div class="fhb-single-topic-wrap">
    <div class="fhb-back-link">
        <a href="<?php echo esc_url( $board_url ); ?>">&laquo; Back to Boards</a>
    </div>

    <div class="fhb-topic-header">
        <h2><?php echo esc_html( $topic->post_title ); ?></h2>
        <?php if ( $is_closed ) : ?>
            <span class="fhb-badge fhb-badge-closed">Closed</span>
        <?php endif; ?>
    </div>

    <!-- Topic meta bar -->
    <?php
    $op_author_name = get_the_author_meta( 'display_name', $topic->post_author );
    $reply_count    = absint( get_post_meta( $topic->ID, '_fhb_reply_count', true ) );
    $status_label   = $is_closed ? '<span class="fhb-status-closed">Closed</span>' : '<span class="fhb-status-open">Open</span>';
    ?>
    <div class="fhb-topic-meta-bar">
        by <?php echo esc_html( $op_author_name ); ?>
        &middot; <?php echo esc_html( get_the_date( '', $topic ) ); ?>
        &middot; <?php echo $reply_count; ?> <?php echo $reply_count === 1 ? 'reply' : 'replies'; ?>
        &middot; <?php echo $status_label; ?>
    </div>

    <!-- Original post -->
    <div class="fhb-post fhb-original-post" data-post-id="<?php echo esc_attr( $topic->ID ); ?>">
        <div class="fhb-post-author">
            <span class="fhb-fish-icon">&#x1F41F;</span>
            <strong><?php echo esc_html( $op_author_name ); ?></strong>
            <span class="fhb-post-date"><?php echo esc_html( get_the_date( '', $topic ) ); ?> at <?php echo esc_html( get_the_time( '', $topic ) ); ?></span>
        </div>
        <div class="fhb-post-content">
            <?php echo wp_kses_post( wpautop( $topic->post_content ) ); ?>
        </div>
        <?php
        $op_created  = get_the_date( 'U', $topic );
        $op_modified = get_post_modified_time( 'U', false, $topic );
        if ( $op_modified && ( $op_modified - $op_created ) > 60 ) :
        ?>
            <span class="fhb-edited-stamp">(edited <?php echo esc_html( get_the_modified_date( '', $topic ) ); ?>)</span>
        <?php endif; ?>
        <?php if ( (int) $topic->post_author === get_current_user_id() || current_user_can( 'manage_options' ) ) : ?>
            <div class="fhb-post-actions"><button type="button" class="fhb-edit-btn">Edit</button></div>
        <?php endif; ?>
    </div>

    <!-- Replies -->
    <?php if ( $replies->have_posts() ) : ?>
        <div class="fhb-replies">
            <?php while ( $replies->have_posts() ) : $replies->the_post(); ?>
                <div class="fhb-post fhb-reply-post" data-post-id="<?php echo esc_attr( get_the_ID() ); ?>">
                    <div class="fhb-post-author">
                        <span class="fhb-fish-icon">&#x1F41F;</span>
                        <strong><?php echo esc_html( get_the_author() ); ?></strong>
                        <span class="fhb-post-date"><?php echo esc_html( get_the_date() ); ?> at <?php echo esc_html( get_the_time() ); ?></span>
                    </div>
                    <div class="fhb-post-content">
                        <?php echo wp_kses_post( wpautop( get_the_content() ) ); ?>
                    </div>
                    <?php
                    $r_created  = get_the_date( 'U' );
                    $r_modified = get_the_modified_date( 'U' );
                    if ( $r_modified && ( $r_modified - $r_created ) > 60 ) :
                    ?>
                        <span class="fhb-edited-stamp">(edited <?php echo esc_html( get_the_modified_date() ); ?>)</span>
                    <?php endif; ?>
                    <?php if ( (int) get_the_author_meta( 'ID' ) === get_current_user_id() || current_user_can( 'manage_options' ) ) : ?>
                        <div class="fhb-post-actions"><button type="button" class="fhb-edit-btn">Edit</button></div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
            <?php wp_reset_postdata(); ?>
        </div>
    <?php endif; ?>

    <!-- Subscribe button -->
    <div class="fhb-subscribe-area" data-topic-id="<?php echo esc_attr( $topic->ID ); ?>">
        <?php if ( $is_subscribed ) : ?>
            <button class="fhb-btn fhb-btn-subscribed fhb-subscribe-btn" data-action="unsubscribe">Unsubscribe from Notifications</button>
        <?php else : ?>
            <button class="fhb-btn fhb-subscribe-btn" data-action="subscribe">Get Notifications</button>
        <?php endif; ?>
        <div class="fhb-subscribe-message" style="display:none;"></div>
    </div>

    <!-- Reply form -->
    <?php if ( ! $is_closed ) : ?>
        <div class="fhb-reply-form-wrap">
            <?php include FHB_PLUGIN_DIR . 'templates/forms/new-reply.php'; ?>
        </div>
    <?php else : ?>
        <p class="fhb-closed-notice">This topic is closed. No new replies can be posted.</p>
    <?php endif; ?>
</div>
