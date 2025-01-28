<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    // JSON verisini al
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['category_id']) || !is_numeric($data['category_id'])) {
        throw new Exception('Geçersiz kategori ID');
    }

    $category_id = (int)$data['category_id'];

    // Kategoriyi kontrol et
    $category_query = $db->prepare("SELECT * FROM ticket_categories WHERE id = ?");
    $category_query->execute([$category_id]);
    $category = $category_query->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        throw new Exception('Kategori bulunamadı');
    }

    // Bu kategoride ticket var mı kontrol et
    $ticket_query = $db->prepare("SELECT COUNT(*) FROM tickets WHERE category_id = ?");
    $ticket_query->execute([$category_id]);
    $ticket_count = $ticket_query->fetchColumn();

    if ($ticket_count > 0) {
        throw new Exception('Bu kategoride ticket bulunduğu için silinemez!');
    }

    // Kategoriyi sil
    $delete_query = $db->prepare("DELETE FROM ticket_categories WHERE id = ?");
    $result = $delete_query->execute([$category_id]);

    if ($result) {
        // Log kaydı
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            'Ticket kategorisi silindi',
            $category['name'] . ' kategorisi silindi',
            $_SERVER['REMOTE_ADDR']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Kategori başarıyla silindi'
        ]);
    } else {
        throw new Exception('Kategori silinirken bir hata oluştu');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 