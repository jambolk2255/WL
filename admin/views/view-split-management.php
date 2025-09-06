<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;

// Table names
$split_table_name = $wpdb->prefix . 'income_split_tables';
$split_percent_name = $wpdb->prefix . 'income_split_percentages';
$course_assign_name = $wpdb->prefix . 'rbic_course_role_assignments';

// Helper function to get users for a role to populate dropdowns
function rbic_get_users_by_role($role) {
    return get_users(['role' => $role, 'fields' => ['ID', 'display_name']]);
}

// --- FORM PROCESSING ---

// Handle Step 1: Create New Split Table
if (isset($_POST['create_split_table']) && check_admin_referer('rbic_create_split_table_action', 'rbic_create_split_table_nonce')) {
    $name = sanitize_text_field($_POST['table_name']);
    $course_id = intval($_POST['course_id']);

    if ($name && $course_id) {
        $wpdb->insert($split_table_name, ['table_name' => $name, 'course_id' => $course_id]);
        Rbic_Log::add('table_created', "Created new split table '{$name}' for course ID {$course_id}.");
        echo '<div class="notice notice-success is-dismissible"><p>Split table created.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Please provide both table name and course.</p></div>';
    }
}

// Handle Step 2: Save Percentages and Assignments
if (isset($_POST['save_settings']) && check_admin_referer('rbic_save_settings_action', 'rbic_save_settings_nonce')) {
    $table_id = intval($_POST['table_id']);
    $course_id = intval($_POST['course_id']);

    if ($table_id && $course_id) {
        $percentages = $_POST['percent'] ?? [];
        $assignments = $_POST['assignment'] ?? [];
        $roles = wp_roles()->roles;

        // 1. Save Percentages
        $total = array_sum($percentages);
        if ($total > 100.1 || $total < 99.9) {
            echo '<div class="notice notice-error is-dismissible"><p>Total percentage must be exactly 100%. Currently: ' . esc_html($total) . '%</p></div>';
        } else {
            $wpdb->delete($split_percent_name, ['table_id' => $table_id]);
            foreach ($percentages as $role => $percent) {
                $percent_val = floatval($percent);
                if ($percent_val > 0) {
                    $wpdb->insert($split_percent_name, ['table_id' => $table_id, 'role' => $role, 'percentage' => $percent_val]);
                }
            }
            // For logging, get old percentages
            $old_percentages = $wpdb->get_results($wpdb->prepare("SELECT role, percentage FROM $split_percent_name WHERE table_id = %d", $table_id), KEY_COLUMN);

            $wpdb->delete($split_percent_name, ['table_id' => $table_id]);
            $changed_data = [];
            foreach ($percentages as $role => $percent) {
                $percent_val = floatval($percent);
                $old_percent = isset($old_percentages[$role]) ? floatval($old_percentages[$role]->percentage) : 0;

                if ($percent_val !== $old_percent) {
                    $changed_data[$role] = ['from' => $old_percent, 'to' => $percent_val];
                }

                if ($percent_val > 0) {
                    $wpdb->insert($split_percent_name, ['table_id' => $table_id, 'role' => $role, 'percentage' => $percent_val]);
                }
            }

            if (!empty($changed_data)) {
                Rbic_Log::add(
                    'percentages_updated',
                    "Percentages updated for table ID {$table_id} on course '" . get_the_title($course_id) . "'.",
                    ['table_id' => $table_id, 'changes' => $changed_data]
                );
            }

             echo '<div class="notice notice-success is-dismissible"><p>Percentages saved successfully.</p></div>';
        }

        // 2. Save Assignments
        foreach ($assignments as $role => $user_id_str) {
            $user_id = sanitize_text_field($user_id_str);

            // Get the old assignment for logging/notifications
            $old_assignment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$course_assign_name} WHERE course_id = %d AND role = %s", $course_id, $role));
            $old_user_id = $old_assignment ? $old_assignment->user_id : null;

            // If the new assignment is different from the old one
            if ($user_id != $old_user_id) {
                $course_name = get_the_title($course_id);

                // Delete old assignment if it exists
                if ($old_assignment) {
                    $wpdb->delete($course_assign_name, ['id' => $old_assignment->id]);
                    if ($old_user_id > 0) { // Don't send email for 'global' or 'default'
                        Rbic_Email::send_unassignment_email($old_user_id, $roles[$role]['name'], $course_name);
                    }
                }

                // Insert new assignment if one was chosen
                if (!empty($user_id)) {
                    $wpdb->insert($course_assign_name, [
                        'course_id' => $course_id,
                        'role' => $role,
                        'user_id' => $user_id, // can be user_id or 'global'
                        'assigned_at' => current_time('mysql')
                    ]);

                    if ($user_id > 0) { // Don't send email for 'global'
                         Rbic_Email::send_assignment_email($user_id, $roles[$role]['name'], $course_name);
                    }
                }

                // Add to audit log
                Rbic_Log::add(
                    'assignment_changed',
                    "Assignment for '{$roles[$role]['name']}' on course '{$course_name}' changed.",
                    ['course_id' => $course_id, 'role' => $role, 'from_user' => $old_user_id, 'to_user' => $user_id]
                );
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Assignments saved successfully.</p></div>';
    }
}

$courses = get_posts(['post_type' => 'lp_course', 'numberposts' => -1]);
$all_roles = wp_roles()->roles;

?>
<div class="wrap">
    <h1>Income Split Management</h1>

    <!-- Step 1: Form to Create a New Split Table -->
    <h2>Step 1: Create New Split Table</h2>
    <form method="post">
        <?php wp_nonce_field('rbic_create_split_table_action', 'rbic_create_split_table_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="table_name">Split Table Name</label></th>
                <td><input type="text" id="table_name" name="table_name" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="course_id">Select Course</label></th>
                <td>
                    <select id="course_id" name="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?= esc_attr($course->ID) ?>"><?= esc_html($course->post_title) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php submit_button('Create Split Table', 'primary', 'create_split_table'); ?>
    </form>

    <hr>

    <!-- Step 2: Display and Manage Existing Split Tables -->
    <h2>Step 2: Define Role-Based Percentages & Assignments</h2>
    <?php
    $tables = $wpdb->get_results("SELECT * FROM $split_table_name ORDER BY id DESC");
    foreach ($tables as $tbl) {
        $course_title = get_the_title($tbl->course_id);
    ?>
        <details class="rbic-details-group" open>
            <summary>
                <strong><?= esc_html($tbl->table_name) ?></strong> (Course: <?= esc_html($course_title) ?>)
            </summary>

            <form method="post" class="rbic-settings-form">
                <?php wp_nonce_field('rbic_save_settings_action', 'rbic_save_settings_nonce'); ?>
                <input type="hidden" name="table_id" value="<?= esc_attr($tbl->id) ?>">
                <input type="hidden" name="course_id" value="<?= esc_attr($tbl->course_id) ?>">

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Role</th>
                            <th style="width: 20%;">Percentage (%)</th>
                            <th>Assigned User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($all_roles as $role_key => $role_data) {
                            // Fetch existing data for this role
                            $existing_percent = $wpdb->get_var($wpdb->prepare("SELECT percentage FROM $split_percent_name WHERE table_id = %d AND role = %s", $tbl->id, $role_key));
                            $current_assignment = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $course_assign_name WHERE course_id = %d AND role = %s", $tbl->course_id, $role_key));
                            $users_for_role = rbic_get_users_by_role($role_key);
                        ?>
                            <tr>
                                <td><?= esc_html($role_data['name']) ?></td>
                                <td>
                                    <input type="number" name="percent[<?= esc_attr($role_key) ?>]" step="0.01" min="0" max="100" value="<?= esc_attr($existing_percent) ?>" placeholder="0">
                                </td>
                                <td>
                                    <select name="assignment[<?= esc_attr($role_key) ?>]" style="width: 100%;">
                                        <option value="" <?php selected($current_assignment, ''); ?>>-- Default Logic --</option>
                                        <option value="global" <?php selected($current_assignment, 'global'); ?>>-- Use Global Assignment --</option>
                                        <?php if (!empty($users_for_role)): ?>
                                            <optgroup label="<?= esc_attr($role_data['name']) ?>s">
                                                <?php foreach ($users_for_role as $user): ?>
                                                    <option value="<?= esc_attr($user->ID) ?>" <?php selected($current_assignment, $user->ID); ?>>
                                                        <?= esc_html($user->display_name) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
                <?php submit_button('Save Settings', 'primary', 'save_settings'); ?>
            </form>
        </details>
    <?php
    } // end foreach table
    ?>
</div> <!-- .wrap -->
<style>
    .rbic-details-group { border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 20px; }
    .rbic-details-group[open] > summary { border-bottom: 1px solid #c3c4c7; }
    .rbic-details-group summary { font-size: 1.2em; padding: 10px; cursor: pointer; background: #f0f0f1; }
    .rbic-settings-form { padding: 15px; }
</style>
