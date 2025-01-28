<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);

    try {
        // Önce veritabanı olmadan bağlan
        $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Veritabanını oluştur
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        
        // Veritabanına bağlan
        $pdo->exec("USE `$db_name`");

        // Bağlantı bilgilerini session'a kaydet
        $_SESSION['db_config'] = [
            'host' => $db_host,
            'name' => $db_name,
            'user' => $db_user,
            'pass' => $db_pass
        ];

        header('Location: ?step=3');
        exit;
    } catch (PDOException $e) {
        $error = 'Bağlantı başarısız: ' . $e->getMessage();
    }
}
?>

<div class="database-step">
    <h2>Veritabanı Ayarları</h2>
    <p>MySQL bağlantı bilgilerini girin.</p>

    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="install-form">
        <div class="form-group">
            <label>Sunucu</label>
            <input type="text" name="db_host" value="localhost" required>
        </div>

        <div class="form-group">
            <label>Veritabanı Adı</label>
            <input type="text" name="db_name" required>
        </div>

        <div class="form-group">
            <label>Kullanıcı Adı</label>
            <input type="text" name="db_user" required>
        </div>

        <div class="form-group">
            <label>Şifre</label>
            <input type="password" name="db_pass">
        </div>

        <div class="form-actions">
            <a href="?step=1" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Geri</span>
            </a>
            <button type="submit" class="next-btn">
                <span>Devam Et</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </form>
</div> 