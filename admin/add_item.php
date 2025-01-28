<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'store';

// Sunucuları çek
$servers_query = $db->query("SELECT * FROM websender_servers WHERE status = 1 ORDER BY id");
$servers = $servers_query->fetchAll(PDO::FETCH_ASSOC);

// Seçili sunucu
$selected_server = isset($_GET['server']) ? (int)$_GET['server'] : ($servers[0]['id'] ?? null);

// Kategorileri çek
$categories_query = $db->prepare("
    SELECT * FROM store_categories 
    WHERE server_id = ? AND status = 1 
    ORDER BY display_order
");
$categories_query->execute([$selected_server]);
$categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verileri al ve doğrula
        $name = trim($_POST['name']);
        $category_id = (int)$_POST['category_id'];
        $description = trim($_POST['description']);
        $price = (int)$_POST['price'];
        $discount_price = !empty($_POST['discount_price']) ? (int)$_POST['discount_price'] : null;
        $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : null;
        $commands = trim($_POST['commands']);
        $stock = !empty($_POST['stock']) ? (int)$_POST['stock'] : null;

        if (empty($name) || empty($commands)) {
            throw new Exception('Lütfen tüm zorunlu alanları doldurun!');
        }

        // Resim yükleme
        $image_path = null;
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

            $upload_dir = '../assets/images/store/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('item_') . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                throw new Exception('Resim yüklenirken bir hata oluştu!');
            }

            $image_path = 'assets/images/store/' . $file_name;
        } else {
            throw new Exception('Lütfen bir resim seçin!');
        }

        // İndirim tarihlerini kontrol et
        $discount_start = null;
        $discount_end = null;
        if ($discount_price !== null) {
            if (empty($_POST['discount_start']) || empty($_POST['discount_end'])) {
                throw new Exception('İndirim fiyatı için başlangıç ve bitiş tarihi gereklidir!');
            }
            $discount_start = date('Y-m-d H:i:s', strtotime($_POST['discount_start']));
            $discount_end = date('Y-m-d H:i:s', strtotime($_POST['discount_end']));
        }

        // Ürünü ekle
        $insert_query = $db->prepare("
            INSERT INTO store_items (
                category_id, name, description, image, price, 
                discount_price, discount_start, discount_end, 
                duration, commands, stock, status
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, 
                ?, ?, ?, 1
            )
        ");
        
        $result = $insert_query->execute([
            $category_id, $name, $description, $image_path, $price,
            $discount_price, $discount_start, $discount_end,
            $duration, $commands, $stock
        ]);

        if ($result) {
            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Ürün eklendi',
                $name . ' ürünü eklendi',
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'Ürün başarıyla eklendi!';
            header('Location: store.php');
            exit;
        } else {
            throw new Exception('Ürün eklenirken bir hata oluştu!');
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
    <title>Ürün Ekle - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Ürün Ekle</h1>
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

            <div class="server-selector">
                <?php foreach ($servers as $server): ?>
                    <a href="?server=<?php echo $server['id']; ?>" 
                       class="server-item <?php echo $server['id'] === $selected_server ? 'active' : ''; ?>">
                        <img src="../<?php echo $server['image']; ?>" alt="<?php echo htmlspecialchars($server['name']); ?>">
                        <span><?php echo htmlspecialchars($server['name']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="form-container">
                <form method="post" class="admin-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category_id" class="form-control" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ürün Adı</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Açıklama</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
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
                            <input type="number" name="price" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>İndirimli Fiyat (Coin)</label>
                            <input type="number" name="discount_price" class="form-control">
                        </div>
                    </div>

                    <div class="form-row discount-dates" style="display: none;">
                        <div class="form-group">
                            <label>İndirim Başlangıç</label>
                            <input type="datetime-local" name="discount_start" class="form-control">
                        </div>

                        <div class="form-group">
                            <label>İndirim Bitiş</label>
                            <input type="datetime-local" name="discount_end" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Süre (Gün)</label>
                            <input type="number" name="duration" class="form-control" 
                                   placeholder="Boş bırakılırsa süresiz">
                        </div>

                        <div class="form-group">
                            <label>Stok</label>
                            <input type="number" name="stock" class="form-control" 
                                   placeholder="Boş bırakılırsa sınırsız">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Komutlar</label>
                        <textarea name="commands" class="form-control" rows="4" required 
                                placeholder="Her satıra bir komut yazın. {player} etiketi oyuncu adı ile değiştirilecektir."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-plus"></i>
                            Ürün Ekle
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

    // İndirim tarihi alanlarını göster/gizle
    const discountPrice = document.querySelector('input[name="discount_price"]');
    const discountDates = document.querySelector('.discount-dates');

    discountPrice.addEventListener('input', function() {
        discountDates.style.display = this.value ? 'flex' : 'none';
    });
    </script>
</body>
</html> 