<?php
require_once '../includes/config/database.php';
require_once 'includes/auth.php';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Aktif sayfa
$current_page = 'tickets';

// Kategorileri çek
$categories_query = $db->query("
    SELECT tc.*, 
           COUNT(t.id) as ticket_count
    FROM ticket_categories tc
    LEFT JOIN tickets t ON tc.id = t.category_id
    GROUP BY tc.id
    ORDER BY tc.name ASC
");
$categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

// Kategori ekleme/düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $icon = trim($_POST['icon']);
        $description = trim($_POST['description']);
        $status = isset($_POST['status']) ? 1 : 0;

        if (empty($name) || empty($icon)) {
            throw new Exception('Lütfen zorunlu alanları doldurun!');
        }

        if (isset($_POST['category_id'])) {
            // Kategori güncelleme
            $update_query = $db->prepare("
                UPDATE ticket_categories 
                SET name = ?, icon = ?, description = ?, status = ?
                WHERE id = ?
            ");
            
            $result = $update_query->execute([
                $name, $icon, $description, $status, $_POST['category_id']
            ]);

            $action = 'güncellendi';
        } else {
            // Yeni kategori ekleme
            $insert_query = $db->prepare("
                INSERT INTO ticket_categories (name, icon, description, status) 
                VALUES (?, ?, ?, ?)
            ");
            
            $result = $insert_query->execute([
                $name, $icon, $description, $status
            ]);

            $action = 'eklendi';
        }

        if ($result) {
            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Ticket kategorisi ' . $action,
                $name . ' kategorisi ' . $action,
                $_SERVER['REMOTE_ADDR']
            ]);

            $_SESSION['success'] = 'Kategori başarıyla ' . $action . '!';
            header('Location: ticket_categories.php');
            exit;
        } else {
            throw new Exception('Kategori ' . $action . 'rken bir hata oluştu!');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Kategorileri - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Ticket Kategorileri</h1>
                <button type="button" class="add-btn" onclick="showAddForm()">
                    <i class="fas fa-plus"></i>
                    Kategori Ekle
                </button>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="categories-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <i class="<?php echo $category['icon']; ?>"></i>
                            <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                            <span class="ticket-count">
                                <?php echo $category['ticket_count']; ?> ticket
                            </span>
                        </div>

                        <?php if ($category['description']): ?>
                            <p class="category-description">
                                <?php echo htmlspecialchars($category['description']); ?>
                            </p>
                        <?php endif; ?>

                        <div class="category-status">
                            <span class="status-badge <?php echo $category['status'] ? 'active' : 'inactive'; ?>">
                                <?php echo $category['status'] ? 'Aktif' : 'Pasif'; ?>
                            </span>
                        </div>

                        <div class="category-actions">
                            <button type="button" class="action-btn edit" 
                                    onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <button type="button" class="action-btn delete" 
                                    onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Kategori Formu Modal -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Kategori Ekle</h2>
                <button type="button" class="close-btn" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="post" class="category-form">
                <input type="hidden" name="category_id" id="categoryId">

                <div class="form-group">
                    <label>Kategori Adı</label>
                    <input type="text" name="name" id="categoryName" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>İkon</label>
                    <div class="icon-selector">
                        <div class="preview-icon">
                            <i id="iconPreview" class="fas fa-folder"></i>
                        </div>
                        <input type="text" name="icon" id="categoryIcon" class="form-control" 
                               placeholder="fas fa-folder" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea name="description" id="categoryDescription" class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="status" id="categoryStatus" checked>
                        Aktif
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i>
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Modal işlemleri
    const modal = document.getElementById('categoryModal');

    function showAddForm() {
        document.getElementById('modalTitle').textContent = 'Kategori Ekle';
        document.getElementById('categoryId').value = '';
        document.getElementById('categoryName').value = '';
        document.getElementById('categoryIcon').value = 'fas fa-folder';
        document.getElementById('iconPreview').className = 'fas fa-folder';
        document.getElementById('categoryDescription').value = '';
        document.getElementById('categoryStatus').checked = true;
        modal.style.display = 'flex';
    }

    function editCategory(category) {
        document.getElementById('modalTitle').textContent = 'Kategori Düzenle';
        document.getElementById('categoryId').value = category.id;
        document.getElementById('categoryName').value = category.name;
        document.getElementById('categoryIcon').value = category.icon;
        document.getElementById('iconPreview').className = category.icon;
        document.getElementById('categoryDescription').value = category.description;
        document.getElementById('categoryStatus').checked = category.status == 1;
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    // İkon önizleme
    document.getElementById('categoryIcon').addEventListener('input', function() {
        document.getElementById('iconPreview').className = this.value;
    });

    // Kategori silme
    function deleteCategory(id, name) {
        if (confirm(`"${name}" kategorisini silmek istediğinize emin misiniz?`)) {
            fetch('ajax/delete_ticket_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ category_id: id })
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

    // Modal dışına tıklandığında kapatma
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    </script>
</body>
</html> 