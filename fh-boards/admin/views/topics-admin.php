<?php
/**
 * Admin view – Topics (categories within subjects).
 *
 * Drag-and-drop reordering via jQuery UI Sortable.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_enqueue_script( 'jquery-ui-sortable' );

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
            </table>
            <?php submit_button( 'Create Topic', 'primary' ); ?>
        </form>
    <?php else : ?>
        <p>Create a <a href="<?php echo esc_url( admin_url( 'admin.php?page=fh-boards' ) ); ?>">Subject</a> first before adding Topics.</p>
    <?php endif; ?>

    <h2>Existing Topics</h2>
    <p class="description">Drag rows to reorder. Changes save automatically.</p>
    <?php if ( ! empty( $topic_cats ) ) : ?>
        <table class="wp-list-table widefat fixed striped" id="fhb-topic-sortable">
            <thead>
                <tr>
                    <th style="width:4%;"></th>
                    <th style="width:5%;">ID</th>
                    <th style="width:22%;">Topic</th>
                    <th style="width:20%;">Subject</th>
                    <th style="width:15%;">Description</th>
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
                    $thread_count = absint( get_post_meta( $tc->ID, FHB_Constants::META_THREAD_COUNT, true ) );
                    $excerpt      = wp_trim_words( $tc->post_content, 10, '&hellip;' );
                    ?>
                    <tr data-id="<?php echo esc_attr( $tc->ID ); ?>">
                        <td class="fhb-drag-handle" title="Drag to reorder">&#x2630;</td>
                        <td><?php echo esc_html( $tc->ID ); ?></td>
                        <td><strong><?php echo esc_html( $tc->post_title ); ?></strong></td>
                        <td><?php echo esc_html( $subject_name ); ?></td>
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
        <div id="fhb-sort-status" style="display:none; margin-top:8px;"></div>
    <?php else : ?>
        <p>No topics yet.</p>
    <?php endif; ?>
</div>

<style>
    .fhb-drag-handle {
        cursor: grab;
        text-align: center;
        font-size: 16px;
        color: #999;
        user-select: none;
    }
    .fhb-drag-handle:hover { color: #0073aa; }
    #fhb-topic-sortable tbody tr.ui-sortable-helper {
        background: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    #fhb-topic-sortable tbody tr.ui-sortable-placeholder {
        visibility: visible !important;
        background: #f0f6fc;
        border: 2px dashed #0073aa;
    }
</style>

<script>
jQuery(function ($) {
    var $tbody = $('#fhb-topic-sortable tbody');
    if (!$tbody.length) return;

    $tbody.sortable({
        handle: '.fhb-drag-handle',
        placeholder: 'ui-sortable-placeholder',
        axis: 'y',
        cursor: 'grabbing',
        update: function () {
            var order = [];
            $tbody.find('tr').each(function () {
                order.push($(this).data('id'));
            });

            var $status = $('#fhb-sort-status');
            $status.text('Saving order\u2026').css('color', '#666').show();

            $.post(ajaxurl, {
                action:   'fhb_reorder_topics',
                nonce:    '<?php echo wp_create_nonce( 'fhb_reorder_topics' ); ?>',
                order:    order
            }, function (res) {
                if (res.success) {
                    $status.text('Order saved.').css('color', '#46b450');
                } else {
                    $status.text('Failed to save order.').css('color', '#dc3232');
                }
                setTimeout(function () { $status.fadeOut(300); }, 2000);
            }).fail(function () {
                $status.text('Failed to save order.').css('color', '#dc3232');
                setTimeout(function () { $status.fadeOut(300); }, 2000);
            });
        }
    });
});
</script>
