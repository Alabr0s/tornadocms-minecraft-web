<?php
session_start();
require_once 'includes/config/database.php';

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'tickets';

// Kategorileri çek
$categories_query = $db->query("SELECT * FROM ticket_categories WHERE status = 1");
$categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)$_POST['category'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        try {
            $db->beginTransaction();
            
            // Ticket oluştur
            $ticket_stmt = $db->prepare("
                INSERT INTO tickets (user_id, category_id, subject) 
                VALUES (?, ?, ?)
            ");
            $ticket_stmt->execute([$_SESSION['user_id'], $category_id, $subject]);
            $ticket_id = $db->lastInsertId();
            
            // İlk mesajı ekle
            $message_stmt = $db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, message) 
                VALUES (?, ?, ?)
            ");
            $message_stmt->execute([$ticket_id, $_SESSION['user_id'], $message]);
            
            $db->commit();
            header('Location: tickets.php?success=created');
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Bir hata oluştu, lütfen tekrar deneyin.';
        }
    }
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Ticket Oluştur - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/tickets.css">
</head>
<body>
    <div class="tickets-container">
        <div class="tickets-header">
            <h1>Yeni Ticket Oluştur</h1>
            <a href="tickets.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Geri Dön
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="ticket-form">
            <div class="form-group">
                <label>Kategori</label>
                <select name="category" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" class="category-option">
                            <i class="fas <?php echo htmlspecialchars($category['icon']); ?>"></i>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Konu</label>
                <input type="text" name="subject" required>
            </div>

            <div class="form-group">
                <label>Mesaj</label>
                <textarea name="message" rows="6" required></textarea>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i>
                Ticket Oluştur
            </button>
        </form>
    </div>
</body>
</html> 