<?php
if (!defined('ABSPATH')) {
    exit;
}

function babu_manager_restrict_admin_menu() {
    if (current_user_can('babu_admin') && !current_user_can('administrator')) {
        remove_menu_page('index.php');
        remove_menu_page('profile.php'); // Block profile menu
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

// Redirect babu_admin to Babu Manager Home after login
function babu_manager_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && in_array('babu_admin', $user->roles) && !in_array('administrator', $user->roles)) {
        return admin_url('admin.php?page=babu-manager');
    }
    return $redirect_to;
}
add_filter('login_redirect', 'babu_manager_login_redirect', 10, 3);

// Block access to profile.php for babu_admin
function babu_manager_block_profile_access() {
    if (is_admin() && current_user_can('babu_admin') && !current_user_can('administrator')) {
        global $pagenow;
        if ($pagenow === 'profile.php') {
            wp_die('Access denied to profile page.', 'Access Denied', ['response' => 403]);
        }
    }
}
add_action('admin_init', 'babu_manager_block_profile_access');

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


function babu_manager_home_page() {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'babu_settings';

    if (!babu_manager_check_table()) {
        echo '<div id="babu-notification" data-type="error" data-message="Gagal memuat pengaturan. Silakan coba lagi."></div>';
        return;
    }

    if (isset($_POST['save_settings']) && check_admin_referer('babu_manager_home_save')) {
        $title = wp_kses_post(trim($_POST['babu_title']));
        $target = intval(trim($_POST['babu_target']));
        $telegram_token = sanitize_text_field(trim($_POST['telegram_token']));
        $telegram_chat_id = sanitize_text_field(trim($_POST['telegram_chat_id']));

        if (empty($title) || $target < 1) {
            echo '<div id="babu-notification" data-type="error" data-message="Judul tidak boleh kosong dan target harus lebih dari 0!"></div>';
        } else {
            global $wpdb;
            $table_name = $wpdb->prefix . 'babu_list';
            $settings_table = $wpdb->prefix . 'babu_settings';
            $total_babu = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $new_target = "$total_babu/$target";

            $title_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'babu_title'));
            if ($title_exists) {
                $wpdb->update($settings_table, ['setting_value' => $title], ['setting_key' => 'babu_title']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'babu_title', 'setting_value' => $title]);
            }

            $target_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'babu_target'));
            if ($target_exists) {
                $wpdb->update($settings_table, ['setting_value' => $new_target], ['setting_key' => 'babu_target']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'babu_target', 'setting_value' => $new_target]);
            }

            $token_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'telegram_token'));
            if ($token_exists) {
                $wpdb->update($settings_table, ['setting_value' => $telegram_token], ['setting_key' => 'telegram_token']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'telegram_token', 'setting_value' => $telegram_token]);
            }

            $chat_id_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $settings_table WHERE setting_key = %s", 'telegram_chat_id'));
            if ($chat_id_exists) {
                $wpdb->update($settings_table, ['setting_value' => $telegram_chat_id], ['setting_key' => 'telegram_chat_id']);
            } else {
                $wpdb->insert($settings_table, ['setting_key' => 'telegram_chat_id', 'setting_value' => $telegram_chat_id]);
            }

            babu_manager_update_target();
            babu_manager_backup_list();
            echo '<div id="babu-notification" data-type="success" data-message="Pengaturan berhasil disimpan!"></div>';
        }
    }

    $title = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_title'") ?: 'Daftar Babu';
    $target = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_target'") ?: '0/100';
    $telegram_token = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'telegram_token'") ?: '';
    $telegram_chat_id = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'telegram_chat_id'") ?: '';
    ?>
    <div class="wrap babu-home">
        <h1 class="babu-home-title">Pengaturan Babu Manager</h1>
        <div class="babu-card">
            <form method="post" class="babu-form">
                <?php wp_nonce_field('babu_manager_home_save'); ?>
                <div class="babu-form-group">
                    <label for="babu_title">Judul Daftar</label>
                    <input type="text" name="babu_title" id="babu_title" value="<?php echo esc_attr($title); ?>" required placeholder="Masukkan judul daftar">
                </div>
                <div class="babu-form-group">
                    <label for="babu_target">Target Babu</label>
                    <input type="number" name="babu_target" id="babu_target" value="<?php echo esc_attr(explode('/', $target)[1] ?: '100'); ?>" required placeholder="Masukkan target (contoh: 400)" min="1">
                </div>
                <div class="babu-form-group">
                    <label for="telegram_token">Telegram Bot Token</label>
                    <input type="text" name="telegram_token" id="telegram_token" value="<?php echo esc_attr($telegram_token); ?>" placeholder="Contoh: 7833787166:AAGlg3_nmlb9K_JL7JGtfO4sSnE83BDkeT0">
                </div>
                <div class="babu-form-group">
                    <label for="telegram_chat_id">Telegram Chat ID</label>
                    <input type="text" name="telegram_chat_id" id="telegram_chat_id" value="<?php echo esc_attr($telegram_chat_id); ?>" placeholder="Contoh: 6990643296">
                </div>
                <button type="submit" name="save_settings" class="babu-button babu-button-primary">Simpan Pengaturan</button>
            </form>
        </div>
    </div>
    <?php
}

function babu_manager_add_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div id="babu-notification" data-type="error" data-message="Gagal memuat halaman. Silakan coba lagi."></div>';
        return;
    }

    if (isset($_POST['add_babu']) && check_admin_referer('babu_manager_add_babu')) {
        $name = wp_kses_post(trim($_POST['babu_name']));
        if (empty($name)) {
            echo '<div id="babu-notification" data-type="error" data-message="Nama komunitas tidak boleh kosong!"></div>';
        } else {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE BINARY name = %s", $name));
            if ($exists) {
                echo '<div id="babu-notification" data-type="error" data-message="Nama komunitas \'' . esc_html($name) . '\' sudah ada!"></div>';
            } else {
                $max_number = $wpdb->get_var("SELECT MAX(number) FROM $table_name") ?: 0;
                $new_number = $max_number + 1;

                $result = $wpdb->insert($table_name, ['name' => $name, 'number' => $new_number]);
                if ($result === false) {
                    echo '<div id="babu-notification" data-type="error" data-message="Gagal menambahkan babu: ' . esc_html($name) . '."></div>';
                    error_log('Babu Add Error: Failed to add babu ' . $name . ', Error: ' . $wpdb->last_error);
                } else {
                    if (!babu_manager_update_numbers()) {
                        echo '<div id="babu-notification" data-type="error" data-message="Gagal memperbarui nomor urut!"></div>';
                    } else {
                        babu_manager_update_target();
                        babu_manager_backup_list();
                        echo '<div id="babu-notification" data-type="success" data-message="Babu \'' . esc_html($name) . '\' ditambahkan!"></div>';
                    }
                }
            }
        }
    }
    ?>
    <div class="wrap babu-add">
        <h1 class="babu-page-title">Tambah Babu Baru</h1>
        <div class="babu-card">
            <form method="post" class="babu-form">
                <?php wp_nonce_field('babu_manager_add_babu'); ?>
                <div class="babu-form-group">
                    <label for="babu_name">Nama Komunitas</label>
                    <input type="text" name="babu_name" id="babu_name" required placeholder="Masukkan nama komunitas">
                </div>
                <button type="submit" name="add_babu" class="babu-button babu-button-primary">Tambah Babu</button>
            </form>
        </div>
    </div>
    <?php
}

function babu_manager_upload_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div id="babu-notification" data-type="error" data-message="Gagal memuat halaman. Silakan coba lagi."></div>';
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
                echo '<div id="babu-notification" data-type="error" data-message="Error upload: ' . esc_html($error_messages[$file_error] ?? 'Unknown error') . '"></div>';
                return;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                echo '<div id="babu-notification" data-type="error" data-message="Gagal membaca file!"></div>';
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
                    error_log('Babu Upload Error: Failed to insert babu ' . $name . ', Error: ' . $wpdb->last_error);
                    continue;
                }
                $added_count++;
                $temp_number++;
            }

            if ($added_count > 0) {
                if (!babu_manager_update_numbers()) {
                    echo '<div id="babu-notification" data-type="error" data-message="Gagal memperbarui nomor urut!"></div>';
                } else {
                    babu_manager_update_target();
                    babu_manager_backup_list();
                    echo '<div id="babu-notification" data-type="success" data-message="' . $added_count . ' babu berhasil diupload!"></div>';
                }
                if (!empty($duplicates)) {
                    echo '<div id="babu-notification" data-type="warning" data-message="Duplikat diabaikan: ' . esc_html(implode(', ', $duplicates)) . '"></div>';
                }
                if (!empty($failed_names)) {
                    echo '<div id="babu-notification" data-type="error" data-message="Gagal menyisipkan: ' . esc_html(implode(', ', $failed_names)) . '"></div>';
                }
            } else {
                echo '<div id="babu-notification" data-type="error" data-message="Tidak ada babu baru yang diupload."></div>';
            }
        } else {
            echo '<div id="babu-notification" data-type="error" data-message="Silakan pilih file TXT!"></div>';
        }
    }
    ?>
    <div class="wrap babu-upload">
        <h1 class="babu-page-title">Upload Daftar Babu</h1>
        <div class="babu-card">
            <p class="babu-upload-info">Unggah file TXT berisi daftar nama komunitas (satu nama per baris, contoh: "1. BABUVET" atau "444BUTUH").</p>
            <form method="post" enctype="multipart/form-data" class="babu-form babu-upload-form">
                <?php wp_nonce_field('babu_manager_upload_babu'); ?>
                <div class="babu-form-group babu-upload-area" id="babu-upload-area">
                    <input type="file" name="babu_file" id="babu_file" accept=".txt" required hidden>
                    <p>Drag & drop file TXT di sini atau klik untuk memilih</p>
                </div>
                <button type="submit" name="upload_babu" class="babu-button babu-button-primary">Upload File</button>
            </form>
        </div>
    </div>
    <?php
}

function babu_manager_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div id="babu-notification" data-type="error" data-message="Gagal memuat daftar. Silakan coba lagi."></div>';
        return;
    }

    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    if ($babu_list === null) {
        echo '<div id="babu-notification" data-type="error" data-message="Gagal memuat daftar babu!"></div>';
        error_log('Babu List Error: Failed to fetch babu list, Error: ' . $wpdb->last_error);
        return;
    }

    $total_babu = count($babu_list);
    ?>
    <div class="wrap">
        <h1 class="babu-page-title">Daftar Babu</h1>
        <div class="babu-box">
            <div class="babu-header">
                <span>Total Babu: <?php echo $total_babu; ?></span>
                <button class="babu-button babu-button-secondary copy-btn" onclick="babuCopyList()">Copy Daftar</button>
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

function babu_manager_delete_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (!babu_manager_check_table()) {
        echo '<div id="babu-notification" data-type="error" data-message="Gagal memuat halaman. Silakan coba lagi."></div>';
        error_log('Babu Delete Error: Table check failed');
        return;
    }

    // Log all POST data
    if (!empty($_POST)) {
        error_log('Delete POST data: ' . print_r($_POST, true));
    }

    // Handle delete single
    if (isset($_POST['delete_by_id']) && isset($_POST['_wpnonce']) && isset($_POST['babu_id'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'babu_manager_delete_babu')) {
            $id = intval($_POST['babu_id']);
            error_log('Attempting to delete babu ID: ' . $id);
            $babu_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_name WHERE id = %d", $id));
            if ($babu_name) {
                $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
                if ($result === false) {
                    echo '<div id="babu-notification" data-type="error" data-message="Gagal menghapus babu: ' . esc_html($babu_name) . '."></div>';
                    error_log('Babu Delete Error: Failed to delete babu ID ' . $id . ', Name: ' . $babu_name . ', Error: ' . $wpdb->last_error);
                } else {
                    if (!babu_manager_update_numbers()) {
                        echo '<div id="babu-notification" data-type="error" data-message="Gagal memperbarui nomor urut!"></div>';
                        error_log('Babu Delete Error: Failed to update numbers after deleting ID ' . $id);
                    } else {
                        babu_manager_update_target();
                        babu_manager_backup_list();
                        echo '<div id="babu-notification" data-type="success" data-message="Babu \'' . esc_html($babu_name) . '\' dihapus!"></div>';
                    }
                }
            } else {
                echo '<div id="babu-notification" data-type="error" data-message="Babu tidak ditemukan!"></div>';
                error_log('Babu Delete Error: Babu ID ' . $id . ' not found');
            }
        } else {
            echo '<div id="babu-notification" data-type="error" data-message="Verifikasi keamanan gagal!"></div>';
            error_log('Babu Delete Error: Nonce verification failed for delete_by_id');
        }
    } elseif (isset($_POST['delete_by_id'])) {
        echo '<div id="babu-notification" data-type="error" data-message="Data tidak lengkap untuk hapus babu!"></div>';
        error_log('Babu Delete Error: Missing nonce or babu_id');
    }

    // Handle delete all
    if (isset($_POST['delete_all']) && isset($_POST['_wpnonce'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'babu_manager_delete_all')) {
            error_log('Attempting to delete all babu');
            $result = $wpdb->query("DELETE FROM $table_name");
            if ($result === false) {
                echo '<div id="babu-notification" data-type="error" data-message="Gagal menghapus semua babu!"></div>';
                error_log('Babu Delete Error: Failed to delete all babu, Error: ' . $wpdb->last_error);
            } else {
                babu_manager_update_target();
                babu_manager_backup_list();
                echo '<div id="babu-notification" data-type="success" data-message="Semua babu dihapus!"></div>';
            }
        } else {
            echo '<div id="babu-notification" data-type="error" data-message="Verifikasi keamanan gagal!"></div>';
            error_log('Babu Delete Error: Nonce verification failed for delete_all');
        }
    } elseif (isset($_POST['delete_all'])) {
        echo '<div id="babu-notification" data-type="error" data-message="Data tidak lengkap untuk hapus semua!"></div>';
        error_log('Babu Delete Error: Missing nonce for delete_all');
    }

    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
    if ($babu_list === null) {
        echo '<div id="babu-notification" data-type="error" data-message="Gagal memuat daftar babu!"></div>';
        error_log('Babu Delete Error: Failed to fetch babu list, Error: ' . $wpdb->last_error);
        return;
    }
    ?>
    <div class="wrap">
        <h1 class="babu-page-title">Hapus Babu</h1>
        <form method="post" id="delete-all-babu" class="babu-form">
            <?php wp_nonce_field('babu_manager_delete_all'); ?>
            <input type="hidden" name="delete_all" value="1">
            <button type="submit" class="babu-button babu-button-danger">Hapus Semua Babu</button>
        </form>
        <div class="babu-search">
            <input type="text" id="babu-search" placeholder="Cari Babu..." oninput="babuSearch()">
        </div>
        <div class="babu-box">
            <table class="babu-table">
                <thead>
                    <tr>
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
                                <form method="post" class="delete-babu-form" data-babu-name="<?php echo esc_attr($babu->name); ?>">
                                    <?php wp_nonce_field('babu_manager_delete_babu'); ?>
                                    <input type="hidden" name="babu_id" value="<?php echo esc_attr($babu->id); ?>">
                                    <input type="hidden" name="delete_by_id" value="1">
                                    <button type="submit" class="babu-button babu-button-danger">Hapus</button>
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