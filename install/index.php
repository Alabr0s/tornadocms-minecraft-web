<?php
session_start();

// Eğer database.php varsa kuruluma erişimi engelle
if (file_exists('../includes/config/database.php')) {
    header('Location: ../index.php');
    exit;
}

// Kurulum adımını kontrol et
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tornado CMS Kurulum</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/install.css">
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <img src="assets/images/logo.png" alt="Tornado CMS" class="install-logo">
            <h1 class="install-title">Tornado CMS Kurulum</h1>
            <p class="install-subtitle">Kurulum sihirbazına hoş geldiniz</p>
        </div>
        
        <div class="install-steps">
            <div class="step-item <?php echo $step == 1 ? 'active' : ''; ?>">
                <div class="step-number">1</div>
                <span>Gereksinimler</span>
            </div>
            <div class="step-item <?php echo $step == 2 ? 'active' : ''; ?>">
                <div class="step-number">2</div>
                <span>Veritabanı</span>
            </div>
            <div class="step-item <?php echo $step == 3 ? 'active' : ''; ?>">
                <div class="step-number">3</div>
                <span>Site Ayarları</span>
            </div>
            <div class="step-item <?php echo $step == 4 ? 'active' : ''; ?>">
                <div class="step-number">4</div>
                <span>Admin Hesabı</span>
            </div>
        </div>

        <div class="install-content">
            <?php
            switch ($step) {
                case 1:
                    include 'steps/welcome.php';
                    break;
                case 2:
                    include 'steps/database.php';
                    break;
                case 3:
                    include 'steps/settings.php';
                    break;
                case 4:
                    include 'steps/admin.php';
                    break;
            }
            ?>
        </div>
    </div>

    <script src="assets/js/install.js"></script>
</body>
</html> 