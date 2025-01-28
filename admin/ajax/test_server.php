<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    // JSON verisini al
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['server_id']) || !is_numeric($data['server_id'])) {
        throw new Exception('Geçersiz sunucu ID');
    }

    $server_id = (int)$data['server_id'];

    // Sunucuyu kontrol et
    $server_query = $db->prepare("SELECT host, port, password FROM websender_servers WHERE id = ?");
    $server_query->execute([$server_id]);
    $server = $server_query->fetch();

    if (!$server) {
        throw new Exception('Sunucu bulunamadı');
    }

    // WebSender bağlantısını test et
    $socket = @fsockopen($server['host'], $server['port'], $errno, $errstr, 3);
    
    if (!$socket) {
        throw new Exception('Sunucuya bağlanılamadı: ' . $errstr);
    }

    // Bağlantıyı kapat
    fclose($socket);

    // Log kaydı
    $log_query = $db->prepare("
        INSERT INTO admin_logs (admin_id, action, details, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $log_query->execute([
        $_SESSION['user_id'],
        'Sunucu testi',
        'Sunucu ID: ' . $server_id . ' - Test başarılı',
        $_SERVER['REMOTE_ADDR']
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Bağlantı başarılı'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 