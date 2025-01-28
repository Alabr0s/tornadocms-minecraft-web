<?php
session_start();
require_once '../includes/config/database.php';

try {
    if (!isset($_GET['server_id'])) {
        throw new Exception('Sunucu ID gerekli');
    }

    $server_id = (int)$_GET['server_id'];
    $category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

    // Kategorileri çek
    $categories_query = $db->prepare("
        SELECT c.*, COUNT(i.id) as item_count 
        FROM store_categories c 
        LEFT JOIN store_items i ON c.id = i.category_id AND i.status = 1
        WHERE c.server_id = ? AND c.status = 1
        GROUP BY c.id 
        ORDER BY c.display_order
    ");
    $categories_query->execute([$server_id]);
    $categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);

    // Ürünleri çek
    if ($category_id === 0) {
        $items_query = $db->prepare("
            SELECT i.* 
            FROM store_items i 
            JOIN store_categories c ON i.category_id = c.id 
            WHERE c.server_id = ? AND i.status = 1 
            ORDER BY i.price
        ");
        $items_query->execute([$server_id]);
    } else {
        $items_query = $db->prepare("
            SELECT * FROM store_items 
            WHERE category_id = ? AND status = 1 
            ORDER BY price
        ");
        $items_query->execute([$category_id]);
    }
    $items = $items_query->fetchAll(PDO::FETCH_ASSOC);

    // Kategoriler HTML'i
    ob_start();
    ?>
    <a href="javascript:void(0)" 
       class="category-item <?php echo $category_id === 0 ? 'active' : ''; ?>"
       data-category-id="0">
        <i class="fas fa-border-all"></i>
        <span>Tümü</span>
    </a>
    
    <?php foreach ($categories as $category): ?>
        <a href="javascript:void(0)" 
           class="category-item <?php echo $category_id === $category['id'] ? 'active' : ''; ?>"
           data-category-id="<?php echo $category['id']; ?>">
            <i class="<?php echo htmlspecialchars($category['icon']); ?>"></i>
            <span><?php echo htmlspecialchars($category['name']); ?></span>
        </a>
    <?php endforeach;
    $categories_html = ob_get_clean();

    // Ürünler HTML'i
    ob_start();
    if (empty($items)): ?>
        <div class="alert alert-warning">
            <h2>Ürün Bulunamadı</h2>
            <p>Bu kategoride henüz ürün bulunmuyor.</p>
        </div>
    <?php else:
        foreach ($items as $item):
            $has_discount = !empty($item['discount_price']) && 
                           (!$item['discount_start'] || strtotime($item['discount_start']) <= time()) && 
                           (!$item['discount_end'] || strtotime($item['discount_end']) >= time());
            ?>
            <div class="store-item">
                <div class="item-image">
                    <?php if ($item['image']): ?>
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-cube"></i>
                    <?php endif; ?>

                    <?php if ($has_discount): ?>
                        <div class="discount-badge">
                            <i class="fas fa-tag"></i>
                            <?php 
                            $discount_percent = round((($item['price'] - $item['discount_price']) / $item['price']) * 100);
                            echo $discount_percent . '% İndirim';
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($item['stock'] !== null): ?>
                        <div class="stock-badge">
                            <i class="fas fa-box"></i>
                            Stok: <?php echo $item['stock']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="item-info">
                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description']); ?></p>

                    <?php if ($item['duration']): ?>
                        <div class="duration">
                            <i class="fas fa-clock"></i>
                            <?php echo $item['duration']; ?> Gün
                        </div>
                    <?php endif; ?>

                    <div class="item-price">
                        <div class="price-info">
                            <?php if ($has_discount): ?>
                                <div class="price-column">
                                    <span class="price-label">Normal Fiyat</span>
                                    <span class="old-price">
                                        <i class="fas fa-coins"></i>
                                        <?php echo number_format($item['price']); ?>
                                    </span>
                                </div>
                                <div class="price-column">
                                    <span class="price-label">İndirimli Fiyat</span>
                                    <span class="price">
                                        <i class="fas fa-coins"></i>
                                        <?php echo number_format($item['discount_price']); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="price-column">
                                    <span class="price-label">Fiyat</span>
                                    <span class="price">
                                        <i class="fas fa-coins"></i>
                                        <?php echo number_format($item['price']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_discount): ?>
                            <div class="discount-details">
                                <div class="discount-info">
                                    <i class="fas fa-tag"></i>
                                    Kazancınız: <?php echo number_format($item['price'] - $item['discount_price']); ?> Coin
                                </div>
                                <div class="discount-percent">
                                    <i class="fas fa-percent"></i>
                                    <?php echo $discount_percent; ?>% İndirim
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button class="buy-btn" onclick="showConfirmPopup(<?php echo $item['id']; ?>)">
                        <i class="fas fa-shopping-cart"></i>
                        Satın Al
                    </button>
                </div>
            </div>
        <?php endforeach;
    endif;
    $items_html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'categories' => $categories_html,
        'items' => $items_html
    ]);

} catch (Exception $e) {
    error_log("Store Categories Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 