<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'cases';

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verileri al ve doğrula
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $icon = trim($_POST['icon']);
        $display_order = (int)$_POST['display_order'];

        if (empty($name) || empty($icon)) {
            throw new Exception('Lütfen zorunlu alanları doldurun!');
        }

        // Kategoriyi ekle
        $insert_query = $db->prepare("
            INSERT INTO case_categories (name, description, icon, display_order, status) 
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $result = $insert_query->execute([$name, $description, $icon, $display_order]);

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
            header('Location: cases.php');
            exit;
        } else {
            throw new Exception('Kategori eklenirken bir hata oluştu!');
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
                <a href="cases.php" class="back-btn">
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
                        <label>Kategori Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>

                    <div class="form-group">
                        <label>İkon</label>
                        <div class="icon-selector">
                            <div class="preview-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <input type="text" name="icon" class="form-control" 
                                   value="fas fa-box" required>
                        </div>
                        <small class="form-text">
                            Font Awesome 6 ikonları kullanılabilir. 
                            <a href="https://fontawesome.com/icons" target="_blank">İkon listesi</a>
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="display_order" class="form-control" 
                               value="0" min="0">
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
    const previewIcon = document.querySelector('.preview-icon i');

    iconInput.addEventListener('input', function() {
        const iconClass = this.value;
        previewIcon.className = iconClass;
    });
    </script>
</body>
</html> 