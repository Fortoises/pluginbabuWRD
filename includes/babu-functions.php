<?php
if (!defined('ABSPATH')) {
    exit;
}

// Fungsi untuk memeriksa dan memperbaiki tabel
function babu_manager_check_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $settings_table = $wpdb->prefix . 'babu_settings';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    $settings_exists = $wpdb->get_var("SHOW TABLES LIKE '$settings_table'") === $settings_table;

    if (!$table_exists || !$settings_exists) {
        babu_manager_fix_database();
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $settings_exists = $wpdb->get_var("SHOW TABLES LIKE '$settings_table'") === $settings_table;
        if (!$table_exists || !$settings_exists) {
            error_log('Babu Error: Failed to create tables ' . $table_name . ' or ' . $settings_table);
            return false;
        }
    }
    return true;
}

// Fungsi untuk memperbarui nomor urut
function babu_manager_update_numbers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        return false;
    }

    $babu_list = $wpdb->get_results("SELECT id FROM $table_name ORDER BY name ASC");
    if ($babu_list === null) {
        error_log('Babu Update Numbers Error: Failed to fetch babu list, Error: ' . $wpdb->last_error);
        return false;
    }

    $number = 1;
    foreach ($babu_list as $babu) {
        $result = $wpdb->update($table_name, ['number' => $number], ['id' => $babu->id]);
        if ($result === false) {
            error_log('Babu Update Numbers Error: Failed to update number for ID ' . $babu->id . ', Error: ' . $wpdb->last_error);
            return false;
        }
        $number++;
    }
    return true;
}

// Fungsi untuk backup daftar babu
function babu_manager_backup_list() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        return;
    }

    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    if ($babu_list === null) {
        error_log('Babu Backup Error: Failed to fetch babu list, Error: ' . $wpdb->last_error);
        return;
    }

    $backup_dir = WP_CONTENT_DIR . '/babu-backups/';
    if (!is_dir($backup_dir) && !wp_mkdir_p($backup_dir)) {
        error_log('Babu Backup Error: Failed to create backup directory ' . $backup_dir);
        return;
    }

    $backup_file = $backup_dir . 'babu_backup_' . date('Y-m-d_H-i-s') . '.txt';
    $content = '';
    foreach ($babu_list as $babu) {
        $content .= $babu->number . '. ' . $babu->name . "\n";
    }

    if (file_put_contents($backup_file, $content) === false) {
        error_log('Babu Backup Error: Failed to write backup file ' . $backup_file);
    } else {
        babu_manager_send_to_telegram($backup_file);
    }
}

// Fungsi untuk memperbarui target
function babu_manager_update_target() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $settings_table = $wpdb->prefix . 'babu_settings';

    if (!babu_manager_check_table()) {
        return;
    }

    $total_babu = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($total_babu === null) {
        error_log('Babu Update Target Error: Failed to count babu, Error: ' . $wpdb->last_error);
        return;
    }

    $max_target = 100;
    $new_target = "$total_babu/$max_target";

    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'babu_target'));
    if ($exists) {
        $result = $wpdb->update($settings_table, ['setting_value' => $new_target], ['setting_key' => 'babu_target']);
        if ($result === false) {
            error_log('Babu Update Target Error: Failed to update target, Error: ' . $wpdb->last_error);
        }
    } else {
        $wpdb->insert($settings_table, ['setting_key' => 'babu_target', 'setting_value' => $new_target]);
    }
}

// Fungsi untuk memperbaiki database otomatis
function babu_manager_fix_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $settings_table = $wpdb->prefix . 'babu_settings';
    $charset_collate = $wpdb->get_charset_collate();

    // Buat tabel babu_list
    $sql_list = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        number INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Buat tabel babu_settings
    $sql_settings = "CREATE TABLE IF NOT EXISTS $settings_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        setting_key VARCHAR(255) NOT NULL,
        setting_value TEXT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY setting_key (setting_key)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_list);
    dbDelta($sql_settings);

    // Hapus constraint unik pada kolom number jika ada
    $indexes = $wpdb->get_results("SHOW INDEXES FROM $table_name WHERE Non_unique = 0 AND Column_name = 'number'");
    foreach ($indexes as $index) {
        $index_name = $index->Key_name;
        $wpdb->query("ALTER TABLE $table_name DROP INDEX $index_name");
        error_log('Babu Fix Database: Removed unique constraint ' . $index_name . ' on number column');
    }

    // Perbaiki entri dengan number = 0 atau null
    $invalid_entries = $wpdb->get_results("SELECT id FROM $table_name WHERE number = 0 OR number IS NULL");
    if (!empty($invalid_entries)) {
        $max_number = $wpdb->get_var("SELECT MAX(number) FROM $table_name WHERE number > 0");
        $temp_number = $max_number ? $max_number + 1 : 1;
        foreach ($invalid_entries as $entry) {
            $wpdb->update($table_name, ['number' => $temp_number], ['id' => $entry->id]);
            $temp_number++;
        }
        error_log('Babu Fix Database: Fixed ' . count($invalid_entries) . ' entries with number = 0');
    }

    // Inisiasi entri default di babu_settings
    $settings = [
        'babu_title' => 'Daftar Babu',
        'babu_target' => '0/100',
        'telegram_token' => '',
        'telegram_chat_id' => ''
    ];
    foreach ($settings as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", $key));
        if (!$exists) {
            $wpdb->insert($settings_table, ['setting_key' => $key, 'setting_value' => $value]);
            error_log('Babu Fix Database: Created default entry for ' . $key);
        }
    }

    // Perbarui nomor urut
    babu_manager_update_numbers();
    error_log('Babu Fix Database: Database fixed for tables ' . $table_name . ' and ' . $settings_table);
}
register_activation_hook(dirname(__FILE__, 2) . '/babu-manager.php', 'babu_manager_fix_database');
?>