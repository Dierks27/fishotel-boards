<?php
/**
 * Form – Create new topic.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<form class="fhb-form fhb-form-new-topic" data-action="fhb_new_topic">
    <?php wp_nonce_field( FHB_Constants::NONCE_ACTION, 'fhb_nonce_field' ); ?>
    <input type="hidden" name="subject_id" value="<?php echo esc_attr( $subject->ID ); ?>" />
    <div class="fhb-form-group">
        <label for="fhb-topic-title">Title</label>
        <input type="text" id="fhb-topic-title" name="topic_title" required maxlength="200" placeholder="Topic title&hellip;" />
    </div>
    <div class="fhb-form-group">
        <label for="fhb-topic-content">Message</label>
        <textarea id="fhb-topic-content" name="topic_content" required rows="6" placeholder="What's on your mind?"></textarea>
    </div>
    <div class="fhb-form-actions">
        <button type="submit" class="fhb-btn fhb-btn-primary">Post Topic</button>
        <button type="button" class="fhb-btn fhb-new-topic-cancel">Cancel</button>
    </div>
    <div class="fhb-form-message" style="display:none;"></div>
</form>
