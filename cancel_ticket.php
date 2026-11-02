<?php
require_once 'config/init.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    set_flash('error', 'Bu işlemi gerçekleştirmek için giriş yapmalısınız.');
    header("Location: login.php");
    exit();
}

// CSRF Güvenlik Kontrolü
if (!verify_csrf_token()) {
    set_flash('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen işlemi tekrar deneyin.');
    header("Location: account.php");
    exit();
}

$ticket_id = $_POST['ticket_id'] ?? $_GET['ticket_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$ticket_id) {
    set_flash('error', 'Geçersiz bilet numarası.');
    header("Location: account.php");
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Bileti ve sefer bilgilerini çek. Yalnızca biletin sahibi iptal edebilir.
    $stmt = $pdo->prepare(
        "SELECT 
            T.id as ticket_id, T.status, T.total_price,
            TR.departure_time
         FROM Tickets T
         JOIN Trips TR ON T.trip_id = TR.id
         WHERE T.id = ? AND T.user_id = ?"
    );
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        throw new Exception("Bilet bulunamadı veya bu bileti iptal etme yetkiniz yok.");
    }
    if ($ticket['status'] !== 'ACTIVE') {
        throw new Exception("Bu bilet zaten iptal edilmiş veya aktif değil.");
    }

    // 2. Zaman kontrolü: Sefere 1 saatten az mı kaldı?
    $departure_timestamp = strtotime($ticket['departure_time']);
    if (($departure_timestamp - time()) <= 3600) {
        throw new Exception("Seferin kalkışına 1 saatten az kaldığı için bilet iptal edilemez.");
    }

    // 3. Biletin durumunu 'CANCELLED' olarak güncelle
    $stmt_cancel = $pdo->prepare("UPDATE Tickets SET status = 'CANCELLED' WHERE id = ?");
    $stmt_cancel->execute([$ticket_id]);

    // 4. Bilet ücretini kullanıcının hesabına iade et
    $stmt_refund = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
    $stmt_refund->execute([$ticket['total_price'], $user_id]);

    $pdo->commit();

    log_activity(
        'BİLET_İPTAL', 
        "Bilet iptal edildi (Bilet ID: {$ticket_id}). ₺" . number_format($ticket['total_price'], 2) . " kullanıcı bakiyesine iade edildi."
    );

    set_flash('success', 'Biletiniz başarıyla iptal edildi ve ' . number_format($ticket['total_price'], 2) . ' TL hesabınıza iade edildi.');
    header("Location: account.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'İptal işlemi başarısız: ' . $e->getMessage());
    header("Location: account.php");
    exit();
}