<?php
session_start();
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    // JSON verisini al
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = (int)$data['user_id'];

    if ($user_id <= 0) {
        throw new Exception('Geçersiz kullanıcı ID!');
    }

    // Kendini değiştirmeye çalışıyor mu kontrol et
    if ($user_id === $_SESSION['user_id']) {
        throw new Exception('Kendi admin durumunuzu değiştiremezsiniz!');
    }

    // Kullanıcıyı kontrol et
    $user_query = $db->prepare("SELECT username, is_admin FROM authme WHERE id = ?");
    $user_query->execute([$user_id]);
    $user = $user_query->fetch();

    if (!$user) {
        throw new Exception('Kullanıcı bulunamadı');
    }

    // Admin durumunu değiştir
    $update_query = $db->prepare("UPDATE authme SET is_admin = NOT is_admin WHERE id = ?");
    $result = $update_query->execute([$user_id]);

    if ($result) {
        // Log kaydı
        $action = $user['is_admin'] ? 'Admin yetkisi alındı' : 'Admin yetkisi verildi';
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            $action,
            $user['username'] . ' kullanıcısının ' . strtolower($action),
            $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode([
            'success' => true,
            'message' => $user['username'] . ' kullanıcısının ' . strtolower($action)
        ]);
    } else {
        throw new Exception('Güncelleme başarısız oldu');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 