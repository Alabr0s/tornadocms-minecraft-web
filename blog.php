<?php
session_start();
require_once 'includes/config/database.php';

// Blog ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$blog_id = (int)$_GET['id'];

// Blog detaylarını çek
$blog_query = $db->prepare("
    SELECT b.*, a.realname as author_name 
    FROM blogs b 
    LEFT JOIN authme a ON b.author_id = a.id 
    WHERE b.id = ?
");
$blog_query->execute([$blog_id]);
$blog = $blog_query->fetch(PDO::FETCH_ASSOC);

// Blog bulunamadıysa ana sayfaya yönlendir
if (!$blog) {
    header('Location: index.php');
    exit;
}

// Sayfa başlığı ve meta bilgileri
$query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $query->fetch(PDO::FETCH_ASSOC);
$logo_path = !empty($settings['site_logo']) ? $settings['site_logo'] : 'assets/images/default-logo.png';

// Aktif sayfa
$current_page = 'blog';

// Header'ı include et
include 'includes/header.php';

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog['title']) . ' - ' . $settings['site_name']; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <main>
        <div class="blog-detail-container">
            <div class="blog-detail">
                <div class="blog-header">
                    <div class="blog-meta">
                        <span>
                            <i class="far fa-calendar-alt"></i>
                            <?php echo date('d.m.Y', strtotime($blog['created_at'])); ?>
                        </span>
                        <span>
                            <i class="far fa-user"></i>
                            <?php echo htmlspecialchars($blog['author_name']); ?>
                        </span>
                    </div>
                    <h1 class="blog-title"><?php echo htmlspecialchars($blog['title']); ?></h1>
                </div>

                <div class="blog-image">
                    <img src="<?php echo htmlspecialchars($blog['image']); ?>" alt="<?php echo htmlspecialchars($blog['title']); ?>">
                </div>

                <div class="blog-content">
                    <?php echo $blog['content']; ?>
                </div>

                <div class="blog-footer">
                    <div class="blog-author">
                        <img src="https://mc-heads.net/avatar/<?php echo $blog['author_name']; ?>/100" alt="<?php echo $blog['author_name']; ?>" class="author-avatar">
                        <div class="author-info">
                            <span class="author-name"><?php echo htmlspecialchars($blog['author_name']); ?></span>
                            <span class="author-role">Yazar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        // User dropdown menüsü için JavaScript kodları
        document.addEventListener('DOMContentLoaded', function() {
            const userTrigger = document.querySelector('.user-trigger');
            const userPopup = document.querySelector('.user-popup');
            
            if (userTrigger && userPopup) {
                userTrigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userPopup.classList.toggle('active');
                });

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