<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: account.php");
    exit();
}

if (!verify_csrf_token()) {
    set_flash('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    header("Location: account.php");
    exit();
}

$amount = floatval($_POST['amount'] ?? 0);
$card_number = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
$card_name = trim($_POST['card_name'] ?? '');
$expiry = trim($_POST['expiry'] ?? '');
$cvv = trim($_POST['cvv'] ?? '');

if ($amount < 10 || $amount > 10000) {
    set_flash('error', 'Bakiye yükleme tutarı en az 10 TL, en fazla 10.000 TL olabilir.');
    header("Location: account.php");
    exit();
}

if (strlen($card_number) < 15 || strlen($card_number) > 19 || empty($card_name) || empty($cvv)) {
    set_flash('error', 'Lütfen geçerli kart ve ödeme bilgileri giriniz.');
    header("Location: account.php");
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$amount, $_SESSION['user_id']]);

    $last4 = substr($card_number, -4);
    log_activity(
        'BAKİYE_YÜKLEME', 
        "Hesaba ₺" . number_format($amount, 2) . " tutarında bakiye yüklendi. (Kart Sahibi: {$card_name}, Kart Sonu: {$last4})"
    );

    set_flash('success', "🎉 ₺" . number_format($amount, 2) . " tutarındaki bakiye başarıyla yüklendi! Yeni bakiyeniz kullanıma hazırdır.");
} catch (Exception $e) {
    set_flash('error', 'Bakiye yüklenirken bir hata oluştu: ' . $e->getMessage());
}

header("Location: account.php");
exit();
