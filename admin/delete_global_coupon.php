<?php
require_once '../config/init.php';

// Güvenlik Kontrolü: Sadece adminler
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

// CSRF Güvenlik Kontrolü
if (!verify_csrf_token()) {
    set_flash('error', 'Güvenlik doğrulaması başarısız oldu. İşlem iptal edildi.');
    header("Location: index.php");
    exit();
}

$coupon_id_to_delete = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$coupon_id_to_delete) {
    set_flash('error', 'Silinecek kupon belirtilmedi.');
    header("Location: index.php");
    exit();
}

try {
    $stmt_delete = $pdo->prepare("DELETE FROM Coupons WHERE id = ? AND company_id IS NULL");
    $stmt_delete->execute([$coupon_id_to_delete]);

    if ($stmt_delete->rowCount() > 0) {
        set_flash('success', 'Genel kupon başarıyla silindi.');
    } else {
        set_flash('error', 'Genel kupon bulunamadı veya silme yetkiniz yok.');
    }
} catch (PDOException $e) {
    set_flash('error', 'Kupon silinirken bir veritabanı hatası oluştu.');
}

header("Location: index.php");
exit();