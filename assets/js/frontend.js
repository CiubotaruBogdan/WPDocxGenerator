(function($) {
    'use strict';

    $(document).on('click', '.dg-download-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $wrapper = $btn.closest('.dg-download-wrapper, .dg-repeat-table-wrapper, td');
        var $status = $btn.siblings('.dg-status');

        if ($btn.hasClass('dg-loading')) {
            return;
        }

        var templateId = $btn.data('template-id');
        var format = $btn.data('format');
        var nonce = $btn.data('nonce');
        var entryIndex = $btn.data('entry-index');

        // Get current post ID from body class.
        // WP adds postid-{ID} for posts/CPTs and page-id-{ID} for pages.
        var postId = 0;
        var bodyClasses = $('body').attr('class') || '';
        var match = bodyClasses.match(/(?:^|\s)(?:postid|page-id)-(\d+)(?:\s|$)/);
        if (match) {
            postId = match[1];
        }

        $btn.addClass('dg-loading').prop('disabled', true);
        $status.hide().removeClass('dg-error dg-success');

        var requestData = {
            action: 'dg_download',
            template_id: templateId,
            format: format,
            nonce: nonce,
            post_id: postId
        };

        // Include entry_index for repeating source downloads.
        if (typeof entryIndex !== 'undefined' && entryIndex !== '') {
            requestData.entry_index = entryIndex;
        }

        $.ajax({
            url: dgFrontend.ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                $btn.removeClass('dg-loading').prop('disabled', false);

                if (response.success && response.data.download_url) {
                    // Trigger download.
                    window.location.href = response.data.download_url;
                } else {
                    $status
                        .text(response.data || dgFrontend.strings.error)
                        .addClass('dg-error')
                        .show();
                }
            },
            error: function() {
                $btn.removeClass('dg-loading').prop('disabled', false);
                $status
                    .text(dgFrontend.strings.error)
                    .addClass('dg-error')
                    .show();
            }
        });
    });

})(jQuery);
