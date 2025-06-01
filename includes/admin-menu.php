<?php
if (!defined('ABSPATH')) {
    exit;
}

// Membatasi akses menu untuk Babu Admin
function babu_manager_restrict_admin_menu() {
    if (current_user_can('babu_admin') && !current_user_can('administrator')) {
        remove_menu_page('index.php');
        remove_menu_page('profile.php');
        remove_menu_page('users.php');
        remove_menu_page('edit.php');
        remove_menu_page('upload.php');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
    }
}
add_action('admin_menu', 'babu_manager_restrict_admin_menu', 1000);

// Membuat menu Babu Manager
function babu_manager_admin_menu() {
    add_menu_page(
        'Babu Manager',
        'Babu Manager',
        'babu_manager_access',
        'babu-manager',
        'babu_manager_home_page',
        'dashicons-list-view',
        10
    );
    add_submenu_page(
        'babu-manager',
        'Home',
        'Home',
        'babu_manager_access',
        'babu-manager',
        'babu_manager_home_page'
    );
    add_submenu_page(
        'babu-manager',
        'Add Babu',
        'Add Babu',
        'babu_manager_access',
        'babu-manager-add',
        'babu_manager_add_page'
    );
    add_submenu_page(
        'babu-manager',
        'Upload Babu',
        'Upload Babu',
        'babu_manager_access',
        'babu-manager-upload',
        'babu_manager_upload_page'
    );
    add_submenu_page(
        'babu-manager',
        'List Babu',
        'List Babu',
        'babu_manager_access',
        'babu-manager-list',
        'babu_manager_list_page'
    );
    add_submenu_page(
        'babu-manager',
        'Delete Babu',
        'Delete Babu',
        'babu_manager_access',
        'babu-manager-delete',
        'babu_manager_delete_page'
    );
}
add_action('admin_menu', 'babu_manager_admin_menu');

// Halaman Home
function babu_manager_home_page() {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'babu_settings';

    if (!babu_manager_check_table()) {
        echo '<div class="error"><p>Gagal memuat pengaturan. Silakan coba lagi.</p></div>';
        return;
    }

    if (isset($_POST['save_settings']) && check_admin_referer('babu_manager_home_save')) {
        $title = wp_kses_post(trim($_POST['babu_title']));
        $target = sanitize_text_field(trim($_POST['babu_target']));
        $telegram_token = sanitize_text_field(trim($_POST['telegram_token']));
        $telegram_chat_id = sanitize_text_field(trim($_POST['telegram_chat_id']));

        if (empty($title) || empty($target)) {
            echo '<div class="error"><p>Judul dan target tidak boleh kosong!</p></div>';
        } else {
            // Simpan babu_title
            $title_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'babu_title'));
            if ($title_exists) {
                $wpdb->update($settings_table, ['setting_value' => $title], ['setting_key' => 'babu_title']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'babu_title', 'setting_value' => $title]);
            }

            // Simpan babu_target
            $target_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'babu_target'));
            if ($target_exists) {
                $wpdb->update($settings_table, ['setting_value' => $target], ['setting_key' => 'babu_target']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'babu_target', 'setting_value' => $target]);
            }

            // Simpan telegram_token
            $token_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'telegram_token'));
            if ($token_exists) {
                $wpdb->update($settings_table, ['setting_value' => $telegram_token], ['setting_key' => 'telegram_token']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'telegram_token', 'setting_value' => $telegram_token]);
            }

            // Simpan telegram_chat_id
            $chat_id_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'telegram_chat_id'));
            if ($chat_id_exists) {
                $wpdb->update($settings_table, ['setting_value' => $telegram_chat_id], ['setting_key' => 'telegram_chat_id']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'telegram_chat_id', 'setting_value' => $telegram_chat_id]);
            }

            babu_manager_update_target();
            babu_manager_backup_list();
            echo '<div class="updated"><p>Pengaturan disimpan!</p></div>';
        }
    }

    $title = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_title'") ?: 'Daftar Babu';
    $target = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_target'") ?: '0/100';
    $telegram_token = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'telegram_token'") ?: '';
    $telegram_chat_id = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'telegram_chat_id'") ?: '';
    ?>
    <div class="wrap">
        <h1>Babu Manager - Home</h1>
        <form method="post">
            <?php wp_nonce_field('babu_manager_home_save'); ?>
            <label for="babu_title">Judul:</label>
            <input type="text" name="babu_title" value="<?php echo esc_attr($title); ?>" required>
            <label for="babu_target">Target Babu:</label>
            <input type="text" name="babu_target" value="<?php echo esc_attr($target); ?>" required>
            <label for="telegram_token">Telegram Bot Token:</label>
            <input type="text" name="telegram_token" value="<?php echo esc_attr($telegram_token); ?>" placeholder="Contoh: 7833787166:AAGlg3_nmlb9K_JL7JGtfO4sSnE83BDkeT0">
            <label for="telegram_chat_id">Telegram Chat ID:</label>
            <input type="text" name="telegram_chat_id" value="<?php echo esc_attr($telegram_chat_id); ?>" placeholder="Contoh: 6990643296">
            <input type="submit" name="save_settings" class="button-primary" value="Simpan">
        </form>
    </div>
    <?php
}

// Halaman Add Babu
function babu_manager_add_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div class="error"><p>Gagal memuat halaman. Silakan coba lagi.</p></div>';
        return;
    }

    if (isset($_POST['add_babu']) && check_admin_referer('babu_manager_add_babu')) {
        $name = wp_kses_post(trim($_POST['babu_name']));
        if (empty($name)) {
            echo '<div class="error"><p>Nama komunitas tidak boleh kosong!</p></div>';
        } else {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE BINARY name = %s", $name));
            if ($exists) {
                echo '<div class="error"><p>Nama komunitas "' . esc_html($name) . '" sudah ada!</p></div>';
            } else {
                $max_number = $wpdb->get_var("SELECT MAX(number) FROM $table_name") ?: 0;
                $new_number = $max_number + 1;

                $result = $wpdb->insert($table_name, ['name' => $name, 'number' => $new_number]);
                if ($result === false) {
                    echo '<div class="error"><p>Gagal menambahkan babu: ' . esc_html($name) . '.</p></div>';
                    error_log('Babu Add Error: Failed to add babu ' . $name . ', Error: ' . $wpdb->last_error);
                } else {
                    if (!babu_manager_update_numbers()) {
                        echo '<div class="error"><p>Gagal memperbarui nomor urut!</p></div>';
                    } else {
                        babu_manager_update_target();
                        babu_manager_backup_list();
                        echo '<div class="updated"><p>Babu "' . esc_html($name) . '" ditambahkan!</p></div>';
                    }
                }
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Add Babu</h1>
        <form method="post">
            <?php wp_nonce_field('babu_manager_add_babu'); ?>
            <label for="babu_name">Nama Komunitas:</label>
            <input type="text" name="babu_name" required>
            <input type="submit" name="add_babu" class="button-primary" value="Tambah Babu">
        </form>
    </div>
    <?php
}

// Halaman Upload Babu
function babu_manager_upload_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div class="error"><p>Gagal memuat halaman. Silakan coba lagi.</p></div>';
        return;
    }

    if (isset($_POST['upload_babu']) && check_admin_referer('babu_manager_upload_babu')) {
        if (!empty($_FILES['babu_file']['tmp_name'])) {
            set_time_limit(300);

            $file = $_FILES['babu_file']['tmp_name'];
            $file_error = $_FILES['babu_file']['error'];

            if ($file_error !== UPLOAD_ERR_OK) {
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'File terlalu besar.',
                    UPLOAD_ERR_FORM_SIZE => 'File terlalu besar.',
                    UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
                    UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara hilang.',
                    UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
                    UPLOAD_ERR_EXTENSION => 'Ekstensi file tidak diizinkan.'
                ];
                echo '<div class="error"><p>Error upload: ' . esc_html($error_messages[$file_error] ?? 'Unknown error') . '</p></div>';
                return;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                echo '<div class="error"><p>Gagal membaca file!</p></div>';
                return;
            }

            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $lines = explode("\n", $content);
            $names_to_add = [];
            $added_count = 0;
            $duplicates = [];
            $failed_names = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                if (preg_match('/^(\d+)\.\s*(.+)$/', $line, $matches)) {
                    $name = wp_kses_post(trim($matches[2]));
                } else {
                    $name = wp_kses_post(trim($line));
                }

                if (empty($name)) continue;

                $name_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE BINARY name = %s", $name));
                if (!$name_exists) {
                    $names_to_add[] = $name;
                } else {
                    $duplicates[] = $name;
                }
            }

            sort($names_to_add, SORT_NATURAL | SORT_FLAG_CASE);

            $max_number = $wpdb->get_var("SELECT MAX(number) FROM $table_name") ?: 0;
            $temp_number = $max_number + 1;

            foreach ($names_to_add as $name) {
                $result = $wpdb->insert($table_name, ['name' => $name, 'number' => $temp_number]);
                if ($result === false) {
                    $failed_names[] = $name;
                    error_log('Babu Upload Error: Failed to insert ' . $name . ', Error: ' . $wpdb->last_error);
                    continue;
                }
                $added_count++;
                $temp_number++;
            }

            if ($added_count > 0) {
                if (!babu_manager_update_numbers()) {
                    echo '<div class="error"><p>Gagal memperbarui nomor urut!</p></div>';
                } else {
                    babu_manager_update_target();
                    babu_manager_backup_list();
                    echo '<div class="updated"><p>' . $added_count . ' babu diupload.</p></div>';
                }
                if (!empty($duplicates)) {
                    echo '<div class="notice notice-warning"><p>Duplikat diabaikan: ' . esc_html(implode(', ', $duplicates)) . '</p></div>';
                }
                if (!empty($failed_names)) {
                    echo '<div class="error"><p>Gagal menyisipkan: ' . esc_html(implode(', ', $failed_names)) . '</p></div>';
                }
            } else {
                echo '<div class="error"><p>Tidak ada babu baru yang diupload.</p></div>';
            }
        } else {
            echo '<div class="error"><p>Silakan pilih file TXT!</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Upload Babu</h1>
        <p>Unggah file TXT berisi daftar nama komunitas (satu nama per baris, contoh: "1. UNDERVET" atau "444BUTUH").</p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('babu_manager_upload_babu'); ?>
            <label for="babu_file">File TXT:</label>
            <input type="file" name="babu_file" accept=".txt" required>
            <input type="submit" name="upload_babu" class="button-primary" value="Upload">
        </form>
    </div>
    <?php
}

// Halaman List Babu
function babu_manager_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div class="error"><p>Gagal memuat daftar. Silakan coba lagi.</p></div>';
        return;
    }

    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    if ($babu_list === null) {
        echo '<div class="error"><p>Gagal memuat daftar babu!</p></div>';
        error_log('Babu List Error: Failed to fetch babu list, Error: ' . $wpdb->last_error);
        return;
    }

    $total_babu = count($babu_list);
    ?>
    <div class="wrap">
        <h1>List Babu</h1>
        <div class="babu-box">
            <div class="babu-header">
                <span>Total Babu: <?php echo $total_babu; ?></span>
                <button class="copy-btn" onclick="babuCopyList()">Copy List</button>
            </div>
            <div class="babu-list">
                <?php foreach ($babu_list as $babu) : ?>
                    <div class="babu-item" data-name="<?php echo esc_attr(strtolower($babu->name)); ?>">
                        <?php echo esc_html($babu->number . '. ' . $babu->name); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

// Halaman Delete Babu
function babu_manager_delete_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div class="error"><p>Gagal memuat halaman. Silakan coba lagi.</p></div>';
        return;
    }

    if (isset($_POST['delete_by_id']) && check_admin_referer('babu_manager_delete_babu')) {
        $id = intval($_POST['babu_id']);
        $babu_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_name WHERE id = %d", $id));
        if ($babu_name) {
            $result = $wpdb->delete($table_name, ['id' => $id]);
            if ($result === false) {
                echo '<div class="error"><p>Gagal menghapus babu: ' . esc_html($babu_name) . '.</p></div>';
                error_log('Babu Delete Error: Failed to delete babu ' . $babu_name . ', Error: ' . $wpdb->last_error);
            } else {
                if (!babu_manager_update_numbers()) {
                    echo '<div class="error"><p>Gagal memperbarui nomor urut!</p></div>';
                } else {
                    babu_manager_update_target();
                    babu_manager_backup_list();
                    echo '<div class="updated"><p>Babu "' . esc_html($babu_name) . '" dihapus!</p></div>';
                }
            }
        } else {
            echo '<div class="error"><p>Babu tidak ditemukan!</p></div>';
        }
    }

    if (isset($_POST['delete_all']) && check_admin_referer('babu_manager_delete_all')) {
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        if ($result === false) {
            echo '<div class="error"><p>Gagal menghapus semua babu!</p></div>';
            error_log('Babu Delete Error: Failed to delete all babu, Error: ' . $wpdb->last_error);
        } else {
            babu_manager_update_target();
            babu_manager_backup_list();
            echo '<div class="updated"><p>Semua Babu dihapus!</p></div>';
        }
    }

    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    if ($babu_list === null) {
        echo '<div class="error"><p>Gagal memuat daftar babu!</p></div>';
        error_log('Babu Delete Error: Failed to fetch babu list, Error: ' . $wpdb->last_error);
        return;
    }
    ?>
    <div class="wrap">
        <h1>Delete Babu</h1>
        <form method="post" onsubmit="return confirm('Yakin ingin menghapus semua babu?');">
            <?php wp_nonce_field('babu_manager_delete_all'); ?>
            <input type="submit" name="delete_all" class="button-delete-all" value="Hapus Semua Babu">
        </form>
        <div class="babu-search">
            <input type="text" id="babu-search" placeholder="Cari Babu..." onkeyup="babuSearch()">
        </div>
        <div class="babu-box">
            <table class="babu-table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Nama Komunitas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="babu-list">
                    <?php foreach ($babu_list as $babu) : ?>
                        <tr class="babu-item" data-name="<?php echo esc_attr(strtolower($babu->name)); ?>">
                            <td><?php echo esc_html($babu->number); ?></td>
                            <td><?php echo esc_html($babu->name); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('babu_manager_delete_babu'); ?>
                                    <input type="hidden" name="babu_id" value="<?php echo esc_attr($babu->id); ?>">
                                    <input type="submit" name="delete_by_id" class="button-secondary" value="Hapus" onclick="return confirm('Yakin ingin menghapus <?php echo esc_js($babu->name); ?>?');">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
?>