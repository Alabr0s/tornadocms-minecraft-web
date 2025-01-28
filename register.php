<?php
session_start();
require_once 'includes/config/database.php';

// Aktif sayfa
$current_page = 'register';

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Kayıt işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['password_confirm'];
    $email = $_POST['email'];
    
    try {
        // Minecraft kullanıcı adı validasyonu
        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $username)) {
            throw new Exception('Kullanıcı adı 3-16 karakter uzunluğunda olmalı ve sadece harf, rakam ve alt çizgi içermelidir.');
        }

        // Şifre kontrolü
        if (strlen($password) < 6) {
            throw new Exception('Şifre en az 6 karakter olmalıdır.');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Şifreler eşleşmiyor!');
        }

        // Email kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Geçerli bir email adresi giriniz.');
        }

        // Kullanıcı adı kontrolü
        $stmt = $db->prepare("SELECT COUNT(*) FROM authme WHERE username = ?");
        $stmt->execute([$username]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            throw new Exception('Bu kullanıcı adı zaten kullanılıyor!');
        }

        // Kayıt işlemi
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO authme (username, realname, password, email, regdate, regip) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $username,
            $username,
            $hashed_password,
            $email,
            time(),
            $_SERVER['REMOTE_ADDR']
        ]);
        
        if ($result) {
            $success = 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.';
            // 2 saniye sonra login sayfasına yönlendir
            header("Refresh: 2; url=login.php");
        } else {
            throw new Exception('Bir hata oluştu, lütfen tekrar deneyin.');
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Site ayarlarını çek
$query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $query->fetch(PDO::FETCH_ASSOC);
$logo_path = !empty($settings['site_logo']) ? $settings['site_logo'] : 'assets/images/default-logo.png';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <img src="<?php echo $logo_path; ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>">
            </div>
            <h1 class="auth-title">Kayıt Ol</h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" pattern="[a-zA-Z0-9_]{3,16}" 
                           title="3-16 karakter uzunluğunda, sadece harf, rakam ve alt çizgi içerebilir" required>
                </div>
                <div class="form-group">
                    <label class="form-label">E-posta</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="password" class="form-control" minlength="6" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Şifre Tekrar</label>
                    <input type="password" name="password_confirm" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="auth-btn">
                    <i class="fas fa-user-plus"></i>
                    Kayıt Ol
                </button>
            </form>
            
            <div class="auth-links">
                <a href="login.php">Zaten hesabın var mı? Giriş yap</a>
            </div>
        </div>
    </div>
</body>
</html> 