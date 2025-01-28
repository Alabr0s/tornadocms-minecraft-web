<?php
session_start();
require_once '../includes/config/database.php';

try {
    if (!isset($_GET['case_id'])) {
        throw new Exception('Kasa ID gerekli!');
    }

    $case_id = (int)$_GET['case_id'];

    // Kasadaki ödülleri çek
    $items_query = $db->prepare("
        SELECT * FROM case_items 
        WHERE case_id = ? AND status = 1 
        ORDER BY chance DESC
    ");
    $items_query->execute([$case_id]);
    $items = $items_query->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('Bu kasada ödül bulunmuyor!');
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 