<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);
$server_id = (int)$data['server_id'];

try {
    // Sunucuyu kontrol et
    $server_query = $db->prepare("SELECT name, status FROM websender_servers WHERE id = ?");
    $server_query->execute([$server_id]);
    $server = $server_query->fetch();

    if (!$server) {
        throw new Exception('Sunucu bulunamadı');
    }

    // Durumu değiştir
    $update_query = $db->prepare("UPDATE websender_servers SET status = NOT status WHERE id = ?");
    $result = $update_query->execute([$server_id]);

    if ($result) {
        // Log kaydı
        $action = $server['status'] ? 'Sunucu devre dışı bırakıldı' : 'Sunucu aktifleştirildi';
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            $action,
            $server['name'] . ' sunucusu ' . strtolower($action),
            $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Güncelleme başarısız oldu');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 