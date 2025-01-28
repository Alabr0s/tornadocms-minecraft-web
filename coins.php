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

// Aktif sayfa - header.php'den önce tanımlanmalı
$current_page = 'coins';

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

// Son 5 coin işlemini çek
$transactions_query = $db->prepare("
    SELECT ct.*, u.username 
    FROM coin_transactions ct
    JOIN authme u ON ct.user_id = u.id
    WHERE ct.user_id = ?
    ORDER BY ct.created_at DESC
    LIMIT 5
");
$transactions_query->execute([$_SESSION['user_id']]);
$transactions = $transactions_query->fetchAll(PDO::FETCH_ASSOC);

// Yeni ödeme oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount = (int)$_POST['amount'];
    
    if ($amount < 10) {
        $error = 'Minimum 10 coin yükleyebilirsiniz.';
    } else {
        // Yeni transaction oluştur
        $payment_id = uniqid('COIN_');
        $stmt = $db->prepare("
            INSERT INTO coin_transactions (user_id, amount, payment_id, status) 
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$_SESSION['user_id'], $amount, $payment_id]);

        // Shopier API parametreleri
        $api_key = trim($settings['shopier_api_key']);
        $api_secret = trim($settings['shopier_api_secret']);
        $website_index = (int)$settings['shopier_website_index'];

        // Kullanıcı kayıt tarihi hesapla
        $user_query = $db->prepare("SELECT regdate FROM authme WHERE id = ?");
        $user_query->execute([$_SESSION['user_id']]);
        $user_data = $user_query->fetch();
        $register_date = date('Y.m.d', $user_data['regdate']);
        $buyer_account_age = (int)((time() - strtotime($register_date)) / 86400);

        // Shopier için gerekli parametreler
        $shopier_params = array(
            'API_key' => $api_key,
            'website_index' => $website_index,
            'platform_order_id' => $payment_id,
            'product_name' => $amount . ' Coin',
            'product_type' => 1,
            'buyer_name' => $_SESSION['username'],
            'buyer_surname' => $_SESSION['username'],
            'buyer_email' => 'buyer@example.com',
            'buyer_account_age' => $buyer_account_age,
            'buyer_id_nr' => 0,
            'buyer_phone' => '5555555555',
            'billing_address' => 'Türkiye',
            'billing_city' => 'İstanbul',
            'billing_country' => 'TR',
            'billing_postcode' => '',
            'shipping_address' => 'Türkiye',
            'shipping_city' => 'İstanbul',
            'shipping_country' => 'TR',
            'shipping_postcode' => '',
            'total_order_value' => number_format($amount, 2, '.', ''),
            'currency' => 0, // TL için 0
            'platform' => 0,
            'is_in_frame' => 1,
            'current_language' => 0, // TR için 0
            'modul_version' => '1.0.4',
            'random_nr' => rand(1000000, 9999999)
        );

        // Signature oluştur
        $data = $shopier_params['random_nr'] . 
                $shopier_params['platform_order_id'] . 
                $shopier_params['total_order_value'] . 
                $shopier_params['currency'];

        $signature = base64_encode(hash_hmac('SHA256', $data, $api_secret, true));
        $shopier_params['signature'] = $signature;

        // Shopier'a yönlendir
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta content="True" name="HandheldFriendly">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="robots" content="noindex, nofollow, noarchive" />
            <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=0" />
            <title>Güvenli Ödeme Sayfası</title>
        </head>
        <body>
            <form action="https://www.shopier.com/ShowProduct/api_pay4.php" method="post" id="shopier_payment_form" style="display:none">';
            
        foreach ($shopier_params as $key => $value) {
            echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
        }
        
        echo '</form>
            <script>document.getElementById("shopier_payment_form").submit();</script>
        </body>
        </html>';
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coin Yükle - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <!-- Font ve stil dosyaları -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Önce ana stil dosyası yüklenmeli -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Sonra sayfa özel stili -->
    <link rel="stylesheet" href="assets/css/coins.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="coins-container">
            <div class="coins-content">
                <!-- Sol Taraf - Coin Yükleme -->
                <div class="coins-add-section">
                    <h2 class="section-title">
                        <i class="fas fa-coins"></i>
                        Coin Yükle
                    </h2>
                    <div class="coins-info">
                        <p>Mevcut Bakiye: <strong><?php echo number_format($_SESSION['coins']); ?> Coin</strong></p>
                        <p class="coins-rate">1 TL = 1 Coin</p>
                    </div>
                    <?php if (isset($error)): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['success'])): ?>
                        <div class="success-message">Coin yükleme işlemi başarıyla tamamlandı!</div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message">
                            <?php
                            switch($_GET['error']) {
                                case 'payment_failed':
                                    echo 'Ödeme işlemi başarısız oldu.';
                                    break;
                                case 'system_error':
                                    echo 'Sistem hatası oluştu, lütfen daha sonra tekrar deneyin.';
                                    break;
                                default:
                                    echo 'Bir hata oluştu.';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="coins-form">
                        <div class="form-group">
                            <label>Yüklenecek Coin Miktarı</label>
                            <input type="number" name="amount" min="10" step="1" class="form-control" placeholder="Minimum 10 coin" required>
                        </div>
                        <div class="total-price">
                            Ödenecek Tutar: <span id="totalPrice">0.00 TL</span>
                        </div>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-shopping-cart"></i>
                            Coin Satın Al
                        </button>
                    </form>
                </div>

                <!-- Sağ Taraf - Son İşlemler -->
                <div class="coins-history-section">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Son İşlemler
                    </h2>
                    <div class="transactions-list">
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <span class="amount">+<?php echo number_format($transaction['amount']); ?> Coin</span>
                                    <span class="date"><?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?></span>
                                </div>
                                <div class="transaction-status <?php echo $transaction['status']; ?>">
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
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                            <div class="no-transactions">Henüz işlem bulunmuyor.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('input[name="amount"]').addEventListener('input', function() {
            let amount = this.value || 0;
            document.getElementById('totalPrice').textContent = amount + '.00 TL';
        });
    </script>
</body>
</html> 