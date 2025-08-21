/**
 * Research Team Manager - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize admin functionality
        initMetaBoxes();
        initPublicationsAdmin();
        initScholarSettings();
        initDragAndDrop();
        
    });

    /**
     * Initialize meta box functionality
     */
    function initMetaBoxes() {
        // Toggle current member checkbox behavior
        $('#rtm_is_current').on('change', function() {
            var $endDateField = $('#rtm_end_date').closest('tr');
            
            if ($(this).is(':checked')) {
                $endDateField.fadeOut();
                $('#rtm_end_date').val('');
            } else {
                $endDateField.fadeIn();
            }
        });

        // Initial state check
        if ($('#rtm_is_current').is(':checked')) {
            $('#rtm_end_date').closest('tr').hide();
        }

        // Date field validation
        $('#rtm_start_date, #rtm_end_date').on('change', function() {
            validateDateFields();
        });

        // Email validation
        $('#rtm_email').on('blur', function() {
            validateEmail($(this));
        });

        // URL validation
        $('input[type="url"]').on('blur', function() {
            validateURL($(this));
        });

        // Auto-populate Google Scholar ID from URL
        $('#rtm_google_scholar').on('blur', function() {
            extractGoogleScholarID($(this));
        });

        // Character counter for textareas
        $('textarea').each(function() {
            addCharacterCounter($(this));
        });
    }

    /**
     * Validate date fields
     */
    function validateDateFields() {
        var startDate = new Date($('#rtm_start_date').val());
        var endDate = new Date($('#rtm_end_date').val());
        var $endDateField = $('#rtm_end_date');

        if (startDate && endDate && endDate < startDate) {
            $endDateField.addClass('error');
            showFieldError($endDateField, 'End date cannot be before start date.');
        } else {
            $endDateField.removeClass('error');
            hideFieldError($endDateField);
        }
    }

    /**
     * Validate email field
     */
    function validateEmail($field) {
        var email = $field.val();
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email && !emailRegex.test(email)) {
            $field.addClass('error');
            showFieldError($field, 'Please enter a valid email address.');
        } else {
            $field.removeClass('error');
            hideFieldError($field);
        }
    }

    /**
     * Validate URL field
     */
    function validateURL($field) {
        var url = $field.val();
        
        if (url && !isValidURL(url)) {
            $field.addClass('error');
            showFieldError($field, 'Please enter a valid URL starting with http:// or https://');
        } else {
            $field.removeClass('error');
            hideFieldError($field);
        }
    }

    /**
     * Check if URL is valid
     */
    function isValidURL(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    /**
     * Extract Google Scholar ID from URL
     */
    function extractGoogleScholarID($field) {
        var url = $field.val();
        var match = url.match(/user=([^&]+)/);
        
        if (match && match[1]) {
            var userId = match[1];
            showFieldSuccess($field, 'Google Scholar ID detected: ' + userId);
            
            // Store the ID in a hidden field or data attribute for later use
            $field.data('scholar-id', userId);
        }
    }

    /**
     * Show field error
     */
    function showFieldError($field, message) {
        hideFieldError($field);
        
        var $error = $('<div class="rtm-field-error">' + message + '</div>');
        $error.insertAfter($field);
    }

    /**
     * Hide field error
     */
    function hideFieldError($field) {
        $field.nextAll('.rtm-field-error').remove();
    }

    /**
     * Show field success message
     */
    function showFieldSuccess($field, message) {
        hideFieldError($field);
        
        var $success = $('<div class="rtm-field-success">' + message + '</div>');
        $success.insertAfter($field);
        
        setTimeout(function() {
            $success.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    /**
     * Add character counter to textarea
     */
    function addCharacterCounter($textarea) {
        var maxLength = $textarea.attr('maxlength');
        if (!maxLength) return;

        var $counter = $('<div class="rtm-char-counter">0/' + maxLength + '</div>');
        $counter.insertAfter($textarea);

        $textarea.on('input', function() {
            var length = $(this).val().length;
            $counter.text(length + '/' + maxLength);
            
            if (length > maxLength * 0.9) {
                $counter.addClass('warning');
            } else {
                $counter.removeClass('warning');
            }
        });

        // Initial count
        $textarea.trigger('input');
    }

    /**
     * Initialize publications admin functionality
     */
    function initPublicationsAdmin() {
        // Delete publication
        $('.rtm-delete-pub').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(rtm_admin_localize.confirm_delete_publication)) {
                return;
            }
            
            var $button = $(this);
            var pubId = $button.data('id');
            var nonce = $button.data('nonce');
            
            $button.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rtm_delete_publication',
                    publication_id: pubId,
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                            updatePublicationStats();
                        });
                    } else {
                        alert('Error deleting publication: ' + response.data.message);
                        $button.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    alert('Error deleting publication.');
                    $button.prop('disabled', false).text('Delete');
                }
            });
        });

        // Sync publications
        $('.rtm-sync-publications').on('click', function(e) {
            var $button = $(this);
            var originalText = $button.val();
            
            $button.prop('disabled', true).val('Syncing...');
            $button.after('<span class="rtm-spinner"></span>');
            
            // The form will handle the actual sync, but we can show progress
            setTimeout(function() {
                $('.rtm-spinner').remove();
            }, 1000);
        });

        // Auto-refresh publications list
        if ($('.rtm-publications-table').length) {
            setInterval(function() {
                refreshPublicationStats();
            }, 30000); // Refresh every 30 seconds
        }
    }

    /**
     * Update publication statistics
     */
    function updatePublicationStats() {
        var totalPubs = $('.rtm-publications-table tbody tr').length;
        var totalCitations = 0;
        
        $('.rtm-publications-table tbody tr').each(function() {
            var citations = parseInt($(this).find('td').eq(4).text()) || 0;
            totalCitations += citations;
        });
        
        $('.rtm-stat-card h3').eq(0).text(totalPubs.toLocaleString());
        $('.rtm-stat-card h3').eq(1).text(totalCitations.toLocaleString());
    }

    /**
     * Refresh publication statistics via AJAX
     */
    function refreshPublicationStats() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rtm_get_publication_stats',
                nonce: rtm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('.rtm-stat-card h3').eq(0).text(response.data.total_publications.toLocaleString());
                    $('.rtm-stat-card h3').eq(1).text(response.data.total_citations.toLocaleString());
                }
            }
        });
    }

    /**
     * Initialize Google Scholar settings functionality
     */
    function initScholarSettings() {
        // Test Scholar connection
        $('#rtm-test-scholar').on('click', function() {
            var $button = $(this);
            var userId = $button.data('user-id') || $('#rtm_google_scholar_user_id').val();
            var $results = $('#rtm-test-results');
            
            if (!userId) {
                $results.html('<div class="notice notice-error"><p>Please enter a Google Scholar User ID first.</p></div>');
                return;
            }
            
            $button.prop('disabled', true).text('Testing...');
            $results.html('<div class="notice notice-info"><p>Testing connection to Google Scholar...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'rtm_test_scholar_connection',
                    user_id: userId,
                    nonce: $('#rtm-test-scholar').data('nonce') || rtm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="notice notice-success"><p>Connection successful!</p></div>';
                        
                        if (response.data.publications && response.data.publications.length > 0) {
                            html += '<h4>Recent Publications:</h4><ul>';
                            response.data.publications.slice(0, 5).forEach(function(pub) {
                                html += '<li><strong>' + pub.title + '</strong> (' + pub.year + ') - ' + pub.citations + ' citations</li>';
                            });
                            html += '</ul>';
                            html += '<p><strong>Total publications found:</strong> ' + response.data.count + '</p>';
                        }
                        
                        $results.html(html);
                    } else {
                        $results.html('<div class="notice notice-error"><p>Connection failed: ' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>Error testing connection. Please try again.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Scholar Connection');
                }
            });
        });

        // Auto-test when user ID is entered
        $('#rtm_google_scholar_user_id').on('blur', function() {
            var userId = $(this).val();
            if (userId && userId !== $(this).data('last-value')) {
                $(this).data('last-value', userId);
                $('#rtm-test-scholar').data('user-id', userId);
                
                // Optional: Auto-test after a short delay
                setTimeout(function() {
                    if ($('#rtm_enable_auto_test').is(':checked')) {
                        $('#rtm-test-scholar').trigger('click');
                    }
                }, 500);
            }
        });

        // Sync frequency change handler
        $('#rtm_sync_frequency').on('change', function() {
            var frequency = $(this).val();
            var $notice = $('.rtm-sync-notice');
            
            if ($notice.length === 0) {
                $notice = $('<div class="notice notice-info rtm-sync-notice"><p></p></div>');
                $notice.insertAfter($(this).closest('tr'));
            }
            
            var message = 'Publications will be synced ' + frequency + '.';
            $notice.find('p').text(message);
            
            setTimeout(function() {
                $notice.fadeOut();
            }, 3000);
        });
    }

    /**
     * Initialize drag and drop functionality
     */
    function initDragAndDrop() {
        // Make team member list sortable (if jQuery UI is available)
        if ($.fn.sortable) {
            $('.rtm-sortable').sortable({
                handle: '.rtm-drag-handle',
                placeholder: 'rtm-sort-placeholder',
                update: function(event, ui) {
                    updateMemberOrder();
                }
            });
        }

        // File upload drag and drop for profile pictures
        initFileUploadDragDrop();
    }

    /**
     * Update team member order via AJAX
     */
    function updateMemberOrder() {
        var order = [];
        
        $('.rtm-sortable li').each(function(index) {
            order.push({
                id: $(this).data('member-id'),
                order: index + 1
            });
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rtm_update_member_order',
                order: order,
                nonce: rtm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showAdminNotice('Member order updated successfully.', 'success');
                } else {
                    showAdminNotice('Error updating member order.', 'error');
                }
            }
        });
    }

    /**
     * Initialize file upload drag and drop
     */
    function initFileUploadDragDrop() {
        var $uploadArea = $('.rtm-file-upload-area');
        
        if ($uploadArea.length === 0) return;
        
        $uploadArea.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });
        
        $uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });
        
        $uploadArea.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });
    }

    /**
     * Handle file upload
     */
    function handleFileUpload(file) {
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file.');
            return;
        }
        
        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'rtm_upload_member_image');
        formData.append('nonce', rtm_ajax.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Update the image preview
                    $('.rtm-image-preview').attr('src', response.data.url);
                    $('#_thumbnail_id').val(response.data.attachment_id);
                    showAdminNotice('Image uploaded successfully.', 'success');
                } else {
                    showAdminNotice('Error uploading image: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showAdminNotice('Error uploading image.', 'error');
            }
        });
    }

    /**
     * Show admin notice
     */
    function showAdminNotice(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible rtm-admin-notice"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Initialize tooltips (if available)
     */
    function initTooltips() {
        if ($.fn.tooltip) {
            $('.rtm-tooltip').tooltip({
                position: { my: "center bottom-20", at: "center top" }
            });
        }
    }

    /**
     * Initialize confirmation dialogs
     */
    function initConfirmationDialogs() {
        $('.rtm-confirm-action').on('click', function(e) {
            var message = $(this).data('confirm-message') || 'Are you sure?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Initialize on window load
     */
    $(window).on('load', function() {
        initTooltips();
        initConfirmationDialogs();
        
        // Auto-save draft functionality
        if ($('#post-type-team_member').length) {
            setInterval(function() {
                if ($('#title').val() && $('#content').val()) {
                    $('#save-post').trigger('click');
                }
            }, 60000); // Auto-save every minute
        }
    });

    // Make functions available globally for debugging
    window.RTMAdmin = {
        validateDateFields: validateDateFields,
        validateEmail: validateEmail,
        validateURL: validateURL,
        updatePublicationStats: updatePublicationStats,
        showAdminNotice: showAdminNotice
    };

})(jQuery);

// Localization object (would be populated by wp_localize_script)
var rtm_admin_localize = rtm_admin_localize || {
    confirm_delete_publication: 'Are you sure you want to delete this publication?',
    confirm_delete_member: 'Are you sure you want to delete this team member?',
    sync_in_progress: 'Sync in progress...',
    sync_complete: 'Sync completed successfully!'
};