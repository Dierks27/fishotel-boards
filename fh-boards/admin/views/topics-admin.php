<?php
/**
 * Admin view – Topics (categories within subjects).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';

$subjects = get_posts( array(
    'post_type'      => FHB_Constants::POST_TYPE_SUBJECT,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
) );

$topic_cats = get_posts( array(
    'post_type'      => FHB_Constants::POST_TYPE_TOPIC_CAT,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_key'       => FHB_Constants::META_SORT_ORDER,
    'orderby'        => array( 'meta_value_num' => 'ASC', 'title' => 'ASC' ),
) );
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards &mdash; Topics</h1>

    <?php if ( $message === 'topic_created' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Topic created.</p></div>
    <?php elseif ( $message === 'topic_deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Topic and all its threads deleted.</p></div>
    <?php elseif ( $message === 'topic_error' ) : ?>
        <div class="notice notice-error is-dismissible"><p>Topic title and subject are required.</p></div>
    <?php endif; ?>

    <?php if ( ! empty( $subjects ) ) : ?>
        <h2>Create New Topic</h2>
        <form method="post" style="max-width:500px; margin-bottom:30px;">
            <?php wp_nonce_field( 'fhb_admin_create_topic', 'fhb_admin_nonce' ); ?>
            <input type="hidden" name="fhb_admin_action" value="create_topic" />
            <table class="form-table">
                <tr>
                    <th><label for="fhb_topic_subject">Subject</label></th>
                    <td>
                        <select name="subject_id" id="fhb_topic_subject" required>
                            <option value="">-- Select a subject --</option>
                            <?php foreach ( $subjects as $s ) : ?>
                                <option value="<?php echo esc_attr( $s->ID ); ?>"><?php echo esc_html( $s->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="fhb_topic_title">Title</label></th>
                    <td><input type="text" id="fhb_topic_title" name="topic_title" class="regular-text" required placeholder="e.g. Bug Reports, General Feedback&hellip;" /></td>
                </tr>
                <tr>
                    <th><label for="fhb_topic_description">Description <small>(optional)</small></label></th>
                    <td><textarea id="fhb_topic_description" name="topic_description" class="large-text" rows="2" placeholder="Brief description&hellip;"></textarea></td>
                </tr>
                <tr>
                    <th><label for="fhb_sort_order">Sort Order</label></th>
                    <td>
                        <input type="number" id="fhb_sort_order" name="sort_order" class="small-text" value="0" min="0" step="1" />
                        <p class="description">Lower numbers appear first. Topics with the same order are sorted alphabetically.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Create Topic', 'primary' ); ?>
        </form>
    <?php else : ?>
        <p>Create a <a href="<?php echo esc_url( admin_url( 'admin.php?page=fh-boards' ) ); ?>">Subject</a> first before adding Topics.</p>
    <?php endif; ?>

    <h2>Existing Topics</h2>
    <?php if ( ! empty( $topic_cats ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th style="width:22%;">Topic</th>
                    <th style="width:20%;">Subject</th>
                    <th style="width:8%;">Order</th>
                    <th style="width:12%;">Description</th>
                    <th style="width:8%;">Threads</th>
                    <th style="width:10%;">Created</th>
                    <th style="width:10%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $topic_cats as $tc ) : ?>
                    <?php
                    $sid          = get_post_meta( $tc->ID, FHB_Constants::META_SUBJECT_ID, true );
                    $subject_name = $sid ? get_the_title( $sid ) : '(none)';
                    $sort_order   = absint( get_post_meta( $tc->ID, FHB_Constants::META_SORT_ORDER, true ) );
                    $thread_count = absint( get_post_meta( $tc->ID, FHB_Constants::META_THREAD_COUNT, true ) );
                    $excerpt      = wp_trim_words( $tc->post_content, 10, '&hellip;' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $tc->ID ); ?></td>
                        <td><strong><?php echo esc_html( $tc->post_title ); ?></strong></td>
                        <td><?php echo esc_html( $subject_name ); ?></td>
                        <td><?php echo esc_html( $sort_order ); ?></td>
                        <td><?php echo esc_html( $excerpt ); ?></td>
                        <td><?php echo esc_html( $thread_count ); ?></td>
                        <td><?php echo esc_html( get_the_date( '', $tc ) ); ?></td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this topic and ALL its threads and replies?');">
                                <?php wp_nonce_field( 'fhb_admin_delete_topic', 'fhb_admin_nonce' ); ?>
                                <input type="hidden" name="fhb_admin_action" value="delete_topic" />
                                <input type="hidden" name="topic_cat_id" value="<?php echo esc_attr( $tc->ID ); ?>" />
                                <button type="submit" class="button button-small button-link-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No topics yet.</p>
    <?php endif; ?>
</div>
