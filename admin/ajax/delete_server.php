<?php
require_once '../../includes/config/database.php';
require_once '../includes/auth.php';

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);
$server_id = (int)$data['server_id'];

try {
    // Sunucuyu kontrol et
    $server_query = $db->prepare("SELECT name, image FROM websender_servers WHERE id = ?");
    $server_query->execute([$server_id]);
    $server = $server_query->fetch();

    if (!$server) {
        throw new Exception('Sunucu bulunamadı');
    }

    // İşlemi başlat
    $db->beginTransaction();

    // Sunucuya ait kategorileri ve ürünleri sil
    $categories_query = $db->prepare("SELECT id FROM store_categories WHERE server_id = ?");
    $categories_query->execute([$server_id]);
    $categories = $categories_query->fetchAll();

    foreach ($categories as $category) {
        // Ürün resimlerini sil
        $items_query = $db->prepare("SELECT image FROM store_items WHERE category_id = ?");
        $items_query->execute([$category['id']]);
        $items = $items_query->fetchAll();

        foreach ($items as $item) {
            if ($item['image'] && file_exists('../../' . $item['image'])) {
                unlink('../../' . $item['image']);
            }
        }

        // Ürünleri sil
        $db->prepare("DELETE FROM store_items WHERE category_id = ?")->execute([$category['id']]);
    }

    // Kategorileri sil
    $db->prepare("DELETE FROM store_categories WHERE server_id = ?")->execute([$server_id]);

    // Sunucuyu sil
    $delete_query = $db->prepare("DELETE FROM websender_servers WHERE id = ?");
    $result = $delete_query->execute([$server_id]);

    if ($result) {
        // Sunucu resmini sil
        if ($server['image'] && file_exists('../../' . $server['image'])) {
            unlink('../../' . $server['image']);
        }

        // Log kaydı
        $log_query = $db->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $log_query->execute([
            $_SESSION['user_id'],
            'Sunucu silindi',
            $server['name'] . ' sunucusu ve tüm içerikleri silindi',
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