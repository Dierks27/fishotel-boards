<?php
/**
 * FHB Helpers – Reusable template helper functions.
 *
 * These are procedural functions used by frontend templates to avoid
 * duplicating author display, edited-stamp, and permission-check logic.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the post author block (fish icon, name, date/time).
 *
 * @param string $author_name Display name of the author.
 * @param string $date        Formatted date string.
 * @param string $time        Formatted time string.
 */
function fhb_render_post_author( $author_name, $date, $time ) {
    ?>
    <div class="fhb-post-author">
        <span class="fhb-fish-icon">&#x1F41F;</span>
        <strong><?php echo esc_html( $author_name ); ?></strong>
        <span class="fhb-post-date"><?php echo esc_html( $date ); ?> at <?php echo esc_html( $time ); ?></span>
    </div>
    <?php
}

/**
 * Render the "(edited ...)" stamp if a post was modified after creation.
 *
 * Compares created vs modified timestamps; shows stamp only if the
 * difference exceeds 60 seconds (to ignore auto-save noise).
 *
 * @param int|WP_Post $post Post ID or object.
 */
function fhb_render_edited_stamp( $post ) {
    $created  = get_the_date( 'U', $post );
    $modified = get_post_modified_time( 'U', false, $post );

    if ( $modified && ( $modified - $created ) > 60 ) {
        ?>
        <span class="fhb-edited-stamp">(edited <?php echo esc_html( get_the_modified_date( '', $post ) ); ?>)</span>
        <?php
    }
}
