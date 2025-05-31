<?php
/*
Plugin Name: Babu Manager
Description: Plugin untuk mengelola daftar babu komunitas dengan fitur modern.
Version: 1.0
Author: Your Name
*/

// Mencegah akses langsung
if (!defined('ABSPATH')) {
    exit;
}

// Membuat tabel database saat aktivasi plugin
function babu_manager_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        number int NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY number (number)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Tabel untuk pengaturan (judul dan target)
    $settings_table = $wpdb->prefix . 'babu_settings';
    $sql_settings = "CREATE TABLE $settings_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        setting_key varchar(255) NOT NULL,
        setting_value text NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql_settings);

    // Inisialisasi pengaturan default
    $wpdb->insert($settings_table, [
        'setting_key' => 'babu_title',
        'setting_value' => 'List Babu Genz Pride'
    ]);
    $wpdb->insert($settings_table, [
        'setting_key' => 'babu_target',
        'setting_value' => '1/100'
    ]);
}
register_activation_hook(__FILE__, 'babu_manager_activate');

// Membuat role custom untuk admin Babu Manager
function babu_manager_create_role() {
    // Hapus role babu_admin jika sudah ada untuk memastikan capability diperbarui
    remove_role('babu_admin');
    // Buat ulang role babu_admin dengan capability yang benar
    add_role('babu_admin', 'Babu Admin', [
        'read' => true,
        'babu_manager_access' => true,
    ]);
    // Pastikan capability tetap ada
    $role = get_role('babu_admin');
    $role->add_cap('babu_manager_access');
    // Hapus capability yang tidak diperlukan
    $role->remove_cap('edit_posts');
    $role->remove_cap('edit_pages');
    $role->remove_cap('edit_users');
    $role->remove_cap('edit_profile');
    // Tambahkan capability ke administrator
    $admin_role = get_role('administrator');
    $admin_role->add_cap('babu_manager_access');
}
register_activation_hook(__FILE__, 'babu_manager_create_role');

// Include file lain
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/babu-functions.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/telegram-bot.php';

// Enqueue assets
function babu_manager_enqueue_assets() {
    if (is_admin()) {
        wp_enqueue_style('babu-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
        wp_enqueue_script('babu-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', ['jquery'], null, true);
    } else {
        wp_enqueue_style('babu-front-style', plugin_dir_url(__FILE__) . 'assets/css/front-style.css');
        wp_enqueue_script('babu-front-script', plugin_dir_url(__FILE__) . 'assets/js/front-script.js', ['jquery'], null, true);
    }
}
add_action('admin_enqueue_scripts', 'babu_manager_enqueue_assets');
add_action('wp_enqueue_scripts', 'babu_manager_enqueue_assets');