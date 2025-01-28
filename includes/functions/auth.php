<?php
function registerUser($username, $email, $password) {
    global $db;
    
    try {
        // AuthMe şifre formatı
        $salt = generateSalt();
        $hashedPassword = createHash($password, $salt);
        
        // AuthMe tablosuna kayıt
        $stmt = $db->prepare("INSERT INTO authme (username, realname, password, email, regdate, regip, coins, role) VALUES (?, ?, ?, ?, ?, ?, 0, 'user')");
        $stmt->execute([
            strtolower($username), // AuthMe her zaman küçük harf kullanır
            $username,
            $hashedPassword,
            $email,
            time(),
            $_SERVER['REMOTE_ADDR']
        ]);
        
        return true;
    } catch(PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

function loginUser($username, $password) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM authme WHERE username = ?");
        $stmt->execute([strtolower($username)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && isValidPassword($password, $user['password'])) {
            // AuthMe tablosunu güncelle
            $stmt = $db->prepare("UPDATE authme SET lastlogin = ?, ip = ?, isLogged = 1 WHERE username = ?");
            $stmt->execute([time(), $_SERVER['REMOTE_ADDR'], strtolower($username)]);
            
            // Session bilgilerini kaydet
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['realname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['coins'] = $user['coins'];
            
            return true;
        }
        return false;
    } catch(PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

function logoutUser() {
    global $db;
    
    if (isset($_SESSION['username'])) {
        try {
            $stmt = $db->prepare("UPDATE authme SET isLogged = 0 WHERE username = ?");
            $stmt->execute([strtolower($_SESSION['username'])]);
        } catch(PDOException $e) {
            error_log($e->getMessage());
        }
    }
    
    session_destroy();
}

// AuthMe şifreleme fonksiyonları
function generateSalt() {
    return random_bytes(16);
}

function createHash($password, $salt) {
    $salt = bin2hex($salt);
    return '$SHA$' . $salt . '$' . hash('sha256', hash('sha256', $password) . $salt);
}

function isValidPassword($password, $hash) {
    if (strlen($hash) == 0) return false;
    $parts = explode('$', $hash);
    if (count($parts) != 4) return false;
    
    $salt = $parts[2];
    $validHash = '$SHA$' . $salt . '$' . hash('sha256', hash('sha256', $password) . $salt);
    
    return hash_equals($hash, $validHash);
}

// Coin işlemleri
function addCoins($username, $amount) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE authme SET coins = coins + ? WHERE username = ?");
        return $stmt->execute([$amount, strtolower($username)]);
    } catch(PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

function removeCoins($username, $amount) {
    global $db;
    try {
        $stmt = $db->prepare("UPDATE authme SET coins = GREATEST(0, coins - ?) WHERE username = ?");
        return $stmt->execute([$amount, strtolower($username)]);
    } catch(PDOException $e) {
        error_log($e->getMessage());
        return false;
    }
}

function getCoins($username) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT coins FROM authme WHERE username = ?");
        $stmt->execute([strtolower($username)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['coins'] : 0;
    } catch(PDOException $e) {
        error_log($e->getMessage());
        return 0;
    }
}
?> 