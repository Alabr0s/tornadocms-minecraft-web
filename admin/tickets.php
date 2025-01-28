<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'tickets';

// Filtreleme
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ticket cevaplama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'], $_POST['reply'])) {
    try {
        $ticket_id = (int)$_POST['ticket_id'];
        $reply = trim($_POST['reply']);
        
        if (empty($reply)) {
            throw new Exception('Cevap boş olamaz.');
        }

        // İşlemi başlat
        $db->beginTransaction();

        // Cevabı ekle
        $stmt = $db->prepare("
            INSERT INTO ticket_replies (ticket_id, user_id, message, is_admin) 
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$ticket_id, $_SESSION['user_id'], $reply]);

        // Ticket durumunu güncelle
        $stmt = $db->prepare("
            UPDATE tickets 
            SET status = 'answered', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$ticket_id]);

        // İşlemi tamamla
        $db->commit();

        // Başarılı yanıt
        echo json_encode(['success' => true, 'message' => 'Cevap başarıyla gönderildi.']);
        exit;

    } catch (Exception $e) {
        // Hata durumunda rollback
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        // Hata yanıtı
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Ticket'ları çek
$query = "
    SELECT t.*, tc.name as category_name, tc.icon as category_icon,
           a.username, 
           (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count,
           (SELECT MAX(created_at) FROM ticket_messages WHERE ticket_id = t.id) as last_message
    FROM tickets t
    JOIN ticket_categories tc ON t.category_id = tc.id
    JOIN authme a ON t.user_id = a.id
    WHERE 1=1
";

$params = [];

if ($status_filter !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (a.username LIKE ? OR t.subject LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY t.updated_at DESC";

$tickets_query = $db->prepare($query);
$tickets_query->execute($params);
$tickets = $tickets_query->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$stats_query = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'answered' THEN 1 ELSE 0 END) as answered,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM tickets
");
$stats = $stats_query->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Destek Sistemi - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-content-header">
                <h1>Destek Sistemi</h1>
            </div>

            <div class="tickets-stats">
                <div class="stat-item">
                    <div class="stat-icon all">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['total']; ?></span>
                        <span class="stat-label">Toplam</span>
                    </div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon open">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['open']; ?></span>
                        <span class="stat-label">Açık</span>
                    </div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon answered">
                        <i class="fas fa-reply"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['answered']; ?></span>
                        <span class="stat-label">Yanıtlandı</span>
                    </div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon closed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $stats['closed']; ?></span>
                        <span class="stat-label">Kapandı</span>
                    </div>
                </div>
            </div>

            <div class="tickets-filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <select name="status" class="filter-select">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tüm Durumlar</option>
                            <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Açık</option>
                            <option value="answered" <?php echo $status_filter === 'answered' ? 'selected' : ''; ?>>Yanıtlandı</option>
                            <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Kapandı</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <input type="text" name="search" class="filter-search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Kullanıcı adı veya konu ara...">
                    </div>

                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i>
                        Filtrele
                    </button>
                </form>
            </div>

            <div class="tickets-list">
                <?php if ($tickets): ?>
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="ticket-item status-<?php echo $ticket['status']; ?>">
                            <div class="ticket-header">
                                <div class="ticket-category">
                                    <i class="<?php echo $ticket['category_icon']; ?>"></i>
                                    <span><?php echo htmlspecialchars($ticket['category_name']); ?></span>
                                </div>
                                <div class="ticket-status">
                                    <?php
                                    switch($ticket['status']) {
                                        case 'open':
                                            echo '<span class="status-badge open">Açık</span>';
                                            break;
                                        case 'answered':
                                            echo '<span class="status-badge answered">Yanıtlandı</span>';
                                            break;
                                        case 'closed':
                                            echo '<span class="status-badge closed">Kapandı</span>';
                                            break;
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="ticket-content">
                                <h3 class="ticket-subject">
                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                </h3>
                                <div class="ticket-meta">
                                    <span class="ticket-user">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($ticket['username']); ?>
                                    </span>
                                    <span class="ticket-date">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('d.m.Y H:i', strtotime($ticket['created_at'])); ?>
                                    </span>
                                    <span class="ticket-messages">
                                        <i class="fas fa-comments"></i>
                                        <?php echo $ticket['message_count']; ?> mesaj
                                    </span>
                                </div>
                            </div>

                            <div class="ticket-actions">
                                <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn view-btn">
                                    <i class="fas fa-eye"></i>
                                    Görüntüle
                                </a>
                                <?php if ($ticket['status'] !== 'closed'): ?>
                                    <button class="btn reply-button" data-ticket-id="<?php echo $ticket['id']; ?>">
                                        <i class="fas fa-reply"></i>
                                        Cevapla
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-tickets">
                        <i class="fas fa-ticket-alt"></i>
                        <p>Ticket bulunamadı.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ticket-reply-form">
                <form id="replyForm" method="post">
                    <input type="hidden" name="ticket_id" id="ticket_id">
                    <textarea name="reply" id="reply" required></textarea>
                    <button type="submit">Cevapla</button>
                </form>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cevaplama formunu gönder
        const replyForm = document.getElementById('replyForm');
        const replyFormContainer = document.querySelector('.ticket-reply-form');
        
        // Cevapla butonuna tıklandığında
        document.querySelectorAll('.reply-button').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const ticketId = this.dataset.ticketId;
                document.getElementById('ticket_id').value = ticketId;
                // Form görünür yap
                replyFormContainer.style.display = 'block';
            });
        });

        // Form dışına tıklandığında kapat
        document.addEventListener('click', function(e) {
            if (!replyFormContainer.contains(e.target) && !e.target.classList.contains('reply-button')) {
                replyFormContainer.style.display = 'none';
            }
        });
        
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Submit butonunu devre dışı bırak
            const submitButton = this.querySelector('button[type="submit"]');
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
                    // Formu temizle ve kapat
                    replyForm.reset();
                    replyFormContainer.style.display = 'none';
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