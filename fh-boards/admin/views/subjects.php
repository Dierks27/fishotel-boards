<?php
/**
 * Admin view – Subjects list with create form.
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
?>
<div class="wrap fhb-admin-wrap">
    <h1>FH Boards &mdash; Subjects</h1>

    <?php if ( $message === 'subject_created' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Subject created.</p></div>
    <?php elseif ( $message === 'subject_deleted' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Subject and all its topics deleted.</p></div>
    <?php elseif ( $message === 'subject_error' ) : ?>
        <div class="notice notice-error is-dismissible"><p>Subject title is required.</p></div>
    <?php endif; ?>

    <h2>Create New Subject</h2>
    <form method="post" style="max-width:500px; margin-bottom:30px;">
        <?php wp_nonce_field( 'fhb_admin_create_subject', 'fhb_admin_nonce' ); ?>
        <input type="hidden" name="fhb_admin_action" value="create_subject" />
        <table class="form-table">
            <tr>
                <th><label for="fhb_subject_title">Title</label></th>
                <td><input type="text" id="fhb_subject_title" name="subject_title" class="regular-text" required placeholder="e.g. Testing, Photos, Debugging&hellip;" /></td>
            </tr>
            <tr>
                <th><label for="fhb_subject_description">Description <small>(optional)</small></label></th>
                <td><textarea id="fhb_subject_description" name="subject_description" class="large-text" rows="3" placeholder="Brief description of this subject&hellip;"></textarea></td>
            </tr>
        </table>
        <?php submit_button( 'Create Subject', 'primary' ); ?>
    </form>

    <h2>Existing Subjects</h2>
    <?php if ( ! empty( $subjects ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:5%;">ID</th>
                    <th style="width:30%;">Title</th>
                    <th style="width:30%;">Description</th>
                    <th style="width:10%;">Topics</th>
                    <th style="width:15%;">Created</th>
                    <th style="width:10%;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $subjects as $subject ) : ?>
                    <?php
                    $topic_count = absint( get_post_meta( $subject->ID, FHB_Constants::META_TOPIC_COUNT, true ) );
                    $excerpt     = wp_trim_words( $subject->post_content, 15, '&hellip;' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $subject->ID ); ?></td>
                        <td><strong><?php echo esc_html( $subject->post_title ); ?></strong></td>
                        <td><?php echo esc_html( $excerpt ); ?></td>
                        <td><?php echo esc_html( $topic_count ); ?></td>
                        <td><?php echo esc_html( get_the_date( '', $subject ) ); ?></td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this subject and ALL its topics and replies?');">
                                <?php wp_nonce_field( 'fhb_admin_delete_subject', 'fhb_admin_nonce' ); ?>
                                <input type="hidden" name="fhb_admin_action" value="delete_subject" />
                                <input type="hidden" name="subject_id" value="<?php echo esc_attr( $subject->ID ); ?>" />
                                <button type="submit" class="button button-small button-link-delete">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p>No subjects yet. Create one above to get started.</p>
    <?php endif; ?>
</div>
