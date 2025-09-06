<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$global_assign_table = $wpdb->prefix . 'rbic_global_role_assignments';
$all_roles = wp_roles()->roles;

// --- FORM PROCESSING ---
if (isset($_POST['save_global_assignments']) && check_admin_referer('rbic_save_global_assignments_action', 'rbic_save_global_assignments_nonce')) {
    $assignments = $_POST['global_assignments'] ?? [];

    foreach ($all_roles as $role_key => $role_data) {
        $new_user_id = !empty($assignments[$role_key]) ? intval($assignments[$role_key]) : 0;

        // Get the old assignment for logging
        $old_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $global_assign_table WHERE role = %s", $role_key));
        $old_user_id = $old_user_id ? intval($old_user_id) : 0;

        if ($new_user_id !== $old_user_id) {
            // Delete existing assignment
            $wpdb->delete($global_assign_table, ['role' => $role_key]);

            // Insert new one if a user was selected
            if ($new_user_id > 0) {
                $wpdb->insert($global_assign_table, [
                    'role'        => $role_key,
                    'user_id'     => $new_user_id,
                    'assigned_at' => current_time('mysql')
                ]);
            }

            // Add to audit log
            Rbic_Log::add(
                'global_assignment_changed',
                "Global assignment for '{$role_data['name']}' changed.",
                ['role' => $role_key, 'from_user' => $old_user_id, 'to_user' => $new_user_id]
            );
        }
    }
    echo '<div class="notice notice-success is-dismissible"><p>Global assignments saved.</p></div>';
}

// --- PAGE DISPLAY ---

// Get all users for the dropdown
$all_users = get_users(['fields' => ['ID', 'display_name']]);

// Get all current assignments to populate the form
$current_assignments = $wpdb->get_results("SELECT role, user_id FROM $global_assign_table", KEY_COLUMN);

?>
<div class="wrap">
    <h1>Global Role Assignments</h1>
    <p>From this page, you can assign a specific user to a role globally. When a course's income split is set to use the "Global" setting for a role, the income will be directed to the user specified here.</p>

    <form method="post">
        <?php wp_nonce_field('rbic_save_global_assignments_action', 'rbic_save_global_assignments_nonce'); ?>

        <table class="form-table">
            <thead>
                <tr>
                    <th scope="col">Role</th>
                    <th scope="col">Globally Assigned User</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_roles as $role_key => $role_data) :
                    $current_user_id = isset($current_assignments[$role_key]) ? $current_assignments[$role_key]->user_id : 0;
                ?>
                    <tr>
                        <th scope="row">
                            <label for="global_assignment_<?= esc_attr($role_key) ?>"><?= esc_html($role_data['name']) ?></label>
                        </th>
                        <td>
                            <select name="global_assignments[<?= esc_attr($role_key) ?>]" id="global_assignment_<?= esc_attr($role_key) ?>" style="width: 300px;">
                                <option value="0" <?php selected($current_user_id, 0); ?>>-- None --</option>
                                <?php foreach ($all_users as $user) : ?>
                                    <option value="<?= esc_attr($user->ID) ?>" <?php selected($current_user_id, $user->ID); ?>>
                                        <?= esc_html($user->display_name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button('Save Global Assignments', 'primary', 'save_global_assignments'); ?>
    </form>
</div>
