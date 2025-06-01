<?php
// Membatasi akses menu untuk Babu Admin
function babu_manager_restrict_admin_menu() {
    if (current_user_can('babu_admin') && !current_user_can('administrator')) {
        remove_menu_page('index.php'); // Dashboard
        remove_menu_page('profile.php'); // Profile
        remove_menu_page('users.php'); // Users
        remove_menu_page('edit.php'); // Posts
        remove_menu_page('upload.php'); // Media
        remove_menu_page('edit.php?post_type=page'); // Pages
        remove_menu_page('themes.php'); // Appearance
        remove_menu_page('plugins.php'); // Plugins
        remove_menu_page('tools.php'); // Tools
        remove_menu_page('options-general.php'); // Settings
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

// Fungsi untuk setiap halaman
function babu_manager_home_page() {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'babu_settings';

    if (isset($_POST['save_settings'])) {
        $title = sanitize_text_field($_POST['babu_title']);
        $target = sanitize_text_field($_POST['babu_target']);
        $wpdb->update($settings_table, ['setting_value' => $title], ['setting_key' => 'babu_title']);
        $wpdb->update($settings_table, ['setting_value' => $target], ['setting_key' => 'babu_target']);
        babu_manager_backup_list(); // Backup otomatis
        echo '<div class="updated"><p>Pengaturan disimpan!</p></div>';
    }

    $title = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_title'");
    $target = $wpdb->get_var("SELECT setting_value FROM $settings_table WHERE setting_key = 'babu_target'");
    ?>
    <div class="wrap">
        <h1>Babu Manager - Home</h1>
        <form method="post">
            <label for="babu_title">Judul:</label>
            <input type="text" name="babu_title" value="<?php echo esc_attr($title); ?>" required>
            <label for="babu_target">Target Babu:</label>
            <input type="text" name="babu_target" value="<?php echo esc_attr($target); ?>" required>
            <input type="submit" name="save_settings" class="button-primary" value="Simpan">
        </form>
    </div>
    <?php
}

function babu_manager_add_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (isset($_POST['add_babu'])) {
        $name = sanitize_text_field($_POST['babu_name']);
        $last_number = $wpdb->get_var("SELECT MAX(number) FROM $table_name");
        $new_number = $last_number ? $last_number + 1 : 1;

        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE name = %s", $name));
        if ($exists) {
            echo '<div class="error"><p>Nama komunitas "' . esc_html($name) . '" sudah ada!</p></div>';
        } else {
            $wpdb->insert($table_name, [
                'name' => $name,
                'number' => $new_number
            ]);
            babu_manager_update_target();
            babu_manager_backup_list();
            echo '<div class="updated"><p>Babu "' . esc_html($name) . '" ditambahkan!</p></div>';
        }
    }
    ?>
    <div class="wrap">
        <h1>Add Babu</h1>
        <form method="post">
            <label for="babu_name">Nama Komunitas:</label>
            <input type="text" name="babu_name" required>
            <input type="submit" name="add_babu" class="button-primary" value="Tambah Babu">
        </form>
    </div>
    <?php
}

function babu_manager_upload_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (isset($_POST['upload_babu'])) {
        if (!empty($_FILES['babu_file']['tmp_name'])) {
            $file = $_FILES['babu_file']['tmp_name'];
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $last_number = $wpdb->get_var("SELECT MAX(number) FROM $table_name") ?: 0;
            $added_count = 0;
            $added_names = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                if (preg_match('/^(\d+)\.\s*(.+)$/', $line, $matches)) {
                    $number = (int)$matches[1];
                    $name = sanitize_text_field($matches[2]);

                    $name_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE name = %s", $name));
                    $number_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE number = %d", $number));

                    if ($name_exists || $number_exists) {
                        continue;
                    }

                    $wpdb->insert($table_name, [
                        'name' => $name,
                        'number' => $number
                    ]);
                    $added_names[] = $name;
                    $added_count++;
                } else {
                    $name = sanitize_text_field($line);
                    $name_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE name = %s", $name));
                    if (!$name_exists) {
                        $last_number++;
                        $wpdb->insert($table_name, [
                            'name' => $name,
                            'number' => $last_number
                        ]);
                        $added_names[] = $name;
                        $added_count++;
                    }
                }
            }
            babu_manager_update_target();
            babu_manager_backup_list();
            if ($added_count > 0) {
                echo '<div class="updated"><p>' . $added_count . ' babu diupload: ' . esc_html(implode(', ', $added_names)) . '</p></div>';
            } else {
                echo '<div class="error"><p>Tidak ada babu baru yang diupload (mungkin duplikat).</p></div>';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Upload Babu</h1>
        <form method="post" enctype="multipart/form-data">
            <label for="babu_file">File TXT:</label>
            <input type="file" name="babu_file" accept=".txt" required>
            <input type="submit" name="upload_babu" class="button-primary" value="Upload">
        </form>
    </div>
    <?php
}

function babu_manager_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';
    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY number ASC");
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
                    <div class="babu-item"><?php echo esc_html($babu->number . '. ' . $babu->name); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function babu_manager_delete_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'babu_list';

    if (isset($_POST['delete_by_id'])) {
        $id = intval($_POST['babu_id']);
        $babu_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_name WHERE id = %d", $id));
        $wpdb->delete($table_name, ['id' => $id]);
        
        // Perbarui nomor urut
        $babu_list = $wpdb->get_results("SELECT id FROM $table_name ORDER BY number ASC");
        $number = 1;
        foreach ($babu_list as $babu) {
            $wpdb->update($table_name, ['number' => $number], ['id' => $babu->id]);
            $number++;
        }

        babu_manager_update_target();
        babu_manager_backup_list();
        echo '<div class="updated"><p>Babu "' . esc_html($babu_name) . '" dihapus dan nomor urut diperbarui!</p></div>';
    }

    if (isset($_POST['delete_all'])) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        babu_manager_update_target();
        babu_manager_backup_list();
        echo '<div class="updated"><p>Semua Babu dihapus!</p></div>';
    }

    $babu_list = $wpdb->get_results("SELECT * FROM $table_name ORDER BY number ASC");
    ?>
    <div class="wrap">
        <h1>Delete Babu</h1>
        <form method="post" onsubmit="return confirm('Yakin ingin menghapus semua babu?');">
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