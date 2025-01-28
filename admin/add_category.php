<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'store';

// Sunucuları çek - websender_servers tablosunu kullan
$servers_query = $db->query("SELECT id, name FROM websender_servers WHERE status = 1 ORDER BY name");
$servers = $servers_query->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $server_id = isset($_POST['server_id']) ? (int)$_POST['server_id'] : 0;
    $icon = trim($_POST['icon']);
    $display_order = (int)$_POST['display_order'];
    
    // Validasyon
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Kategori adı boş olamaz!";
    }
    
    // Sunucu kontrolünü de güncelle
    if ($server_id <= 0) {
        $errors[] = "Lütfen bir sunucu seçin!";
    } else {
        // Sunucunun gerçekten var olup olmadığını kontrol et
        $server_check = $db->prepare("SELECT id FROM websender_servers WHERE id = ? AND status = 1");
        $server_check->execute([$server_id]);
        if (!$server_check->fetch()) {
            $errors[] = "Geçersiz sunucu seçimi!";
        }
    }
    
    if (empty($errors)) {
        try {
            // Kategoriyi ekle
            $insert_query = $db->prepare("
                INSERT INTO store_categories (server_id, name, icon, display_order, status) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $result = $insert_query->execute([$server_id, $name, $icon, $display_order]);

            if ($result) {
                // Log kaydı
                $log_query = $db->prepare("
                    INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                    VALUES (?, ?, ?, ?)
                ");
                $log_query->execute([
                    $_SESSION['user_id'],
                    'Kategori eklendi',
                    $name . ' kategorisi eklendi',
                    $_SERVER['REMOTE_ADDR']
                ]);

                $_SESSION['success'] = 'Kategori başarıyla eklendi!';
                header('Location: store.php');
                exit;
            } else {
                throw new Exception('Kategori eklenirken bir hata oluştu!');
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Ekle - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Kategori Ekle</h1>
                <a href="store.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
            </div>

            <?php if (isset($errors) && count($errors) > 0): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo implode('<br>', $errors); ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="post" class="admin-form">
                    <div class="form-group">
                        <label for="server_id">Sunucu</label>
                        <select name="server_id" id="server_id" class="form-control" required>
                            <option value="">Sunucu Seçin</option>
                            <?php foreach ($servers as $server): ?>
                                <option value="<?php echo $server['id']; ?>"><?php echo htmlspecialchars($server['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Kategori Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>İkon (FontAwesome)</label>
                        <div class="icon-selector">
                            <input type="text" name="icon" class="form-control" 
                                   value="fas fa-box" required>
                            <i class="fas fa-box preview-icon"></i>
                        </div>
                        <small>Örnek: fas fa-box, fas fa-star, fas fa-crown</small>
                    </div>

                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="display_order" class="form-control" 
                               value="0" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-plus"></i>
                            Kategori Ekle
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