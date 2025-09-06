<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$log_table = $wpdb->prefix . 'rbic_audit_log';

// --- PAGINATION ---
$per_page = 25;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$total_items = $wpdb->get_var("SELECT COUNT(id) FROM $log_table");
$total_pages = ceil($total_items / $per_page);

// --- FETCH LOGS ---
$logs = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $log_table ORDER BY log_time DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

?>
<div class="wrap">
    <h1>Audit Log</h1>
    <p>This page displays a log of all important changes made within the Role-Based Income Calculator plugin.</p>

    <table class="widefat striped fixed" cellspacing="0">
        <thead>
            <tr>
                <th scope="col" id="date" class="manage-column column-date" style="width:15%">Date</th>
                <th scope="col" id="user" class="manage-column column-author" style="width:12%">User</th>
                <th scope="col" id="action" class="manage-column column-categories" style="width:18%">Action Type</th>
                <th scope="col" id="details" class="manage-column column-comment">Details</th>
            </tr>
        </thead>

        <tbody>
            <?php if (empty($logs)) : ?>
                <tr>
                    <td colspan="4">No log entries found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $log) :
                    $user_info = get_userdata($log->user_id);
                    $user_name = $user_info ? $user_info->display_name : 'Unknown User';

                    $details_data = maybe_unserialize($log->details);
                    $message = is_array($details_data) && isset($details_data['message']) ? $details_data['message'] : 'No message provided.';
                    $raw_data = is_array($details_data) && isset($details_data['data']) ? $details_data['data'] : [];
                ?>
                    <tr>
                        <td><?= esc_html(date('Y/m/d H:i:s', strtotime($log->log_time))) ?></td>
                        <td><?= esc_html($user_name) ?></td>
                        <td><?= esc_html(ucwords(str_replace('_', ' ', $log->log_type))) ?></td>
                        <td>
                            <p><?= esc_html($message) ?></p>
                            <?php if (!empty($raw_data)) : ?>
                                <small><a href="#" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'; return false;">Show/Hide Raw Data</a></small>
                                <pre style="display:none; background-color: #f0f0f1; padding: 10px; border-radius: 4px; white-space: pre-wrap;"><?= esc_html(print_r($raw_data, true)) ?></pre>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num"><?= $total_items ?> items</span>
                <span class="pagination-links">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

</div>
