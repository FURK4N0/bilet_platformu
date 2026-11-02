<?php
require_once 'config/init.php';

// Çıkış işlemini logla
if (isset($_SESSION['user_id'])) {
    log_activity('ÇIKIŞ', 'Kullanıcı sistemden çıkış yaptı');
}

// Tüm oturum değişkenlerini temizle
$_SESSION = array();

// Oturumu sonlandır
session_destroy();

// Kullanıcıyı ana sayfaya yönlendir
header("Location: index.php");
exit;
?>