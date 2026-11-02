<?php
require_once '../config/init.php';

// Güvenlik Kontrolü: Sadece adminler erişebilir
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

$company_id_to_delete = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$company_id_to_delete) {
    set_flash('error', 'Silinecek firma belirtilmedi.');
    header("Location: index.php");
    exit();
}

try {
    $stmt_delete = $pdo->prepare("DELETE FROM Bus_Company WHERE id = ?");
    $stmt_delete->execute([$company_id_to_delete]);

    if ($stmt_delete->rowCount() > 0) {
        log_activity('FİRMA_YÖNETİMİ', "Firma ve ilişkili veriler sistemden silindi (Firma ID: {$company_id_to_delete})");
        set_flash('success', 'Firma ve ilişkili tüm kayıtlar (CASCADE) başarıyla silindi.');
    } else {
        set_flash('error', 'Silinecek firma bulunamadı.');
    }
} catch (PDOException $e) {
    set_flash('error', 'Firma silinirken bir veritabanı hatası oluştu.');
}

header("Location: index.php");
exit();