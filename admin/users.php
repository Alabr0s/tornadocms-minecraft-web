<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'users';

// Sayfalama için değişkenler
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Arama parametresi
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Toplam kullanıcı sayısı
$total_query = $db->prepare("
    SELECT COUNT(*) FROM authme 
    WHERE username LIKE :search 
    OR realname LIKE :search 
    OR email LIKE :search
");
$total_query->execute(['search' => "%$search%"]);
$total_users = $total_query->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Kullanıcıları çek
$users_query = $db->prepare("
    SELECT * FROM authme 
    WHERE username LIKE :search 
    OR realname LIKE :search 
    OR email LIKE :search
    ORDER BY id DESC 
    LIMIT :offset, :per_page
");
$users_query->bindValue(':search', "%$search%", PDO::PARAM_STR);
$users_query->bindValue(':offset', $offset, PDO::PARAM_INT);
$users_query->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$users_query->execute();
$users = $users_query->fetchAll(PDO::FETCH_ASSOC);

// İşlem mesajları
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

// Admin işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && isset($_POST['user_id'])) {
            $user_id = (int)$_POST['user_id'];
            
            // Kendini değiştirmeye çalışıyor mu kontrol et
            if ($user_id === $_SESSION['user_id']) {
                throw new Exception('Kendi hesabınız üzerinde bu işlemi yapamazsınız!');
            }

            // Kullanıcıyı kontrol et
            $user_query = $db->prepare("SELECT username, is_admin FROM authme WHERE id = ?");
            $user_query->execute([$user_id]);
            $user = $user_query->fetch();

            if (!$user) {
                throw new Exception('Kullanıcı bulunamadı!');
            }

            switch ($_POST['action']) {
                case 'toggle_admin':
                    // Admin durumunu değiştir
                    $update_query = $db->prepare("UPDATE authme SET is_admin = NOT is_admin WHERE id = ?");
                    $result = $update_query->execute([$user_id]);
                    
                    if ($result) {
                        $action = $user['is_admin'] ? 'Admin yetkisi alındı' : 'Admin yetkisi verildi';
                        $_SESSION['success'] = $user['username'] . ' kullanıcısının ' . strtolower($action);
                    }
                    break;

                case 'delete':
                    // İşlemi başlat
                    $db->beginTransaction();
                    
                    try {
                        // İlişkili kayıtları sil
                        $tables = ['store_history', 'case_history', 'coin_transactions', 'tickets'];
                        foreach ($tables as $table) {
                            $db->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$user_id]);
                        }

                        // Kullanıcıyı sil
                        $delete_query = $db->prepare("DELETE FROM authme WHERE id = ?");
                        $result = $delete_query->execute([$user_id]);

                        if ($result) {
                            $db->commit();
                            $_SESSION['success'] = $user['username'] . ' kullanıcısı başarıyla silindi';
                        } else {
                            throw new Exception('Silme işlemi başarısız oldu');
                        }
                    } catch (Exception $e) {
                        $db->rollBack();
                        throw $e;
                    }
                    break;

                default:
                    throw new Exception('Geçersiz işlem!');
            }

            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                $_POST['action'] === 'delete' ? 'Kullanıcı silindi' : $action,
                $_SESSION['success'],
                $_SERVER['REMOTE_ADDR']
            ]);

        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    // Sayfayı yenile
    header('Location: users.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Kullanıcı Yönetimi</h1>
                <div class="header-actions">
                    <form class="search-form">
                        <input type="text" name="search" placeholder="Kullanıcı ara..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
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

            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Avatar</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Coin</th>
                            <th>Rol</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <img src="https://mc-heads.net/avatar/<?php echo $user['username']; ?>/40" 
                                         alt="<?php echo htmlspecialchars($user['username']); ?>" 
                                         class="user-avatar-small">
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td><?php echo number_format($user['coins']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo $user['is_admin'] ? 'admin' : 'user'; ?>">
                                        <?php echo $user['is_admin'] ? 'Admin' : 'Kullanıcı'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d.m.Y H:i', $user['regdate']); ?></td>
                                <td class="actions">
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="action-btn edit" title="Düzenle">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <!-- Admin Toggle Butonu -->
                                    <form method="post" style="display: inline-block; margin: 0 2px;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="toggle_admin">
                                        <button type="submit" 
                                            class="action-btn <?php echo $user['is_admin'] ? 'remove-admin' : 'make-admin'; ?>"
                                                title="<?php echo $user['is_admin'] ? 'Admin Yetkisini Al' : 'Admin Yap'; ?>"
                                                onclick="return confirm('<?php echo htmlspecialchars($user['username']); ?> kullanıcısının admin yetkisini <?php echo $user['is_admin'] ? 'almak' : 'vermek'; ?> istediğinize emin misiniz?');">
                                        <i class="fas <?php echo $user['is_admin'] ? 'fa-user-minus' : 'fa-user-plus'; ?>"></i>
                                    </button>
                                    </form>
                                    
                                    <!-- Silme Butonu -->
                                    <form method="post" style="display: inline-block; margin: 0 2px;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" 
                                            class="action-btn delete"
                                                title="Sil"
                                                onclick="return confirm('DİKKAT: <?php echo htmlspecialchars($user['username']); ?> kullanıcısını silmek istediğinize emin misiniz?\n\nBu işlem:\n- Kullanıcının tüm verilerini silecek\n- Geri alınamaz!');">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                           class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function toggleAdmin(userId, username) {
        if (confirm(`${username} kullanıcısının admin yetkisini değiştirmek istediğinize emin misiniz?`)) {
            fetch('ajax/toggle_admin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
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

    function deleteUser(userId, username) {
        if (confirm(`${username} kullanıcısını silmek istediğinize emin misiniz? Bu işlem geri alınamaz!`)) {
            fetch('ajax/delete_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
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