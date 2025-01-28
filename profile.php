<?php
// Hata raporlamayı aktif et
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/config/database.php';

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'profile';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Settings boşsa varsayılan değerleri ayarla
if (!$settings) {
    $settings = [
        'site_name' => 'Minecraft Server',
        'site_logo' => 'assets/images/default-logo.png'
    ];
}

// Kullanıcı bilgilerini çek
$user_query = $db->prepare("SELECT * FROM authme WHERE id = ?");
$user_query->execute([$_SESSION['user_id']]);
$user = $user_query->fetch();

// Son işlemleri çek
$transactions = [];

    // Market işlemleri
$market_query = $db->prepare("
        SELECT 'market' as type, created_at, item_name as name, price as amount 
        FROM store_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC LIMIT 5
");
$market_query->execute([$_SESSION['user_id']]);
$transactions['market'] = $market_query->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Coin işlemleri
$coin_query = $db->prepare("
        SELECT 'coin' as type, created_at, amount, status 
        FROM coin_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC LIMIT 5
");
$coin_query->execute([$_SESSION['user_id']]);
$transactions['coin'] = $coin_query->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Kasa işlemleri
$case_query = $db->prepare("
        SELECT 
            'case' as type,
            ch.created_at,
        COALESCE(c.name, 'Silinmiş Kasa') as case_name,
        COALESCE(ci.name, 'Silinmiş Ürün') as item_name,
        COALESCE(ci.rarity, 'common') as rarity,
            ch.coins_won as amount
        FROM case_history ch
    LEFT JOIN cases c ON ch.case_id = c.id
    LEFT JOIN case_items ci ON ch.item_id = ci.id
        WHERE ch.user_id = ?
        ORDER BY ch.created_at DESC LIMIT 5
");
$case_query->execute([$_SESSION['user_id']]);
$transactions['case'] = $case_query->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Destek talepleri
$ticket_query = $db->prepare("
        SELECT 'ticket' as type, created_at, subject, status 
        FROM tickets 
        WHERE user_id = ? 
        ORDER BY created_at DESC LIMIT 5
");
$ticket_query->execute([$_SESSION['user_id']]);
$transactions['ticket'] = $ticket_query->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password !== $confirm_password) {
        $error = 'Yeni şifreler eşleşmiyor!';
    } elseif (strlen($new_password) < 6) {
        $error = 'Yeni şifre en az 6 karakter olmalıdır!';
    } else {
        // Mevcut şifreyi kontrol et
        $check_pass = $db->prepare("SELECT password FROM authme WHERE id = ?");
        $check_pass->execute([$_SESSION['user_id']]);
        $current_hash = $check_pass->fetchColumn();
        
        if (password_verify($current_password, $current_hash)) {
            // Yeni şifreyi güncelle
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $db->prepare("UPDATE authme SET password = ? WHERE id = ?");
            $update->execute([$new_hash, $_SESSION['user_id']]);
            $success = 'Şifreniz başarıyla güncellendi!';
        } else {
            $error = 'Mevcut şifreniz yanlış!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/profile.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="profile-container">
            <!-- Profil Başlığı -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="https://mc-heads.net/avatar/<?php echo $user['username']; ?>/100" alt="Avatar">
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                    <div class="profile-stats">
                        <div class="stat">
                            <i class="fas fa-coins"></i>
                            <span><?php echo number_format($user['coins']); ?> Coin</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-box-open"></i>
                            <span><?php echo count($transactions['case']); ?> Kasa Açıldı</span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-shopping-cart"></i>
                            <span><?php echo count($transactions['market']); ?> Market Alışverişi</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-content">
                <!-- Sol Taraf - Son İşlemler -->
                <div class="profile-transactions">
                    <h2>Son İşlemler</h2>
                    
                    <!-- Market İşlemleri -->
                    <div class="transaction-section">
                        <h3><i class="fas fa-shopping-cart"></i> Market İşlemleri</h3>
                        <div class="transaction-list">
                            <?php foreach ($transactions['market'] as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <span class="transaction-name"><?php echo htmlspecialchars($transaction['name']); ?></span>
                                        <span class="transaction-date"><?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?></span>
                                    </div>
                                    <div class="transaction-amount">
                                        <i class="coin-icon"></i>
                                        <span><?php echo number_format($transaction['amount']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Kasa İşlemleri -->
                    <div class="transaction-section">
                        <h3><i class="fas fa-box-open"></i> Kasa İşlemleri</h3>
                        <div class="transaction-list">
                            <?php foreach ($transactions['case'] as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <span class="transaction-name"><?php echo htmlspecialchars($transaction['case_name']); ?></span>
                                        <span class="item-name rarity-<?php echo $transaction['rarity']; ?>"><?php echo htmlspecialchars($transaction['item_name']); ?></span>
                                        <span class="transaction-date"><?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?></span>
                                    </div>
                                    <div class="transaction-amount">
                                        <i class="coin-icon"></i>
                                        <span><?php echo number_format($transaction['amount']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Coin İşlemleri -->
                    <div class="transaction-section">
                        <h3><i class="fas fa-coins"></i> Coin İşlemleri</h3>
                        <div class="transaction-list">
                            <?php foreach ($transactions['coin'] as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-info">
                                        <span class="transaction-name">Coin Yükleme</span>
                                        <span class="transaction-status <?php echo $transaction['status']; ?>">
                                            <?php
                                            switch($transaction['status']) {
                                                case 'completed':
                                                    echo '<i class="fas fa-check-circle"></i> Tamamlandı';
                                                    break;
                                                case 'pending':
                                                    echo '<i class="fas fa-clock"></i> Bekliyor';
                                                    break;
                                                case 'cancelled':
                                                    echo '<i class="fas fa-times-circle"></i> İptal Edildi';
                                                    break;
                                            }
                                            ?>
                                        </span>
                                        <span class="transaction-date"><?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?></span>
                                    </div>
                                    <div class="transaction-amount">
                                        <i class="coin-icon"></i>
                                        <span><?php echo number_format($transaction['amount']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Sağ Taraf - Ayarlar -->
                <div class="profile-settings">
                    <h2>Hesap Ayarları</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <!-- Şifre Değiştirme Formu -->
                    <form method="post" class="settings-form">
                        <div class="form-group">
                            <label>Mevcut Şifre</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Yeni Şifre</label>
                            <input type="password" name="new_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Yeni Şifre (Tekrar)</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-primary">
                            <i class="fas fa-key"></i>
                            Şifreyi Değiştir
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 