<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'users';

// Kullanıcı ID kontrolü
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Kullanıcı ID belirtilmedi!';
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Kullanıcı bilgilerini çek
$user_query = $db->prepare("SELECT * FROM authme WHERE id = ?");
$user_query->execute([$user_id]);
$user = $user_query->fetch();

if (!$user) {
    $_SESSION['error'] = 'Kullanıcı bulunamadı!';
    header('Location: users.php');
    exit;
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Güncelleme verilerini hazırla
        $updates = [];
        $params = [];

        // E-posta güncelleme
        if (!empty($_POST['email']) && $_POST['email'] !== $user['email']) {
            $updates[] = "email = ?";
            $params[] = $_POST['email'];
        }

        // Coin güncelleme
        if (isset($_POST['coins']) && $_POST['coins'] !== $user['coins']) {
            $updates[] = "coins = ?";
            $params[] = (int)$_POST['coins'];

            // Coin log kaydı
            $coin_difference = (int)$_POST['coins'] - $user['coins'];
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Coin güncellendi',
                sprintf(
                    '%s kullanıcısının coin miktarı %s (%d → %d)', 
                    $user['username'],
                    $coin_difference > 0 ? $coin_difference . ' arttırıldı' : abs($coin_difference) . ' azaltıldı',
                    $user['coins'],
                    $_POST['coins']
                ),
                $_SERVER['REMOTE_ADDR']
            ]);
        }

        // Şifre güncelleme
        if (!empty($_POST['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        // Parametreleri tamamla
        if (!empty($updates)) {
            $params[] = $user_id;

            // Güncelleme sorgusu
            $sql = "UPDATE authme SET " . implode(", ", $updates) . " WHERE id = ?";
            $update_query = $db->prepare($sql);
            $result = $update_query->execute($params);

            if ($result) {
                $db->commit();
                $_SESSION['success'] = 'Kullanıcı başarıyla güncellendi!';
                header('Location: users.php');
                exit;
            } else {
                throw new Exception('Güncelleme başarısız oldu');
            }
        } else {
            $_SESSION['error'] = 'Güncellenecek veri bulunamadı!';
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Düzenle - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Kullanıcı Düzenle</h1>
                <a href="users.php" class="back-btn">
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

            <div class="edit-container">
                <div class="user-info-header">
                    <img src="https://mc-heads.net/avatar/<?php echo $user['username']; ?>/100" 
                         alt="<?php echo htmlspecialchars($user['username']); ?>" 
                         class="user-avatar-large">
                    <div class="user-details">
                        <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                        <p>Kayıt Tarihi: <?php echo date('d.m.Y H:i', $user['regdate']); ?></p>
                        <p>Son Giriş: <?php echo $user['lastlogin'] ? date('d.m.Y H:i', $user['lastlogin']) : 'Hiç giriş yapmadı'; ?></p>
                    </div>
                </div>

                <form method="post" class="edit-form">
                    <div class="form-group">
                        <label>E-posta</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                               class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Coin Miktarı</label>
                        <input type="number" name="coins" value="<?php echo $user['coins']; ?>" 
                               class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Yeni Şifre</label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Değiştirmek için yeni şifre girin">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="save-btn">
                            <i class="fas fa-save"></i>
                            Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>

                <div class="user-history">
                    <h3>Son İşlemler</h3>
                    <div class="history-tabs">
                        <button class="tab-btn active" data-tab="store">Market</button>
                        <button class="tab-btn" data-tab="cases">Kasalar</button>
                        <button class="tab-btn" data-tab="coins">Coin</button>
                    </div>

                    <div class="tab-content active" id="store">
                        <?php
                        $store_query = $db->prepare("
                            SELECT * FROM store_history 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT 10
                        ");
                        $store_query->execute([$user_id]);
                        $store_history = $store_query->fetchAll();
                        ?>
                        
                        <?php if ($store_history): ?>
                            <div class="history-list">
                                <?php foreach ($store_history as $item): ?>
                                    <div class="history-item">
                                        <div class="history-icon">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                        <div class="history-info">
                                            <span class="history-title"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                            <span class="history-date"><?php echo date('d.m.Y H:i', strtotime($item['created_at'])); ?></span>
                                        </div>
                                        <div class="history-amount">
                                            <?php echo number_format($item['price']); ?> Coin
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">Henüz market işlemi yok.</p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content" id="cases">
                        <?php
                        $cases_query = $db->prepare("
                            SELECT ch.*, c.name as case_name 
                            FROM case_history ch
                            JOIN cases c ON ch.case_id = c.id
                            WHERE ch.user_id = ? 
                            ORDER BY ch.created_at DESC 
                            LIMIT 10
                        ");
                        $cases_query->execute([$user_id]);
                        $cases_history = $cases_query->fetchAll();
                        ?>
                        
                        <?php if ($cases_history): ?>
                            <div class="history-list">
                                <?php foreach ($cases_history as $case): ?>
                                    <div class="history-item">
                                        <div class="history-icon">
                                            <i class="fas fa-box-open"></i>
                                        </div>
                                        <div class="history-info">
                                            <span class="history-title"><?php echo htmlspecialchars($case['case_name']); ?></span>
                                            <span class="history-date"><?php echo date('d.m.Y H:i', strtotime($case['created_at'])); ?></span>
                                        </div>
                                        <div class="history-amount">
                                            <?php echo number_format($case['coins_won']); ?> Coin
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">Henüz kasa açılmamış.</p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-content" id="coins">
                        <?php
                        $coins_query = $db->prepare("
                            SELECT * FROM coin_transactions 
                            WHERE user_id = ? AND status = 'completed'
                            ORDER BY created_at DESC 
                            LIMIT 10
                        ");
                        $coins_query->execute([$user_id]);
                        $coins_history = $coins_query->fetchAll();
                        ?>
                        
                        <?php if ($coins_history): ?>
                            <div class="history-list">
                                <?php foreach ($coins_history as $transaction): ?>
                                    <div class="history-item">
                                        <div class="history-icon">
                                            <i class="fas fa-coins"></i>
                                        </div>
                                        <div class="history-info">
                                            <span class="history-title">Coin Yükleme</span>
                                            <span class="history-date"><?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?></span>
                                        </div>
                                        <div class="history-amount">
                                            <?php echo number_format($transaction['amount']); ?> Coin
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">Henüz coin yüklemesi yok.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Tab sistemi
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', () => {
            // Aktif tab'ı değiştir
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            
            // İçeriği göster/gizle
            const tabId = button.dataset.tab;
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
        });
    });
    </script>
</body>
</html> 