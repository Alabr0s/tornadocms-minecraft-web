<?php
session_start();
require_once 'includes/config/database.php';

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_page = 'tickets';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Ticket ID kontrolü
if (!isset($_GET['id'])) {
    header('Location: tickets.php');
    exit;
}

$ticket_id = (int)$_GET['id'];

// Ticket bilgilerini çek
$ticket_query = $db->prepare("
    SELECT t.*, tc.name as category_name, tc.icon as category_icon
    FROM tickets t
    JOIN ticket_categories tc ON t.category_id = tc.id
    WHERE t.id = ? AND t.user_id = ?
");
$ticket_query->execute([$ticket_id, $_SESSION['user_id']]);
$ticket = $ticket_query->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Mesajları çek
$messages_query = $db->prepare("
    SELECT tm.*, a.username, a.is_admin
    FROM ticket_messages tm
    JOIN authme a ON tm.user_id = a.id
    WHERE tm.ticket_id = ?
    ORDER BY tm.created_at ASC
");
$messages_query->execute([$ticket_id]);
$messages = $messages_query->fetchAll(PDO::FETCH_ASSOC);

// Yeni mesaj gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        try {
            // Mesajı ekle
            $stmt = $db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) 
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$ticket_id, $_SESSION['user_id'], $message]);
            
            // Ticket durumunu güncelle
            $status_update = $db->prepare("
                UPDATE tickets SET status = 'open', updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $status_update->execute([$ticket_id]);
            
            header("Location: view_ticket.php?id=$ticket_id&success=replied");
            exit;
        } catch (Exception $e) {
            $error = 'Mesaj gönderilemedi, lütfen tekrar deneyin.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Detay - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/tickets.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="tickets-container">
        <div class="ticket-detail-header">
            <div class="header-top">
                <h1 class="ticket-detail-title"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                <a href="tickets.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
            </div>
            
            <div class="ticket-detail-meta">
                <div class="meta-item">
                    <div class="meta-icon">
                        <i class="<?php echo htmlspecialchars($ticket['category_icon']); ?>"></i>
                    </div>
                    <div class="meta-info">
                        <span class="meta-label">Kategori</span>
                        <span class="meta-value"><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <?php
                        switch($ticket['status']) {
                            case 'open':
                                echo '<i class="fas fa-envelope-open"></i>';
                                break;
                            case 'answered':
                                echo '<i class="fas fa-reply"></i>';
                                break;
                            case 'closed':
                                echo '<i class="fas fa-check-circle"></i>';
                                break;
                        }
                        ?>
                    </div>
                    <div class="meta-info">
                        <span class="meta-label">Durum</span>
                        <span class="meta-value status-<?php echo $ticket['status']; ?>">
                            <?php
                            switch($ticket['status']) {
                                case 'open':
                                    echo 'Açık';
                                    break;
                                case 'answered':
                                    echo 'Yanıtlandı';
                                    break;
                                case 'closed':
                                    echo 'Kapandı';
                                    break;
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="meta-item">
                    <div class="meta-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="meta-info">
                        <span class="meta-label">Oluşturulma Tarihi</span>
                        <span class="meta-value"><?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                Yanıtınız başarıyla gönderildi!
            </div>
        <?php endif; ?>

        <div class="messages-container">
            <?php foreach ($messages as $message): ?>
                <div class="message <?php echo $message['is_admin'] ? 'admin' : 'user'; ?>">
                    <?php if (!$message['is_admin']): ?>
                        <div class="message-avatar">
                            <img src="https://mc-heads.net/avatar/<?php echo $message['username']; ?>/50" alt="avatar">
                        </div>
                    <?php endif; ?>
                    
                    <div class="message-content">
                        <div class="message-header">
                            <div class="message-info">
                                <span class="message-author">
                                    <?php if ($message['is_admin']): ?>
                                        <span class="admin-badge">
                                            <i class="fas fa-shield-alt"></i>
                                            Yönetici
                                        </span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($message['username']); ?>
                                </span>
                            </div>
                            <span class="message-date">
                                <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                            </span>
                        </div>
                        <div class="message-text">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                    
                    <?php if ($message['is_admin']): ?>
                        <div class="message-avatar">
                            <img src="https://mc-heads.net/avatar/<?php echo $message['username']; ?>/50" alt="avatar">
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($ticket['status'] !== 'closed'): ?>
            <form method="post" class="reply-form">
                <div class="form-group">
                    <label for="message">Yanıtınız</label>
                    <textarea name="message" id="message" rows="4" required></textarea>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    Yanıt Gönder
                </button>
            </form>
        <?php else: ?>
            <div class="ticket-closed-message">
                <i class="fas fa-lock"></i>
                <p>Bu ticket kapatılmıştır.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 