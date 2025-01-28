<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'tickets';

// Ticket ID kontrolü
if (!isset($_GET['id'])) {
    header('Location: tickets.php');
    exit;
}

$ticket_id = (int)$_GET['id'];

// Ticket bilgilerini çek
$ticket_query = $db->prepare("
    SELECT t.*, u.username, tc.name as category_name, tc.icon as category_icon
    FROM tickets t
    JOIN authme u ON t.user_id = u.id
    JOIN ticket_categories tc ON t.category_id = tc.id
    WHERE t.id = ?
");
$ticket_query->execute([$ticket_id]);
$ticket = $ticket_query->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header('Location: tickets.php');
    exit;
}

// Mesajları çek
$messages_query = $db->prepare("
    SELECT tm.*, u.username, tm.is_admin
    FROM ticket_messages tm
    JOIN authme u ON tm.user_id = u.id
    WHERE tm.ticket_id = ?
    ORDER BY tm.created_at ASC
");
$messages_query->execute([$ticket_id]);
$messages = $messages_query->fetchAll(PDO::FETCH_ASSOC);

// Yanıt gönderme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $reply = trim($_POST['reply']);
        $close_ticket = isset($_POST['close_ticket']) ? 1 : 0;
        
        if (empty($reply)) {
            throw new Exception('Yanıt boş olamaz.');
        }

        // İşlemi başlat
        $db->beginTransaction();

        // Yanıtı ekle
        $reply_stmt = $db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_admin) 
            VALUES (?, ?, ?, 1)
        ");
        $reply_stmt->execute([$ticket_id, $_SESSION['user_id'], $reply]);

        // Ticket durumunu güncelle
        $status = $close_ticket ? 'closed' : 'answered';
        $update_stmt = $db->prepare("
            UPDATE tickets 
            SET status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $update_stmt->execute([$status, $ticket_id]);

        $db->commit();

        // AJAX isteği kontrolü
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => true, 'message' => 'Yanıt başarıyla gönderildi.']);
            exit;
        }

        header('Location: view_ticket.php?id=' . $ticket_id . '&success=true');
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }

        $error = $e->getMessage();
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
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Ticket #<?php echo $ticket_id; ?></h1>
                <a href="tickets.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="ticket-details">
                <div class="ticket-info">
                    <div class="info-row">
                        <div class="info-group">
                            <label>Kategori</label>
                            <div class="info-value">
                                <i class="<?php echo $ticket['category_icon']; ?>"></i>
                                <?php echo htmlspecialchars($ticket['category_name']); ?>
                            </div>
                        </div>

                        <div class="info-group">
                            <label>Durum</label>
                            <div class="info-value">
                                <span class="status-badge <?php echo $ticket['status']; ?>">
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

                        <div class="info-group">
                            <label>Oluşturan</label>
                            <div class="info-value">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($ticket['username']); ?>
                            </div>
                        </div>

                        <div class="info-group">
                            <label>Tarih</label>
                            <div class="info-value">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="ticket-subject">
                        <h2><?php echo htmlspecialchars($ticket['subject']); ?></h2>
                    </div>
                </div>

                <div class="messages-container">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo $message['is_admin'] ? 'admin' : 'user'; ?>">
                            <div class="message-header">
                                <div class="message-user">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($message['username']); ?></span>
                                    <?php if ($message['is_admin']): ?>
                                        <span class="admin-badge">Admin</span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-date">
                                    <?php echo date('d.m.Y H:i', strtotime($message['created_at'])); ?>
                                </div>
                            </div>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($ticket['status'] !== 'closed'): ?>
                    <div class="reply-form">
                        <form id="replyForm" method="post">
                            <div class="form-group">
                                <label for="reply">Yanıtınız</label>
                                <textarea name="reply" id="reply" required></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <label class="close-ticket">
                                    <input type="checkbox" name="close_ticket">
                                    Yanıtladıktan sonra ticket'ı kapat
                                </label>
                                
                                <button type="submit" class="submit-btn">
                                    <i class="fas fa-paper-plane"></i>
                                    Yanıtla
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="ticket-closed">
                        <i class="fas fa-lock"></i>
                        <p>Bu ticket kapatılmış.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const replyForm = document.getElementById('replyForm');
        
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            
            // Submit butonunu devre dışı bırak
            submitButton.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Başarılı mesajı göster
                    alert(data.message);
                    // Sayfayı yenile
                    window.location.reload();
                } else {
                    alert(data.message || 'Bir hata oluştu');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu, lütfen tekrar deneyin.');
            })
            .finally(() => {
                // Submit butonunu tekrar aktif et
                submitButton.disabled = false;
            });
        });
    });
    </script>
</body>
</html> 