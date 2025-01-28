<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'servers';

// Sunucuları çek
$servers_query = $db->query("SELECT * FROM websender_servers ORDER BY id");
$servers = $servers_query->fetchAll(PDO::FETCH_ASSOC);

// İşlem mesajları
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunucu Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Sunucu Yönetimi</h1>
                <a href="add_server.php" class="action-button">
                    <i class="fas fa-plus"></i>
                    Sunucu Ekle
                </a>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="servers-grid">
                <?php foreach ($servers as $server): ?>
                    <div class="server-card">
                        <div class="server-header">
                            <div class="server-image">
                                <?php if ($server['image']): ?>
                                    <img src="../<?php echo htmlspecialchars($server['image']); ?>" alt="<?php echo htmlspecialchars($server['name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-server"></i>
                                <?php endif; ?>
                            </div>
                            <div class="server-info">
                                <h3 class="server-name"><?php echo htmlspecialchars($server['name']); ?></h3>
                                <div class="server-host">
                                    <i class="fas fa-network-wired"></i>
                                    <?php echo htmlspecialchars($server['host']); ?>:<?php echo $server['port']; ?>
                                </div>
                            </div>
                            <div class="server-status <?php echo $server['status'] ? 'online' : 'offline'; ?>">
                                <i class="fas fa-<?php echo $server['status'] ? 'check' : 'times'; ?>"></i>
                            </div>
                        </div>

                        <div class="server-body">
                            <div class="server-details">
                                <div class="detail-item">
                                    <i class="fas fa-hashtag"></i>
                                    <span class="detail-label">ID:</span>
                                    <span class="detail-value"><?php echo $server['id']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span class="detail-label">Oluşturulma:</span>
                                    <span class="detail-value"><?php echo date('d.m.Y', strtotime($server['created_at'])); ?></span>
                                </div>
                            </div>

                            <div class="server-actions">
                                <button class="test-btn" onclick="testServer(<?php echo $server['id']; ?>)">
                                    <i class="fas fa-plug"></i>
                                    Test Et
                                </button>
                                <button class="edit-btn" onclick="location.href='edit_server.php?id=<?php echo $server['id']; ?>'">
                                    <i class="fas fa-edit"></i>
                                    Düzenle
                                </button>
                                <button class="toggle-btn" onclick="toggleServer(<?php echo $server['id']; ?>)">
                                    <i class="fas fa-power-off"></i>
                                    <?php echo $server['status'] ? 'D. D. Bırak' : 'Aktifleştir'; ?>
                                </button>
                                <button class="delete-btn" onclick="deleteServer(<?php echo $server['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                    Sil
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script>
    function toggleServer(serverId) {
        if (confirm('Sunucu durumunu değiştirmek istediğinize emin misiniz?')) {
            fetch('ajax/toggle_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ server_id: serverId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Bir hata oluştu');
                }
            });
        }
    }

    function testServer(serverId) {
        // Test başladığında butonu devre dışı bırak
        const testBtn = event.target.closest('button');
        const originalText = testBtn.innerHTML;
        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Test Ediliyor...';

        fetch('ajax/test_server.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ server_id: serverId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Başarılı test
                testBtn.innerHTML = '<i class="fas fa-check"></i> Bağlantı Başarılı';
                testBtn.style.background = 'rgba(46, 204, 113, 0.2)';
                testBtn.style.color = '#2ecc71';
                
                // 2 saniye sonra butonu eski haline getir
                setTimeout(() => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = originalText;
                    testBtn.style.background = 'rgba(52, 152, 219, 0.2)';
                    testBtn.style.color = '#3498db';
                }, 2000);
            } else {
                // Başarısız test
                testBtn.innerHTML = '<i class="fas fa-times"></i> Bağlantı Başarısız';
                testBtn.style.background = 'rgba(231, 76, 60, 0.2)';
                testBtn.style.color = '#e74c3c';
                
                // 2 saniye sonra butonu eski haline getir
                setTimeout(() => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = originalText;
                    testBtn.style.background = 'rgba(52, 152, 219, 0.2)';
                    testBtn.style.color = '#3498db';
                }, 2000);
            }
        })
        .catch(error => {
            // Hata durumu
            testBtn.innerHTML = '<i class="fas fa-times"></i> Hata Oluştu';
            testBtn.style.background = 'rgba(231, 76, 60, 0.2)';
            testBtn.style.color = '#e74c3c';
            
            // 2 saniye sonra butonu eski haline getir
            setTimeout(() => {
                testBtn.disabled = false;
                testBtn.innerHTML = originalText;
                testBtn.style.background = 'rgba(52, 152, 219, 0.2)';
                testBtn.style.color = '#3498db';
            }, 2000);
            
            console.error('Test hatası:', error);
        });
    }

    function deleteServer(serverId, serverName) {
        if (confirm(`"${serverName}" sunucusunu silmek istediğinize emin misiniz? Bu işlem geri alınamaz!`)) {
            fetch('ajax/delete_server.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ server_id: serverId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Bir hata oluştu');
                }
            });
        }
    }
    </script>
</body>
</html> 