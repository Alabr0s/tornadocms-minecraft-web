<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'cases';

// Kasa ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: cases.php');
    exit;
}

$case_id = (int)$_GET['id'];

// Kasa bilgilerini çek
$case_query = $db->prepare("SELECT * FROM cases WHERE id = ?");
$case_query->execute([$case_id]);
$case = $case_query->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    header('Location: cases.php');
    exit;
}

// İçerikleri çek
$items_query = $db->prepare("
    SELECT * FROM case_items 
    WHERE case_id = ? 
    ORDER BY chance DESC
");
$items_query->execute([$case_id]);
$items = $items_query->fetchAll(PDO::FETCH_ASSOC);

// Toplam şansı hesapla
$total_chance = 0;
foreach ($items as $item) {
    $total_chance += $item['chance'];
}

// İşlem kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && isset($_POST['item_id'])) {
            $item_id = (int)$_POST['item_id'];
            
            // İçeriği kontrol et
            $item_query = $db->prepare("SELECT name FROM case_items WHERE id = ? AND case_id = ?");
            $item_query->execute([$item_id, $case_id]);
            $item = $item_query->fetch();

            if (!$item) {
                throw new Exception('İçerik bulunamadı!');
            }

            switch ($_POST['action']) {
                case 'toggle_status':
                    // Durumu değiştir
                    $update_query = $db->prepare("UPDATE case_items SET status = NOT status WHERE id = ?");
                    $result = $update_query->execute([$item_id]);
                    
                    if ($result) {
                        $_SESSION['success'] = $item['name'] . ' içeriğinin durumu güncellendi';
                    }
                    break;

                case 'delete':
                    // İçeriği sil
                    $delete_query = $db->prepare("DELETE FROM case_items WHERE id = ?");
                    $result = $delete_query->execute([$item_id]);

                    if ($result) {
                        $_SESSION['success'] = $item['name'] . ' içeriği başarıyla silindi';
                    } else {
                        throw new Exception('Silme işlemi başarısız oldu');
                    }
                    break;

                default:
                    throw new Exception('Geçersiz işlem!');
            }

            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                $_POST['action'] === 'delete' ? 'İçerik silindi' : 'İçerik durumu değiştirildi',
                $_SESSION['success'],
                $_SERVER['REMOTE_ADDR']
            ]);

        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    // Sayfayı yenile
    header('Location: case_items.php?id=' . $case_id);
    exit;
}

// İşlem mesajları
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasa İçerikleri - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1><?php echo htmlspecialchars($case['name']); ?> - İçerikler</h1>
                <div class="header-actions">
                    <a href="add_case_item.php?case_id=<?php echo $case_id; ?>" class="action-button">
                        <i class="fas fa-plus"></i>
                        İçerik Ekle
                    </a>
                    <a href="cases.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                        Geri Dön
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($total_chance > 100): ?>
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    Toplam kazanma şansı %100'ü geçiyor! (%<?php echo number_format($total_chance, 2); ?>)
                </div>
            <?php elseif ($total_chance < 100): ?>
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    Toplam kazanma şansı %100'den az! (%<?php echo number_format($total_chance, 2); ?>)
                </div>
            <?php endif; ?>

            <div class="items-grid">
                <?php if (empty($items)): ?>
                    <div class="no-items">Henüz içerik eklenmemiş.</div>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="item-card rarity-<?php echo $item['rarity']; ?>">
                            <div class="item-image">
                                <img src="../<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     style="max-width: 150px; max-height: 150px; object-fit: contain; margin: 0 auto; display: block;">
                            </div>
                            
                            <div class="item-info">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="item-description">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="item-chance">
                                    <span class="chance-label">Kazanma Şansı:</span>
                                    <span class="chance-value">%<?php echo number_format($item['chance'], 2); ?></span>
                                </div>

                                <div class="item-coin">
                                    <span class="coin-label">Kazanılacak Coin:</span>
                                    <span class="coin-value"><?php echo number_format($item['coin_value']); ?></span>
                                </div>

                                <div class="item-commands">
                                    <span class="commands-label">Komutlar:</span>
                                    <code><?php echo htmlspecialchars($item['commands']); ?></code>
                                </div>
                            </div>

                            <div class="item-actions">
                                <a href="edit_case_item.php?id=<?php echo $item['id']; ?>" 
                                   class="action-btn edit" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <!-- Durum Toggle Butonu -->
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" 
                                            class="action-btn <?php echo $item['status'] ? 'active' : 'inactive'; ?>"
                                            title="<?php echo $item['status'] ? 'Devre Dışı Bırak' : 'Aktifleştir'; ?>"
                                            onclick="return confirm('<?php echo htmlspecialchars($item['name']); ?> içeriğinin durumunu değiştirmek istediğinize emin misiniz?');">
                                        <i class="fas <?php echo $item['status'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                    </button>
                                </form>
                                
                                <!-- Silme Butonu -->
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" 
                                            class="action-btn delete"
                                            title="Sil"
                                            onclick="return confirm('DİKKAT: <?php echo htmlspecialchars($item['name']); ?> içeriğini silmek istediğinize emin misiniz?\n\nBu işlem geri alınamaz!');">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html> 