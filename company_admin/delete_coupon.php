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

$coupon_id_to_delete = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$coupon_id_to_delete) {
    set_flash('error', 'Silinecek kupon belirtilmedi.');
    header("Location: index.php");
    exit();
}

try {
    $stmt_delete = $pdo->prepare("DELETE FROM Coupons WHERE id = ? AND company_id = ?");
    $stmt_delete->execute([$coupon_id_to_delete, $company_id]);

    if ($stmt_delete->rowCount() > 0) {
        set_flash('success', 'İndirim kuponu başarıyla silindi.');
    } else {
        set_flash('error', 'Kupon bulunamadı veya bu kuponu silme yetkiniz yok.');
    }
} catch (PDOException $e) {
    set_flash('error', 'Kupon silinirken bir veritabanı hatası oluştu.');
}

header("Location: index.php");
exit();