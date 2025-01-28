<?php
session_start();
require_once 'includes/config/database.php';
require_once 'includes/classes/WebSender.php';

// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kullanıcı girişi kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // Aktif sayfa
    $current_page = 'store';

    // Sunucuları çek
    $servers_query = $db->query("SELECT * FROM websender_servers ORDER BY id");
    if (!$servers_query) {
        throw new Exception("Sunucu sorgusu başarısız oldu");
    }
    $servers = $servers_query->fetchAll(PDO::FETCH_ASSOC);

    // Debug için sunucu verilerini kontrol edelim
    error_log("Store - Active Servers: " . print_r($servers, true));

    // Seçili sunucu - varsayılan olarak ilk sunucuyu seç
    $selected_server = isset($_GET['server']) ? (int)$_GET['server'] : ($servers[0]['id'] ?? null);

    if (!$selected_server && !empty($servers)) {
        $selected_server = $servers[0]['id'];
    }

    // Kategorileri çek
    $categories_query = $db->prepare("
        SELECT * FROM store_categories 
        WHERE server_id = ? AND status = 1 
        ORDER BY display_order
    ");
    $categories_query->execute([$selected_server]);
    $categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

    // Seçili kategori
    $selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

    // Ürünleri çek
    if ($selected_category === 0) {
        $items_query = $db->prepare("
            SELECT i.* 
            FROM store_items i 
            JOIN store_categories c ON i.category_id = c.id 
            WHERE c.server_id = ? AND i.status = 1 
            ORDER BY i.price
        ");
        $items_query->execute([$selected_server]);
    } else {
        $items_query = $db->prepare("
            SELECT * FROM store_items 
            WHERE category_id = ? AND status = 1
            ORDER BY price
        ");
        $items_query->execute([$selected_category]);
    }
    $items = $items_query->fetchAll(PDO::FETCH_ASSOC);

    // Header'ı dahil et
    include 'includes/header.php';

    // HTML çıktısı başlat
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Market - <?php echo htmlspecialchars($settings['site_name'] ?? 'Market'); ?></title>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="assets/css/store.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
        <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    </head>
    <body>
        <main>
            <div class="store-container">
                <?php if (empty($servers)): ?>
                    <div class="alert alert-warning">
                        <h2>Aktif Sunucu Bulunamadı</h2>
                        <p>Lütfen yönetici panelinden en az bir sunucu ekleyin.</p>
                        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                            <a href="admin/servers.php">Sunucu Yönetimine Git</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Sunucu Seçimi -->
                    <div class="server-selector">
                        <?php foreach ($servers as $server): ?>
                            <a href="javascript:void(0)" 
                               onclick="loadServerContent(<?php echo $server['id']; ?>)"
                               data-server-id="<?php echo $server['id']; ?>"
                               class="server-item <?php echo $server['id'] === $selected_server ? 'active' : ''; ?>">
                                <?php if ($server['image']): ?>
                                    <img src="<?php echo htmlspecialchars($server['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($server['name']); ?>"
                                         class="server-image">
                                <?php else: ?>
                                    <i class="fas fa-server"></i>
                                <?php endif; ?>
                                <span class="server-name"><?php echo htmlspecialchars($server['name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Store Content -->
                    <div class="store-content">
                        <!-- Kategoriler -->
                        <div class="store-categories" id="store-categories">
                            <a href="javascript:void(0)" 
                               class="category-item <?php echo $selected_category === 0 ? 'active' : ''; ?>"
                               data-category-id="0">
                                <i class="fas fa-border-all"></i>
                                <span>Tümü</span>
                            </a>
                            
                            <?php foreach ($categories as $category): ?>
                                <a href="javascript:void(0)" 
                                   class="category-item <?php echo $selected_category === $category['id'] ? 'active' : ''; ?>"
                                   data-category-id="<?php echo $category['id']; ?>">
                                    <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
                                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <!-- Ürünler -->
                        <div id="store-items" class="store-items">
                            <!-- Ürünler AJAX ile yüklenecek -->
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Onay Popup'ı -->
        <div class="confirm-popup" id="confirmPopup">
            <div class="confirm-content">
                <div class="confirm-header">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Satın Alma Onayı</h3>
                </div>
                <p>Bu ürünü satın almak istediğinize emin misiniz?</p>
                <div class="confirm-buttons">
                    <button class="confirm-btn cancel" onclick="closeConfirmPopup()">
                        <i class="fas fa-times"></i>
                        İptal
                    </button>
                    <button class="confirm-btn confirm" onclick="confirmBuy()">
                        <i class="fas fa-check"></i>
                        Satın Al
                    </button>
                </div>
            </div>
        </div>

        <script>
        let currentItemId = null;

        // Bildirim gösterme fonksiyonu
        function showNotification(message, type = 'success') {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                stopOnFocus: true,
                className: `notification ${type}`,
                style: {
                    background: type === 'success' ? 'rgba(0, 179, 126, 0.9)' : 'rgba(255, 68, 68, 0.9)',
                    borderRadius: '8px',
                    padding: '1rem 1.5rem',
                    fontSize: '0.9rem',
                    fontWeight: '500'
                }
            }).showToast();
        }

        function showConfirmPopup(itemId) {
            currentItemId = itemId;
            document.getElementById('confirmPopup').classList.add('active');
        }

        function closeConfirmPopup() {
            document.getElementById('confirmPopup').classList.remove('active');
        }

        function confirmBuy() {
            if (currentItemId) {
                const itemId = currentItemId;
                closeConfirmPopup();
                buyItem(itemId);
            }
        }

        function buyItem(itemId) {
            // Seçili sunucuyu kontrol et
            const serverId = <?php echo $selected_server ?: 'null'; ?>;

            if (!serverId) {
                showNotification('Lütfen bir sunucu seçin!', 'error');
                return;
            }

            // Buy butonu devre dışı bırak
            const buyButton = event.target;
            if (buyButton) buyButton.disabled = true;

            // URL parametreleri oluştur
            const url = `ajax/buy_item.php?item_id=${itemId}&server_id=${serverId}`;
            
            console.log('İstek URL:', url); // Debug için

            fetch(url)
                .then(response => {
                    console.log('Sunucu yanıtı:', response); // Debug için
                    return response.json();
                })
            .then(data => {
                    console.log('İşlem sonucu:', data); // Debug için
                    
                if (data.success) {
                    showNotification('Ürün başarıyla satın alındı!', 'success');
                    
                    // Bakiyeyi güncelle
                    if (data.newBalance !== undefined) {
                        const balanceElement = document.querySelector('.user-balance');
                        if (balanceElement) {
                            balanceElement.textContent = formatCoin(data.newBalance);
                        }
                    }
                    
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(data.error || 'Bir hata oluştu!', 'error');
                }
            })
            .catch(error => {
                console.error('Satın alma hatası:', error);
                showNotification('Bir hata oluştu! Lütfen daha sonra tekrar deneyin.', 'error');
            })
            .finally(() => {
                // Buy butonu tekrar aktif et
                if (buyButton) buyButton.disabled = false;
            });
        }

        // Para formatı için yardımcı fonksiyon
        function formatCoin(amount) {
            return new Intl.NumberFormat('tr-TR').format(amount);
        }

        function loadServerContent(serverId) {
            // Aktif sunucu butonunu güncelle
            document.querySelectorAll('.server-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Tıklanan sunucu butonunu aktif et
            const clickedServer = document.querySelector(`.server-item[data-server-id="${serverId}"]`);
            if (clickedServer) {
                clickedServer.classList.add('active');
            }

            // URL'yi güncelle
            window.history.pushState({}, '', '?server=' + serverId);

            // Yükleniyor animasyonu göster
            document.getElementById('store-items').innerHTML = '<div class="loading">Yükleniyor...</div>';

            // Kategorileri ve ürünleri AJAX ile yükle
            fetch('ajax/get_categories.php?server_id=' + serverId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('store-categories').innerHTML = data.categories;
                        document.getElementById('store-items').innerHTML = data.items;
                        
                        // Kategori linklerini güncelle
                        updateCategoryLinks(serverId);
                        
                        // "Tümü" kategorisini aktif et
                        const allCategoryButton = document.querySelector('.category-item[data-category-id="0"]');
                        if (allCategoryButton) {
                            allCategoryButton.classList.add('active');
                        }
                    } else {
                        document.getElementById('store-items').innerHTML = 
                            '<div class="error-message">İçerik yüklenirken bir hata oluştu</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('store-items').innerHTML = 
                        '<div class="error-message">Bir hata oluştu: ' + error.message + '</div>';
                });
        }

        function updateCategoryLinks(serverId) {
            document.querySelectorAll('.category-item').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Aktif kategoriyi güncelle
                    document.querySelectorAll('.category-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    this.classList.add('active');

                    // Kategori ID'sini al
                    const categoryId = this.dataset.categoryId;
                    
                    // Ürünleri filtrele
                    loadCategoryItems(serverId, categoryId);
                });
            });
        }

        // Sayfa yüklendiğinde çalışacak fonksiyon
        document.addEventListener('DOMContentLoaded', function() {
            // İlk sunucuyu seç
            const defaultServerId = <?php echo $servers[0]['id'] ?? 'null'; ?>;
            
            if (defaultServerId) {
                // URL'den sunucu ID'sini kontrol et
            const urlParams = new URLSearchParams(window.location.search);
                const serverId = urlParams.get('server') || defaultServerId;
                
                // Sunucu içeriğini yükle
                loadServerContent(serverId);
                
                // İlgili sunucu butonunu aktif et
                const serverButton = document.querySelector(`.server-item[data-server-id="${serverId}"]`);
                if (serverButton) {
                    serverButton.classList.add('active');
                }
            } else {
                // Sunucu yoksa hata mesajı göster
                document.getElementById('store-items').innerHTML = `
                    <div class="alert alert-warning">
                        <h2>Aktif Sunucu Bulunamadı</h2>
                        <p>Lütfen yönetici panelinden en az bir sunucu ekleyin.</p>
                    </div>
                `;
            }
        });

        // Sunucu butonlarına data-server-id ekle
        document.querySelectorAll('.server-item').forEach(item => {
            const serverId = item.getAttribute('onclick').match(/\d+/)[0];
            item.setAttribute('data-server-id', serverId);
        });
        </script>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    error_log("Store Error: " . $e->getMessage());
    die("Bir hata oluştu: " . htmlspecialchars($e->getMessage()));
}
?> 