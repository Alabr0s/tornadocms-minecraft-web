<?php
session_start();
require_once 'includes/config/database.php';

// Aktif sayfa
$current_page = 'login';

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Giriş işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $db->prepare("SELECT * FROM authme WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['coins'] = $user['coins'];
        $_SESSION['role'] = $user['is_admin'] ? 'admin' : 'user';
        
        header('Location: index.php');
        exit;
    } else {
        $error = 'Kullanıcı adı veya şifre hatalı!';
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
    <title>Giriş Yap - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        /* Bildirim stilleri */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            background: rgba(26, 27, 38, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            z-index: 9999;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            border-left: 4px solid #4CAF50;
        }

        .notification.error {
            border-left: 4px solid #f44336;
        }

        .notification-icon {
            font-size: 1.2rem;
        }

        .notification.success .notification-icon {
            color: #4CAF50;
        }

        .notification.error .notification-icon {
            color: #f44336;
        }

        /* Uyarı mesajları */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
        }

        .alert i {
            font-size: 1.1rem;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.2);
            color: #f44336;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        /* Form stilleri */
        .auth-container {
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-box {
            background: rgba(26, 27, 38, 0.95);
            border-radius: 15px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .auth-logo img {
            max-width: 150px;
            height: auto;
        }

        .auth-title {
            text-align: center;
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-color);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
        }

        .auth-btn {
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            background: var(--primary-color);
            color: var(--secondary-color);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .auth-btn:hover {
            background: var(--hover-color);
            transform: translateY(-2px);
        }

        .auth-links {
            margin-top: 1.5rem;
            text-align: center;
        }

        .auth-links a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: var(--primary-color);
        }

        /* Responsive düzenlemeler */
        @media (max-width: 480px) {
            .auth-box {
                padding: 1.5rem;
            }

            .auth-title {
                font-size: 1.3rem;
            }

            .auth-btn {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <img src="<?php echo $logo_path; ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>">
            </div>
            <h1 class="auth-title">Giriş Yap</h1>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    Başarıyla çıkış yaptınız!
                </div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label class="form-label">Kullanıcı Adı</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Şifre</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="auth-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Giriş Yap
                </button>
            </form>
            
            <div class="auth-links">
                <a href="register.php">Hesabın yok mu? Kayıt ol</a>
            </div>
        </div>
    </div>
</body>
</html> 