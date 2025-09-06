<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Rbic_Email {

    /**
     * Sends an email notification about an assignment change.
     *
     * @param string $to The email address to send to.
     * @param string $subject The subject of the email.
     * @param string $message The body of the email.
     * @return bool Whether the email was sent successfully.
     */
    public static function send_notification($to, $subject, $message) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        // In a real implementation, we would use a proper HTML email template.
        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Prepares and sends an email for when a user is assigned to a role.
     *
     * @param int $user_id The user ID of the newly assigned user.
     * @param string $role The role they were assigned to.
     * @param string $course_name The name of the course.
     */
    public static function send_assignment_email($user_id, $role, $course_name) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return;

        $to = $user->user_email;
        $subject = "You have been assigned a new role!";
        $message = "
            <p>Hello {$user->display_name},</p>
            <p>You have been assigned the role of <strong>{$role}</strong> for the course: <strong>{$course_name}</strong>.</p>
            <p>You will now receive income distributions for this role according to the course's income split settings.</p>
            <p>Thank you!</p>
        ";

        self::send_notification($to, $subject, $message);
    }

    /**
     * Prepares and sends an email for when a user is unassigned from a role.
     *
     * @param int $user_id The user ID of the unassigned user.
     * @param string $role The role they were unassigned from.
     * @param string $course_name The name of the course.
     */
    public static function send_unassignment_email($user_id, $role, $course_name) {
        $user = get_user_by('ID', $user_id);
        if (!$user) return;

        $to = $user->user_email;
        $subject = "Your role assignment has changed";
        $message = "
            <p>Hello {$user->display_name},</p>
            <p>Your assignment for the role of <strong>{$role}</strong> for the course: <strong>{$course_name}</strong> has been removed or changed.</p>
            <p>You will no longer receive income distributions for this role for this course.</p>
            <p>Thank you!</p>
        ";

        self::send_notification($to, $subject, $message);
    }
}
