<?php
session_start();
require_once '../includes/config/database.php';

try {
    // Kullanıcı kontrolü
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Oturum açmanız gerekiyor!');
    }

    // Kasa ID kontrolü
    if (!isset($_GET['case_id'])) {
        throw new Exception('Kasa ID gerekli!');
    }

    $case_id = (int)$_GET['case_id'];
    $user_id = $_SESSION['user_id'];

    // Debug için
    error_log("Opening case ID: " . $case_id . " for user ID: " . $user_id);

    // Kasayı kontrol et
    $case_query = $db->prepare("SELECT * FROM cases WHERE id = ? AND status = 1");
    $case_query->execute([$case_id]);
    $case = $case_query->fetch(PDO::FETCH_ASSOC);

    if (!$case) {
        throw new Exception('Kasa bulunamadı! (ID: ' . $case_id . ')');
    }

    // Kullanıcının coin miktarını kontrol et
    $user_query = $db->prepare("SELECT coins FROM authme WHERE id = ?");
    $user_query->execute([$user_id]);
    $user_coins = $user_query->fetchColumn();

    if ($user_coins < $case['price']) {
        throw new Exception('Yetersiz coin!');
    }

    // Kasadaki ödülleri çek
    $items_query = $db->prepare("
        SELECT ci.*, c.price as case_price 
        FROM case_items ci 
        JOIN cases c ON ci.case_id = c.id 
        WHERE ci.case_id = ? AND ci.status = 1
    ");
    $items_query->execute([$case_id]);
    $items = $items_query->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('Bu kasada ödül bulunmuyor!');
    }

    // Ödül seç (şans oranlarına göre)
    $total_chance = 0;
    foreach ($items as $item) {
        $total_chance += $item['chance'];
    }

    $random = mt_rand(1, $total_chance);
    $current_chance = 0;
    $won_item = null;

    foreach ($items as $item) {
        $current_chance += $item['chance'];
        if ($random <= $current_chance) {
            $won_item = $item;
            break;
        }
    }

    if (!$won_item) {
        throw new Exception('Ödül seçiminde hata oluştu!');
    }

    // Veritabanı işlemlerini başlat
    $db->beginTransaction();

    try {
        // Kullanıcının coinlerini güncelle
        $coins_won = isset($won_item['coins']) ? (int)$won_item['coins'] : 0;
        $new_coins = $user_coins - $case['price'] + $coins_won;
        
        $update_coins = $db->prepare("UPDATE authme SET coins = ? WHERE id = ?");
        $update_coins->execute([$new_coins, $user_id]);

        // Kasa geçmişine ekle
        $add_history = $db->prepare("
            INSERT INTO case_history 
            (user_id, case_id, item_id, coins_won, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $add_history->execute([
            $user_id,
            $case_id,
            $won_item['id'],
            $coins_won
        ]);

        // Eğer komut varsa WebSender ile gönder
        if (!empty($won_item['commands'])) {
            // WebSender işlemleri burada yapılacak
            // ...
        }

        $db->commit();

        // Başarılı sonuç döndür
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'item' => [
                'id' => $won_item['id'],
                'name' => $won_item['name'],
                'image' => $won_item['image'],
                'coin_value' => (int)$won_item['coin_value'],
                'rarity' => $won_item['rarity']
            ],
            'newBalance' => $new_coins
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('İşlem sırasında bir hata oluştu: ' . $e->getMessage());
    }

} catch (Exception $e) {
    error_log("Case Opening Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 