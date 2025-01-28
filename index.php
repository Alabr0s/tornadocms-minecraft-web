<?php
if (!file_exists('includes/config/database.php')) {
    header('Location: install/index.php');
    exit;
}

session_start();
require_once 'includes/config/database.php';

// Sayfa başlığı ve meta bilgileri
$query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $query->fetch(PDO::FETCH_ASSOC);

// Logo yolunu kontrol et
$logo_path = !empty($settings['site_logo']) ? $settings['site_logo'] : 'assets/images/default-logo.png';
$server_ip = !empty($settings['server_ip']) ? $settings['server_ip'] : 'play.tornadocms.com';
$discord_url = !empty($settings['discord_url']) ? $settings['discord_url'] : 'https://discord.gg/xxxxx';

// Aktif sayfa
$current_page = 'home';

// Header'ı include et
include 'includes/header.php';

// Son blogları çek
$blogs_query = $db->query("SELECT b.*, a.realname as author_name 
                          FROM blogs b 
                          LEFT JOIN authme a ON b.author_id = a.id 
                          WHERE b.status = 1
                          ORDER BY b.created_at DESC 
                          LIMIT 3");
$blogs = $blogs_query ? $blogs_query->fetchAll(PDO::FETCH_ASSOC) : [];

// Son kayıt olan kullanıcıları çek (sorguyu güncelle)
$users_query = $db->query("SELECT realname, username, regdate 
                          FROM authme 
                          ORDER BY id DESC 
                          LIMIT 3");
$latest_users = $users_query->fetchAll(PDO::FETCH_ASSOC);

// Son coin yüklemeleri
$coin_history_query = $db->query("
    SELECT ct.*, a.realname, a.username 
    FROM coin_transactions ct 
    LEFT JOIN authme a ON ct.user_id = a.id 
    WHERE ct.status = 'completed'
    ORDER BY ct.created_at DESC 
    LIMIT 3
");
$coin_history = $coin_history_query->fetchAll(PDO::FETCH_ASSOC);

// PHP kısmına eklenecek sorgu (diğer sorgulardan sonra)
$latest_purchases_query = $db->query("
    SELECT sh.*, a.username, a.realname 
    FROM store_history sh
    LEFT JOIN authme a ON sh.user_id = a.id
    ORDER BY sh.created_at DESC 
    LIMIT 3
");
$latest_purchases = $latest_purchases_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $settings['site_name']; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
        <div class="loader">
            <img src="<?php echo $logo_path; ?>" alt="Loading...">
            <div class="loading-bar">
                <div class="progress"></div>
            </div>
        </div>
    </div>

    <main>
        <!-- Hero Container -->
        <div class="hero-container">
            <div class="particles" id="particles-js"></div>
            
            <div class="hero-content">
                <div class="hero-logo">
                    <img src="<?php echo $logo_path; ?>" alt="<?php echo $settings['site_name']; ?>">
                </div>
                
                <div class="ip-container">
                    <div class="server-ip" onclick="copyIP()">
                        <div class="ip-box">
                            <span class="server-ip-text"><?php echo $server_ip; ?></span>
                            <i class="fas fa-copy copy-icon"></i>
                        </div>
                    </div>
                    <a href="<?php echo $discord_url; ?>" class="discord-btn" target="_blank">
                        <i class="fab fa-discord"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- İçerik Bölümü -->
        <div class="content-section">
            <div class="content-container">
                <!-- Blog Bölümü -->
                <div class="blog-section">
                    <h2 class="section-title">
                        <i class="fas fa-newspaper"></i>
                        Son Haberler
                    </h2>
                    <div class="blog-cards">
                        <?php foreach ($blogs as $blog): ?>
                        <div class="blog-card">
                            <div class="blog-image">
                                <span class="blog-category">Duyuru</span>
                                <img src="<?php echo $blog['image']; ?>" alt="Blog Görseli">
                            </div>
                            <div class="blog-content">
                                <div class="blog-meta">
                                    <span><i class="far fa-calendar"></i><?php echo date('d.m.Y', strtotime($blog['created_at'])); ?></span>
                                    <span><i class="far fa-eye"></i><?php echo $blog['views']; ?> görüntülenme</span>
                                </div>
                                <h3 class="blog-title"><?php echo $blog['title']; ?></h3>
                                <p class="blog-excerpt"><?php echo substr($blog['content'], 0, 120); ?>...</p>
                                <div class="blog-footer">
                                    <div class="blog-author">
                                        <img src="https://mc-heads.net/avatar/<?php echo $blog['author_name']; ?>" alt="Yazar" class="author-avatar">
                                        <span class="author-name"><?php echo $blog['author_name']; ?></span>
                                    </div>
                                    <a href="blog.php?id=<?php echo $blog['id']; ?>" class="read-more">
                                        Devamını Oku <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Sağ Bölüm -->
                <div class="sidebar-content">
                    <!-- Son Kayıt Olan Kullanıcılar -->
                    <div class="latest-users">
                        <h2 class="section-title">
                            <i class="fas fa-users"></i>
                            Son Kayıt Olanlar
                        </h2>
                        <div class="user-cards">
                            <?php foreach ($latest_users as $user): ?>
                            <div class="user-card">
                                <div class="user-avatar">
                                    <img src="https://mc-heads.net/avatar/<?php echo $user['realname']; ?>" alt="Kullanıcı">
                                </div>
                                <div class="user-info">
                                    <h3 class="user-name"><?php echo $user['realname']; ?></h3>
                                    <span class="join-date">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('d.m.Y', strtotime($user['regdate'])); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Son Alışverişler -->
                    <div class="latest-purchases">
                        <h2 class="section-title">
                            <i class="fas fa-shopping-cart"></i>
                            Son Alışverişler
                        </h2>
                        <div class="purchase-cards">
                            <?php if ($latest_purchases): ?>
                                <?php foreach ($latest_purchases as $purchase): ?>
                                <div class="purchase-card">
                                    <div class="purchase-avatar">
                                        <img src="https://mc-heads.net/avatar/<?php echo $purchase['username']; ?>" alt="Kullanıcı">
                                    </div>
                                    <div class="purchase-info">
                                        <h3 class="purchase-name"><?php echo $purchase['realname']; ?></h3>
                                        <div class="purchase-details">
                                            <span><i class="fas fa-shopping-bag"></i><?php echo $purchase['item_name']; ?></span>
                                            <span><i class="far fa-clock"></i><?php echo date('d.m.Y H:i', strtotime($purchase['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <span class="purchase-price">
                                        <i class="fas fa-coins"></i>
                                        <?php echo number_format($purchase['price']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>Henüz hiç alışveriş yapılmamış.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Son Coin Yüklemeleri -->
                    <div class="latest-purchases">
                        <h2 class="section-title">
                            <i class="fas fa-coins"></i>
                            Son Coin Yüklemeleri
                        </h2>
                        <div class="purchase-cards">
                            <?php if ($coin_history): ?>
                                <?php foreach ($coin_history as $coin): ?>
                                <div class="purchase-card">
                                    <div class="purchase-avatar">
                                        <img src="https://mc-heads.net/avatar/<?php echo $coin['username']; ?>" alt="Kullanıcı">
                                    </div>
                                    <div class="purchase-info">
                                        <h3 class="purchase-name"><?php echo $coin['realname']; ?></h3>
                                        <div class="purchase-details">
                                            <span><i class="far fa-clock"></i><?php echo date('d.m.Y H:i', strtotime($coin['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <span class="purchase-price">
                                        <i class="fas fa-coins"></i>
                                        <?php echo number_format($coin['amount']); ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-coins"></i>
                                    <p>Henüz hiç coin yüklemesi yapılmamış.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
    // Preloader
    document.addEventListener('DOMContentLoaded', function() {
        const preloader = document.querySelector('.preloader');
        
        // Minimum yükleme süresi (1.5 saniye)
        const minimumLoadingTime = 1500;
        const loadingStartTime = Date.now();
        
    window.addEventListener('load', function() {
            // Geçen süreyi hesapla
            const elapsedTime = Date.now() - loadingStartTime;
            
            // Eğer minimum süreden az geçmişse, kalan süre kadar bekle
            const remainingTime = Math.max(minimumLoadingTime - elapsedTime, 0);
            
            setTimeout(() => {
        preloader.style.opacity = '0';
        setTimeout(() => {
            preloader.style.display = 'none';
                }, 300); // Fade out animasyonu için 300ms bekle
            }, remainingTime);
        });
    });

    function copyIP() {
        const ip = "<?php echo $server_ip; ?>";
        const serverIp = document.querySelector('.server-ip');
        const copyIcon = document.querySelector('.copy-icon');
        
        navigator.clipboard.writeText(ip).then(() => {
            copyIcon.classList.remove('fa-copy');
            copyIcon.classList.add('fa-check');
            serverIp.classList.add('copied');
            
            setTimeout(() => {
                copyIcon.classList.remove('fa-check');
                copyIcon.classList.add('fa-copy');
                serverIp.classList.remove('copied');
            }, 2000);
        });
    }

    // Sunucu durumu kontrolü
    function checkServerStatus() {
        fetch('https://api.mcsrvstat.us/2/<?php echo $server_ip; ?>')
            .then(response => response.json())
            .then(data => {
                const onlineCount = document.querySelector('.online-count');
                if(data.online) {
                    onlineCount.textContent = data.players.online;
                } else {
                    onlineCount.textContent = '0';
                }
            })
            .catch(() => {
                document.querySelector('.online-count').textContent = '0';
            });
    }

    // Sayfa yüklendiğinde ve her 30 saniyede bir sunucu durumunu kontrol et
    document.addEventListener('DOMContentLoaded', function() {
        checkServerStatus();
        setInterval(checkServerStatus, 30000);
    });

    // Particles.js konfigürasyonu
    particlesJS('particles-js',
    {
        "particles": {
            "number": {
                "value": 80,
                "density": {
                    "enable": true,
                    "value_area": 800
                }
            },
            "color": {
                "value": "#ffd700"
            },
            "shape": {
                "type": "circle"
            },
            "opacity": {
                "value": 0.5,
                "random": true
            },
            "size": {
                "value": 3,
                "random": true
            },
            "line_linked": {
                "enable": true,
                "distance": 150,
                "color": "#ffd700",
                "opacity": 0.2,
                "width": 1
            },
            "move": {
                "enable": true,
                "speed": 2,
                "direction": "none",
                "random": true,
                "straight": false,
                "out_mode": "out",
                "bounce": false
            }
        },
        "interactivity": {
            "detect_on": "canvas",
            "events": {
                "onhover": {
                    "enable": true,
                    "mode": "grab"
                },
                "onclick": {
                    "enable": true,
                    "mode": "push"
                },
                "resize": true
            }
        },
        "retina_detect": true
    });

    document.addEventListener('DOMContentLoaded', function() {
        // User dropdown menüsü
        const userTrigger = document.querySelector('.user-trigger');
        const userPopup = document.querySelector('.user-popup');
        
        if (userTrigger && userPopup) {
            userTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                userPopup.classList.toggle('active');
            });

            // Popup dışına tıklandığında kapanması için
            document.addEventListener('click', function(e) {
                if (!userTrigger.contains(e.target) && !userPopup.contains(e.target)) {
                    userPopup.classList.remove('active');
                }
            });
        }
    });
    </script>
</body>
</html> 