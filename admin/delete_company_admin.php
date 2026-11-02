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

$user_id_to_delete = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$user_id_to_delete) {
    set_flash('error', 'Silinecek kullanıcı belirtilmedi.');
    header("Location: index.php");
    exit();
}

// Güvenlik: Admin kendini silemesin
if ($user_id_to_delete === $_SESSION['user_id']) {
    set_flash('error', 'Kendi hesabınızı silemezsiniz.');
    header("Location: index.php");
    exit();
}

try {
    $stmt_delete = $pdo->prepare("DELETE FROM User WHERE id = ? AND role = 'company_admin'");
    $stmt_delete->execute([$user_id_to_delete]);

    if ($stmt_delete->rowCount() > 0) {
        set_flash('success', 'Firma yöneticisi başarıyla silindi.');
    } else {
        set_flash('error', 'Firma yöneticisi bulunamadı veya silme yetkiniz yok.');
    }
} catch (PDOException $e) {
    set_flash('error', 'Kullanıcı silinirken bir veritabanı hatası oluştu.');
}

header("Location: index.php");
exit();