<?php
/**
 * Database Class with Account Types and Split Percentage
 * File: includes/class-wla-database.php
 */

class WLA_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Enhanced Accounts table with account type and custom fields
        $table_accounts = $wpdb->prefix . 'wl_accounts';
        $sql_accounts = "CREATE TABLE $table_accounts (
            id INT(11) NOT NULL AUTO_INCREMENT,
            platform VARCHAR(255) NOT NULL,
            username VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            notes TEXT,
            account_type ENUM('individual', 'public') DEFAULT 'individual',
            first_owner_id INT(11) DEFAULT NULL,
            split_first_owner DECIMAL(5,2) DEFAULT 0,
            split_current_owner DECIMAL(5,2) DEFAULT 0,
            custom_field_1 VARCHAR(255),
            custom_field_2 VARCHAR(255),
            custom_field_3 TEXT,
            custom_field_4 TEXT,
            custom_field_5 TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY first_owner_id (first_owner_id)
        ) $charset_collate;";
        
        // Account assignments table
        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
        $sql_assignments = "CREATE TABLE $table_assignments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            account_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'inactive') DEFAULT 'active',
            assignment_count INT(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY account_id (account_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Enhanced Assignment logs table
        $table_logs = $wpdb->prefix . 'wl_assignment_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            account_id INT(11) NOT NULL,
            old_user_id INT(11),
            new_user_id INT(11),
            date DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            action VARCHAR(50),
            account_type_changed BOOLEAN DEFAULT FALSE,
            PRIMARY KEY (id),
            KEY account_id (account_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_accounts);
        dbDelta($sql_assignments);
        dbDelta($sql_logs);
        
        // Add custom field labels option if not exists
        if (!get_option('wla_custom_field_labels')) {
            add_option('wla_custom_field_labels', array(
                'custom_field_1' => '',
                'custom_field_2' => '',
                'custom_field_3' => '',
                'custom_field_4' => '',
                'custom_field_5' => ''
            ));
        }
    }
    
    public static function get_all_accounts() {
        global $wpdb;
        $table = $wpdb->prefix . 'wl_accounts';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }
    
    public static function get_account($account_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wl_accounts';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $account_id));
    }
    
    public static function add_account($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wl_accounts';
        
        $insert_data = array(
            'platform' => sanitize_text_field($data['platform']),
            'username' => sanitize_text_field($data['username']),
            'password' => $data['password'],
            'email' => sanitize_email($data['email']),
            'notes' => sanitize_textarea_field($data['notes']),
            'account_type' => 'individual', // New accounts start as individual
            'custom_field_1' => isset($data['custom_field_1']) ? sanitize_text_field($data['custom_field_1']) : '',
            'custom_field_2' => isset($data['custom_field_2']) ? sanitize_text_field($data['custom_field_2']) : '',
            'custom_field_3' => isset($data['custom_field_3']) ? sanitize_textarea_field($data['custom_field_3']) : '',
            'custom_field_4' => isset($data['custom_field_4']) ? sanitize_textarea_field($data['custom_field_4']) : '',
            'custom_field_5' => isset($data['custom_field_5']) ? sanitize_textarea_field($data['custom_field_5']) : ''
        );
        
        return $wpdb->insert($table, $insert_data);
    }
    
    public static function update_account($account_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'wl_accounts';
        
        $update_data = array(
            'platform' => sanitize_text_field($data['platform']),
            'username' => sanitize_text_field($data['username']),
            'password' => $data['password'],
            'email' => sanitize_email($data['email']),
            'notes' => sanitize_textarea_field($data['notes']),
            'custom_field_1' => isset($data['custom_field_1']) ? sanitize_text_field($data['custom_field_1']) : '',
            'custom_field_2' => isset($data['custom_field_2']) ? sanitize_text_field($data['custom_field_2']) : '',
            'custom_field_3' => isset($data['custom_field_3']) ? sanitize_textarea_field($data['custom_field_3']) : '',
            'custom_field_4' => isset($data['custom_field_4']) ? sanitize_textarea_field($data['custom_field_4']) : '',
            'custom_field_5' => isset($data['custom_field_5']) ? sanitize_textarea_field($data['custom_field_5']) : ''
        );
        
        return $wpdb->update($table, $update_data, array('id' => $account_id));
    }
    
    public static function delete_account($account_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'wl_accounts';
        return $wpdb->delete($table, array('id' => $account_id));
    }
    
    public static function assign_account($account_id, $user_id, $notes = '') {
        global $wpdb;
        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
        $table_logs = $wpdb->prefix . 'wl_assignment_logs';
        $table_accounts = $wpdb->prefix . 'wl_accounts';
        
        // Deactivate any existing assignments for this account
        $wpdb->update($table_assignments, 
            array('status' => 'inactive'), 
            array('account_id' => $account_id)
        );
        
        // Create new assignment
        $result = $wpdb->insert($table_assignments, array(
            'account_id' => $account_id,
            'user_id' => $user_id,
            'status' => 'active',
            'assignment_count' => 1
        ));
        
        if ($result) {
            // Update first_owner_id if this is the first assignment
            $account = self::get_account($account_id);
            if (!$account->first_owner_id) {
                $wpdb->update($table_accounts, 
                    array('first_owner_id' => $user_id), 
                    array('id' => $account_id)
                );
            }
            
            // Log the assignment
            $wpdb->insert($table_logs, array(
                'account_id' => $account_id,
                'old_user_id' => null,
                'new_user_id' => $user_id,
                'action' => 'assigned',
                'notes' => $notes
            ));
        }
        
        return $result;
    }
    
    public static function reassign_account($account_id, $new_user_id, $notes = '') {
        global $wpdb;
        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
        $table_logs = $wpdb->prefix . 'wl_assignment_logs';
        $table_accounts = $wpdb->prefix . 'wl_accounts';
        
        // Get current assignment to properly track old_user_id
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assignments WHERE account_id = %d AND status = 'active'",
            $account_id
        ));
        
        $old_user_id = $current ? $current->user_id : null;
        
        // Get total assignment count for this account
        $total_assignments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE account_id = %d AND (action = 'assigned' OR action = 'reassigned')",
            $account_id
        ));
        
        // Deactivate current assignment
        $wpdb->update($table_assignments, 
            array('status' => 'inactive'), 
            array('account_id' => $account_id)
        );
        
        // Create new assignment with incremented count
        $result = $wpdb->insert($table_assignments, array(
            'account_id' => $account_id,
            'user_id' => $new_user_id,
            'status' => 'active',
            'assignment_count' => $total_assignments + 1
        ));
        
        if ($result) {
            $account_type_changed = false;
            
            // Check if account should become public (after first reassignment)
            $account = self::get_account($account_id);
            if ($account->account_type === 'individual' && $total_assignments >= 1) {
                $wpdb->update($table_accounts, 
                    array(
                        'account_type' => 'public',
                        'split_first_owner' => 50.00,
                        'split_current_owner' => 50.00
                    ), 
                    array('id' => $account_id)
                );
                $account_type_changed = true;
            }
            
            // Log the reassignment with proper old_user_id
            $wpdb->insert($table_logs, array(
                'account_id' => $account_id,
                'old_user_id' => $old_user_id,
                'new_user_id' => $new_user_id,
                'action' => 'reassigned',
                'notes' => $notes,
                'account_type_changed' => $account_type_changed
            ));
            
            // Send notifications
            if ($old_user_id) {
                // Notify old user about removal
                WLA_Notifications::send_assignment_notification($old_user_id, $account_id, 'removed');
            }
            
            // Notify new user about reassignment
            WLA_Notifications::send_assignment_notification($new_user_id, $account_id, 'reassigned');
            
            // If account became public, notify about split percentage
            if ($account_type_changed && $account->first_owner_id) {
                WLA_Notifications::send_split_notification($account->first_owner_id, $new_user_id, $account_id);
            }
        }
        
        return $result;
    }
    
    public static function update_split_percentage($account_id, $split_first, $split_current) {
        global $wpdb;
        $table_accounts = $wpdb->prefix . 'wl_accounts';
        
        $account = self::get_account($account_id);
        if (!$account || $account->account_type !== 'public') {
            return false;
        }
        
        // Ensure splits add up to 100
        if (($split_first + $split_current) != 100) {
            return false;
        }
        
        // Check if this is the first time splits are being configured
        $is_first_configuration = !$account->splits_configured;
        
        $result = $wpdb->update($table_accounts, 
            array(
                'split_first_owner' => $split_first,
                'split_current_owner' => $split_current,
                'splits_configured' => 1 // Mark as configured
            ), 
            array('id' => $account_id)
        );
        
        if ($result) {
            // Get current assignment
            $table_assignments = $wpdb->prefix . 'wl_account_assignments';
            $current = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_assignments WHERE account_id = %d AND status = 'active'",
                $account_id
            ));
            
            if ($current && $account->first_owner_id) {
                if ($is_first_configuration) {
                    // First time setting splits - send initial notification
                    WLA_Notifications::send_split_notification(
                        $account->first_owner_id, 
                        $current->user_id, 
                        $account_id,
                        $split_first,
                        $split_current
                    );
                } else {
                    // Splits being updated - send update notification
                    WLA_Notifications::send_split_update_notification(
                        $account->first_owner_id, 
                        $current->user_id, 
                        $account_id, 
                        $split_first, 
                        $split_current
                    );
                }
            }
        }
        
        return $result;
    }
    
    public static function get_user_assignments($user_id) {
        global $wpdb;
        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
        $table_accounts = $wpdb->prefix . 'wl_accounts';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, acc.* 
             FROM $table_assignments a 
             JOIN $table_accounts acc ON a.account_id = acc.id 
             WHERE a.user_id = %d AND a.status = 'active'",
            $user_id
        ));
    }
    
    public static function get_assignment_logs($account_id = null) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'wl_assignment_logs';
        
        if ($account_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_logs WHERE account_id = %d ORDER BY date DESC",
                $account_id
            ));
        } else {
            return $wpdb->get_results("SELECT * FROM $table_logs ORDER BY date DESC");
        }
    }
    
    public static function update_custom_field_labels($labels) {
        return update_option('wla_custom_field_labels', $labels);
    }
    
    public static function get_custom_field_labels() {
        return get_option('wla_custom_field_labels', array(
            'custom_field_1' => '',
            'custom_field_2' => '',
            'custom_field_3' => '',
            'custom_field_4' => '',
            'custom_field_5' => ''
        ));
    }
}
