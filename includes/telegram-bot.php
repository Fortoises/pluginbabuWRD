<?php
if (!defined('ABSPATH')) {
    exit;
}

function babu_manager_send_to_telegram($file_path) {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'babu_settings';

    // Ambil pengaturan dari database
    $bot_token = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'telegram_token'");
    $chat_id = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'telegram_chat_id'");

    // Cek apakah token dan chat ID tersedia
    if (empty($bot_token) || empty($chat_id)) {
        error_log('Babu Telegram Error: Bot token or chat ID not configured');
        return false;
    }

    // Cek apakah file ada
    if (!file_exists($file_path)) {
        error_log('Babu Telegram Error: Backup file not found at ' . $file_path);
        return false;
    }

    $url = "https://api.telegram.org/bot$bot_token/sendDocument";
    $document = new CURLFile($file_path);

    $post_fields = [
        'chat_id' => $chat_id,
        'document' => $document,
        'caption' => 'Babu Backup ' . date('Y-m-d H:i:s')
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Untuk debugging, hapus di produksi
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code !== 200 || $response === false) {
        error_log('Babu Telegram Error: Failed to send backup, HTTP Code: ' . $http_code . ', Error: ' . $curl_error . ', Response: ' . $response);
        return false;
    }

    $result = json_decode($response, true);
    if (!$result['ok']) {
        error_log('Babu Telegram Error: Telegram API error, Response: ' . $response);
        return false;
    }

    error_log('Babu Telegram Success: Backup sent to chat ID ' . $chat_id);
    return true;
}
?>