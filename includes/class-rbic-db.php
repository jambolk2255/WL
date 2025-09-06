<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Rbic_Db {

    /**
     * Create the necessary database tables on plugin activation.
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Note: The original tables like income_split_tables, income_split_percentages,
        // course_teacher_assignments, income_history_pending, and income_history_approved
        // are assumed to be created by another plugin or the previous version.
        // We will add the new tables required for the upgraded functionality.

        // New table for course-specific role assignments
        $table_name = $wpdb->prefix . 'rbic_course_role_assignments';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            course_id bigint(20) NOT NULL,
            role varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            assigned_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_assignment (course_id, role)
        ) $charset_collate;";
        dbDelta($sql);

        // New table for global role assignments
        $table_name = $wpdb->prefix . 'rbic_global_role_assignments';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            role varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            assigned_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY role (role)
        ) $charset_collate;";
        dbDelta($sql);

        // New table for audit logs
        $table_name = $wpdb->prefix . 'rbic_audit_log';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            user_id bigint(20) NOT NULL,
            log_type varchar(50) NOT NULL,
            details longtext NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql);
    }
}
