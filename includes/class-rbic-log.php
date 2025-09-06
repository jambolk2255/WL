<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Rbic_Log {

    /**
     * Adds a new entry to the audit log.
     *
     * @param string $log_type Type of the log (e.g., 'split_update', 'assignment_change').
     * @param string $details A human-readable description of the event.
     * @param array $data Additional data to be stored as a serialized array.
     */
    public static function add($log_type, $details, $data = []) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'rbic_audit_log',
            [
                'log_time'  => current_time('mysql'),
                'user_id'   => get_current_user_id(),
                'log_type'  => $log_type,
                'details'   => maybe_serialize(
                    [
                        'message' => $details,
                        'data' => $data
                    ]
                ),
            ]
        );
    }
}
