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

// Kategorileri çek
$categories_query = $db->query("SELECT * FROM ticket_categories WHERE status = 1");
$categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcının ticketlarını çek
$tickets_query = $db->prepare("
    SELECT t.*, tc.name as category_name, tc.icon as category_icon,
           (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
    FROM tickets t
    JOIN ticket_categories tc ON t.category_id = tc.id
    WHERE t.user_id = ?
    ORDER BY t.updated_at DESC
");
$tickets_query->execute([$_SESSION['user_id']]);
$tickets = $tickets_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destek Sistemi - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/tickets.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="tickets-container">
        <div class="tickets-header">
            <h1>Destek Sistemi</h1>
            <a href="create_ticket.php" class="create-ticket-btn">
                <i class="fas fa-plus"></i>
                Yeni Ticket Oluştur
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php 
                switch($_GET['success']) {
                    case 'created':
                        echo 'Ticket başarıyla oluşturuldu!';
                        break;
                    case 'replied':
                        echo 'Yanıtınız başarıyla gönderildi!';
                        break;
                    case 'closed':
                        echo 'Ticket başarıyla kapatıldı!';
                        break;
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="tickets-list">
            <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="ticket-card">
                        <div class="ticket-info">
                            <div class="ticket-category">
                                <i class="<?php echo htmlspecialchars($ticket['category_icon']); ?>"></i>
                                <?php echo htmlspecialchars($ticket['category_name']); ?>
                            </div>
                            <h3 class="ticket-subject">
                                <?php echo htmlspecialchars($ticket['subject']); ?>
                            </h3>
                            <div class="ticket-meta">
                                <span class="ticket-date">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?>
                                </span>
                                <span class="message-count">
                                    <i class="fas fa-comments"></i>
                                    <?php echo $ticket['message_count']; ?> mesaj
                                </span>
                            </div>
                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="view-ticket-btn">
                                <i class="fas fa-eye"></i>
                                Detayları Görüntüle
                            </a>
                        </div>
                        <div class="ticket-status <?php echo $ticket['status']; ?>">
                            <?php
                            switch($ticket['status']) {
                                case 'open':
                                    echo '<i class="fas fa-envelope-open"></i> Açık';
                                    break;
                                case 'answered':
                                    echo '<i class="fas fa-reply"></i> Yanıtlandı';
                                    break;
                                case 'closed':
                                    echo '<i class="fas fa-check-circle"></i> Kapandı';
                                    break;
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-tickets">
                    <i class="fas fa-ticket-alt"></i>
                    <p>Henüz hiç ticket oluşturmadınız.</p>
                    <a href="create_ticket.php" class="create-ticket-btn">
                        <i class="fas fa-plus"></i>
                        Yeni Ticket Oluştur
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 