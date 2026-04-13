/**
 * FH Boards – Public JS.
 *
 * Handles AJAX for new topics, replies, and subscriptions.
 */

(function ($) {
    'use strict';

    /* ------------------------------------------------------------------
     * Toggle new-topic form
     * ----------------------------------------------------------------*/
    $(document).on('click', '.fhb-new-topic-toggle', function () {
        $('.fhb-new-topic-form').slideToggle(200);
    });

    $(document).on('click', '.fhb-new-topic-cancel', function () {
        $('.fhb-new-topic-form').slideUp(200);
    });

    /* ------------------------------------------------------------------
     * New Topic (AJAX)
     * ----------------------------------------------------------------*/
    $(document).on('submit', '.fhb-form-new-topic', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $msg  = $form.find('.fhb-form-message');
        var $btn  = $form.find('button[type="submit"]');

        $btn.prop('disabled', true).text('Posting…');

        $.post(fhb_ajax.ajax_url, {
            action:        'fhb_new_topic',
            nonce:         fhb_ajax.nonce,
            topic_title:   $form.find('[name="topic_title"]').val(),
            topic_content: $form.find('[name="topic_content"]').val()
        }, function (res) {
            if (res.success) {
                $msg.removeClass('fhb-msg-error').addClass('fhb-msg-success')
                    .text(res.data.message).show();
                // Reload to show the new topic.
                setTimeout(function () {
                    location.reload();
                }, 800);
            } else {
                $msg.removeClass('fhb-msg-success').addClass('fhb-msg-error')
                    .text(res.data.message).show();
                $btn.prop('disabled', false).text('Post Topic');
            }
        }).fail(function () {
            $msg.removeClass('fhb-msg-success').addClass('fhb-msg-error')
                .text('An error occurred. Please try again.').show();
            $btn.prop('disabled', false).text('Post Topic');
        });
    });

    /* ------------------------------------------------------------------
     * New Reply (AJAX)
     * ----------------------------------------------------------------*/
    $(document).on('submit', '.fhb-form-new-reply', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $msg  = $form.find('.fhb-form-message');
        var $btn  = $form.find('button[type="submit"]');

        $btn.prop('disabled', true).text('Posting…');

        $.post(fhb_ajax.ajax_url, {
            action:        'fhb_new_reply',
            nonce:         fhb_ajax.nonce,
            topic_id:      $form.find('[name="topic_id"]').val(),
            reply_content: $form.find('[name="reply_content"]').val()
        }, function (res) {
            if (res.success) {
                // Append the new reply to the page.
                if ($('.fhb-replies').length) {
                    $('.fhb-replies').append(res.data.html);
                } else {
                    // First reply — create the container.
                    $('.fhb-subscribe-area').before('<div class="fhb-replies">' + res.data.html + '</div>');
                }
                $form.find('textarea').val('');
                $msg.removeClass('fhb-msg-error').addClass('fhb-msg-success')
                    .text(res.data.message).show();
                $btn.prop('disabled', false).text('Post Reply');
                // Scroll to the new reply.
                $('html, body').animate({
                    scrollTop: $('.fhb-reply-post:last').offset().top - 50
                }, 400);
                // Hide success message after a moment.
                setTimeout(function () {
                    $msg.fadeOut(300);
                }, 3000);
            } else {
                $msg.removeClass('fhb-msg-success').addClass('fhb-msg-error')
                    .text(res.data.message).show();
                $btn.prop('disabled', false).text('Post Reply');
            }
        }).fail(function () {
            $msg.removeClass('fhb-msg-success').addClass('fhb-msg-error')
                .text('An error occurred. Please try again.').show();
            $btn.prop('disabled', false).text('Post Reply');
        });
    });

    /* ------------------------------------------------------------------
     * Subscribe / Unsubscribe (AJAX)
     * ----------------------------------------------------------------*/
    $(document).on('click', '.fhb-subscribe-btn', function () {
        var $btn     = $(this);
        var $area    = $btn.closest('.fhb-subscribe-area');
        var $msg     = $area.find('.fhb-subscribe-message');
        var topicId  = $area.data('topic-id');
        var action   = $btn.data('action'); // 'subscribe' or 'unsubscribe'

        $btn.prop('disabled', true);

        $.post(fhb_ajax.ajax_url, {
            action:   'fhb_' + action,
            nonce:    fhb_ajax.nonce,
            topic_id: topicId
        }, function (res) {
            if (res.success) {
                $msg.removeClass('fhb-msg-info').addClass('fhb-msg-success')
                    .text(res.data.message).show();

                // Toggle button state.
                if (action === 'subscribe') {
                    $btn.text('Unsubscribe from Notifications')
                        .data('action', 'unsubscribe')
                        .addClass('fhb-btn-subscribed');
                } else {
                    $btn.text('Get Notifications')
                        .data('action', 'subscribe')
                        .removeClass('fhb-btn-subscribed');
                }
                $btn.prop('disabled', false);
            } else {
                // Check if the user needs to opt in.
                if (res.data && res.data.needs_opt_in) {
                    $msg.removeClass('fhb-msg-success').addClass('fhb-msg-info')
                        .html(
                            res.data.message +
                            ' <button class="fhb-btn fhb-btn-small fhb-btn-yes fhb-enable-notifs-btn">Yes</button>'
                        ).show();
                    $btn.prop('disabled', false);
                } else {
                    $msg.removeClass('fhb-msg-success').addClass('fhb-msg-info')
                        .text(res.data.message).show();
                    $btn.prop('disabled', false);
                }
            }
        }).fail(function () {
            $msg.text('An error occurred.').addClass('fhb-msg-info').show();
            $btn.prop('disabled', false);
        });
    });

    /* ------------------------------------------------------------------
     * Enable Notifications + Subscribe (AJAX)
     * ----------------------------------------------------------------*/
    $(document).on('click', '.fhb-enable-notifs-btn', function () {
        var $enableBtn = $(this);
        var $area      = $enableBtn.closest('.fhb-subscribe-area');
        var $msg       = $area.find('.fhb-subscribe-message');
        var $subBtn    = $area.find('.fhb-subscribe-btn');
        var topicId    = $area.data('topic-id');

        $enableBtn.prop('disabled', true).text('Enabling…');

        $.post(fhb_ajax.ajax_url, {
            action:   'fhb_enable_notifications',
            nonce:    fhb_ajax.nonce,
            topic_id: topicId
        }, function (res) {
            if (res.success) {
                $msg.removeClass('fhb-msg-info').addClass('fhb-msg-success')
                    .text(res.data.message).show();
                $subBtn.text('Unsubscribe from Notifications')
                    .data('action', 'unsubscribe')
                    .addClass('fhb-btn-subscribed');
            } else {
                $msg.text(res.data.message).show();
            }
        }).fail(function () {
            $msg.text('An error occurred.').show();
        });
    });

})(jQuery);
