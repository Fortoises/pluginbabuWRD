<?php
/*
Plugin Name: Babu Manager
Description: Plugin untuk mengelola daftar babu komunitas dengan fitur modern.
Version: 1.3
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
    $settings_table = $wpdb->prefix . 'babu_settings';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        number int NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_settings = "CREATE TABLE $settings_table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        setting_key varchar(255) NOT NULL,
        setting_value text NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY setting_key (setting_key)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_settings);

    $default_settings = [
        'babu_title' => 'List Babu Genz Pride',
        'babu_target' => '0/100',
        'telegram_token' => '',
        'telegram_chat_id' => ''
    ];
    foreach ($default_settings as $key => $value) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", $key));
        if (!$exists) {
            $wpdb->insert($settings_table, [
                'setting_key' => $key,
                'setting_value' => $value
            ]);
        }
    }
}
register_activation_hook(__FILE__, 'babu_manager_activate');

// Membuat role custom untuk admin Babu Manager
function babu_manager_create_role() {
    remove_role('babu_admin');
    add_role('babu_admin', 'Babu Admin', [
        'read' => true,
        'babu_manager_access' => true,
    ]);
    $role = get_role('babu_admin');
    $role->add_cap('babu_manager_access');
    $role->remove_cap('edit_posts');
    $role->remove_cap('edit_pages');
    $role->remove_cap('edit_users');
    $role->remove_cap('edit_profile');
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
function babu_manager_enqueue_assets($hook) {
    // Admin assets
    if (is_admin() && (strpos($hook, 'babu-manager') !== false || strpos($hook, 'toplevel_page_babu-manager') !== false)) {
        wp_enqueue_style('babu-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', [], '1.3');
        wp_enqueue_style('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', [], '11.12.4');
        wp_enqueue_style('toastify', 'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css', [], '1.12.0');
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js', [], '11.12.4', true);
        wp_enqueue_script('toastify', 'https://cdn.jsdelivr.net/npm/toastify-js', [], '1.12.0', true);
        wp_enqueue_script('babu-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', ['jquery', 'sweetalert2', 'toastify'], '1.3', true);
        wp_localize_script('babu-admin-script', 'babuSettings', [
            'sweetalert2_fallback' => plugin_dir_url(__FILE__) . 'assets/js/sweetalert2.min.js',
            'toastify_fallback' => plugin_dir_url(__FILE__) . 'assets/js/toastify.min.js',
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
    // Frontend assets
    wp_enqueue_style('babu-front-style', plugin_dir_url(__FILE__) . 'assets/css/front-style.css', [], '1.3');
    wp_enqueue_style('toastify', 'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css', [], '1.12.0');
    wp_enqueue_script('toastify', 'https://cdn.jsdelivr.net/npm/toastify-js', [], '1.12.0', true);
    wp_enqueue_script('babu-front-script', plugin_dir_url(__FILE__) . 'assets/js/front-script.js', ['jquery', 'toastify'], '1.3', true);
    wp_localize_script('babu-front-script', 'babuSettings', [
        'toastify_fallback' => plugin_dir_url(__FILE__) . 'assets/js/toastify.min.js',
    ]);
}
add_action('admin_enqueue_scripts', 'babu_manager_enqueue_assets');
add_action('wp_enqueue_scripts', 'babu_manager_enqueue_assets');