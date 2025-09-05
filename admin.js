/**
 * Enhanced Admin JavaScript for WP Learning Accounts
 * File: assets/js/admin.js
 */

jQuery(document).ready(function($) {
    
    // Handle account assignment/reassignment
    $('#assign_account_btn').on('click', function(e) {
        e.preventDefault();
        
        var accountId = $('#account_select').val();
        var userId = $('#user_select').val();
        var notes = $('#assignment_notes').val();
        
        if (!accountId || !userId) {
            showMessage('Please select both an account and a user.', 'error');
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        var isReassignment = originalText.includes('Reassign');
        button.text('Processing...').prop('disabled', true);
        
        // Determine the correct action based on whether it's assignment or reassignment
        var action = isReassignment ? 'wla_reassign_account' : 'wla_assign_account';
        
        $.ajax({
            url: wla_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                account_id: accountId,
                user_id: userId,
                new_user_id: userId, // For reassignment
                notes: notes,
                nonce: wla_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data, 'success');
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showMessage(response.data || 'An error occurred', 'error');
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                showMessage('An error occurred. Please try again.', 'error');
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Auto-populate fields when account is selected from dropdown
    $('#account_select').on('change', function() {
        var accountId = $(this).val();
        if (accountId) {
            // Redirect to assignments page with account pre-selected
            if (!window.location.href.includes('account_id=' + accountId)) {
                window.location.href = wla_ajax.admin_url + 'admin.php?page=wla-assignments&account_id=' + accountId;
            }
        }
    });
    
    // Split Percentage Modal Management
    var modal = $('#split-modal');
    var modalContent = $('.wla-modal-content');
    
    // Open modal when edit split is clicked
    $('.edit-split').on('click', function(e) {
        e.preventDefault();
        
        var accountId = $(this).data('account-id');
        var splitFirst = $(this).data('split-first');
        var splitCurrent = $(this).data('split-current');
        
        $('#split-account-id').val(accountId);
        $('#split-first').val(splitFirst);
        $('#split-current').val(splitCurrent);
        updateSplitTotal();
        
        modal.show();
    });
    
    // Close modal
    $('.wla-modal-close').on('click', function() {
        modal.hide();
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });
    
    // Update total when split values change
    $('#split-first, #split-current').on('input', function() {
        updateSplitTotal();
    });
    
    function updateSplitTotal() {
        var first = parseFloat($('#split-first').val()) || 0;
        var current = parseFloat($('#split-current').val()) || 0;
        var total = first + current;
        
        $('#split-total').text(total.toFixed(2));
        
        if (Math.abs(total - 100) < 0.01) {
            $('#split-total').css('color', 'green');
            $('#save-split').prop('disabled', false);
        } else {
            $('#split-total').css('color', 'red');
            $('#save-split').prop('disabled', true);
        }
    }
    
    // Save split percentages
    $('#save-split').on('click', function() {
        var accountId = $('#split-account-id').val();
        var splitFirst = parseFloat($('#split-first').val());
        var splitCurrent = parseFloat($('#split-current').val());
        
        if (splitFirst + splitCurrent !== 100) {
            alert('Split percentages must equal 100%');
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        var isFirstConfig = $('.set-split-btn[data-account-id="' + accountId + '"]').length > 0;
        
        button.text('Saving...').prop('disabled', true);
        
        $.ajax({
            url: wla_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wla_update_split',
                account_id: accountId,
                split_first: splitFirst,
                split_current: splitCurrent,
                nonce: wla_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var message = isFirstConfig 
                        ? 'Split percentages configured successfully. Both users have been notified about the split arrangement.'
                        : 'Split percentages updated successfully. Both users have been notified about the changes.';
                    
                    showMessage(message, 'success');
                    modal.hide();
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Failed to update split percentages: ' + (response.data || 'Unknown error'));
                    button.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Show/hide password field
    $('.wla-toggle-password').on('click', function() {
        var passwordField = $(this).siblings('input[type="password"], input[type="text"]');
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            $(this).text('Hide');
        } else {
            passwordField.attr('type', 'password');
            $(this).text('Show');
        }
    });
    
    // Dismiss notifications
    $('.wla-notice .notice-dismiss').on('click', function() {
        var noticeId = $(this).closest('.wla-notice').data('notice-id');
        
        $.ajax({
            url: wla_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wla_dismiss_notice',
                notice_id: noticeId
            }
        });
    });
    
    // Confirmation dialog for delete actions
    $('.button-link-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Filter assignments table by platform
    if ($('#filter_platform').length) {
        $('#filter_platform').on('change', function() {
            var platform = $(this).val().toLowerCase();
            
            if (platform === '') {
                $('.assignments-table tbody tr').show();
            } else {
                $('.assignments-table tbody tr').each(function() {
                    var rowPlatform = $(this).find('td:nth-child(1)').text().toLowerCase();
                    if (rowPlatform.includes(platform)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });
    }
    
    // Custom Fields Dynamic Preview
    $('input[name^="label_custom_field_"]').on('input', function() {
        var fieldName = $(this).attr('name');
        var fieldNumber = fieldName.replace('label_custom_field_', '');
        var label = $(this).val();
        
        // Update preview if exists
        if ($('#custom_field_preview_' + fieldNumber).length) {
            if (label) {
                $('#custom_field_preview_' + fieldNumber).text(label + ':');
                $('#custom_field_row_' + fieldNumber).show();
            } else {
                $('#custom_field_row_' + fieldNumber).hide();
            }
        }
    });
    
    // Function to show messages
    function showMessage(message, type) {
        var messageDiv = $('#assignment_message');
        
        // If messageDiv doesn't exist, create it at the top of the page
        if (!messageDiv.length) {
            messageDiv = $('<div id="assignment_message"></div>');
            $('.wrap').prepend(messageDiv);
        }
        
        var alertClass = type === 'success' ? 'notice-success' : 'notice-error';
        
        messageDiv.html(
            '<div class="notice ' + alertClass + ' is-dismissible">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss">' +
            '<span class="screen-reader-text">Dismiss this notice.</span>' +
            '</button>' +
            '</div>'
        );
        
        // Add dismiss functionality
        messageDiv.find('.notice-dismiss').on('click', function() {
            $(this).parent('.notice').fadeOut();
        });
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                messageDiv.find('.notice').fadeOut();
            }, 5000);
        }
    }
    
    // Export functionality for assignments
    $('#export_assignments').on('click', function(e) {
        e.preventDefault();
        
        var data = [];
        $('.wp-list-table tbody tr').each(function() {
            var row = {
                platform: $(this).find('td:eq(1)').text().trim(),
                username: $(this).find('td:eq(2)').text().trim(),
                email: $(this).find('td:eq(3)').text().trim(),
                type: $(this).find('td:eq(4)').text().trim(),
                assigned_to: $(this).find('td:eq(5)').text().trim(),
                split_percentage: $(this).find('td:eq(-2)').text().trim()
            };
            data.push(row);
        });
        
        // Convert to CSV
        var csv = 'Platform,Username,Email,Type,Assigned To,Split Percentage\n';
        data.forEach(function(row) {
            csv += '"' + row.platform + '","' + row.username + '","' + row.email + '","' + 
                   row.type + '","' + row.assigned_to + '","' + row.split_percentage + '"\n';
        });
        
        // Download CSV
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'account_assignments_' + new Date().toISOString().split('T')[0] + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });
    
    // Bulk actions
    $('#doaction, #doaction2').on('click', function(e) {
        var action = $(this).siblings('select').val();
        
        if (action === 'bulk_delete') {
            var checked = $('tbody .check-column input:checked');
            
            if (checked.length === 0) {
                alert('Please select at least one account.');
                e.preventDefault();
                return false;
            }
            
            if (!confirm('Are you sure you want to delete ' + checked.length + ' account(s)?')) {
                e.preventDefault();
                return false;
            }
        }
    });
    
    // Search functionality for logs with highlighting
    $('#search_logs').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.wp-list-table tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            if (searchTerm === '' || rowText.includes(searchTerm)) {
                $(this).show();
                
                // Highlight matching text
                if (searchTerm !== '') {
                    $(this).find('td').each(function() {
                        var text = $(this).text();
                        var regex = new RegExp('(' + searchTerm + ')', 'gi');
                        $(this).html(text.replace(regex, '<mark>$1</mark>'));
                    });
                }
            } else {
                $(this).hide();
            }
        });
    });
    
    // Date range filter for logs
    $('#filter_date_from, #filter_date_to').on('change', function() {
        filterLogsByDate();
    });
    
    function filterLogsByDate() {
        var dateFrom = $('#filter_date_from').val();
        var dateTo = $('#filter_date_to').val();
        
        if (!dateFrom && !dateTo) {
            $('.wp-list-table tbody tr').show();
            return;
        }
        
        $('.wp-list-table tbody tr').each(function() {
            var rowDate = $(this).find('td:first').text().trim();
            var rowDateObj = new Date(rowDate);
            var show = true;
            
            if (dateFrom && rowDateObj < new Date(dateFrom)) show = false;
            if (dateTo && rowDateObj > new Date(dateTo)) show = false;
            
            if (show) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
    
    // Account type indicator tooltip
    $('.wla-badge-public, .wla-badge-individual').on('mouseenter', function() {
        var type = $(this).hasClass('wla-badge-public') ? 'Public' : 'Individual';
        var message = type === 'Public' 
            ? 'This account has been reassigned and is now shared with split percentages'
            : 'This account is exclusively assigned to one user';
        
        $(this).attr('title', message);
    });
    
    // Validate split percentage inputs
    $('#split-first, #split-current').on('blur', function() {
        var value = parseFloat($(this).val());
        if (value < 0) $(this).val(0);
        if (value > 100) $(this).val(100);
        updateSplitTotal();
    });
    
});
