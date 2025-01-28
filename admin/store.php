<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Site ayarlarını çek
    $settings_query = $db->query("SELECT * FROM settings LIMIT 1");
    $settings = $settings_query->fetch(PDO::FETCH_ASSOC);

    // Aktif sayfa
    $current_page = 'store';

    // Sunucuları çek
    $servers_query = $db->query("SELECT * FROM websender_servers ORDER BY id");
    $servers = $servers_query->fetchAll(PDO::FETCH_ASSOC);

    // Debug için sunucu verilerini kontrol edelim
    error_log("Admin Store - Servers: " . print_r($servers, true));

    // Seçili sunucu
    $selected_server = isset($_GET['server']) ? (int)$_GET['server'] : ($servers[0]['id'] ?? null);

    if (!$selected_server && !empty($servers)) {
        $selected_server = $servers[0]['id'];
    }

    // Kategorileri çek
    $categories_query = $db->prepare("
        SELECT c.*, COUNT(i.id) as item_count 
        FROM store_categories c 
        LEFT JOIN store_items i ON c.id = i.category_id 
        WHERE c.server_id = ? 
        GROUP BY c.id 
        ORDER BY c.display_order
    ");
    $categories_query->execute([$selected_server]);
    $categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

    // Debug için kategori verilerini kontrol edelim
    error_log("Admin Store - Categories: " . print_r($categories, true));

    // İşlem mesajları
    $success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
    $error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
    unset($_SESSION['success'], $_SESSION['error']);

    // HTML çıktısı
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Market Yönetimi - Admin Panel</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="assets/css/admin.css">
    </head>
    <body>
        <div class="admin-container">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="admin-content">
                <div class="admin-header">
                    <h1>Market Yönetimi</h1>
                    <div class="header-actions">
                        <a href="add_category.php" class="action-button">
                            <i class="fas fa-plus"></i>
                            Kategori Ekle
                        </a>
                        <a href="add_item.php" class="action-button">
                            <i class="fas fa-plus"></i>
                            Ürün Ekle
                        </a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($servers)): ?>
                    <div class="alert alert-warning">
                        <h2>Sunucu Bulunamadı</h2>
                        <p>Lütfen önce bir sunucu ekleyin.</p>
                        <a href="servers.php" class="btn">Sunucu Yönetimine Git</a>
                    </div>
                <?php else: ?>
                    <!-- Sunucu Seçimi -->
                    <div class="server-selector">
                        <?php foreach ($servers as $server): ?>
                            <a href="?server=<?php echo $server['id']; ?>" 
                               class="server-item <?php echo $server['id'] === $selected_server ? 'active' : ''; ?>">
                                <?php if ($server['image']): ?>
                                    <img src="../<?php echo htmlspecialchars($server['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($server['name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-server"></i>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($server['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Kategoriler -->
                    <div class="categories-grid">
                        <?php if (empty($categories)): ?>
                            <div class="alert alert-info">
                                <h2>Kategori Bulunamadı</h2>
                                <p>Bu sunucu için henüz kategori eklenmemiş.</p>
                                <a href="add_category.php" class="btn">Kategori Ekle</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <div class="category-card">
                                    <div class="category-header">
                                        <div class="category-icon">
                                        <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                                        </div>
                                        <div class="category-info">
                                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                        <span class="item-count"><?php echo $category['item_count']; ?> Ürün</span>
                                    </div>
                                    <div class="category-actions">
                                            <button class="action-btn edit" onclick="location.href='edit_category.php?id=<?php echo $category['id']; ?>'" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="action-btn <?php echo $category['status'] ? 'active' : 'inactive'; ?>" 
                                                onclick="toggleCategoryStatus(<?php echo $category['id']; ?>)"
                                                title="<?php echo $category['status'] ? 'Devre Dışı Bırak' : 'Aktifleştir'; ?>">
                                            <i class="fas <?php echo $category['status'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                        </button>
                                            <button class="action-btn delete" 
                                                onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo $category['name']; ?>')"
                                                title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        </div>
                                    </div>

                                    <?php
                                    // Kategorideki ürünleri çek
                                    $items_query = $db->prepare("
                                        SELECT * FROM store_items 
                                        WHERE category_id = ? 
                                        ORDER BY id DESC
                                    ");
                                    $items_query->execute([$category['id']]);
                                    $items = $items_query->fetchAll(PDO::FETCH_ASSOC);
                                    ?>

                                    <div class="store-items">
                                        <?php foreach ($items as $item): ?>
                                            <div class="store-item-card">
                                                <div class="item-header">
                                                    <div class="item-image">
                                                        <?php if ($item['image']): ?>
                                                            <img src="../<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                        <?php else: ?>
                                                            <i class="fas fa-cube"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="item-status <?php echo $item['status'] ? 'active' : 'inactive'; ?>">
                                                        <i class="fas fa-<?php echo $item['status'] ? 'check' : 'times'; ?>"></i>
                                                    </div>
                                                </div>

                                                <div class="item-body">
                                                    <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                                    
                                                    <div class="item-price">
                                                    <?php if ($item['discount_price']): ?>
                                                            <span class="original-price"><?php echo number_format($item['price']); ?> Coin</span>
                                                            <span class="discount-price"><?php echo number_format($item['discount_price']); ?> Coin</span>
                                                        <?php else: ?>
                                                            <span class="price"><?php echo number_format($item['price']); ?> Coin</span>
                                                    <?php endif; ?>
                                                </div>

                                                    <div class="item-commands">
                                                        <div class="command-label">Komutlar:</div>
                                                        <div class="command-list">
                                                            <?php 
                                                            $commands = explode("\n", $item['commands']);
                                                            foreach ($commands as $command): 
                                                                if (trim($command)):
                                                            ?>
                                                                <div class="command-item">
                                                                    <i class="fas fa-terminal"></i>
                                                                    <span><?php echo htmlspecialchars(trim($command)); ?></span>
                                                                </div>
                                                            <?php 
                                                                endif;
                                                            endforeach; 
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="item-footer">
                                                    <button class="edit-btn" onclick="location.href='edit_item.php?id=<?php echo $item['id']; ?>'">
                                                        <i class="fas fa-edit"></i>
                                                        Düzenle
                                                    </button>
                                                    <button class="toggle-btn" onclick="toggleItem(<?php echo $item['id']; ?>)">
                                                        <i class="fas fa-power-off"></i>
                                                        <?php echo $item['status'] ? 'Devre Dışı Bırak' : 'Aktifleştir'; ?>
                                                    </button>
                                                    <button class="delete-btn" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                        Sil
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>

        <script>
        function toggleCategoryStatus(categoryId) {
            if (confirm('Kategori durumunu değiştirmek istediğinize emin misiniz?')) {
                fetch('ajax/toggle_category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ category_id: categoryId })
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

        function deleteCategory(categoryId, categoryName) {
            if (confirm(`"${categoryName}" kategorisini silmek istediğinize emin misiniz? Bu işlem geri alınamaz!`)) {
                fetch('ajax/delete_category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ category_id: categoryId })
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

        function toggleItem(itemId) {
            if (confirm('Ürün durumunu değiştirmek istediğinize emin misiniz?')) {
                fetch('ajax/toggle_item.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ item_id: itemId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Bir hata oluştu');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu: ' + error);
                });
            }
        }

        function deleteItem(itemId) {
            if (confirm('Bu ürünü silmek istediğinize emin misiniz? Bu işlem geri alınamaz!')) {
                fetch('ajax/delete_item.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ item_id: itemId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Bir hata oluştu');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu: ' + error);
                });
            }
        }
        </script>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    error_log("Admin Store Error: " . $e->getMessage());
    die("Bir hata oluştu: " . htmlspecialchars($e->getMessage()));
}
?> 