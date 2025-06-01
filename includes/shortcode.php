<?php
function babu_manager_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $settings_table = $wpdb->prefix . 'babu_settings';

    $title = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_title'");
    $target = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_target'");
    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY number ASC");
    $total_babu = count($babu_list);

    ob_start();
    ?>
    <div class="babu-container">
        <h2><?php echo esc_html($title); ?></h2>
        <div class="babu-info">
            <span>Total Babu: <?php echo $total_babu; ?></span>
            <span>Target: <?php echo esc_html($target); ?></span>
        </div>
        <div class="babu-search">
            <input type="text" id="babu-search" placeholder="Cari Babu..." onkeyup="babuSearch()">
        </div>
        <div class="babu-box">
            <div class="babu-header">
                <button class="copy-btn" onclick="babuCopyList()">Copy List</button>
            </div>
            <div class="babu-list" id="babu-list">
                <?php foreach ($babu_list as $babu) : ?>
                    <div class="babu-item"><?php echo esc_html($babu->number . '. ' . $babu->name); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('daftar_babu', 'babu_manager_shortcode');