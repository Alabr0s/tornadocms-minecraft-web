<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'servers';

// Sunucu ID kontrolü
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Sunucu ID belirtilmedi!';
    header('Location: servers.php');
    exit;
}

$server_id = (int)$_GET['id'];

// Sunucu bilgilerini çek
$server_query = $db->prepare("SELECT * FROM websender_servers WHERE id = ?");
$server_query->execute([$server_id]);
$server = $server_query->fetch();

if (!$server) {
    $_SESSION['error'] = 'Sunucu bulunamadı!';
    header('Location: servers.php');
    exit;
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verileri al ve doğrula
        $name = trim($_POST['name']);
        $host = trim($_POST['host']);
        $port = (int)$_POST['port'];
        $password = trim($_POST['password']);

        if (empty($name) || empty($host) || empty($port)) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Resim yükleme
        $image_path = $server['image'];
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

            $upload_dir = '../assets/images/servers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('server_') . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                // Eski resmi sil
                if ($server['image'] && file_exists('../' . $server['image'])) {
                    unlink('../' . $server['image']);
                }
                $image_path = 'assets/images/servers/' . $file_name;
            } else {
                throw new Exception('Resim yüklenirken bir hata oluştu!');
            }
        }

        // Şifre değiştirildi mi?
        $update_password = !empty($password);
        
        // Sunucuyu güncelle
        if ($update_password) {
            $update_query = $db->prepare("
                UPDATE websender_servers 
                SET name = ?, host = ?, port = ?, password = ?, image = ? 
                WHERE id = ?
            ");
            $result = $update_query->execute([$name, $host, $port, $password, $image_path, $server_id]);
        } else {
            $update_query = $db->prepare("
                UPDATE websender_servers 
                SET name = ?, host = ?, port = ?, image = ? 
                WHERE id = ?
            ");
            $result = $update_query->execute([$name, $host, $port, $image_path, $server_id]);
        }

        if ($result) {
            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Sunucu güncellendi',
                $name . ' sunucusu güncellendi',
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'Sunucu başarıyla güncellendi!';
            header('Location: servers.php');
            exit;
        } else {
            throw new Exception('Sunucu güncellenirken bir hata oluştu!');
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
    <title>Sunucu Düzenle - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Sunucu Düzenle</h1>
                <a href="servers.php" class="back-btn">
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
                        <label>Sunucu Adı</label>
                        <input type="text" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($server['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Resim</label>
                        <div class="image-upload">
                            <input type="file" name="image" id="image" accept="image/*">
                            <label for="image" class="upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Yeni Resim Seç</span>
                            </label>
                            <div id="image-preview">
                                <?php if ($server['image']): ?>
                                    <img src="../<?php echo $server['image']; ?>" class="preview-image">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Host</label>
                            <input type="text" name="host" class="form-control" 
                                   value="<?php echo htmlspecialchars($server['host']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Port</label>
                            <input type="number" name="port" class="form-control" 
                                   value="<?php echo $server['port']; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>WebSender Şifresi (Boş bırakılırsa değiştirilmez)</label>
                        <input type="password" name="password" class="form-control">
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