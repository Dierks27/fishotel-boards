/**
 * FH Boards – Public JS.
 *
 * Handles AJAX for new topics, replies, and subscriptions.
 */

(function ($) {
    'use strict';

    /* ------------------------------------------------------------------
     * Shared helpers
     * ----------------------------------------------------------------*/

    /**
     * Show a status message with a given type class.
     *
     * @param {jQuery} $el   Message element.
     * @param {string} type  'success', 'error', or 'info'.
     * @param {string} text  Message text (or HTML when using .html()).
     */
    function fhbShowMsg($el, type, text) {
        $el.removeClass('fhb-msg-success fhb-msg-error fhb-msg-info')
            .addClass('fhb-msg-' + type).text(text).show();
    }

    /**
     * Toggle a button's disabled state and label.
     */
    function fhbSetBtn($btn, disabled, text) {
        $btn.prop('disabled', disabled).text(text);
    }

    /**
     * Convert displayed post HTML back to plain text for editing.
     */
    function fhbHtmlToText(html) {
        return html
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/p>\s*<p[^>]*>/gi, '\n\n')
            .replace(/<[^>]+>/g, '')
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#0?39;/g, "'")
            .trim();
    }

    /* ------------------------------------------------------------------
     * Topic Search (AJAX, debounced)
     * ----------------------------------------------------------------*/
    var fhbSearchTimer = null;

    function fhbBuildTopicUrl(postId) {
        var url = window.location.href.split('?')[0];
        var params = new URLSearchParams(window.location.search);
        params.delete('fhb_topic');
        params.delete('fhb_paged');
        params.set('fhb_topic', postId);
        return url + '?' + params.toString();
    }

    function fhbHighlight(text, query) {
        if (!query) return $('<div>').text(text).html();
        var escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var safe    = $('<div>').text(text).html();
        return safe.replace(new RegExp('(' + escaped + ')', 'gi'),
            '<mark class="fhb-highlight">$1</mark>');
    }

    $(document).on('input', '.fhb-search-input', function () {
        var $input   = $(this);
        var query    = $.trim($input.val());
        var $clear   = $input.siblings('.fhb-search-clear');
        var $loading = $input.siblings('.fhb-search-loading');
        var $results = $('.fhb-search-results');
        var $list    = $('.fhb-topic-list');
        var $pag     = $('.fhb-pagination');
        var $empty   = $('.fhb-no-topics');

        // Show/hide clear button.
        $clear.toggle(query.length > 0);

        clearTimeout(fhbSearchTimer);

        if (query.length < 2) {
            $results.hide().empty();
            $list.show();
            $pag.show();
            $empty.show();
            $loading.hide();
            return;
        }

        $loading.show();

        fhbSearchTimer = setTimeout(function () {
            $.post(fhb_ajax.ajax_url, {
                action: 'fhb_search',
                nonce:  fhb_ajax.nonce,
                query:  query
            }, function (res) {
                $loading.hide();
                $list.hide();
                $pag.hide();
                $empty.hide();

                if (res.success && res.data.topics.length) {
                    var html = '';
                    $.each(res.data.topics, function (i, t) {
                        var cls = t.is_closed ? ' fhb-closed' : '';
                        var rc  = t.reply_count === 1 ? '1 reply' : t.reply_count + ' replies';
                        html += '<div class="fhb-topic-row' + cls + '">';
                        html += '<div class="fhb-topic-title">';
                        var topicUrl = fhbBuildTopicUrl(t.post_id);
                        if (t.reply_id) {
                            topicUrl += '#fhb-post-' + t.reply_id;
                        }
                        html += '<a href="' + topicUrl + '">' + fhbHighlight(t.title, query) + '</a>';
                        if (t.is_closed) html += ' <span class="fhb-badge fhb-badge-closed">Closed</span>';
                        html += '</div>';
                        if (t.snippet) {
                            html += '<div class="fhb-search-snippet">' + fhbHighlight(t.snippet, query) + '</div>';
                        }
                        html += '<div class="fhb-topic-meta">';
                        html += '<span class="fhb-topic-author">by ' + $('<span>').text(t.author_name).html() + '</span>';
                        html += '<span class="fhb-topic-replies">' + rc + '</span>';
                        html += '</div></div>';
                    });
                    $results.html(html).show();
                } else {
                    $results.html(
                        '<p class="fhb-no-topics">No topics found for \'' +
                        $('<span>').text(query).html() + '\'</p>'
                    ).show();
                }
            }).fail(function () {
                $loading.hide();
            });
        }, 350);
    });

    $(document).on('click', '.fhb-search-clear', function () {
        var $wrap = $(this).closest('.fhb-search-wrap');
        $wrap.find('.fhb-search-input').val('').trigger('input');
    });

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

        fhbSetBtn($btn, true, 'Posting\u2026');

        $.post(fhb_ajax.ajax_url, {
            action:        'fhb_new_topic',
            nonce:         fhb_ajax.nonce,
            topic_title:   $form.find('[name="topic_title"]').val(),
            topic_content: $form.find('[name="topic_content"]').val()
        }, function (res) {
            if (res.success) {
                fhbShowMsg($msg, 'success', res.data.message);
                setTimeout(function () { location.reload(); }, 800);
            } else {
                fhbShowMsg($msg, 'error', res.data.message);
                fhbSetBtn($btn, false, 'Post Topic');
            }
        }).fail(function () {
            fhbShowMsg($msg, 'error', 'An error occurred. Please try again.');
            fhbSetBtn($btn, false, 'Post Topic');
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

        fhbSetBtn($btn, true, 'Posting\u2026');

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
                fhbShowMsg($msg, 'success', res.data.message);
                fhbSetBtn($btn, false, 'Post Reply');
                // Scroll to the new reply.
                $('html, body').animate({
                    scrollTop: $('.fhb-reply-post:last').offset().top - 50
                }, 400);
                // Hide success message after a moment.
                setTimeout(function () { $msg.fadeOut(300); }, 3000);
            } else {
                fhbShowMsg($msg, 'error', res.data.message);
                fhbSetBtn($btn, false, 'Post Reply');
            }
        }).fail(function () {
            fhbShowMsg($msg, 'error', 'An error occurred. Please try again.');
            fhbSetBtn($btn, false, 'Post Reply');
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
                fhbShowMsg($msg, 'success', res.data.message);

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
                    $msg.removeClass('fhb-msg-success fhb-msg-error').addClass('fhb-msg-info')
                        .html(
                            res.data.message +
                            ' <button class="fhb-btn fhb-btn-small fhb-btn-yes fhb-enable-notifs-btn">Yes</button>'
                        ).show();
                } else {
                    fhbShowMsg($msg, 'info', res.data.message);
                }
                $btn.prop('disabled', false);
            }
        }).fail(function () {
            fhbShowMsg($msg, 'info', 'An error occurred.');
            $btn.prop('disabled', false);
        });
    });

    /* ------------------------------------------------------------------
     * Inline Edit Post (AJAX)
     * ----------------------------------------------------------------*/
    $(document).on('click', '.fhb-edit-btn', function () {
        var $post    = $(this).closest('.fhb-post');
        var $content = $post.find('.fhb-post-content');
        var $actions = $post.find('.fhb-post-actions');

        // Convert displayed HTML back to plain text for the textarea.
        var raw = fhbHtmlToText($content.html());

        // Store original HTML so Cancel can restore it.
        $content.data('original-html', $content.html());

        // Replace content with textarea.
        $content.html(
            '<textarea class="fhb-edit-textarea" rows="4">' +
            $('<div>').text(raw).html() +
            '</textarea>'
        );

        // Swap Edit button for Save / Cancel.
        $actions.data('original-html', $actions.html());
        $actions.html(
            '<button type="button" class="fhb-btn fhb-btn-small fhb-save-btn">Save</button> ' +
            '<button type="button" class="fhb-btn fhb-btn-small fhb-cancel-btn">Cancel</button>'
        );
    });

    $(document).on('click', '.fhb-cancel-btn', function () {
        var $post    = $(this).closest('.fhb-post');
        var $content = $post.find('.fhb-post-content');
        var $actions = $post.find('.fhb-post-actions');

        $content.html($content.data('original-html'));
        $actions.html($actions.data('original-html'));
    });

    $(document).on('click', '.fhb-save-btn', function () {
        var $btn     = $(this);
        var $post    = $btn.closest('.fhb-post');
        var $content = $post.find('.fhb-post-content');
        var $actions = $post.find('.fhb-post-actions');
        var postId   = $post.data('post-id');
        var newText  = $content.find('.fhb-edit-textarea').val();

        fhbSetBtn($btn, true, 'Saving\u2026');

        $.post(fhb_ajax.ajax_url, {
            action:  'fhb_edit_post',
            nonce:   fhb_ajax.nonce,
            post_id: postId,
            content: newText
        }, function (res) {
            if (res.success) {
                $content.html(res.data.html);
                // Insert or update the edited stamp.
                var $stamp = $post.find('.fhb-edited-stamp');
                if ($stamp.length) {
                    $stamp.replaceWith(res.data.edited_stamp);
                } else {
                    $content.after(res.data.edited_stamp);
                }
                $actions.html($actions.data('original-html'));
            } else {
                alert(res.data.message);
                fhbSetBtn($btn, false, 'Save');
            }
        }).fail(function () {
            alert('An error occurred. Please try again.');
            fhbSetBtn($btn, false, 'Save');
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

        fhbSetBtn($enableBtn, true, 'Enabling\u2026');

        $.post(fhb_ajax.ajax_url, {
            action:   'fhb_enable_notifications',
            nonce:    fhb_ajax.nonce,
            topic_id: topicId
        }, function (res) {
            if (res.success) {
                fhbShowMsg($msg, 'success', res.data.message);
                $subBtn.text('Unsubscribe from Notifications')
                    .data('action', 'unsubscribe')
                    .addClass('fhb-btn-subscribed');
            } else {
                fhbShowMsg($msg, 'info', res.data.message);
            }
        }).fail(function () {
            fhbShowMsg($msg, 'info', 'An error occurred.');
        });
    });

})(jQuery);
