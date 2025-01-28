<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);
$category_id = (int)$data['category_id'];

try {
    // Kategoriyi kontrol et
    $category_query = $db->prepare("SELECT name FROM store_categories WHERE id = ?");
    $category_query->execute([$category_id]);
    $category = $category_query->fetch();

    if (!$category) {
        throw new Exception('Kategori bulunamadı');
    }

    // İşlemi başlat
    $db->beginTransaction();

    // Kategoriye ait ürünlerin resimlerini sil
    $items_query = $db->prepare("SELECT image FROM store_items WHERE category_id = ?");
    $items_query->execute([$category_id]);
    $items = $items_query->fetchAll();

    foreach ($items as $item) {
        if ($item['image'] && file_exists('../../' . $item['image'])) {
            unlink('../../' . $item['image']);
        }
    }

    // Kategoriye ait ürünleri sil
    $delete_items = $db->prepare("DELETE FROM store_items WHERE category_id = ?");
    $delete_items->execute([$category_id]);

    // Kategoriyi sil
    $delete_category = $db->prepare("DELETE FROM store_categories WHERE id = ?");
    $result = $delete_category->execute([$category_id]);

    if ($result) {
        // Log kaydı
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            'Kategori silindi',
            $category['name'] . ' kategorisi ve tüm ürünleri silindi',
            $_SERVER['REMOTE_ADDR']
        ]);

        $db->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Silme işlemi başarısız oldu');
    }
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 