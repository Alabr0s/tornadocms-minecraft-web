-- Kasa Kategorileri
CREATE TABLE case_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kasalar
CREATE TABLE cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    price INT NOT NULL,
    discount_price INT NULL,
    discount_start DATETIME NULL,
    discount_end DATETIME NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES case_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kasa İçerikleri
CREATE TABLE case_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    commands TEXT NOT NULL,
    chance DECIMAL(5,2) NOT NULL COMMENT 'Çıkma şansı (%)',
    rarity ENUM('common', 'uncommon', 'rare', 'epic', 'legendary') NOT NULL,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kasa Açma Geçmişi
CREATE TABLE case_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    case_id INT,
    item_id INT,
    price INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES authme(id) ON DELETE SET NULL,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES case_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Örnek kategori ekle
INSERT INTO case_categories (name, description, icon, display_order) VALUES 
('Genel', 'Genel kasalar', 'fas fa-box', 1),
('VIP', 'VIP kasalar', 'fas fa-crown', 2),
('Özel', 'Özel kasalar', 'fas fa-star', 3); 