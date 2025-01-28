<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);
$category_id = (int)$data['category_id'];

try {
    // Kategoriyi kontrol et
    $category_query = $db->prepare("SELECT name, status FROM store_categories WHERE id = ?");
    $category_query->execute([$category_id]);
    $category = $category_query->fetch();

    if (!$category) {
        throw new Exception('Kategori bulunamadı');
    }

    // Durumu değiştir
    $update_query = $db->prepare("UPDATE store_categories SET status = NOT status WHERE id = ?");
    $result = $update_query->execute([$category_id]);

    if ($result) {
        // Log kaydı
        $action = $category['status'] ? 'Kategori devre dışı bırakıldı' : 'Kategori aktifleştirildi';
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            $action,
            $category['name'] . ' kategorisi ' . strtolower($action),
            $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Güncelleme başarısız oldu');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 