<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Rbic_Core_Logic {

    public function __construct() {
        add_action('proof_submission_saved', [$this, 'calculate_income']);
        add_action('init', [$this, 'recalculate_existing_submissions'], 20);
    }

    /**
     * Recalculates income for all existing 'Approved' or 'Pending' submissions.
     */
    public function recalculate_existing_submissions() {
        global $wpdb;
        // This can be performance intensive. Consider adding a trigger button for this in admin.
        if (is_admin() && isset($_GET['rbic_recalculate_all'])) {
            $existing = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}proof_submissions WHERE status IN ('Approved', 'Pending')");
            foreach ($existing as $sub) {
                do_action('proof_submission_saved', $sub->id);
            }
            // Optional: add an admin notice
        }
    }

    /**
     * Main income calculation logic - REVISED.
     *
     * @param int $submission_id The ID of the proof submission.
     */
    public function calculate_income($submission_id) {
        global $wpdb;

        // --- Initial Setup ---
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}proof_submissions WHERE id = %d", intval($submission_id)));
        if (!$submission) return;

        $usd = floatval($submission->usd_amount);
        if ($usd <= 0) return;

        $status = sanitize_text_field($submission->status);
        $title_id = intval($submission->title_id);
        $course_id = intval(get_post_meta($title_id, 'assigned_course_id', true));
        if (!$course_id) return;

        $submitting_user_id = intval($submission->user_id);
        $submitting_user = get_user_by('ID', $submitting_user_id);
        if (!$submitting_user) return;

        $split_table_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}income_split_tables WHERE course_id = %d", $course_id));
        if (!$split_table_id) return;

        $percentages = $wpdb->get_results($wpdb->prepare("SELECT role, percentage FROM {$wpdb->prefix}income_split_percentages WHERE table_id = %d", $split_table_id));
        if (!$percentages) return;

        // --- Clear Old Data and Determine Target Table ---
        $recorded_at = $submission->submitted_at ?? $submission->created_at ?? current_time('mysql');
        $wpdb->delete("{$wpdb->prefix}income_history_pending", ['submission_id' => $submission->id]);
        $wpdb->delete("{$wpdb->prefix}income_history_approved", ['submission_id' => $submission->id]);

        $target_table = null;
        if ($status === 'Approved') {
            $target_table = "{$wpdb->prefix}income_history_approved";
        } elseif ($status === 'Pending') {
            $target_table = "{$wpdb->prefix}income_history_pending";
        } else {
            return; // No income for other statuses
        }

        // --- NEW CALCULATION LOGIC ---
        foreach ($percentages as $entry) {
            $role = sanitize_text_field($entry->role);
            $percentage = floatval($entry->percentage);
            $income = round($usd * ($percentage / 100), 2);
            $recipient_user_ids = [];

            // 1. Check for a course-specific assignment
            $course_assignment = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}rbic_course_role_assignments WHERE course_id = %d AND role = %s", $course_id, $role));

            if ($course_assignment !== null) {
                if ($course_assignment === 'global') {
                    // 2. Assignment is 'global', so check for a global assignment
                    $global_user_id = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}rbic_global_role_assignments WHERE role = %s", $role));
                    if ($global_user_id > 0) {
                        $recipient_user_ids[] = $global_user_id;
                    }
                } elseif ($course_assignment > 0) {
                    // A specific user is assigned for this course and role
                    $recipient_user_ids[] = $course_assignment;
                }
            }

            // 3. If no recipients found yet, use fallback logic
            if (empty($recipient_user_ids)) {
                if ($role === 'student' || $role === 'subscriber') {
                    if (in_array($role, $submitting_user->roles)) {
                        $recipient_user_ids[] = $submitting_user_id;
                    }
                } elseif ($role === 'author' || $role === 'instructor' || $role === 'lp_teacher') {
                    // Fallback for author-type roles is the course author
                    $course_author_id = get_post_field('post_author', $course_id);
                    if ($course_author_id) {
                         $recipient_user_ids[] = $course_author_id;
                    }
                } else {
                    // Fallback for all other roles is to distribute to all users with that role
                    $users_with_role = get_users(['role' => $role, 'fields' => 'ID']);
                    $recipient_user_ids = array_merge($recipient_user_ids, $users_with_role);
                }
            }

            // --- Insert Income Records ---
            foreach (array_unique($recipient_user_ids) as $user_id) {
                $this->insert_income($target_table, $user_id, $role, $course_id, $income, $submission->id, $recorded_at);
            }
        }
    }

    /**
     * Helper function to insert income record.
     */
    private function insert_income($table, $user_id, $role, $course_id, $income, $submission_id, $recorded_at) {
        global $wpdb;
        if ($income > 0) {
            $wpdb->insert($table, [
                'user_id'       => $user_id,
                'role'          => $role,
                'course_id'     => $course_id,
                'income'        => $income,
                'submission_id' => $submission_id,
                'recorded_at'   => $recorded_at
            ]);
        }
    }
}
