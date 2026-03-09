(function($) {
    'use strict';

    $(document).on('click', '.dg-download-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $wrapper = $btn.closest('.dg-download-wrapper');
        var $status = $wrapper.find('.dg-status');

        if ($btn.hasClass('dg-loading')) {
            return;
        }

        var templateId = $btn.data('template-id');
        var format = $btn.data('format');
        var nonce = $btn.data('nonce');

        // Get current post ID from body class or a global variable.
        var postId = 0;
        var bodyClasses = $('body').attr('class') || '';
        var match = bodyClasses.match(/postid-(\d+)|page-id-(\d+)/);
        if (match) {
            postId = match[1] || match[2];
        }

        $btn.addClass('dg-loading').prop('disabled', true);
        $status.hide().removeClass('dg-error dg-success');

        $.ajax({
            url: dgFrontend.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dg_download',
                template_id: templateId,
                format: format,
                nonce: nonce,
                post_id: postId
            },
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
