<?php
session_start();
require_once 'includes/config/database.php';

// Kullanıcı giriş yapmış mı kontrol et
if (isset($_SESSION['user_id'])) {
    try {
        // AuthMe tablosunda isLogged durumunu güncelle
        $stmt = $db->prepare("UPDATE authme SET isLogged = 0 WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        
        // Session'ı temizle
        session_unset();
        session_destroy();
        
        // Başarılı mesajıyla login sayfasına yönlendir
        header('Location: login.php?logout=success');
    } catch (PDOException $e) {
        // Hata durumunda yine de session'ı temizle ve yönlendir
        session_unset();
        session_destroy();
        header('Location: login.php');
    }
} else {
    // Zaten giriş yapılmamışsa ana sayfaya yönlendir
    header('Location: index.php');
}
exit; 