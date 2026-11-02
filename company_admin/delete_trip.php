<?php
require_once '../config/init.php';

// Güvenlik Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company_admin') {
    header("Location: /index.php");
    exit();
}

// CSRF Güvenlik Kontrolü
if (!verify_csrf_token()) {
    set_flash('error', 'Güvenlik doğrulaması başarısız oldu. İşlem iptal edildi.');
    header("Location: index.php");
    exit();
}

// Firma yetkilisinin şirket ID'sini al
$stmt_user = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$company_id = $stmt_user->fetchColumn();

if (!$company_id) {
    set_flash('error', 'Bu kullanıcı bir şirkete atanmamış.');
    header("Location: /index.php");
    exit();
}

$trip_id_to_delete = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$trip_id_to_delete) {
    set_flash('error', 'Silinecek sefer belirtilmedi.');
    header("Location: index.php");
    exit();
}

try {
    $stmt_delete = $pdo->prepare("DELETE FROM Trips WHERE id = ? AND company_id = ?");
    $stmt_delete->execute([$trip_id_to_delete, $company_id]);

    if ($stmt_delete->rowCount() > 0) {
        log_activity('SEFER_SİLME', "Sefer sistemden silindi (Sefer ID: {$trip_id_to_delete})");
        set_flash('success', 'Sefer başarıyla silindi.');
    } else {
        set_flash('error', 'Sefer bulunamadı veya bu seferi silme yetkiniz yok.');
    }
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        set_flash('error', 'Bu sefere ait satılmış biletler bulunduğu için doğrudan silinemez.');
    } else {
        set_flash('error', 'Sefer silinirken bir veritabanı hatası oluştu.');
    }
}

header("Location: index.php");
exit();