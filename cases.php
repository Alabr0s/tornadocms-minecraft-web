<?php
session_start();
require_once 'includes/config/database.php';

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Aktif sayfa - header.php'den önce tanımlanmalı
$current_page = 'cases';

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

// Kasaları çek
$cases_query = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM case_items WHERE case_id = c.id) as item_count
    FROM cases c 
    WHERE c.status = 1 
    ORDER BY c.price ASC
");
$cases = $cases_query->fetchAll(PDO::FETCH_ASSOC);

// Son açılan kasaları çek
$history_query = $db->prepare("
    SELECT ch.*, c.name as case_name, ci.name as item_name, ci.image as item_image, ci.rarity
    FROM case_history ch
    JOIN cases c ON ch.case_id = c.id
    JOIN case_items ci ON ch.item_id = ci.id
    WHERE ch.user_id = ?
    ORDER BY ch.created_at DESC
    LIMIT 10
");
$history_query->execute([$_SESSION['user_id']]);
$history = $history_query->fetchAll(PDO::FETCH_ASSOC);

// Bekleme süresini formatlayan fonksiyon
if (!function_exists('formatCooldown')) {
    function formatCooldown($cooldown) {
        $days = floor($cooldown / 86400);
        $hours = floor(($cooldown % 86400) / 3600);
        $minutes = floor(($cooldown % 3600) / 60);
        $seconds = $cooldown % 60;
        
        if ($days > 0) {
            return $days . ' Gün';
        } elseif ($hours > 0) {
            return $hours . ' Saat';
        } elseif ($minutes > 0) {
            return $minutes . ' Dakika';
        } else {
            return $seconds . ' Saniye';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasalar - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/cases.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    <style>
        /* Modal düzenlemeleri */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.95);
            background: rgba(26, 27, 38, 0.95);
            border-radius: 15px;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            border: 1px solid rgba(255, 215, 0, 0.1);
            opacity: 0;
            animation: modalOpen 0.3s ease forwards;
        }

        @keyframes modalOpen {
            to {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }

        /* Dönme animasyonu stilleri */
        .case-opening-animation {
            position: relative;
            height: 150px;
            overflow: hidden;
            margin-bottom: 2rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
            border: 1px solid rgba(255, 215, 0, 0.1);
        }

        .items-container {
            display: flex;
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            will-change: transform;
            transform: translateX(0);
        }

        .spin-items-group {
            display: flex;
            flex-shrink: 0;
        }

        .spin-item {
            flex: 0 0 120px;
            height: 150px;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translateZ(0);
            backface-visibility: hidden;
        }

        .spin-item-inner {
            width: 100px;
            height: 120px;
            background: rgba(255, 215, 0, 0.1);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid rgba(255, 215, 0, 0.2);
            padding: 8px;
            transition: transform 0.3s ease;
        }

        .spin-item-inner:hover {
            transform: translateY(-2px);
        }

        .spin-item-image {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 8px;
        }

        .spin-item-name {
            font-size: 0.8rem;
            color: var(--text-color);
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            font-weight: 500;
        }

        .spin-item-coins {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.9rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .spin-item-coins i {
            font-size: 0.8rem;
        }

        /* Seçici çizgisi */
        .selector {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            z-index: 2;
            box-shadow: 0 0 20px var(--primary-color);
            pointer-events: none;
        }

        .selector::before,
        .selector::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
        }

        .selector::before {
            top: 0;
            border-top: 8px solid var(--primary-color);
        }

        .selector::after {
            bottom: 0;
            border-bottom: 8px solid var(--primary-color);
        }

        /* Animasyon efektleri */
        @keyframes glow {
            0%, 100% {
                box-shadow: 0 0 10px var(--primary-color);
            }
            50% {
                box-shadow: 0 0 20px var(--primary-color);
            }
        }

        .spin-item-inner.rarity-legendary {
            animation: glow 2s infinite;
        }

        /* Kazanan öğe efekti */
        .spin-item-inner.winner {
            transform: scale(1.1);
            box-shadow: 0 0 30px var(--primary-color);
            animation: winnerPulse 1s infinite;
        }

        @keyframes winnerPulse {
            0% {
                transform: scale(1.1);
                box-shadow: 0 0 30px var(--primary-color);
            }
            50% {
                transform: scale(1.15);
                box-shadow: 0 0 50px var(--primary-color);
            }
            100% {
                transform: scale(1.1);
                box-shadow: 0 0 30px var(--primary-color);
            }
        }

        /* Gradient overlay'ları güçlendir */
        .case-opening-animation::before,
        .case-opening-animation::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 250px; /* Genişliği artırdık */
            z-index: 1;
            pointer-events: none;
        }

        .case-opening-animation::before {
            left: 0;
            background: linear-gradient(to right, 
                rgba(26, 27, 38, 1) 0%,
                rgba(26, 27, 38, 0.98) 30%,
                rgba(26, 27, 38, 0.5) 70%,
                transparent 100%
            );
        }

        .case-opening-animation::after {
            right: 0;
            background: linear-gradient(to left, 
                rgba(26, 27, 38, 1) 0%,
                rgba(26, 27, 38, 0.98) 30%,
                rgba(26, 27, 38, 0.5) 70%,
                transparent 100%
            );
        }

        /* Won item container düzenlemeleri */
        .won-item-container {
            text-align: center;
            padding: 2rem;
        }

        .won-item-card {
            margin-bottom: 2rem;
            padding: 2rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 12px;
            border: 1px solid rgba(255, 215, 0, 0.1);
        }

        .won-item-card img {
            max-width: 200px;
            height: auto;
            margin-bottom: 1rem;
            border-radius: 8px;
        }

        .won-item-card h4 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-color);
        }

        .won-coins {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .close-modal-btn {
            margin-top: 1.5rem;
            padding: 0.8rem 1.5rem;
            background: var(--primary-color);
            color: var(--secondary-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem auto 0;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .close-modal-btn:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
        }

        /* Nadir seviyelerine göre parıltı efektleri */
        .rarity-common {
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
        }

        .rarity-uncommon {
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.3);
        }

        .rarity-rare {
            box-shadow: 0 0 20px rgba(0, 123, 255, 0.4);
        }

        .rarity-epic {
            box-shadow: 0 0 25px rgba(163, 53, 238, 0.5);
        }

        .rarity-legendary {
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.6);
            animation: legendary-glow 2s infinite;
        }

        @keyframes legendary-glow {
            0% { box-shadow: 0 0 30px rgba(255, 215, 0, 0.6); }
            50% { box-shadow: 0 0 50px rgba(255, 215, 0, 0.8); }
            100% { box-shadow: 0 0 30px rgba(255, 215, 0, 0.6); }
        }

        /* Responsive düzenlemeler */
        @media (max-width: 768px) {
            .modal-content {
                padding: 1.5rem;
            }

            .won-item-card {
                padding: 1.5rem;
            }

            .won-item-card img {
                max-width: 150px;
            }

            .won-item-card h4 {
                font-size: 1.2rem;
            }

            .close-modal-btn {
                padding: 0.7rem 1.2rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="cases-container">
            <!-- Kasalar -->
            <div class="cases-grid">
                <?php foreach ($cases as $case): ?>
                    <div class="case-card" data-id="<?php echo $case['id']; ?>">
                        <div class="case-image">
                            <img src="<?php echo htmlspecialchars($case['image']); ?>" alt="<?php echo htmlspecialchars($case['name']); ?>">
                        </div>
                        <div class="case-info">
                            <h3><?php echo htmlspecialchars($case['name']); ?></h3>
                            <div class="case-price">
                                <i class="coin-icon"></i>
                                <span><?php echo number_format($case['price']); ?></span>
                            </div>
                            <div class="case-stats">
                                <span><?php echo $case['item_count']; ?> Ödül</span>
                                <span class="cooldown" data-seconds="<?php echo $case['cooldown']; ?>">
                                    <?php echo formatCooldown($case['cooldown']); ?> Bekleme
                                </span>
                            </div>
                        </div>
                        <button class="open-case-btn" onclick="openCase(event, <?php echo (int)$case['id']; ?>)">
                            <i class="fas fa-box-open"></i>
                            Kasa Aç
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Son Açılan Kasalar -->
            <div class="history-section">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Son Açılan Kasalar
                </h2>
                <div class="history-list">
                    <?php foreach ($history as $item): ?>
                        <div class="history-item rarity-<?php echo $item['rarity']; ?>">
                            <img src="<?php echo htmlspecialchars($item['item_image']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                            <div class="history-info">
                                <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                <span class="case-name"><?php echo htmlspecialchars($item['case_name']); ?></span>
                            </div>
                            <div class="coins-won">
                                <i class="coin-icon"></i>
                                <span><?php echo number_format($item['coins_won']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($history)): ?>
                        <div class="no-history">Henüz kasa açılmamış.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Kasa Açma Modal -->
    <div id="caseModal" class="modal">
        <div class="modal-content">
            <div class="case-opening-animation">
                <div class="items-container"></div>
                <div class="selector"></div>
            </div>
            <div class="won-item-container" style="display: none;">
                <div class="won-item"></div>
                <button class="close-modal-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                    Kapat
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/cases.js"></script>
</body>
</html> 