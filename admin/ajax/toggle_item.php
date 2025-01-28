<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);
$item_id = (int)$data['item_id'];

try {
    // Ürünü kontrol et
    $item_query = $db->prepare("SELECT name, status FROM store_items WHERE id = ?");
    $item_query->execute([$item_id]);
    $item = $item_query->fetch();

    if (!$item) {
        throw new Exception('Ürün bulunamadı');
    }

    // Durumu değiştir
    $update_query = $db->prepare("UPDATE store_items SET status = NOT status WHERE id = ?");
    $result = $update_query->execute([$item_id]);

    if ($result) {
        // Log kaydı
        $action = $item['status'] ? 'Ürün devre dışı bırakıldı' : 'Ürün aktifleştirildi';
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            $action,
            $item['name'] . ' ürünü ' . strtolower($action),
            $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Güncelleme başarısız oldu');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 