<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    // JSON verisini al
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['item_id']) || !is_numeric($data['item_id'])) {
        throw new Exception('Geçersiz içerik ID');
    }

    $item_id = (int)$data['item_id'];

    // İçeriği kontrol et
    $item_query = $db->prepare("SELECT ci.*, c.name as case_name FROM case_items ci 
                               LEFT JOIN cases c ON c.id = ci.case_id 
                               WHERE ci.id = ?");
    $item_query->execute([$item_id]);
    $item = $item_query->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('İçerik bulunamadı');
    }

    // Resmi sil
    if (file_exists('../../' . $item['image'])) {
        unlink('../../' . $item['image']);
    }

    // İçeriği sil
    $delete_query = $db->prepare("DELETE FROM case_items WHERE id = ?");
    $result = $delete_query->execute([$item_id]);

    if ($result) {
        // Log kaydı
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            'İçerik silindi',
            $item['case_name'] . ' kasasındaki ' . $item['name'] . ' içeriği silindi',
            $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'İçerik başarıyla silindi'
        ]);
    } else {
        throw new Exception('İçerik silinirken bir hata oluştu');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 