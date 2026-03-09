(function($) {
    'use strict';

    var DG = {
        fieldsCache: {},
        currentFilename: $('#dg-filename').val() || '',
        mapping: {},

        init: function() {
            this.bindEvents();
            this.initDragDrop();
            this.loadExistingMapping();
        },

        bindEvents: function() {
            // File upload.
            $('#dg-browse-btn').on('click', function() {
                $('#dg-file-input').trigger('click');
            });
            $('#dg-change-file').on('click', function() {
                $('.dg-current-file').hide();
                $('.dg-upload-prompt').show();
                $('#dg-file-input').trigger('click');
            });
            $('#dg-file-input').on('change', function() {
                if (this.files.length > 0) {
                    DG.uploadFile(this.files[0]);
                }
            });

            // Source change - load fields.
            $(document).on('change', '.dg-source-select', function() {
                DG.onSourceChange($(this));
            });

            // Field change - update mapping.
            $(document).on('change', '.dg-field-select', function() {
                DG.updateMappingFromUI();
            });
            $(document).on('input', '.dg-meta-input', function() {
                DG.updateMappingFromUI();
            });

            // Save.
            $('#dg-template-form').on('submit', function(e) {
                e.preventDefault();
                DG.saveTemplate();
            });

            // Delete.
            $(document).on('click', '.dg-delete-template', function() {
                DG.deleteTemplate($(this).data('template-id'));
            });

            // Copy shortcode.
            $('#dg-copy-shortcode').on('click', function() {
                DG.copyShortcode();
            });
            $(document).on('click', '.dg-shortcode-display', function() {
                DG.copyToClipboard($(this).text());
            });

            // Repeat block source change.
            $(document).on('change', '.dg-repeat-source', function() {
                var $block = $(this).closest('.dg-repeat-block');
                var $field = $block.find('.dg-repeat-field');
                var source = $(this).val();

                if (source === 'wp_users') {
                    $field.html(
                        '<option value="">' + '— All users —' + '</option>' +
                        '<option value="administrator">Administrator</option>' +
                        '<option value="editor">Editor</option>' +
                        '<option value="author">Author</option>' +
                        '<option value="contributor">Contributor</option>' +
                        '<option value="subscriber">Subscriber</option>'
                    );
                } else {
                    $field.html('<option value="">— Select field —</option>');
                }
            });

            // Button style live preview.
            $('.dg-button-style-grid').on('input change', 'input', function() {
                DG.updateButtonPreview();
            });
            $('#dg-button-text').on('input', function() {
                $('#dg-btn-preview').text($(this).val() || 'Download Document');
            });
        },

        initDragDrop: function() {
            var $area = $('#dg-upload-area');

            $area.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dg-drag-over');
            });

            $area.on('dragleave drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dg-drag-over');
            });

            $area.on('drop', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    DG.uploadFile(files[0]);
                }
            });
        },

        uploadFile: function(file) {
            if (!file.name.match(/\.docx$/i)) {
                alert(dgAdmin.strings.error + ' Only DOCX files are allowed.');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'dg_upload_template');
            formData.append('nonce', dgAdmin.nonce);
            formData.append('template_file', file);

            $('.dg-upload-prompt').hide();
            $('.dg-upload-progress').show();

            $.ajax({
                url: dgAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var pct = (e.loaded / e.total) * 100;
                            $('.dg-progress-fill').css('width', pct + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    $('.dg-upload-progress').hide();

                    if (response.success) {
                        DG.currentFilename = response.data.filename;
                        $('#dg-filename').val(response.data.filename);

                        // Show current file info.
                        var $area = $('#dg-upload-area');
                        $area.addClass('has-file');
                        if ($area.find('.dg-current-file').length === 0) {
                            $area.prepend(
                                '<div class="dg-current-file">' +
                                '<span class="dashicons dashicons-media-document"></span> ' +
                                '<span class="dg-filename">' + response.data.filename + '</span> ' +
                                '<button type="button" class="button button-small" id="dg-change-file">Change File</button>' +
                                '</div>'
                            );
                        } else {
                            $area.find('.dg-filename').text(response.data.filename);
                            $area.find('.dg-current-file').show();
                        }

                        // Populate mapping table.
                        DG.populateMappingTable(response.data.placeholders);
                    } else {
                        alert(response.data || dgAdmin.strings.error);
                        $('.dg-upload-prompt').show();
                    }
                },
                error: function() {
                    alert(dgAdmin.strings.error);
                    $('.dg-upload-progress').hide();
                    $('.dg-upload-prompt').show();
                }
            });
        },

        populateMappingTable: function(data) {
            var $body = $('#dg-mapping-body');
            var template = $('#tmpl-dg-mapping-row').html();

            // Simple placeholders.
            var placeholders = data.placeholders || [];
            var repeatBlocks = data.repeat_blocks || [];

            if (placeholders.length === 0 && repeatBlocks.length === 0) {
                $body.html('<tr><td colspan="5">' + dgAdmin.strings.noPlaceholders + '</td></tr>');
                return;
            }

            $body.empty();

            placeholders.forEach(function(p) {
                var html = template.replace(/\{\{data\.placeholder\}\}/g, p);
                $body.append(html);
            });

            // Repeat blocks.
            if (repeatBlocks.length > 0) {
                $('#dg-repeat-blocks').show();
                var $list = $('#dg-repeat-blocks-list');
                $list.empty();

                repeatBlocks.forEach(function(block) {
                    $list.append(
                        '<div class="dg-repeat-block" data-block="' + block + '">' +
                        '<strong>#repeat:' + block + '#</strong> ' +
                        '<select class="dg-repeat-source" data-block="' + block + '">' +
                        '<option value="">— Select data source —</option>' +
                        '<option value="toolset_repeating">Toolset Repeating Fields</option>' +
                        '<option value="wp_users">WordPress Users</option>' +
                        '</select>' +
                        '<select class="dg-repeat-field" data-block="' + block + '">' +
                        '<option value="">— Select field —</option>' +
                        '</select>' +
                        '</div>'
                    );
                });
            }

            $('#dg-mapping-section').show();

            // If we have existing mapping data, apply it.
            DG.applyExistingMapping();
        },

        loadExistingMapping: function() {
            if (typeof dgExistingMapping !== 'undefined' && dgExistingMapping && Object.keys(dgExistingMapping).length > 0) {
                this.mapping = dgExistingMapping;

                // If we already have rows, apply mapping.
                if ($('#dg-mapping-body .dg-mapping-row').length > 0) {
                    this.applyExistingMapping();
                }
            }
        },

        applyExistingMapping: function() {
            var mapping = this.mapping;
            if (!mapping || Object.keys(mapping).length === 0) return;

            Object.keys(mapping).forEach(function(placeholder) {
                var config = mapping[placeholder];
                var $row = $('[data-placeholder="' + placeholder + '"]').closest('.dg-mapping-row');

                if ($row.length === 0) return;

                var $source = $row.find('.dg-source-select');
                $source.val(config.source);

                // Load fields for this source, then set the field value.
                if (config.source) {
                    DG.loadFieldsForSource(config.source, function(fields) {
                        var $field = $row.find('.dg-field-select');
                        DG.populateFieldSelect($field, fields);
                        $field.val(config.field);
                    });
                }

                if (config.meta) {
                    $row.find('.dg-meta-input').val(config.meta);
                }
            });
        },

        onSourceChange: function($select) {
            var source = $select.val();
            var placeholder = $select.data('placeholder');
            var $row = $select.closest('.dg-mapping-row');
            var $fieldSelect = $row.find('.dg-field-select');

            if (!source) {
                $fieldSelect.html('<option value="">' + dgAdmin.strings.selectField + '</option>');
                return;
            }

            DG.loadFieldsForSource(source, function(fields) {
                DG.populateFieldSelect($fieldSelect, fields);
            });
        },

        loadFieldsForSource: function(source, callback) {
            // Check cache.
            if (this.fieldsCache[source]) {
                callback(this.fieldsCache[source]);
                return;
            }

            $.post(dgAdmin.ajaxUrl, {
                action: 'dg_get_fields',
                nonce: dgAdmin.nonce,
                source: source
            }, function(response) {
                if (response.success) {
                    DG.fieldsCache[source] = response.data;
                    callback(response.data);
                }
            });
        },

        populateFieldSelect: function($select, fields) {
            $select.html('<option value="">' + dgAdmin.strings.selectField + '</option>');

            if (Array.isArray(fields)) {
                fields.forEach(function(field) {
                    if (field.disabled) {
                        // Group header.
                        $select.append(
                            '<option value="" disabled style="font-weight:bold;background:#f0f0f1;">' + field.label + '</option>'
                        );
                    } else {
                        $select.append(
                            '<option value="' + field.value + '">' + field.label + '</option>'
                        );
                    }
                });
            }
        },

        updateMappingFromUI: function() {
            var mapping = {};

            $('#dg-mapping-body .dg-mapping-row').each(function() {
                var $row = $(this);
                var placeholder = $row.data('placeholder');
                var source = $row.find('.dg-source-select').val();
                var field = $row.find('.dg-field-select').val();
                var meta = $row.find('.dg-meta-input').val();

                if (placeholder) {
                    mapping[placeholder] = {
                        source: source || '',
                        field: field || '',
                        meta: meta || ''
                    };
                }
            });

            // Repeat blocks.
            $('#dg-repeat-blocks-list .dg-repeat-block').each(function() {
                var $block = $(this);
                var blockName = $block.data('block');
                var source = $block.find('.dg-repeat-source').val();
                var field = $block.find('.dg-repeat-field').val();

                mapping[blockName] = {
                    source: source || '',
                    field: field || '',
                    meta: '',
                    is_repeat: true
                };
            });

            this.mapping = mapping;
        },

        updateButtonPreview: function() {
            var $preview = $('#dg-btn-preview');
            var bgColor = $('input[name="button_style[bg_color]"]').val();
            var textColor = $('input[name="button_style[text_color]"]').val();
            var borderColor = $('input[name="button_style[border_color]"]').val();
            var borderWidth = $('input[name="button_style[border_width]"]').val();
            var fontSize = $('input[name="button_style[font_size]"]').val();
            var borderRadius = $('input[name="button_style[border_radius]"]').val();

            $preview.css({
                'background-color': bgColor,
                'color': textColor,
                'border': borderWidth + 'px solid ' + (borderColor || bgColor),
                'font-size': fontSize + 'px',
                'border-radius': borderRadius + 'px'
            });
        },

        saveTemplate: function() {
            var $btn = $('#dg-save-btn');
            var $status = $('#dg-save-status');

            // Build mapping from UI.
            this.updateMappingFromUI();

            var data = {
                action: 'dg_save_mapping',
                nonce: dgAdmin.nonce,
                template_id: $('input[name="template_id"]').val(),
                title: $('#dg-title').val(),
                filename: $('#dg-filename').val(),
                mapping: JSON.stringify(this.mapping),
                allowed_roles: [],
                button_text: $('#dg-button-text').val(),
                button_format: $('#dg-button-format').val(),
                'button_style[bg_color]': $('input[name="button_style[bg_color]"]').val(),
                'button_style[text_color]': $('input[name="button_style[text_color]"]').val(),
                'button_style[border_color]': $('input[name="button_style[border_color]"]').val(),
                'button_style[border_width]': $('input[name="button_style[border_width]"]').val(),
                'button_style[font_size]': $('input[name="button_style[font_size]"]').val(),
                'button_style[border_radius]': $('input[name="button_style[border_radius]"]').val()
            };

            // Collect checked roles.
            $('input[name="allowed_roles[]"]:checked').each(function() {
                data.allowed_roles.push($(this).val());
            });

            $btn.prop('disabled', true).text(dgAdmin.strings.saving);
            $status.text('').removeClass('dg-success dg-error');

            $.post(dgAdmin.ajaxUrl, data, function(response) {
                $btn.prop('disabled', false).text(
                    data.template_id ? 'Update Template' : 'Save Template'
                );

                if (response.success) {
                    $status.text(dgAdmin.strings.saved).addClass('dg-success');

                    // Update template ID for subsequent saves.
                    $('input[name="template_id"]').val(response.data.template_id);

                    // Show shortcode.
                    $('#dg-shortcode-code').text(response.data.shortcode);
                    $('#dg-shortcode-section').show();

                    // Update URL without reload.
                    if (!data.template_id || data.template_id === '0') {
                        var newUrl = window.location.href.replace(
                            'page=document-generator-new',
                            'page=document-generator-edit&template_id=' + response.data.template_id
                        );
                        window.history.replaceState({}, '', newUrl);
                    }
                } else {
                    $status.text(response.data || dgAdmin.strings.error).addClass('dg-error');
                }

                setTimeout(function() {
                    $status.fadeOut(function() {
                        $(this).text('').show().removeClass('dg-success dg-error');
                    });
                }, 3000);
            }).fail(function() {
                $btn.prop('disabled', false);
                $status.text(dgAdmin.strings.error).addClass('dg-error');
            });
        },

        deleteTemplate: function(templateId) {
            if (!confirm(dgAdmin.strings.confirmDelete)) {
                return;
            }

            $.post(dgAdmin.ajaxUrl, {
                action: 'dg_delete_template',
                nonce: dgAdmin.nonce,
                template_id: templateId
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || dgAdmin.strings.error);
                }
            });
        },

        copyShortcode: function() {
            var text = $('#dg-shortcode-code').text();
            this.copyToClipboard(text);
        },

        copyToClipboard: function(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            } else {
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
            }
        }
    };

    $(document).ready(function() {
        DG.init();
    });

})(jQuery);
