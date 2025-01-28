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

    // Kendini silmeye çalışıyor mu kontrol et
    if ($user_id === $_SESSION['user_id']) {
        throw new Exception('Kendinizi silemezsiniz!');
    }

    // Kullanıcıyı kontrol et
    $user_query = $db->prepare("SELECT username FROM authme WHERE id = ?");
    $user_query->execute([$user_id]);
    $user = $user_query->fetch();

    if (!$user) {
        throw new Exception('Kullanıcı bulunamadı');
    }

    // İşlemi başlat
    $db->beginTransaction();

    try {
        // İlişkili kayıtları sil
        $tables = ['store_history', 'case_history', 'coin_transactions', 'tickets'];
        foreach ($tables as $table) {
            $db->prepare("DELETE FROM $table WHERE user_id = ?")->execute([$user_id]);
        }

        // Kullanıcıyı sil
        $delete_query = $db->prepare("DELETE FROM authme WHERE id = ?");
        $result = $delete_query->execute([$user_id]);

        if ($result) {
            // Log kaydı
            $log_query = $db->prepare("
                INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            $log_query->execute([
                $_SESSION['user_id'],
                'Kullanıcı silindi',
                $user['username'] . ' kullanıcısı silindi',
                $_SERVER['REMOTE_ADDR']
            ]);

            $db->commit();
            echo json_encode([
                'success' => true,
                'message' => $user['username'] . ' kullanıcısı başarıyla silindi'
            ]);
        } else {
            throw new Exception('Silme işlemi başarısız oldu');
        }
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 