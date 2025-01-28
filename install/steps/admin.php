<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);

    if (empty($username) || empty($password) || empty($email)) {
        $error = 'Tüm alanları doldurun.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        try {
            // Veritabanı bağlantısı
            $db_config = $_SESSION['db_config'];
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // SQL sorgularını genişletelim ve tüm tabloları oluşturalım
            $sql = "
            -- AuthMe tablosu (Genişletilmiş)
CREATE TABLE authme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    realname VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    ip VARCHAR(40) DEFAULT NULL,
    lastlogin BIGINT DEFAULT NULL,
    x DOUBLE DEFAULT '0',
    y DOUBLE DEFAULT '0',
    z DOUBLE DEFAULT '0',
    world VARCHAR(255) DEFAULT 'world',
    regdate BIGINT DEFAULT NULL,
    regip VARCHAR(40) DEFAULT NULL,
    yaw FLOAT DEFAULT NULL,
    pitch FLOAT DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    isLogged SMALLINT DEFAULT '0',
    hasSession SMALLINT DEFAULT '0',
    totp VARCHAR(32) DEFAULT NULL,
    coins INT DEFAULT 0,
    role ENUM('admin', 'moderator', 'user') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Site ayarları tablosu
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `site_name` varchar(255) NOT NULL DEFAULT 'Tornado CMS',
    `site_logo` varchar(255) DEFAULT 'https://mir-s3-cdn-cf.behance.net/projects/404/afb25e182672951.Y3JvcCwxNDAwLDEwOTUsMCwxNTU.png',
    `server_ip` varchar(255) DEFAULT 'play.tornadocms.com',
    `site_url` varchar(255) DEFAULT NULL,
    `shopier_api_key` varchar(255) DEFAULT NULL,
    `shopier_api_secret` varchar(255) DEFAULT NULL,
    `shopier_callback_url` varchar(255) DEFAULT NULL,
    `shopier_website_url` varchar(255) DEFAULT NULL,
    `shopier_website_index` int(11) DEFAULT 1,
    `discord_url` varchar(255) DEFAULT NULL,
    `maintenance_mode` tinyint(1) DEFAULT 0,
    `maintenance_message` text DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Blog tablosu
CREATE TABLE blogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255) NOT NULL,
    author_id INT NOT NULL,
    status TINYINT(1) DEFAULT 1,
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authme(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Websender Sunucuları
CREATE TABLE websender_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    password VARCHAR(255) NOT NULL,
    status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Market Kategorileri
CREATE TABLE store_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES websender_servers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Market Ürünleri
CREATE TABLE store_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image VARCHAR(255) NOT NULL,
    price INT NOT NULL,
    discount_price INT DEFAULT NULL,
    discount_start DATETIME DEFAULT NULL,
    discount_end DATETIME DEFAULT NULL,
    duration INT DEFAULT NULL COMMENT 'Süre (gün cinsinden), NULL ise süresiz',
    commands TEXT NOT NULL COMMENT 'Satın alındığında çalıştırılacak komutlar',
    stock INT DEFAULT NULL COMMENT 'NULL ise sınırsız stok',
    status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES store_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Satın Alma Geçmişi
CREATE TABLE store_purchases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    server_id INT NOT NULL,
    price INT NOT NULL,
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expire_date DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES authme(id),
    FOREIGN KEY (item_id) REFERENCES store_items(id),
    FOREIGN KEY (server_id) REFERENCES websender_servers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Coin işlemleri için yeni tablo
CREATE TABLE coin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount INT NOT NULL,
    payment_id VARCHAR(255) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES authme(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ticket Kategorileri
CREATE TABLE ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ticketlar
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('open', 'answered', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES authme(id),
    FOREIGN KEY (category_id) REFERENCES ticket_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ticket Mesajları
CREATE TABLE ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_admin tinyint(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (user_id) REFERENCES authme(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Kasa Kategorileri
CREATE TABLE case_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    status TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Kasalar
CREATE TABLE cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    price INT NOT NULL,
    discount_price INT,
    discount_start DATETIME,
    discount_end DATETIME,
    cooldown INT NOT NULL DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES case_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Kasa İçerikleri
CREATE TABLE case_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    coin_value INT NOT NULL, -- Eksik olan coin_value sütunu eklendi
    commands TEXT NOT NULL,
    chance DECIMAL(5,2) NOT NULL, -- Çıkma şansı (%)
    rarity VARCHAR(50) NOT NULL, -- Eksik olan rarity sütunu eklendi
    status TINYINT(1) DEFAULT 1,
    FOREIGN KEY (case_id) REFERENCES cases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Kasa Açma Geçmişi
CREATE TABLE IF NOT EXISTS case_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    case_id INT NOT NULL,
    coins_won INT NOT NULL,
    item_id INT NOT NULL,
    item_won VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES authme(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Store History Tablosu
CREATE TABLE IF NOT EXISTS store_history (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    price INT(11) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tickets Tablosu
CREATE TABLE IF NOT EXISTS `tickets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `status` enum('open','closed','pending') NOT NULL DEFAULT 'open',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Case History Tablosu (Eğer yoksa)
CREATE TABLE IF NOT EXISTS `case_history` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `case_id` int(11) NOT NULL,
    `item_id` int(11) NOT NULL,
    `coins_won` int(11) NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `case_id` (`case_id`),
    KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Coin Transactions Tablosu (Eğer yoksa)
CREATE TABLE IF NOT EXISTS `coin_transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `amount` int(11) NOT NULL,
    `payment_id` varchar(255) NOT NULL,
    `status` enum('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ödeme Yöntemleri Tablosu
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `icon` varchar(255) NOT NULL,
    `display_order` int(11) DEFAULT 0,
    `status` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Coin Paketleri Tablosu
CREATE TABLE IF NOT EXISTS `coin_packages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `coins` int(11) NOT NULL,
    `bonus_coins` int(11) DEFAULT 0,
    `price` decimal(10,2) NOT NULL,
    `status` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Örnek Kategoriler
INSERT INTO ticket_categories (name, icon) VALUES 
('Genel Destek', 'fa-question-circle'),
('Teknik Sorunlar', 'fa-wrench'),
('Ödeme İşlemleri', 'fa-credit-card'),
('Şikayet/İtiraz', 'fa-exclamation-circle');


-- Admin paneli için yeni tablolar
CREATE TABLE admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES authme(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 
            ";

            $pdo->exec($sql);

            // Admin hesabını oluştur
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO authme (username, realname, password, email, role, is_admin) 
                VALUES (?, ?, ?, ?, 'admin', 1)
            ");
            $stmt->execute([$username, $username, $hashed_password, $email]);

            // Site ayarlarını kaydet
            $site_config = $_SESSION['site_config'];
            $stmt = $pdo->prepare("
                INSERT INTO settings (site_name, site_url, discord_url, server_ip) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $site_config['site_name'],
                $site_config['site_url'],
                $site_config['discord_url'],
                $site_config['server_ip']
            ]);

            // database.php dosyasını oluştur
            $db_content = "<?php\n";
            $db_content .= "try {\n";
            $db_content .= "    \$db = new PDO('mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4',\n";
            $db_content .= "        '{$db_config['user']}',\n";
            $db_content .= "        '{$db_config['pass']}'\n";
            $db_content .= "    );\n";
            $db_content .= "    \$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
            $db_content .= "} catch (PDOException \$e) {\n";
            $db_content .= "    die('Veritabanı bağlantı hatası: ' . \$e->getMessage());\n";
            $db_content .= "}\n";

            // Dizin yoksa oluştur
            if (!file_exists('../includes/config')) {
                mkdir('../includes/config', 0777, true);
            }

            // Dosyayı oluştur
            if (!file_put_contents('../includes/config/database.php', $db_content)) {
                throw new Exception('database.php dosyası oluşturulamadı. Dizin yazma izinlerini kontrol edin.');
            }

            // Kurulum tamamlandı, yönlendir
            header('Location: ../index.php');
            exit;
        } catch (Exception $e) {
            $error = 'Kurulum hatası: ' . $e->getMessage();
        }
    }
}
?>

<div class="admin-step">
    <h2>Admin Hesabı</h2>
    <p>Yönetici hesabı oluşturun.</p>

    <?php if (isset($error)): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="install-form">
        <div class="form-group">
            <label>Kullanıcı Adı</label>
            <input type="text" name="username" required>
        </div>

        <div class="form-group">
            <label>E-posta</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Şifre</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-actions">
            <a href="?step=3" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>Geri</span>
            </a>
            <button type="submit" class="next-btn">
                <span>Kurulumu Tamamla</span>
                <i class="fas fa-check"></i>
            </button>
        </div>
    </form>
</div> 