<?php
// Session kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin yetkisi kontrolü
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        $_SESSION['error'] = 'Bu sayfaya erişim yetkiniz yok!';
        header('Location: ../login.php');
        exit;
    }
}

// Her admin sayfasının başında çağırılacak
requireAdmin(); 