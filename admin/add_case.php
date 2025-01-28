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
        $price = (int)$_POST['price'];
        $cooldown = (int)$_POST['cooldown'];

        if (empty($name) || empty($price)) {
            throw new Exception('Lütfen zorunlu alanları doldurun!');
        }

        // Resim yükleme
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Lütfen bir resim seçin!');
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception('Sadece JPG, PNG ve GIF formatları desteklenir!');
        }

        $max_size = 5 * 1024 * 1024; // 5MB
        if ($_FILES['image']['size'] > $max_size) {
            throw new Exception('Resim boyutu 5MB\'dan büyük olamaz!');
        }

        $upload_dir = '../assets/images/cases/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('case_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            throw new Exception('Resim yüklenirken bir hata oluştu!');
        }

        $image_path = 'assets/images/cases/' . $file_name;

        // Kasayı ekle
        $insert_query = $db->prepare("
            INSERT INTO cases (name, description, image, price, cooldown, status) 
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        
        $result = $insert_query->execute([$name, $description, $image_path, $price, $cooldown]);

        if ($result) {
            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Kasa eklendi',
                $name . ' kasası eklendi',
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'Kasa başarıyla eklendi!';
            header('Location: cases.php');
            exit;
        } else {
            throw new Exception('Kasa eklenirken bir hata oluştu!');
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
    <title>Kasa Ekle - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Kasa Ekle</h1>
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
                <form method="post" class="admin-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Kasa Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="description" class="form-control"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Resim</label>
                        <div class="image-upload">
                            <input type="file" name="image" id="image" accept="image/*" required>
                            <label for="image" class="upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Resim Seç</span>
                            </label>
                            <div id="image-preview"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Fiyat (Coin)</label>
                            <input type="number" name="price" class="form-control" 
                                   min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Bekleme Süresi (Saniye)</label>
                            <input type="number" name="cooldown" class="form-control" 
                                   min="0" value="0">
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-plus"></i>
                            Kasa Ekle
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    // Resim önizleme
    const imageInput = document.querySelector('#image');
    const imagePreview = document.querySelector('#image-preview');

    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.innerHTML = `<img src="${e.target.result}" class="preview-image">`;
            }
            reader.readAsDataURL(file);
        }
    });
    </script>
</body>
</html> 