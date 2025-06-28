/**
 * Admin Dashboard JavaScript
 *
 * @package VisitorDashboard
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize admin dashboard functionality
     */
    function initAdminDashboard() {
        // Add loading states to buttons
        initLoadingStates();
        
        // Initialize confirmation dialogs
        initConfirmationDialogs();
        
        // Initialize AJAX forms
        initAjaxForms();
    }

    /**
     * Add loading states to buttons
     */
    function initLoadingStates() {
        $('.memo-admin-btn').on('click', function() {
            var $button = $(this);
            if (!$button.hasClass('memo-admin-btn-danger')) {
                $button.addClass('loading');
            }
        });
    }

    /**
     * Initialize confirmation dialogs
     */
    function initConfirmationDialogs() {
        // Delete confirmations
        $('.delete-item').on('click', function(e) {
            if (!confirm(visitor_dashboard_admin.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        // Export confirmations
        $('.export-data').on('click', function(e) {
            if (!confirm(visitor_dashboard_admin.strings.confirm_export)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Initialize AJAX forms
     */
    function initAjaxForms() {
        $('.ajax-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var formData = new FormData(this);
            formData.append('action', $form.data('action'));
            formData.append('nonce', visitor_dashboard_admin.nonce);

            $.ajax({
                url: visitor_dashboard_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                    } else {
                        showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    showNotice('error', visitor_dashboard_admin.strings.error);
                }
            });
        });
    }

    /**
     * Show admin notice
     */
    function showNotice(type, message) {
        var noticeClass = (type === 'error') ? 'notice-error' : 'notice-success';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        initAdminDashboard();
    });

})(jQuery);
