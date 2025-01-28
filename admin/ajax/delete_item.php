<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);
$item_id = (int)$data['item_id'];

try {
    // Ürünü kontrol et
    $item_query = $db->prepare("SELECT name, image FROM store_items WHERE id = ?");
    $item_query->execute([$item_id]);
    $item = $item_query->fetch();

    if (!$item) {
        throw new Exception('Ürün bulunamadı');
    }

    // İşlemi başlat
    $db->beginTransaction();

    // Ürünü sil
    $delete_query = $db->prepare("DELETE FROM store_items WHERE id = ?");
    $result = $delete_query->execute([$item_id]);

    if ($result) {
        // Ürün resmini sil
        if ($item['image'] && file_exists('../../' . $item['image'])) {
            unlink('../../' . $item['image']);
        }

        // Log kaydı
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            'Ürün silindi',
            $item['name'] . ' ürünü silindi',
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