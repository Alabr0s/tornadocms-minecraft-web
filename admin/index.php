<?php
// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config/database.php';
require_once 'includes/auth.php'; // Yetki kontrolü

try {
    // Site ayarlarını çek
    $settings_query = $db->query("SELECT * FROM settings LIMIT 1");
    $settings = $settings_query->fetch(PDO::FETCH_ASSOC);

    // Aktif sayfa
    $current_page = 'dashboard';

    // İstatistikleri çek
    $stats = [
        'total_users' => $db->query("SELECT COUNT(*) FROM authme")->fetchColumn(),
        'total_coins' => $db->query("SELECT COALESCE(SUM(coins), 0) FROM authme")->fetchColumn(),
        'total_cases' => $db->query("SELECT COUNT(*) FROM case_history")->fetchColumn(),
        'total_store' => $db->query("SELECT COUNT(*) FROM store_history")->fetchColumn()
    ];

    // Son işlemleri çek
    $recent_transactions = $db->query("
        SELECT * FROM (
            SELECT 'store' as type, 
                   created_at, 
                   user_id, 
                   (SELECT username FROM authme WHERE id = store_history.user_id) as username,
                   item_name COLLATE utf8mb4_unicode_ci as description, 
                   price as amount
            FROM store_history 
            ORDER BY created_at DESC 
            LIMIT 5
        ) as store
        UNION ALL
        SELECT * FROM (
            SELECT 'case' as type, 
                   created_at, 
                   user_id,
                   (SELECT username FROM authme WHERE id = case_history.user_id) as username,
                   (SELECT name COLLATE utf8mb4_unicode_ci FROM cases WHERE id = case_history.case_id) as description,
                   (SELECT price FROM cases WHERE id = case_history.case_id) as amount
            FROM case_history 
            ORDER BY created_at DESC 
            LIMIT 5
        ) as cases
        ORDER BY created_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Son kayıt olan kullanıcıları çek
    $recent_users = $db->query("
        SELECT id, username, coins, regdate 
        FROM authme 
        ORDER BY regdate DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
} catch (Exception $e) {
    die("Genel hata: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Dashboard</h1>
                <div class="admin-user">
                    <img src="https://mc-heads.net/avatar/<?php echo $_SESSION['username']; ?>" alt="Admin" class="admin-avatar">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo number_format($stats['total_users']); ?></span>
                        <span class="stat-label">Toplam Kullanıcı</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-coins"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo number_format($stats['total_coins']); ?></span>
                        <span class="stat-label">Toplam Coin</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-box-open"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo number_format($stats['total_cases']); ?></span>
                        <span class="stat-label">Açılan Kasa</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo number_format($stats['total_store']); ?></span>
                        <span class="stat-label">Market İşlemi</span>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h2>Son İşlemler</h2>
                    <div class="transactions-list">
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon">
                                    <i class="fas fa-<?php echo $transaction['type'] === 'store' ? 'shopping-cart' : 'box-open'; ?>"></i>
                                </div>
                                <div class="transaction-info">
                                    <span class="transaction-user"><?php echo htmlspecialchars($transaction['username']); ?></span>
                                    <span class="transaction-desc"><?php echo htmlspecialchars($transaction['description']); ?></span>
                                </div>
                                <div class="transaction-amount">
                                    <?php echo number_format($transaction['amount']); ?> Coin
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h2>Son Kayıt Olan Kullanıcılar</h2>
                    <div class="users-list">
                        <?php foreach ($recent_users as $user): ?>
                            <div class="user-item">
                                <img src="https://mc-heads.net/avatar/<?php echo $user['username']; ?>" alt="<?php echo $user['username']; ?>" class="user-avatar">
                                <div class="user-info">
                                    <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                                    <span class="user-coins"><?php echo number_format($user['coins']); ?> Coin</span>
                                </div>
                                <div class="user-date">
                                    <?php echo date('d.m.Y H:i', $user['regdate']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html> 