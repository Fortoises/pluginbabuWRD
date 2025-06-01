<?php
function babu_manager_backup_list() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY number ASC");

    $backup_dir = WP_CONTENT_DIR . '/babu-backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $backup_file = $backup_dir . 'babu_backup_' . date('Y-m-d_H-i-s') . '.txt';
    $content = '';
    foreach ($babu_list as $babu) {
        $content .= $babu->number . '. ' . $babu->name . "\n";
    }
    file_put_contents($backup_file, $content);

    babu_manager_update_target(); // Perbarui target
    babu_manager_send_to_telegram($backup_file);
}

function babu_manager_update_target() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $settings_table = $wpdb->prefix . 'babu_settings';
    
    $total_babu = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $max_target = 100; // Target maksimum, bisa diubah sesuai kebutuhan
    $new_target = "$total_babu/$max_target";
    
    $wpdb->update(
        $settings_table,
        ['setting_value' => $new_target],
        ['setting_key' => 'babu_target']
    );
}