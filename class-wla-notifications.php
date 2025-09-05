<?php
/**
 * Enhanced Notifications handler with reassignment and split notifications
 * File: includes/class-wla-notifications.php
 */

class WLA_Notifications {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_notices', array($this, 'display_admin_notices'));
        add_action('wp_ajax_wla_dismiss_notice', array($this, 'ajax_dismiss_notice'));
        add_action('wp_ajax_nopriv_wla_dismiss_notice', array($this, 'ajax_dismiss_notice'));
    }
    
    public function init() {
        if (!wp_next_scheduled('wla_daily_notification_check')) {
            wp_schedule_event(time(), 'daily', 'wla_daily_notification_check');
        }
        add_action('wla_daily_notification_check', array($this, 'send_daily_notifications'));
    }
    
    /**
     * Send email notification to user about account assignment
     */
    public static function send_assignment_notification($user_id, $account_id, $action = 'assigned') {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $account = WLA_Database::get_account($account_id);
        if (!$account) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $subject = '';
        $message = '';
        
        switch ($action) {
            case 'assigned':
                $subject = sprintf('[%s] New Account Assigned to You', $site_name);
                $message = self::get_assignment_email_template($user, $account);
                break;
                
            case 'reassigned':
                $subject = sprintf('[%s] Account Reassigned to You', $site_name);
                $message = self::get_reassignment_email_template($user, $account);
                break;
                
            case 'removed':
                $subject = sprintf('[%s] Account Access Removed', $site_name);
                $message = self::get_removal_email_template($user, $account);
                break;
        }
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($user->user_email, $subject, $message, $headers);
        
        self::create_dashboard_notification($user_id, $account_id, $action);
        
        return $sent;
    }
    
    /**
     * Send notification about split percentage
     */
    public static function send_split_notification($first_owner_id, $current_owner_id, $account_id) {
        $account = WLA_Database::get_account($account_id);
        if (!$account) {
            return false;
        }
        
        $first_owner = get_user_by('id', $first_owner_id);
        $current_owner = get_user_by('id', $current_owner_id);
        
        if (!$first_owner || !$current_owner) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $subject = sprintf('[%s] Account Changed to Public - Split Percentage Applied', $site_name);
        
        // Email to first owner
        $message_first = self::get_split_notification_template($first_owner, $current_owner, $account, 'first');
        wp_mail($first_owner->user_email, $subject, $message_first, array('Content-Type: text/html; charset=UTF-8'));
        
        // Email to current owner
        $message_current = self::get_split_notification_template($current_owner, $first_owner, $account, 'current');
        wp_mail($current_owner->user_email, $subject, $message_current, array('Content-Type: text/html; charset=UTF-8'));
        
        return true;
    }
    
    /**
     * Send notification about split percentage update
     */
    public static function send_split_update_notification($first_owner_id, $current_owner_id, $account_id, $split_first, $split_current) {
        $account = WLA_Database::get_account($account_id);
        $first_owner = get_user_by('id', $first_owner_id);
        $current_owner = get_user_by('id', $current_owner_id);
        
        if (!$first_owner || !$current_owner || !$account) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $subject = sprintf('[%s] Split Percentage Updated', $site_name);
        
        $message = self::get_split_update_email_template($account, $split_first, $split_current);
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Send to both users
        wp_mail($first_owner->user_email, $subject, $message, $headers);
        wp_mail($current_owner->user_email, $subject, $message, $headers);
        
        return true;
    }
    
    /**
     * Create dashboard notification for user
     */
    public static function create_dashboard_notification($user_id, $account_id, $action) {
        $notifications = get_user_meta($user_id, 'wla_notifications', true);
        if (!is_array($notifications)) {
            $notifications = array();
        }
        
        $account = WLA_Database::get_account($account_id);
        
        $notification = array(
            'id' => uniqid('wla_'),
            'account_id' => $account_id,
            'action' => $action,
            'message' => self::get_dashboard_notification_message($account, $action),
            'date' => current_time('mysql'),
            'read' => false
        );
        
        array_unshift($notifications, $notification);
        $notifications = array_slice($notifications, 0, 20);
        
        update_user_meta($user_id, 'wla_notifications', $notifications);
    }
    
    private static function get_dashboard_notification_message($account, $action) {
        switch ($action) {
            case 'assigned':
                return sprintf('New account assigned: %s (%s)', $account->platform, $account->username);
            case 'reassigned':
                return sprintf('Account reassigned to you: %s (%s) - This is a %s account', 
                    $account->platform, $account->username, $account->account_type);
            case 'removed':
                return sprintf('Account access removed: %s (%s)', $account->platform, $account->username);
            case 'split_updated':
                return sprintf('Split percentage updated for: %s (%s)', $account->platform, $account->username);
            default:
                return 'Account update';
        }
    }
    
    /**
     * Get email template for initial assignment
     */
    private static function get_assignment_email_template($user, $account) {
        $site_name = get_bloginfo('name');
        $dashboard_url = home_url('/student-dashboard/');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
                .account-details { background-color: white; padding: 15px; margin: 20px 0; border-left: 4px solid #4CAF50; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; }
                .warning { background-color: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 15px 0; border-radius: 5px; }
                .badge { display: inline-block; padding: 3px 8px; background-color: #e3f2fd; color: #1976d2; border-radius: 3px; font-size: 12px; margin-left: 10px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2><?php echo esc_html($site_name); ?></h2>
                    <p>New Account Assignment</p>
                </div>
                
                <div class="content">
                    <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                    
                    <p>A new account has been assigned to you on <?php echo esc_html($site_name); ?>.</p>
                    
                    <div class="account-details">
                        <h3>Account Details:</h3>
                        <p><strong>Platform:</strong> <?php echo esc_html($account->platform); ?></p>
                        <p><strong>Username:</strong> <?php echo esc_html($account->username); ?></p>
                        <p><strong>Email:</strong> <?php echo esc_html($account->email ?: 'N/A'); ?></p>
                        <p><strong>Account Type:</strong> Individual <span class="badge">First Assignment</span></p>
                        <?php if ($account->notes): ?>
                            <p><strong>Notes:</strong> <?php echo nl2br(esc_html($account->notes)); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="warning">
                        <strong>Important:</strong> This is an individual account assigned exclusively to you. Please keep this information secure and do not share your login credentials with others.
                    </div>
                    
                    <p>You can view all your assigned accounts on your dashboard:</p>
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="button">View Dashboard</a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>This is an automated message from <?php echo esc_html($site_name); ?>.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get email template for reassignment
     */
    private static function get_reassignment_email_template($user, $account) {
        $site_name = get_bloginfo('name');
        $dashboard_url = home_url('/student-dashboard/');
        
        $first_owner = null;
        if ($account->first_owner_id) {
            $first_owner = get_user_by('id', $account->first_owner_id);
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2196F3; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
                .account-details { background-color: white; padding: 15px; margin: 20px 0; border-left: 4px solid #2196F3; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background-color: #2196F3; color: white; text-decoration: none; border-radius: 5px; }
                .info { background-color: #e3f2fd; border: 1px solid #2196F3; padding: 10px; margin: 15px 0; border-radius: 5px; }
                .badge-public { display: inline-block; padding: 3px 8px; background-color: #ff9800; color: white; border-radius: 3px; font-size: 12px; margin-left: 10px; }
                .split-info { background-color: #fff3e0; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #ff9800; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2><?php echo esc_html($site_name); ?></h2>
                    <p>Account Reassigned to You</p>
                </div>
                
                <div class="content">
                    <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                    
                    <p>An account has been <strong>reassigned</strong> to you on <?php echo esc_html($site_name); ?>.</p>
                    
                    <div class="account-details">
                        <h3>Account Details:</h3>
                        <p><strong>Platform:</strong> <?php echo esc_html($account->platform); ?></p>
                        <p><strong>Username:</strong> <?php echo esc_html($account->username); ?></p>
                        <p><strong>Email:</strong> <?php echo esc_html($account->email ?: 'N/A'); ?></p>
                        <p>
                            <strong>Account Type:</strong> 
                            <?php if ($account->account_type === 'public'): ?>
                                Public <span class="badge-public">Shared Account</span>
                            <?php else: ?>
                                Individual
                            <?php endif; ?>
                        </p>
                        <?php if ($account->notes): ?>
                            <p><strong>Notes:</strong> <?php echo nl2br(esc_html($account->notes)); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($account->account_type === 'public' && $account->splits_configured): ?>
                        <div class="split-info">
                            <strong>⚠️ Public Account Notice:</strong><br>
                            This account is marked as PUBLIC with configured split percentages.<br>
                            <strong>Split Percentage:</strong><br>
                            • First Owner (<?php echo $first_owner ? esc_html($first_owner->display_name) : 'Unknown'; ?>): <?php echo esc_html($account->split_first_owner); ?>%<br>
                            • Current Owner (You): <?php echo esc_html($account->split_current_owner); ?>%
                        </div>
                    <?php elseif ($account->account_type === 'public'): ?>
                        <div class="split-info">
                            <strong>⚠️ Public Account Notice:</strong><br>
                            This account is now marked as PUBLIC.<br>
                            The administrator will configure split percentages soon. You will be notified once the splits are set.
                        </div>
                    <?php endif; ?>
                    
                    <div class="info">
                        <strong>Note:</strong> This account was previously assigned to another user. 
                        <?php if ($account->account_type === 'public'): ?>
                            As a public account, usage and benefits are shared according to the split percentage shown above.
                        <?php else: ?>
                            Please ensure you follow all usage guidelines.
                        <?php endif; ?>
                    </div>
                    
                    <p>You can view all your assigned accounts on your dashboard:</p>
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="button">View Dashboard</a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>This is an automated message from <?php echo esc_html($site_name); ?>.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get email template for removal
     */
    private static function get_removal_email_template($user, $account) {
        $site_name = get_bloginfo('name');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #ff9800; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
                .account-info { background-color: white; padding: 15px; margin: 20px 0; border-left: 4px solid #ff9800; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; }
                .notice { background-color: #fff3e0; border: 1px solid #ff9800; padding: 10px; margin: 15px 0; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2><?php echo esc_html($site_name); ?></h2>
                    <p>Account Access Removed</p>
                </div>
                
                <div class="content">
                    <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                    
                    <p>Your access to the following account has been removed and reassigned to another user:</p>
                    
                    <div class="account-info">
                        <p><strong>Platform:</strong> <?php echo esc_html($account->platform); ?></p>
                        <p><strong>Username:</strong> <?php echo esc_html($account->username); ?></p>
                        <p><strong>Removal Date:</strong> <?php echo date('F j, Y, g:i a'); ?></p>
                    </div>
                    
                    <div class="notice">
                        <strong>Important:</strong> You no longer have access to this account. Please ensure you have retrieved any necessary data before the removal date.
                    </div>
                    
                    <p>If you believe this is an error or have any questions, please contact the administrator immediately.</p>
                    
                    <?php if ($account->account_type === 'public' && $account->first_owner_id == $user->ID): ?>
                        <div class="notice">
                            <strong>Note for First Owner:</strong> As the first owner of this public account, you may still be entitled to benefits according to the split percentage arrangement. Please contact administration for details.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="footer">
                    <p>This is an automated message from <?php echo esc_html($site_name); ?>.</p>
                    <p>If you have questions, please contact support.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get split notification template
     */
    private static function get_split_notification_template($recipient, $other_user, $account, $recipient_type) {
        $site_name = get_bloginfo('name');
        $dashboard_url = home_url('/student-dashboard/');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #ff9800; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
                .split-details { background-color: white; padding: 15px; margin: 20px 0; border-left: 4px solid #ff9800; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background-color: #ff9800; color: white; text-decoration: none; border-radius: 5px; }
                .percentage { font-size: 24px; font-weight: bold; color: #ff9800; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2><?php echo esc_html($site_name); ?></h2>
                    <p>Account Status Changed to Public</p>
                </div>
                
                <div class="content">
                    <p>Hello <?php echo esc_html($recipient->display_name); ?>,</p>
                    
                    <p>An important update regarding account <strong><?php echo esc_html($account->platform . ' - ' . $account->username); ?></strong>:</p>
                    
                    <div class="split-details">
                        <h3>Account is Now Public</h3>
                        <p>This account has been reassigned and is now marked as a <strong>PUBLIC</strong> account.</p>
                        
                        <h4>Split Percentage Arrangement:</h4>
                        <p>
                            <?php if ($recipient_type === 'first'): ?>
                                As the first owner, you are entitled to: <span class="percentage"><?php echo esc_html($account->split_first_owner); ?>%</span>
                            <?php else: ?>
                                As the current owner, you are entitled to: <span class="percentage"><?php echo esc_html($account->split_current_owner); ?>%</span>
                            <?php endif; ?>
                        </p>
                        
                        <h4>Sharing Details:</h4>
                        <ul>
                            <li>First Owner: <?php echo $recipient_type === 'first' ? 'You' : esc_html($other_user->display_name); ?> - <?php echo esc_html($account->split_first_owner); ?>%</li>
                            <li>Current Owner: <?php echo $recipient_type === 'current' ? 'You' : esc_html($other_user->display_name); ?> - <?php echo esc_html($account->split_current_owner); ?>%</li>
                        </ul>
                    </div>
                    
                    <p>This split percentage applies to any benefits, revenue, or usage rights associated with this account.</p>
                    
                    <p style="text-align: center;">
                        <a href="<?php echo esc_url($dashboard_url); ?>" class="button">View Your Dashboard</a>
                    </p>
                </div>
                
                <div class="footer">
                    <p>This is an automated message from <?php echo esc_html($site_name); ?>.</p>
                    <p>For questions about split percentages, please contact administration.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get split update email template
     */
    private static function get_split_update_email_template($account, $split_first, $split_current) {
        $site_name = get_bloginfo('name');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #673ab7; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
                .update-details { background-color: white; padding: 15px; margin: 20px 0; border-left: 4px solid #673ab7; }
                .percentage { font-size: 20px; font-weight: bold; color: #673ab7; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2><?php echo esc_html($site_name); ?></h2>
                    <p>Split Percentage Updated</p>
                </div>
                
                <div class="content">
                    <p>Hello,</p>
                    
                    <p>The split percentage for account <strong><?php echo esc_html($account->platform . ' - ' . $account->username); ?></strong> has been updated.</p>
                    
                    <div class="update-details">
                        <h3>New Split Percentages:</h3>
                        <p>First Owner: <span class="percentage"><?php echo esc_html($split_first); ?>%</span></p>
                        <p>Current Owner: <span class="percentage"><?php echo esc_html($split_current); ?>%</span></p>
                    </div>
                    
                    <p>These new percentages are effective immediately.</p>
                </div>
                
                <div class="footer">
                    <p>This is an automated message from <?php echo esc_html($site_name); ?>.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    public function display_admin_notices() {
        if (!is_admin()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $notifications = get_user_meta($user_id, 'wla_notifications', true);
        
        if (!is_array($notifications) || empty($notifications)) {
            return;
        }
        
        foreach ($notifications as $key => $notification) {
            if (!$notification['read']) {
                ?>
                <div class="notice notice-info is-dismissible wla-notice" data-notice-id="<?php echo esc_attr($notification['id']); ?>">
                    <p>
                        <strong>Account Update:</strong> 
                        <?php echo esc_html($notification['message']); ?>
                        <small>(<?php echo esc_html(human_time_diff(strtotime($notification['date']), current_time('timestamp')) . ' ago'); ?>)</small>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    public function ajax_dismiss_notice() {
        $notice_id = sanitize_text_field($_POST['notice_id']);
        $user_id = get_current_user_id();
        
        $notifications = get_user_meta($user_id, 'wla_notifications', true);
        
        if (is_array($notifications)) {
            foreach ($notifications as &$notification) {
                if ($notification['id'] === $notice_id) {
                    $notification['read'] = true;
                    break;
                }
            }
            update_user_meta($user_id, 'wla_notifications', $notifications);
        }
        
        wp_die();
    }
    
    public function send_daily_notifications() {
        // Placeholder for future daily summary functionality
    }
    
    public static function get_unread_count($user_id) {
        $notifications = get_user_meta($user_id, 'wla_notifications', true);
        
        if (!is_array($notifications)) {
            return 0;
        }
        
        $unread = 0;
        foreach ($notifications as $notification) {
            if (!$notification['read']) {
                $unread++;
            }
        }
        
        return $unread;
    }
    
    public static function mark_all_read($user_id) {
        $notifications = get_user_meta($user_id, 'wla_notifications', true);
        
        if (is_array($notifications)) {
            foreach ($notifications as &$notification) {
                $notification['read'] = true;
            }
            update_user_meta($user_id, 'wla_notifications', $notifications);
        }
    }
}
