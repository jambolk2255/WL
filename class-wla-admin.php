<?php
/**
 * Enhanced Admin functionality with custom fields and split management
 * File: includes/class-wla-admin.php
 */

class WLA_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'handle_form_submissions'));
        add_action('wp_ajax_wla_update_split', array($this, 'ajax_update_split'));
        add_action('wp_ajax_wla_save_custom_fields', array($this, 'ajax_save_custom_fields'));
    }
    
    public function handle_form_submissions() {
        if (isset($_POST['wla_add_account']) && wp_verify_nonce($_POST['wla_nonce'], 'wla_add_account')) {
            $this->handle_add_account();
        }
        
        if (isset($_POST['wla_edit_account']) && wp_verify_nonce($_POST['wla_nonce'], 'wla_edit_account')) {
            $this->handle_edit_account();
        }
        
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['account_id'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_account')) {
                $this->handle_delete_account();
            }
        }
        
        if (isset($_POST['wla_save_custom_fields']) && wp_verify_nonce($_POST['wla_nonce'], 'wla_custom_fields')) {
            $this->handle_save_custom_fields();
        }
    }
    
    private function handle_add_account() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $data = array(
            'platform' => $_POST['platform'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'email' => $_POST['email'],
            'notes' => $_POST['notes'],
            'custom_field_1' => $_POST['custom_field_1'] ?? '',
            'custom_field_2' => $_POST['custom_field_2'] ?? '',
            'custom_field_3' => $_POST['custom_field_3'] ?? '',
            'custom_field_4' => $_POST['custom_field_4'] ?? '',
            'custom_field_5' => $_POST['custom_field_5'] ?? ''
        );
        
        if (WLA_Database::add_account($data)) {
            add_settings_error('wla_messages', 'wla_message', 'Account added successfully!', 'success');
        } else {
            add_settings_error('wla_messages', 'wla_message', 'Failed to add account.', 'error');
        }
    }
    
    private function handle_edit_account() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $account_id = intval($_POST['account_id']);
        $data = array(
            'platform' => $_POST['platform'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'email' => $_POST['email'],
            'notes' => $_POST['notes'],
            'custom_field_1' => $_POST['custom_field_1'] ?? '',
            'custom_field_2' => $_POST['custom_field_2'] ?? '',
            'custom_field_3' => $_POST['custom_field_3'] ?? '',
            'custom_field_4' => $_POST['custom_field_4'] ?? '',
            'custom_field_5' => $_POST['custom_field_5'] ?? ''
        );
        
        if (WLA_Database::update_account($account_id, $data)) {
            add_settings_error('wla_messages', 'wla_message', 'Account updated successfully!', 'success');
        } else {
            add_settings_error('wla_messages', 'wla_message', 'Failed to update account.', 'error');
        }
    }
    
    private function handle_save_custom_fields() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $labels = array(
            'custom_field_1' => sanitize_text_field($_POST['label_custom_field_1']),
            'custom_field_2' => sanitize_text_field($_POST['label_custom_field_2']),
            'custom_field_3' => sanitize_text_field($_POST['label_custom_field_3']),
            'custom_field_4' => sanitize_text_field($_POST['label_custom_field_4']),
            'custom_field_5' => sanitize_text_field($_POST['label_custom_field_5'])
        );
        
        if (WLA_Database::update_custom_field_labels($labels)) {
            add_settings_error('wla_messages', 'wla_message', 'Custom field labels updated!', 'success');
        }
    }
    
    public function ajax_update_split() {
        check_ajax_referer('wla_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $account_id = intval($_POST['account_id']);
        $split_first = floatval($_POST['split_first']);
        $split_current = floatval($_POST['split_current']);
        
        if (WLA_Database::update_split_percentage($account_id, $split_first, $split_current)) {
            wp_send_json_success('Split percentages updated successfully');
        } else {
            wp_send_json_error('Failed to update split percentages');
        }
    }
    
    public function ajax_save_custom_fields() {
        check_ajax_referer('wla_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $labels = array(
            'custom_field_1' => sanitize_text_field($_POST['labels']['custom_field_1']),
            'custom_field_2' => sanitize_text_field($_POST['labels']['custom_field_2']),
            'custom_field_3' => sanitize_text_field($_POST['labels']['custom_field_3']),
            'custom_field_4' => sanitize_text_field($_POST['labels']['custom_field_4']),
            'custom_field_5' => sanitize_text_field($_POST['labels']['custom_field_5'])
        );
        
        if (WLA_Database::update_custom_field_labels($labels)) {
            wp_send_json_success('Custom field labels updated');
        } else {
            wp_send_json_error('Failed to update custom field labels');
        }
    }
    
    private function handle_delete_account() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $account_id = intval($_GET['account_id']);
        
        if (WLA_Database::delete_account($account_id)) {
            wp_redirect(admin_url('admin.php?page=wla-accounts&message=deleted'));
            exit;
        }
    }
    
    public static function render_accounts_page() {
        $accounts = WLA_Database::get_all_accounts();
        $custom_labels = WLA_Database::get_custom_field_labels();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Learning Accounts</h1>
            <a href="<?php echo admin_url('admin.php?page=wla-add-account'); ?>" class="page-title-action">Add New</a>
            <a href="<?php echo admin_url('admin.php?page=wla-settings'); ?>" class="page-title-action">Settings</a>
            
            <?php settings_errors('wla_messages'); ?>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Account deleted successfully!</p>
                </div>
            <?php endif; ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="10%">Platform</th>
                        <th width="10%">Username</th>
                        <th width="10%">Email</th>
                        <th width="8%">Type</th>
                        <th width="12%">Assigned To</th>
                        <?php 
                        // Add columns for custom fields that have labels
                        foreach ($custom_labels as $field => $label) {
                            if (!empty($label)) {
                                echo '<th width="10%">' . esc_html($label) . '</th>';
                            }
                        }
                        ?>
                        <th width="10%">Split %</th>
                        <th width="15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php 
                        global $wpdb;
                        $table_assignments = $wpdb->prefix . 'wl_account_assignments';
                        $assignment = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $table_assignments WHERE account_id = %d AND status = 'active'",
                            $account->id
                        ));
                        $assigned_user = $assignment ? get_user_by('id', $assignment->user_id) : null;
                        $first_owner = $account->first_owner_id ? get_user_by('id', $account->first_owner_id) : null;
                        ?>
                        <tr>
                            <td><?php echo $account->id; ?></td>
                            <td><?php echo esc_html($account->platform); ?></td>
                            <td><?php echo esc_html($account->username); ?></td>
                            <td><?php echo esc_html($account->email); ?></td>
                            <td>
                                <?php if ($account->account_type === 'public'): ?>
                                    <span class="wla-badge-public">Public</span>
                                <?php else: ?>
                                    <span class="wla-badge-individual">Individual</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($assigned_user): ?>
                                    <?php echo esc_html($assigned_user->display_name); ?>
                                    <br><small>(<?php echo esc_html($assigned_user->user_email); ?>)</small>
                                <?php else: ?>
                                    <em>Not assigned</em>
                                <?php endif; ?>
                            </td>
                            <?php 
                            // Display custom field values
                            foreach ($custom_labels as $field => $label) {
                                if (!empty($label)) {
                                    echo '<td>' . esc_html($account->$field ?: '-') . '</td>';
                                }
                            }
                            ?>
                            <td>
                                <?php if ($account->account_type === 'public'): ?>
                                    <small>
                                        First: <?php echo $account->split_first_owner; ?>%<br>
                                        Current: <?php echo $account->split_current_owner; ?>%
                                        <a href="#" class="edit-split" data-account-id="<?php echo $account->id; ?>" 
                                           data-split-first="<?php echo $account->split_first_owner; ?>" 
                                           data-split-current="<?php echo $account->split_current_owner; ?>">
                                           [Edit]
                                        </a>
                                    </small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wla-add-account&action=edit&account_id=' . $account->id); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo admin_url('admin.php?page=wla-assignments&account_id=' . $account->id); ?>" class="button button-small">Assign</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wla-accounts&action=delete&account_id=' . $account->id), 'delete_account'); ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to delete this account?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Split Percentage Modal -->
        <div id="split-modal" class="wla-modal" style="display: none;">
            <div class="wla-modal-content">
                <span class="wla-modal-close">&times;</span>
                <h3>Edit Split Percentage</h3>
                <div class="split-form">
                    <input type="hidden" id="split-account-id">
                    <label>First Owner: <input type="number" id="split-first" min="0" max="100" step="0.01">%</label>
                    <label>Current Owner: <input type="number" id="split-current" min="0" max="100" step="0.01">%</label>
                    <p class="split-total">Total: <span id="split-total">100</span>%</p>
                    <button id="save-split" class="button button-primary">Save Split</button>
                </div>
            </div>
        </div>
        
        <style>
        .wla-badge-public { background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        .wla-badge-individual { background: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        .wla-modal { position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); }
        .wla-modal-content { background-color: #fff; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 400px; border-radius: 5px; }
        .wla-modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .split-form label { display: block; margin: 10px 0; }
        .split-form input[type="number"] { width: 80px; }
        .split-total { margin: 15px 0; font-weight: bold; }
        </style>
        <?php
    }
    
    public static function render_add_account_page() {
        $is_edit = isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['account_id']);
        $account = null;
        
        if ($is_edit) {
            $account = WLA_Database::get_account(intval($_GET['account_id']));
        }
        
        $custom_labels = WLA_Database::get_custom_field_labels();
        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit Account' : 'Add New Account'; ?></h1>
            
            <?php settings_errors('wla_messages'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field($is_edit ? 'wla_edit_account' : 'wla_add_account', 'wla_nonce'); ?>
                
                <?php if ($is_edit): ?>
                    <input type="hidden" name="account_id" value="<?php echo $account->id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th colspan="2"><h2>Basic Information</h2></th>
                    </tr>
                    <tr>
                        <th scope="row"><label for="platform">Platform Name *</label></th>
                        <td>
                            <input type="text" id="platform" name="platform" class="regular-text" required 
                                   value="<?php echo $is_edit ? esc_attr($account->platform) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="username">Username *</label></th>
                        <td>
                            <input type="text" id="username" name="username" class="regular-text" required 
                                   value="<?php echo $is_edit ? esc_attr($account->username) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="password">Password *</label></th>
                        <td>
                            <input type="password" id="password" name="password" class="regular-text" required 
                                   value="<?php echo $is_edit ? esc_attr($account->password) : ''; ?>">
                            <p class="description">Note: Consider using a secure password manager.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email">Email</label></th>
                        <td>
                            <input type="email" id="email" name="email" class="regular-text" 
                                   value="<?php echo $is_edit ? esc_attr($account->email) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notes">Notes</label></th>
                        <td>
                            <textarea id="notes" name="notes" rows="5" cols="50" class="large-text"><?php echo $is_edit ? esc_textarea($account->notes) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    <?php if ($is_edit && $account->account_type === 'public'): ?>
                    <tr>
                        <th scope="row">Account Type</th>
                        <td>
                            <span class="wla-badge-public">Public Account</span>
                            <?php if ($account->first_owner_id): ?>
                                <?php $first_owner = get_user_by('id', $account->first_owner_id); ?>
                                <p class="description">First Owner: <?php echo esc_html($first_owner->display_name); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php 
                    // Show custom fields if they have labels
                    $has_custom_fields = false;
                    foreach ($custom_labels as $field => $label) {
                        if (!empty($label)) {
                            $has_custom_fields = true;
                            break;
                        }
                    }
                    
                    if ($has_custom_fields): 
                    ?>
                    <tr>
                        <th colspan="2"><h2>Custom Fields</h2></th>
                    </tr>
                    <?php
                    foreach ($custom_labels as $field => $label) {
                        if (!empty($label)) {
                            $field_number = str_replace('custom_field_', '', $field);
                            $field_value = $is_edit ? $account->$field : '';
                            ?>
                            <tr>
                                <th scope="row"><label for="<?php echo $field; ?>"><?php echo esc_html($label); ?></label></th>
                                <td>
                                    <?php if ($field_number <= 2): ?>
                                        <input type="text" id="<?php echo $field; ?>" name="<?php echo $field; ?>" 
                                               class="regular-text" value="<?php echo esc_attr($field_value); ?>">
                                    <?php else: ?>
                                        <textarea id="<?php echo $field; ?>" name="<?php echo $field; ?>" 
                                                  rows="3" cols="50" class="large-text"><?php echo esc_textarea($field_value); ?></textarea>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    endif;
                    ?>
                </table>
                
                <p class="submit">
                    <input type="submit" name="<?php echo $is_edit ? 'wla_edit_account' : 'wla_add_account'; ?>" 
                           class="button button-primary" value="<?php echo $is_edit ? 'Update Account' : 'Add Account'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=wla-accounts'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    public static function render_settings_page() {
        $custom_labels = WLA_Database::get_custom_field_labels();
        ?>
        <div class="wrap">
            <h1>Learning Accounts Settings</h1>
            
            <?php settings_errors('wla_messages'); ?>
            
            <div class="wla-settings-section">
                <h2>Custom Field Configuration</h2>
                <p>Define labels for custom fields. These fields will appear in the account forms and tables when labels are set.</p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('wla_custom_fields', 'wla_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="label_custom_field_1">Custom Field 1 (Text)</label></th>
                            <td>
                                <input type="text" id="label_custom_field_1" name="label_custom_field_1" 
                                       class="regular-text" value="<?php echo esc_attr($custom_labels['custom_field_1']); ?>" 
                                       placeholder="e.g., License Key, API Key, etc.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="label_custom_field_2">Custom Field 2 (Text)</label></th>
                            <td>
                                <input type="text" id="label_custom_field_2" name="label_custom_field_2" 
                                       class="regular-text" value="<?php echo esc_attr($custom_labels['custom_field_2']); ?>" 
                                       placeholder="e.g., Account ID, Subscription Type, etc.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="label_custom_field_3">Custom Field 3 (Textarea)</label></th>
                            <td>
                                <input type="text" id="label_custom_field_3" name="label_custom_field_3" 
                                       class="regular-text" value="<?php echo esc_attr($custom_labels['custom_field_3']); ?>" 
                                       placeholder="e.g., Additional Notes, Description, etc.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="label_custom_field_4">Custom Field 4 (Textarea)</label></th>
                            <td>
                                <input type="text" id="label_custom_field_4" name="label_custom_field_4" 
                                       class="regular-text" value="<?php echo esc_attr($custom_labels['custom_field_4']); ?>" 
                                       placeholder="e.g., Terms of Service, Rules, etc.">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="label_custom_field_5">Custom Field 5 (Textarea)</label></th>
                            <td>
                                <input type="text" id="label_custom_field_5" name="label_custom_field_5" 
                                       class="regular-text" value="<?php echo esc_attr($custom_labels['custom_field_5']); ?>" 
                                       placeholder="e.g., Access Instructions, Support Info, etc.">
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="wla_save_custom_fields" class="button button-primary" value="Save Custom Fields">
                    </p>
                </form>
                
                <div class="wla-settings-info">
                    <h3>How Custom Fields Work</h3>
                    <ul>
                        <li>• Fields with labels will automatically appear in account forms and tables</li>
                        <li>• Leave a field label empty to hide that field</li>
                        <li>• Fields 1-2 are text inputs, Fields 3-5 are textarea inputs</li>
                        <li>• Custom fields are optional and can store any additional account information</li>
                    </ul>
                </div>
            </div>
            
            <style>
            .wla-settings-section {
                background: white;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin-top: 20px;
            }
            .wla-settings-info {
                background: #f1f1f1;
                padding: 15px;
                border-radius: 5px;
                margin-top: 20px;
            }
            .wla-settings-info ul {
                margin: 10px 0;
                padding-left: 20px;
            }
            </style>
        </div>
        <?php
    }
    
    public static function render_assignments_page() {
        $account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
        $accounts = WLA_Database::get_all_accounts();
        $users = get_users(array('orderby' => 'display_name'));
        
        if ($account_id) {
            global $wpdb;
            $table_assignments = $wpdb->prefix . 'wl_account_assignments';
            $current_assignment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_assignments WHERE account_id = %d AND status = 'active'",
                $account_id
            ));
            
            $selected_account = WLA_Database::get_account($account_id);
        }
        ?>
        <div class="wrap">
            <h1>Account Assignments</h1>
            
            <div class="wla-assignment-form">
                <h2>Assign or Reassign Account</h2>
                
                <?php if (isset($selected_account) && $selected_account->account_type === 'public'): ?>
                    <div class="notice notice-warning">
                        <p>
                            <strong>Public Account Notice:</strong> This account is marked as PUBLIC. 
                            Reassigning will maintain the split percentage between the first owner and new current owner.
                        </p>
                    </div>
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="account_select">Select Account</label></th>
                        <td>
                            <select id="account_select" class="regular-text">
                                <option value="">Select an account...</option>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?php echo $account->id; ?>" <?php selected($account_id, $account->id); ?>>
                                        <?php echo esc_html($account->platform . ' - ' . $account->username); ?>
                                        <?php if ($account->account_type === 'public'): ?>
                                            [PUBLIC]
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="user_select">Assign To</label></th>
                        <td>
                            <select id="user_select" class="regular-text">
                                <option value="">Select a user...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user->ID; ?>" 
                                            <?php echo (isset($current_assignment) && $current_assignment->user_id == $user->ID) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="assignment_notes">Notes</label></th>
                        <td>
                            <textarea id="assignment_notes" rows="3" cols="50" class="large-text" 
                                      placeholder="Optional notes about this assignment..."></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button id="assign_account_btn" class="button button-primary">
                        <?php echo isset($current_assignment) ? 'Reassign Account' : 'Assign Account'; ?>
                    </button>
                </p>
                
                <div id="assignment_message"></div>
            </div>
            
            <hr>
            
            <h2>Current Assignments</h2>
            <?php
            global $wpdb;
            $table_assignments = $wpdb->prefix . 'wl_account_assignments';
            $table_accounts = $wpdb->prefix . 'wl_accounts';
            
            $assignments = $wpdb->get_results(
                "SELECT a.*, acc.platform, acc.username, acc.account_type, acc.first_owner_id,
                        acc.split_first_owner, acc.split_current_owner, acc.splits_configured
                 FROM $table_assignments a 
                 JOIN $table_accounts acc ON a.account_id = acc.id 
                 WHERE a.status = 'active' 
                 ORDER BY a.assigned_date DESC"
            );
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Type</th>
                        <th>Assigned To</th>
                        <th>Assignment #</th>
                        <th>Assigned Date</th>
                        <th>Split %</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <?php 
                        $user = get_user_by('id', $assignment->user_id); 
                        $first_owner = $assignment->first_owner_id ? get_user_by('id', $assignment->first_owner_id) : null;
                        ?>
                        <tr>
                            <td><?php echo esc_html($assignment->platform . ' - ' . $assignment->username); ?></td>
                            <td>
                                <?php if ($assignment->account_type === 'public'): ?>
                                    <span class="wla-badge-public">Public</span>
                                <?php else: ?>
                                    <span class="wla-badge-individual">Individual</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user): ?>
                                    <?php echo esc_html($user->display_name); ?>
                                    <br><small><?php echo esc_html($user->user_email); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $assignment->assignment_count; ?></td>
                            <td><?php echo esc_html($assignment->assigned_date); ?></td>
                            <td>
                                <?php if ($assignment->account_type === 'public'): ?>
                                    <small>
                                        First (<?php echo $first_owner ? esc_html($first_owner->display_name) : 'Unknown'; ?>): <?php echo $assignment->split_first_owner; ?>%<br>
                                        Current: <?php echo $assignment->split_current_owner; ?>%
                                    </small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wla-assignments&account_id=' . $assignment->account_id); ?>" 
                                   class="button button-small">Reassign</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public static function render_logs_page() {
        $logs = WLA_Database::get_assignment_logs();
        ?>
        <div class="wrap">
            <h1>Assignment History</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Action</th>
                        <th>Type Change</th>
                        <th>From User</th>
                        <th>To User</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php 
                        $account = WLA_Database::get_account($log->account_id);
                        $old_user = $log->old_user_id ? get_user_by('id', $log->old_user_id) : null;
                        $new_user = $log->new_user_id ? get_user_by('id', $log->new_user_id) : null;
                        ?>
                        <tr>
                            <td><?php echo esc_html($log->date); ?></td>
                            <td>
                                <?php if ($account): ?>
                                    <?php echo esc_html($account->platform . ' - ' . $account->username); ?>
                                <?php else: ?>
                                    <em>Deleted Account</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="wla-action-badge wla-action-<?php echo esc_attr($log->action); ?>">
                                    <?php echo ucfirst(esc_html($log->action)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log->account_type_changed): ?>
                                    <span class="wla-badge-public">→ Public</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($old_user): ?>
                                    <?php echo esc_html($old_user->display_name); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($new_user): ?>
                                    <?php echo esc_html($new_user->display_name); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($log->notes ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
