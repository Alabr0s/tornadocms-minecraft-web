<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'store';

// Kategori ID kontrolü
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Kategori ID belirtilmedi!';
    header('Location: store.php');
    exit;
}

$category_id = (int)$_GET['id'];

// Kategori bilgilerini çek
$category_query = $db->prepare("SELECT * FROM store_categories WHERE id = ?");
$category_query->execute([$category_id]);
$category = $category_query->fetch();

if (!$category) {
    $_SESSION['error'] = 'Kategori bulunamadı!';
    header('Location: store.php');
    exit;
}

// Sunucuları çek
$servers_query = $db->query("SELECT * FROM websender_servers WHERE status = 1 ORDER BY id");
$servers = $servers_query->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verileri al ve doğrula
        $name = trim($_POST['name']);
        $server_id = (int)$_POST['server_id'];
        $icon = trim($_POST['icon']);
        $display_order = (int)$_POST['display_order'];

        if (empty($name)) {
            throw new Exception('Kategori adı boş olamaz!');
        }

        // Sunucu kontrolü
        $server_exists = false;
        foreach ($servers as $server) {
            if ($server['id'] === $server_id) {
                $server_exists = true;
                break;
            }
        }

        if (!$server_exists) {
            throw new Exception('Geçersiz sunucu seçimi!');
        }

        // Kategoriyi güncelle
        $update_query = $db->prepare("
            UPDATE store_categories 
            SET server_id = ?, name = ?, icon = ?, display_order = ? 
            WHERE id = ?
        ");
        $result = $update_query->execute([$server_id, $name, $icon, $display_order, $category_id]);

        if ($result) {
            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Kategori güncellendi',
                $name . ' kategorisi güncellendi',
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'Kategori başarıyla güncellendi!';
            header('Location: store.php');
            exit;
        } else {
            throw new Exception('Kategori güncellenirken bir hata oluştu!');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Düzenle - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Kategori Düzenle</h1>
                <a href="store.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="post" class="admin-form">
                    <div class="form-group">
                        <label>Sunucu</label>
                        <select name="server_id" class="form-control" required>
                            <?php foreach ($servers as $server): ?>
                                <option value="<?php echo $server['id']; ?>" 
                                        <?php echo $server['id'] === $category['server_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($server['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Kategori Adı</label>
                        <input type="text" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($category['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>İkon (FontAwesome)</label>
                        <div class="icon-selector">
                            <input type="text" name="icon" class="form-control" 
                                   value="<?php echo htmlspecialchars($category['icon']); ?>" required>
                            <i class="<?php echo htmlspecialchars($category['icon']); ?> preview-icon"></i>
                        </div>
                        <small>Örnek: fas fa-box, fas fa-star, fas fa-crown</small>
                    </div>

                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="display_order" class="form-control" 
                               value="<?php echo $category['display_order']; ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-save"></i>
                            Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    // İkon önizleme
    const iconInput = document.querySelector('input[name="icon"]');
    const previewIcon = document.querySelector('.preview-icon');

    iconInput.addEventListener('input', function() {
        const iconClass = this.value;
        previewIcon.className = iconClass;
    });
    </script>
</body>
</html> 