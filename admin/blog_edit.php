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

// Blog ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: blog.php');
    exit;
}

$blog_id = (int)$_GET['id'];

// Blog yazısını getir
$blog_query = $db->prepare("SELECT * FROM blogs WHERE id = ?");
$blog_query->execute([$blog_id]);
$blog = $blog_query->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    header('Location: blog.php');
    exit;
}

// Blog yazısı güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_post'])) {
    try {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Resim yükleme işlemi
        $image = $blog['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $newname = uniqid() . '.' . $ext;
                $upload_dir = '../uploads/blog/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $newname;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                    // Eski resmi sil
                    if ($blog['image'] && file_exists('../' . $blog['image'])) {
                        unlink('../' . $blog['image']);
                    }
                    $image = 'uploads/blog/' . $newname;
                }
            }
        }
        
        $query = $db->prepare("UPDATE blogs SET title = ?, content = ?, image = ?, status = ? WHERE id = ?");
        $query->execute([$title, $content, $image, $status, $blog_id]);
        
        // Admin log
        $log_query = $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $log_query->execute([
            $_SESSION['user_id'],
            'Blog yazısı güncellendi',
            $title,
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $success = 'Blog yazısı başarıyla güncellendi!';
        
        // Blog bilgilerini yeniden çek
        $blog_query->execute([$blog_id]);
        $blog = $blog_query->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Blog yazısı güncellenirken bir hata oluştu!';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Yazısı Düzenle - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="admin-header">
                <h1>Blog Yazısı Düzenle</h1>
                <a href="blog.php" class="action-button">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
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

            <div class="dashboard-card">
                <form method="POST" enctype="multipart/form-data" class="blog-form">
                    <div class="form-group">
                        <label for="title">Başlık</label>
                        <input type="text" id="title" name="title" class="form-control" 
                               value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">İçerik</label>
                        <textarea id="content" name="content" class="form-control" required>
                            <?php echo htmlspecialchars($blog['content']); ?>
                        </textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Görsel</label>
                        <label for="image" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Görsel Seç</span>
                        </label>
                        <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                        <div class="image-preview" id="imagePreview" style="display: <?php echo $blog['image'] ? 'block' : 'none'; ?>">
                            <img src="<?php echo $blog['image'] ? '../' . htmlspecialchars($blog['image']) : ''; ?>" 
                                 alt="Önizleme" id="preview">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="status" <?php echo $blog['status'] ? 'checked' : ''; ?>>
                            <span>Aktif</span>
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_post" class="action-button">
                            <i class="fas fa-save"></i>
                            Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // CKEditor
        ClassicEditor
            .create(document.querySelector('#content'), {
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
            .catch(error => {
                console.error(error);
            });

        // Görsel önizleme
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const previewDiv = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewDiv.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html> 