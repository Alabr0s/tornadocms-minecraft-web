<?php
require_once 'includes/config/database.php';

// Shopier'dan gelen verileri al
$status = isset($_POST["status"]) ? $_POST["status"] : '';
$platform_order_id = isset($_POST["platform_order_id"]) ? $_POST["platform_order_id"] : '';
$payment_id = isset($_POST["payment_id"]) ? $_POST["payment_id"] : '';

// İşlem detaylarını veritabanından al
$transaction_query = $db->prepare("
    SELECT ct.*, u.username 
    FROM coin_transactions ct
    JOIN authme u ON ct.user_id = u.id
    WHERE ct.payment_id = ? AND ct.status = 'pending'
");
$transaction_query->execute([$platform_order_id]);
$transaction = $transaction_query->fetch(PDO::FETCH_ASSOC);

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

$message = '';
$status_class = '';

try {
    if ($status === 'success' && $transaction) {
        // İşlemi başlat
        $db->beginTransaction();

        // Transaction durumunu güncelle
        $update_transaction = $db->prepare("
            UPDATE coin_transactions 
            SET status = 'completed' 
            WHERE payment_id = ? AND status = 'pending'
        ");
        $update_transaction->execute([$platform_order_id]);

        // Güncelleme başarılı mı kontrol et
        if ($update_transaction->rowCount() > 0) {
            // Kullanıcının coin miktarını güncelle
            $update_coins = $db->prepare("
                UPDATE authme 
                SET coins = coins + ? 
                WHERE id = ?
            ");
            $update_coins->execute([$transaction['amount'], $transaction['user_id']]);

            // İşlemi tamamla
            $db->commit();

            $message = 'Ödeme başarıyla tamamlandı!';
            $status_class = 'success';
        } else {
            $message = 'Bu işlem daha önce tamamlanmış.';
            $status_class = 'warning';
        }
    } else {
        // Ödeme başarısız
        $db->prepare("
            UPDATE coin_transactions 
            SET status = 'cancelled' 
            WHERE payment_id = ? AND status = 'pending'
        ")->execute([$platform_order_id]);

        $message = 'Ödeme işlemi başarısız oldu.';
        $status_class = 'error';
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $message = 'Bir hata oluştu: ' . $e->getMessage();
    $status_class = 'error';
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme Sonucu - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-color: #1a1b1e;
            --card-bg: #25262b;
            --text-color: #e4e5e7;
            --text-muted: #909296;
            --success-color: #40c057;
            --error-color: #fa5252;
            --warning-color: #fd7e14;
            --primary-color: #339af0;
            --border-color: #2c2d32;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-color);
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--text-color);
        }

        .payment-result {
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
            border: 1px solid var(--border-color);
            backdrop-filter: blur(10px);
        }

        .icon {
            font-size: 4.5rem;
            margin-bottom: 1.5rem;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .success .icon { 
            color: var(--success-color);
            text-shadow: 0 0 20px rgba(64, 192, 87, 0.3);
        }
        .error .icon { 
            color: var(--error-color);
            text-shadow: 0 0 20px rgba(250, 82, 82, 0.3);
        }
        .warning .icon { 
            color: var(--warning-color);
            text-shadow: 0 0 20px rgba(253, 126, 20, 0.3);
        }

        .message {
            margin: 1.5rem 0;
            font-size: 1.2rem;
            color: var(--text-color);
            font-weight: 500;
            line-height: 1.4;
        }

        .redirect-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .amount {
            font-size: 2rem;
            color: var(--primary-color);
            font-weight: 600;
            margin: 1.5rem 0;
            text-shadow: 0 0 20px rgba(51, 154, 240, 0.2);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            0% { transform: translateY(20px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        #countdown {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Mobil için düzenlemeler */
        @media (max-width: 480px) {
            .payment-result {
                padding: 2rem;
                width: 85%;
            }
            .icon {
                font-size: 4rem;
            }
            .message {
                font-size: 1.1rem;
            }
            .amount {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="payment-result <?php echo $status_class; ?>">
        <div class="icon">
            <?php if ($status_class === 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php elseif ($status_class === 'error'): ?>
                <i class="fas fa-times-circle"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php endif; ?>
        </div>
        
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
        
        <?php if ($status_class === 'success' && isset($transaction['amount'])): ?>
            <div class="amount"><?php echo number_format($transaction['amount'], 0, ',', '.'); ?> Coin</div>
        <?php endif; ?>
        
        <div class="redirect-text">
            <span id="countdown">5</span> saniye içinde yönlendiriliyorsunuz...
        </div>
    </div>

    <script>
        let seconds = 5;
        const countdown = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            seconds--;
            countdown.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = 'coins.php';
            }
        }, 1000);
    </script>
</body>
</html> 