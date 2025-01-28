<?php
// Hata raporlamayı etkinleştir
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config/database.php';
require_once 'includes/auth.php';

// Aktif sayfa
$current_page = 'blog';

// Site ayarlarını çek
$settings_query = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $settings_query->fetch(PDO::FETCH_ASSOC);

// Session kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// Blog yazısı ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_post'])) {
    try {
        // Debug için form verilerini kontrol et
        error_log("Form verileri: " . print_r($_POST, true));
        error_log("Dosya verileri: " . print_r($_FILES, true));

        $title = $_POST['title'];
        $content = $_POST['content'];
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Resim yükleme işlemi
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            error_log("Dosya uzantısı: " . $ext);
            
            if (in_array($ext, $allowed)) {
                $newname = uniqid() . '.' . $ext;
                $upload_dir = '../uploads/blog/';
                
                // Klasör yoksa oluştur
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $newname;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    $image = 'uploads/blog/' . $newname;
                    error_log("Dosya yüklendi: " . $image);
                }
            }
        }
        
        // SQL sorgusunu kontrol et
        $query = $db->prepare("INSERT INTO blogs (title, content, image, author_id, status) VALUES (?, ?, ?, ?, ?)");
        error_log("SQL parametreleri: " . print_r([$title, $content, $image, $_SESSION['user_id'], $status], true));
        
        $query->execute([$title, $content, $image, $_SESSION['user_id'], $status]);
        
        // Başarılı ekleme sonrası yönlendirme
        $_SESSION['success'] = 'Blog yazısı başarıyla eklendi!';
        header('Location: blog.php');
        exit;

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = 'Blog yazısı eklenirken bir hata oluştu!';
    }
}

// Blog yazısı silme
if (isset($_GET['delete'])) {
    try {
        $blog_id = (int)$_GET['delete'];
        
        // Önce blog yazısının bilgilerini al
        $blog_query = $db->prepare("SELECT title, image FROM blogs WHERE id = ?");
        $blog_query->execute([$blog_id]);
        $blog = $blog_query->fetch(PDO::FETCH_ASSOC);
        
        // Blog yazısını sil
        $query = $db->prepare("DELETE FROM blogs WHERE id = ?");
        $query->execute([$blog_id]);
        
        // Resmi sil
        if ($blog['image'] && file_exists('../' . $blog['image'])) {
            unlink('../' . $blog['image']);
        }
        
        // Admin log
        $log_query = $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $log_query->execute([
            $_SESSION['user_id'],
            'Blog yazısı silindi',
            $blog['title'],
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $success = 'Blog yazısı başarıyla silindi!';
    } catch (PDOException $e) {
        $error = 'Blog yazısı silinirken bir hata oluştu!';
    }
}

// Blog yazılarını listele
$posts_query = $db->query("
    SELECT b.*, a.realname as author 
    FROM blogs b 
    LEFT JOIN authme a ON b.author_id = a.id 
    ORDER BY b.created_at DESC
");
$posts = $posts_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Yönetimi - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Blog Yönetimi</h1>
                <div class="admin-user">
                    <img src="https://mc-heads.net/avatar/<?php echo $_SESSION['username']; ?>" alt="Admin" class="admin-avatar">
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-newspaper"></i>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo count($posts); ?></div>
                        <div class="stat-label">Toplam Yazı</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h2>Yeni Blog Yazısı</h2>
                    <form method="POST" enctype="multipart/form-data" class="blog-form">
                        <div class="form-group">
                            <label for="title">Başlık</label>
                            <input type="text" id="title" name="title" class="form-control" required minlength="3">
                        </div>
                        
                        <div class="form-group">
                            <label for="content">İçerik</label>
                            <div id="editor"></div>
                            <input type="hidden" name="content" id="content">
                        </div>
                        
                        <div class="form-group">
                            <label for="image">Görsel</label>
                            <label for="image" class="file-upload-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Görsel Seç</span>
                            </label>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif" onchange="previewImage(this)">
                            <div class="image-preview" id="imagePreview" style="display: none;">
                                <img src="" alt="Önizleme" id="preview">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="status" checked>
                                <span>Aktif</span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="add_post" class="action-button" onclick="return validateForm()">
                                <i class="fas fa-plus"></i>
                                Yazı Ekle
                            </button>
                        </div>
                    </form>
                </div>

                <div class="dashboard-card">
                    <h2>Blog Yazıları</h2>
                    <div class="blog-list">
                        <?php foreach ($posts as $post): ?>
                        <div class="blog-item">
                            <div class="blog-item-image">
                                <?php if ($post['image']): ?>
                                    <img src="../<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                <?php else: ?>
                                    <img src="../assets/images/default-blog.jpg" alt="Varsayılan Görsel">
                                <?php endif; ?>
                            </div>
                            <div class="blog-item-content">
                                <h3 class="blog-item-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <div class="blog-item-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($post['author']); ?></span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></span>
                                    <span><i class="fas fa-circle <?php echo $post['status'] ? 'text-success' : 'text-danger'; ?>"></i> 
                                        <?php echo $post['status'] ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="blog-item-actions">
                                <a href="blog_edit.php?id=<?php echo $post['id']; ?>" class="action-btn edit" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $post['id']; ?>" class="action-btn delete" 
                                   onclick="return confirm('Bu yazıyı silmek istediğinize emin misiniz?')" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // CKEditor
        let editor;
        ClassicEditor
            .create(document.querySelector('#editor'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', 'insertTable', 'undo', 'redo'],
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraf', class: 'ck-heading_paragraph' },
                        { model: 'heading1', view: 'h1', title: 'Başlık 1', class: 'ck-heading_heading1' },
                        { model: 'heading2', view: 'h2', title: 'Başlık 2', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Başlık 3', class: 'ck-heading_heading3' }
                    ]
                }
            })
            .then(newEditor => {
                editor = newEditor;
            })
            .catch(error => {
                console.error(error);
            });

        // Görsel önizleme
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const previewDiv = document.getElementById('imagePreview');
            const fileLabel = input.previousElementSibling.querySelector('span');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewDiv.style.display = 'block';
                    fileLabel.textContent = input.files[0].name;
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                fileLabel.textContent = 'Görsel Seç';
            }
        }

        // Form doğrulama
        function validateForm() {
            const title = document.getElementById('title').value.trim();
            const content = editor.getData();

            // Form gönderilmeden önce içeriği gizli alana aktar
            document.getElementById('content').value = content;
            
            if (title.length < 3) {
                alert('Başlık en az 3 karakter olmalıdır.');
                return false;
            }
            
            if (content.replace(/<[^>]*>/g, '').trim().length < 10) {
                alert('İçerik en az 10 karakter olmalıdır.');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html> 