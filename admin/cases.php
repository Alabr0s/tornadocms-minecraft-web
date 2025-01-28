<?php
// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/config/database.php';
require_once 'includes/auth.php';

try {
    // Site ayarlarını çek
    $settings_query = $db->query("SELECT * FROM settings LIMIT 1");
    $settings = $settings_query->fetch(PDO::FETCH_ASSOC);

    // Aktif sayfa
    $current_page = 'cases';

    // Kasaları çek
    $cases_query = $db->query("
        SELECT c.*, 
               COUNT(ch.id) as total_opens,
               (SELECT COUNT(*) FROM case_history ch2 
                WHERE ch2.case_id = c.id 
                AND ch2.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as today_opens
        FROM cases c 
        LEFT JOIN case_history ch ON c.id = ch.case_id
        GROUP BY c.id 
        ORDER BY c.id DESC
    ");
    $cases = $cases_query->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
} catch (Exception $e) {
    die("Genel hata: " . $e->getMessage());
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
    <title>Kasa Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Kasa Yönetimi</h1>
                <div class="header-actions">
                    <a href="add_case.php" class="action-button">
                        <i class="fas fa-box-open"></i>
                        Kasa Ekle
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

            <div class="cases-grid">
                <?php if (empty($cases)): ?>
                    <div class="no-items">Henüz kasa eklenmemiş.</div>
                <?php else: ?>
                    <?php foreach ($cases as $case): ?>
                        <div class="case-card">
                            <div class="case-image">
                                <img src="../<?php echo htmlspecialchars($case['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($case['name']); ?>">
                            </div>
                            
                            <div class="case-info">
                                <h3><?php echo htmlspecialchars($case['name']); ?></h3>
                                <?php if (!empty($case['description'])): ?>
                                    <p class="case-description">
                                        <?php echo htmlspecialchars($case['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="case-stats">
                                    <span title="Bugün açılma">
                                        <i class="fas fa-box-open"></i>
                                        <?php echo $case['today_opens']; ?>
                                    </span>
                                    <span title="Toplam açılma">
                                        <i class="fas fa-history"></i>
                                        <?php echo $case['total_opens']; ?>
                                    </span>
                                </div>

                                <div class="case-price">
                                    <span class="price"><?php echo number_format($case['price']); ?></span>
                                    <i class="fas fa-coins"></i>
                                </div>
                            </div>

                            <div class="case-actions">
                                <a href="edit_case.php?id=<?php echo $case['id']; ?>" 
                                   class="action-btn edit" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="case_items.php?id=<?php echo $case['id']; ?>" 
                                   class="action-btn items" title="İçerik">
                                    <i class="fas fa-box"></i>
                                </a>
                                <button type="button" 
                                        onclick="toggleCase(<?php echo $case['id']; ?>)"
                                        class="action-btn <?php echo $case['status'] ? 'active' : 'inactive'; ?>"
                                        title="<?php echo $case['status'] ? 'Devre Dışı Bırak' : 'Aktifleştir'; ?>">
                                    <i class="fas <?php echo $case['status'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                </button>
                                <button type="button" 
                                        onclick="deleteCase(<?php echo $case['id']; ?>, '<?php echo htmlspecialchars($case['name']); ?>')"
                                        class="action-btn delete"
                                        title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    function toggleCase(caseId) {
        if (confirm('Kasa durumunu değiştirmek istediğinize emin misiniz?')) {
            fetch('ajax/toggle_case.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ case_id: caseId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Bir hata oluştu');
                }
            });
        }
    }

    function deleteCase(caseId, caseName) {
        if (confirm(`"${caseName}" kasasını silmek istediğinize emin misiniz? Bu işlem geri alınamaz!`)) {
            fetch('ajax/delete_case.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ case_id: caseId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Bir hata oluştu');
                }
            });
        }
    }
    </script>
</body>
</html> 