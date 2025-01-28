<?php
session_start();
require_once '../includes/config/database.php';
require_once '../includes/classes/WebSender.php';

// Debug log
error_log("Buy Item Request - GET: " . print_r($_GET, true));
error_log("Buy Item Request - SESSION: " . print_r($_SESSION, true));

if (!isset($_SESSION['user_id'])) {
    error_log("Buy Item Error: Oturum bulunamadı");
    die(json_encode(['success' => false, 'error' => 'Oturum açmanız gerekiyor!']));
}

// GET parametrelerini al ve doğrula
$item_id = isset($_GET['item_id']) ? filter_var($_GET['item_id'], FILTER_VALIDATE_INT) : null;
$server_id = isset($_GET['server_id']) ? filter_var($_GET['server_id'], FILTER_VALIDATE_INT) : null;

error_log("Buy Item Params - item_id: $item_id, server_id: $server_id");

if (!$item_id || !$server_id) {
    error_log("Buy Item Error: Geçersiz parametreler - item_id: $item_id, server_id: $server_id");
    die(json_encode(['success' => false, 'error' => 'Geçersiz parametreler!']));
}

try {
    // İşlemi başlat
    $db->beginTransaction();
    
    // Debug için SQL sorgusunu logla
    error_log("Buy Item - SQL Query: SELECT * FROM websender_servers WHERE id = $server_id");
    
    // Sunucu kontrolü
    $server_query = $db->prepare("SELECT * FROM websender_servers WHERE id = ? AND status = 1");
    $server_query->execute([$server_id]);
    $server = $server_query->fetch(PDO::FETCH_ASSOC);

    // Debug için sunucu bilgilerini logla
    error_log("Buy Item - Server Data: " . print_r($server, true));

    if (!$server) {
        throw new Exception('Sunucu bulunamadı veya aktif değil!');
    }

    // Ürün bilgilerini al
    $item_query = $db->prepare("
        SELECT si.*, sc.server_id 
        FROM store_items si
        INNER JOIN store_categories sc ON si.category_id = sc.id
        WHERE si.id = ? AND si.status = 1
    ");
    $item_query->execute([$item_id]);
    $item = $item_query->fetch(PDO::FETCH_ASSOC);

    // Debug için ürün bilgilerini logla
    error_log("Buy Item - Item Data: " . print_r($item, true));

    if (!$item) {
        throw new Exception('Ürün bulunamadı!');
    }

    // Sunucu kontrolü
    if ($item['server_id'] != $server_id) {
        throw new Exception('Bu ürün seçili sunucuda mevcut değil!');
    }
    
    // Stok kontrolü
    if ($item['stock'] !== null && $item['stock'] <= 0) {
        throw new Exception('Ürün stokta yok!');
    }
    
    // İndirim kontrolü
    $current_price = $item['price'];
    if ($item['discount_price'] > 0 && $item['discount_start'] <= date('Y-m-d H:i:s') && $item['discount_end'] >= date('Y-m-d H:i:s')) {
        $current_price = $item['discount_price'];
    }
    
    // Coin kontrolü
    $user_query = $db->prepare("SELECT coins FROM authme WHERE id = ? FOR UPDATE");
    $user_query->execute([$_SESSION['user_id']]);
    $user = $user_query->fetch(PDO::FETCH_ASSOC);
    
    if ($user['coins'] < $current_price) {
        throw new Exception('Yetersiz coin!');
    }
    
    // Coin düş
    $update_coins = $db->prepare("UPDATE authme SET coins = coins - ? WHERE id = ?");
    $update_coins->execute([$current_price, $_SESSION['user_id']]);
    
    // Stok güncelle
    if ($item['stock'] !== null) {
        $update_stock = $db->prepare("UPDATE store_items SET stock = stock - 1 WHERE id = ?");
        $update_stock->execute([$item_id]);
    }
    
    // Son kullanma tarihi hesapla
    $expire_date = null;
    if ($item['duration']) {
        $expire_date = date('Y-m-d H:i:s', strtotime("+{$item['duration']} days"));
    }
    
    // Satın alma kaydı oluştur
    $purchase_query = $db->prepare("
        INSERT INTO store_purchases (user_id, item_id, server_id, price, expire_date) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $purchase_query->execute([
        $_SESSION['user_id'],
        $item_id,
        $server_id,
        $current_price,
        $expire_date
    ]);
    
    // Komutları çalıştır
    $result = WebSender::executeCommands($server_id, $item['commands'], $_SESSION['username']);
    
    if (!$result['success']) {
        throw new Exception($result['error']);
    }
    
    // İşlemi onayla
    $db->commit();
    
    // Session'daki coin miktarını güncelle
    $_SESSION['coins'] -= $current_price;
    
    // Her önemli adımda log ekleyelim
    error_log("Buy Item - Ürün bilgileri: " . print_r($item, true));
    error_log("Buy Item - Kullanıcı bilgileri: " . print_r($user, true));
    error_log("Buy Item - Fiyat: $current_price");
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Buy Item Error: " . $e->getMessage());
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 