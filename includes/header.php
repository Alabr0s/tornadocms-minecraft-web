<?php
// Kullanıcının coin miktarını çek
if (isset($_SESSION['user_id'])) {
    $coin_query = $db->prepare("SELECT coins FROM authme WHERE id = ?");
    $coin_query->execute([$_SESSION['user_id']]);
    $_SESSION['coins'] = $coin_query->fetchColumn(); // Coin miktarını session'a kaydet
}

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

// Logo yolunu kontrol et
$logo_path = !empty($settings['site_logo']) ? $settings['site_logo'] : 'assets/images/default-logo.png';
?>

<header class="header fixed-header">
    <nav class="nav">
        <a href="index.php" class="logo">
            <img src="<?php echo $logo_path; ?>" alt="<?php echo $settings['site_name']; ?>" class="nav-logo">
        </a>
        <div class="nav-links">
            <a href="index.php" class="<?php echo (!isset($current_page) || $current_page == 'home') ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> ANASAYFA
            </a>
            <a href="store.php" class="<?php echo (isset($current_page) && $current_page == 'store') ? 'active' : ''; ?>">
                <i class="fas fa-shopping-cart"></i> MARKET
            </a>
            <a href="cases.php" class="<?php echo $current_page === 'cases' ? 'active' : ''; ?>">
                <i class="fas fa-box-open"></i> KASA AÇ
            </a>
            <a href="coins.php" class="<?php echo $current_page === 'coins' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i> COIN YÜKLE
            </a>
            <a href="tickets.php" class="<?php echo $current_page === 'tickets' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i> DESTEK
            </a>
            <a href="profile.php" class="<?php echo $current_page === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> PROFİL
            </a>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" <?php echo ($current_page === 'login') ? 'class="active"' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> GİRİŞ YAP
                </a>
            <?php else: ?>
                <div class="user-info-header">
                    <img src="https://mc-heads.net/avatar/<?php echo $_SESSION['username']; ?>/40" alt="skin" class="mc-avatar">
                    <div class="user-details-header">
                        <span class="username"><?php echo $_SESSION['username']; ?></span>
                        <div class="coins-header">
                            <i class="coin-icon"></i>
                            <span><?php echo number_format($_SESSION['coins']); ?></span>
                        </div>
                    </div>
                    <div class="header-buttons" style="display: flex; gap: 5px; margin-left: 10px;">
                        <?php
                        // Admin kontrolü
                        $admin_check = $db->prepare("SELECT is_admin FROM authme WHERE id = ?");
                        $admin_check->execute([$_SESSION['user_id']]);
                        $is_admin = $admin_check->fetchColumn();
                        
                        if ($is_admin): ?>
                            <a href="admin/" class="logout-btn" title="Admin Paneli">
                                <i class="fas fa-crown"></i>
                            </a>
                        <?php endif; ?>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </nav>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userMenu = document.getElementById('userMenu');
    const userTrigger = document.getElementById('userTrigger');
    const userPopup = document.getElementById('userPopup');

    if (userTrigger && userPopup) {
        // Tıklama olayını ekle
        userTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleMenu();
        });

        // Dışarı tıklandığında menüyü kapat
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target)) {
                closeMenu();
            }
        });

        // ESC tuşuna basıldığında menüyü kapat
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });

        // Menüyü aç/kapat fonksiyonu
        function toggleMenu() {
            const isOpen = userPopup.classList.contains('active');
            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        }

        // Menüyü aç
        function openMenu() {
            userPopup.classList.add('active');
            userTrigger.classList.add('active');
        }

        // Menüyü kapat
        function closeMenu() {
            userPopup.classList.remove('active');
            userTrigger.classList.remove('active');
        }
    }
});
</script> 