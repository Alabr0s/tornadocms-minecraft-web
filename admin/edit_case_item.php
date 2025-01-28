<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'cases';

// İçerik ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: cases.php');
    exit;
}

$item_id = (int)$_GET['id'];

// İçerik bilgilerini çek
$item_query = $db->prepare("SELECT * FROM case_items WHERE id = ?");
$item_query->execute([$item_id]);
$item = $item_query->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: cases.php');
    exit;
}

// Kasa bilgilerini çek
$case_query = $db->prepare("SELECT * FROM cases WHERE id = ?");
$case_query->execute([$item['case_id']]);
$case = $case_query->fetch(PDO::FETCH_ASSOC);

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verileri al ve doğrula
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $commands = trim($_POST['commands']);
        $chance = (float)$_POST['chance'];
        $rarity = $_POST['rarity'];
        $coin_value = (int)$_POST['coin_value'];

        if (empty($name) || empty($commands) || empty($chance)) {
            throw new Exception('Lütfen zorunlu alanları doldurun!');
        }

        $image_path = $item['image'];

        // Yeni resim yüklendi mi?
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['image']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception('Sadece JPG, PNG ve GIF formatları desteklenir!');
            }

            $max_size = 5 * 1024 * 1024; // 5MB
            if ($_FILES['image']['size'] > $max_size) {
                throw new Exception('Resim boyutu 5MB\'dan büyük olamaz!');
            }

            $upload_dir = '../assets/images/cases/items/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('item_') . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                throw new Exception('Resim yüklenirken bir hata oluştu!');
            }

            // Eski resmi sil
            if (file_exists('../' . $item['image'])) {
                unlink('../' . $item['image']);
            }

            $image_path = 'assets/images/cases/items/' . $file_name;
        }

        // İçeriği güncelle
        $update_query = $db->prepare("
            UPDATE case_items 
            SET name = ?, description = ?, image = ?, commands = ?, coin_value = ?, chance = ?, rarity = ?
            WHERE id = ?
        ");
        
        $result = $update_query->execute([
            $name, $description, $image_path, $commands, $coin_value, $chance, $rarity, $item_id
        ]);

        if ($result) {
            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Kasa içeriği düzenlendi',
                $case['name'] . ' kasasındaki ' . $name . ' içeriği düzenlendi',
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'İçerik başarıyla güncellendi!';
            header('Location: case_items.php?id=' . $item['case_id']);
            exit;
        } else {
            throw new Exception('İçerik güncellenirken bir hata oluştu!');
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
    <title>İçerik Düzenle - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1><?php echo htmlspecialchars($case['name']); ?> - İçerik Düzenle</h1>
                <a href="case_items.php?id=<?php echo $item['case_id']; ?>" class="back-btn">
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
                        <label>İçerik Adı</label>
                        <input type="text" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($item['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="description" class="form-control"><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Resim</label>
                        <div class="image-upload">
                            <input type="file" name="image" id="image" accept="image/*">
                            <label for="image" class="upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Resim Seç (Opsiyonel)</span>
                            </label>
                            <div id="image-preview">
                                <?php if ($item['image']): ?>
                                    <img src="../<?php echo htmlspecialchars($item['image']); ?>" 
                                         class="preview-image">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Komutlar</label>
                        <textarea name="commands" class="form-control" required><?php echo htmlspecialchars($item['commands']); ?></textarea>
                        <small class="form-text">
                            %player% = Oyuncunun adı
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Kazanılacak Coin</label>
                        <input type="number" name="coin_value" class="form-control" 
                               value="<?php echo $item['coin_value']; ?>"
                               min="0" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Kazanma Şansı (%)</label>
                            <input type="number" name="chance" class="form-control" 
                                   value="<?php echo $item['chance']; ?>"
                                   step="0.01" min="0.01" max="100" required>
                        </div>

                        <div class="form-group">
                            <label>Nadirlik</label>
                            <select name="rarity" class="form-control" required>
                                <option value="common" <?php echo $item['rarity'] === 'common' ? 'selected' : ''; ?>>Yaygın</option>
                                <option value="uncommon" <?php echo $item['rarity'] === 'uncommon' ? 'selected' : ''; ?>>Az Yaygın</option>
                                <option value="rare" <?php echo $item['rarity'] === 'rare' ? 'selected' : ''; ?>>Nadir</option>
                                <option value="epic" <?php echo $item['rarity'] === 'epic' ? 'selected' : ''; ?>>Destansı</option>
                                <option value="legendary" <?php echo $item['rarity'] === 'legendary' ? 'selected' : ''; ?>>Efsanevi</option>
                            </select>
                        </div>
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
    // Resim önizleme
    const imageInput = document.querySelector('#image');
    const imagePreview = document.querySelector('#image-preview');
    const currentImage = imagePreview.innerHTML;

    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.innerHTML = `<img src="${e.target.result}" class="preview-image">`;
            }
            reader.readAsDataURL(file);
        } else {
            imagePreview.innerHTML = currentImage;
        }
    });
    </script>
</body>
</html> 