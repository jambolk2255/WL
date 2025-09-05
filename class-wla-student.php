<?php
/**
 * Enhanced Student functionality with account types display
 * File: includes/class-wla-student.php
 */

class WLA_Student {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_wla_mark_notifications_read', array($this, 'ajax_mark_notifications_read'));
        add_action('wp_ajax_nopriv_wla_mark_notifications_read', array($this, 'ajax_mark_notifications_read'));
    }
    
    /**
     * Render the student dashboard
     */
    public static function render_dashboard() {
        if (!is_user_logged_in()) {
            return '<div class="wla-notice wla-notice-warning">Please log in to view your assigned accounts.</div>';
        }
        
        $user_id = get_current_user_id();
        $assignments = WLA_Database::get_user_assignments($user_id);
        $notifications = get_user_meta($user_id, 'wla_notifications', true);
        
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        // Also check if user is a first owner of any public accounts
        global $wpdb;
        $table_accounts = $wpdb->prefix . 'wl_accounts';
        $first_owner_accounts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_accounts WHERE first_owner_id = %d AND account_type = 'public'",
            $user_id
        ));
        
        $custom_labels = WLA_Database::get_custom_field_labels();
        
        ob_start();
        ?>
        <div class="wla-student-dashboard">
            <h2>My Learning Accounts</h2>
            
            <?php if (!empty($notifications)): ?>
                <div class="wla-notifications-section">
                    <h3>Recent Updates 
                        <?php 
                        $unread_count = WLA_Notifications::get_unread_count($user_id);
                        if ($unread_count > 0): 
                        ?>
                            <span class="wla-badge"><?php echo $unread_count; ?> new</span>
                        <?php endif; ?>
                    </h3>
                    
                    <div class="wla-notifications-list">
                        <?php 
                        $shown_notifications = array_slice($notifications, 0, 5);
                        foreach ($shown_notifications as $notification): 
                        ?>
                            <div class="wla-notification <?php echo !$notification['read'] ? 'wla-notification-unread' : ''; ?>" 
                                 data-notification-id="<?php echo esc_attr($notification['id']); ?>">
                                <div class="wla-notification-content">
                                    <p><?php echo esc_html($notification['message']); ?></p>
                                    <small class="wla-notification-date">
                                        <?php echo esc_html(human_time_diff(strtotime($notification['date']), current_time('timestamp')) . ' ago'); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($unread_count > 0): ?>
                        <button class="wla-btn wla-btn-secondary" id="wla-mark-all-read">Mark All as Read</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="wla-accounts-section">
                <h3>Currently Assigned Accounts</h3>
                
                <?php if (empty($assignments)): ?>
                    <div class="wla-notice wla-notice-info">
                        <p>No accounts are currently assigned to you.</p>
                        <p>Please contact your instructor if you believe this is an error.</p>
                    </div>
                <?php else: ?>
                    <div class="wla-accounts-grid">
                        <?php foreach ($assignments as $assignment): ?>
                            <?php 
                            $first_owner = null;
                            if ($assignment->first_owner_id && $assignment->first_owner_id != $user_id) {
                                $first_owner = get_user_by('id', $assignment->first_owner_id);
                            }
                            ?>
                            <div class="wla-account-card <?php echo $assignment->account_type === 'public' ? 'wla-account-public' : ''; ?>">
                                <div class="wla-account-header">
                                    <h4><?php echo esc_html($assignment->platform); ?></h4>
                                    <div class="wla-account-badges">
                                        <span class="wla-account-status">Active</span>
                                        <?php if ($assignment->account_type === 'public'): ?>
                                            <span class="wla-account-type-badge">Public</span>
                                        <?php else: ?>
                                            <span class="wla-account-type-badge wla-individual">Individual</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="wla-account-body">
                                    <?php if ($assignment->account_type === 'public'): ?>
                                        <div class="wla-split-info">
                                            <strong>Split Arrangement:</strong>
                                            <div class="wla-split-details">
                                                <?php if ($first_owner): ?>
                                                    <span>First Owner (<?php echo esc_html($first_owner->display_name); ?>): <?php echo esc_html($assignment->split_first_owner); ?>%</span>
                                                <?php elseif ($assignment->first_owner_id == $user_id): ?>
                                                    <span>You (First Owner): <?php echo esc_html($assignment->split_first_owner); ?>%</span>
                                                <?php endif; ?>
                                                <span>Current Owner (You): <?php echo esc_html($assignment->split_current_owner); ?>%</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="wla-account-field">
                                        <label>Username:</label>
                                        <div class="wla-account-value">
                                            <span id="username-<?php echo $assignment->account_id; ?>">
                                                <?php echo esc_html($assignment->username); ?>
                                            </span>
                                            <button class="wla-copy-btn" data-copy="username-<?php echo $assignment->account_id; ?>" title="Copy username">
                                                📋
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="wla-account-field">
                                        <label>Password:</label>
                                        <div class="wla-account-value">
                                            <span class="wla-password-field" id="password-<?php echo $assignment->account_id; ?>" data-password="<?php echo esc_attr($assignment->password); ?>">
                                                ••••••••
                                            </span>
                                            <button class="wla-toggle-password" data-target="password-<?php echo $assignment->account_id; ?>" title="Show/hide password">
                                                👁️
                                            </button>
                                            <button class="wla-copy-btn" data-copy="password-<?php echo $assignment->account_id; ?>" data-copy-value="<?php echo esc_attr($assignment->password); ?>" title="Copy password">
                                                📋
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <?php if ($assignment->email): ?>
                                        <div class="wla-account-field">
                                            <label>Email:</label>
                                            <div class="wla-account-value">
                                                <span id="email-<?php echo $assignment->account_id; ?>">
                                                    <?php echo esc_html($assignment->email); ?>
                                                </span>
                                                <button class="wla-copy-btn" data-copy="email-<?php echo $assignment->account_id; ?>" title="Copy email">
                                                    📋
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Display custom fields if they have values
                                    foreach ($custom_labels as $field => $label) {
                                        if (!empty($label) && !empty($assignment->$field)) {
                                            ?>
                                            <div class="wla-account-field">
                                                <label><?php echo esc_html($label); ?>:</label>
                                                <div class="wla-account-value">
                                                    <span id="<?php echo $field; ?>-<?php echo $assignment->account_id; ?>">
                                                        <?php echo esc_html($assignment->$field); ?>
                                                    </span>
                                                    <button class="wla-copy-btn" data-copy="<?php echo $field; ?>-<?php echo $assignment->account_id; ?>" title="Copy <?php echo esc_attr($label); ?>">
                                                        📋
                                                    </button>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($assignment->notes): ?>
                                        <div class="wla-account-field wla-account-notes">
                                            <label>Notes:</label>
                                            <p><?php echo nl2br(esc_html($assignment->notes)); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="wla-account-meta">
                                        <small>Assigned: <?php echo esc_html(date('M j, Y', strtotime($assignment->assigned_date))); ?></small>
                                        <?php if ($assignment->assignment_count > 1): ?>
                                            <small class="wla-reassignment-count">Assignment #<?php echo $assignment->assignment_count; ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($first_owner_accounts)): ?>
            <div class="wla-accounts-section">
                <h3>Accounts Where You Are First Owner</h3>
                <p class="wla-section-description">These are public accounts where you maintain split percentage rights even though they're assigned to others.</p>
                
                <div class="wla-first-owner-list">
                    <?php foreach ($first_owner_accounts as $account): ?>
                        <?php
                        // Get current assignment
                        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
                        $current_assignment = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $table_assignments WHERE account_id = %d AND status = 'active'",
                            $account->id
                        ));
                        $current_owner = $current_assignment ? get_user_by('id', $current_assignment->user_id) : null;
                        ?>
                        <div class="wla-first-owner-item">
                            <div class="wla-first-owner-header">
                                <strong><?php echo esc_html($account->platform . ' - ' . $account->username); ?></strong>
                                <span class="wla-badge-public">Public Account</span>
                            </div>
                            <div class="wla-first-owner-details">
                                <p>Current Owner: <?php echo $current_owner ? esc_html($current_owner->display_name) : 'Unassigned'; ?></p>
                                <?php if ($account->splits_configured): ?>
                                    <p>Your Split: <strong><?php echo esc_html($account->split_first_owner); ?>%</strong></p>
                                    <p>Current Owner Split: <strong><?php echo esc_html($account->split_current_owner); ?>%</strong></p>
                                <?php else: ?>
                                    <p style="color: #ff9800; font-style: italic;">
                                        Split percentages pending administrator configuration
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="wla-help-section">
                <h3>Understanding Account Types</h3>
                <div class="wla-help-grid">
                    <div class="wla-help-item">
                        <h4>🔒 Individual Accounts</h4>
                        <p>Exclusively assigned to you. Full access and benefits.</p>
                    </div>
                    <div class="wla-help-item">
                        <h4>🔄 Public Accounts</h4>
                        <p>Shared accounts with split percentages between first and current owners.</p>
                    </div>
                </div>
                
                <h3>Need Help?</h3>
                <p>If you have any issues with your accounts or need assistance, please contact your instructor or administrator.</p>
                <p>Remember to keep your login credentials secure and never share them with others.</p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle password visibility
            $('.wla-toggle-password').on('click', function() {
                var target = $(this).data('target');
                var passwordField = $('#' + target);
                var actualPassword = passwordField.data('password');
                
                if (passwordField.text() === '••••••••') {
                    passwordField.text(actualPassword);
                    $(this).text('🔒');
                } else {
                    passwordField.text('••••••••');
                    $(this).text('👁️');
                }
            });
            
            // Copy to clipboard functionality
            $('.wla-copy-btn').on('click', function() {
                var copyTarget = $(this).data('copy');
                var copyValue = $(this).data('copy-value');
                var textToCopy = copyValue || $('#' + copyTarget).text();
                
                // Create temporary input element
                var tempInput = $('<input>');
                $('body').append(tempInput);
                tempInput.val(textToCopy).select();
                document.execCommand('copy');
                tempInput.remove();
                
                // Show feedback
                var originalText = $(this).html();
                $(this).html('✅');
                setTimeout(() => {
                    $(this).html(originalText);
                }, 2000);
            });
            
            // Mark all notifications as read
            $('#wla-mark-all-read').on('click', function() {
                $.ajax({
                    url: wla_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wla_mark_notifications_read',
                        user_id: <?php echo $user_id; ?>,
                        nonce: wla_ajax.nonce
                    },
                    success: function() {
                        $('.wla-notification-unread').removeClass('wla-notification-unread');
                        $('#wla-mark-all-read').fadeOut();
                        $('.wla-badge').fadeOut();
                    }
                });
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * AJAX handler to mark notifications as read
     */
    public function ajax_mark_notifications_read() {
        check_ajax_referer('wla_ajax_nonce', 'nonce');
        
        $user_id = intval($_POST['user_id']);
        
        if ($user_id !== get_current_user_id()) {
            wp_die('Unauthorized');
        }
        
        WLA_Notifications::mark_all_read($user_id);
        
        wp_send_json_success();
    }
}
