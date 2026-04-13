<?php
/**
 * Form – Post a reply.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<form class="fhb-form fhb-form-new-reply" data-action="fhb_new_reply">
    <?php wp_nonce_field( 'fhb_nonce', 'fhb_nonce_field' ); ?>
    <input type="hidden" name="topic_id" value="<?php echo esc_attr( $topic->ID ); ?>" />
    <div class="fhb-form-group">
        <label for="fhb-reply-content">Reply</label>
        <textarea id="fhb-reply-content" name="reply_content" required rows="4" placeholder="Write your reply&hellip;"></textarea>
    </div>
    <div class="fhb-form-actions">
        <button type="submit" class="fhb-btn fhb-btn-primary">Post Reply</button>
    </div>
    <div class="fhb-form-message" style="display:none;"></div>
</form>
