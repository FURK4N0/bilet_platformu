<?php
require_once '../config/init.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Koltuk seçimi yapabilmek için lütfen giriş yapınız.'], JSON_UNESCAPED_UNICODE);
    exit();
}

$user_id = $_SESSION['user_id'];
$session_id = session_id();

// JSON veya POST verilerini oku
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? 'status';
$trip_id = trim($input['trip_id'] ?? '');
$seat_number = isset($input['seat_number']) ? intval($input['seat_number']) : 0;

if (empty($trip_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sefer ID belirtilmedi.'], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // 1. Süresi dolmuş kilitleri temizle
    $pdo->exec("DELETE FROM Seat_Locks WHERE expires_at < datetime('now')");

    // 2. Sefer kontrolü
    $stmt_trip = $pdo->prepare("SELECT id, capacity FROM Trips WHERE id = ?");
    $stmt_trip->execute([$trip_id]);
    $trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Sefer bulunamadı.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($action === 'lock') {
        if ($seat_number < 1 || $seat_number > intval($trip['capacity'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Geçersiz koltuk numarası.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // A. Koltuk satılmış mı?
        $stmt_sold = $pdo->prepare("
            SELECT COUNT(*) FROM Booked_Seats bs
            JOIN Tickets t ON bs.ticket_id = t.id
            WHERE t.trip_id = ? AND bs.seat_number = ?
        ");
        $stmt_sold->execute([$trip_id, $seat_number]);
        if ($stmt_sold->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => "Koltuk {$seat_number} zaten satılmıştır."], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // B. Koltuk başkası tarafından kilitlenmiş mi?
        $stmt_lock = $pdo->prepare("
            SELECT * FROM Seat_Locks
            WHERE trip_id = ? AND seat_number = ? AND user_id != ? AND expires_at >= datetime('now')
        ");
        $stmt_lock->execute([$trip_id, $seat_number, $user_id]);
        $existing_lock = $stmt_lock->fetch(PDO::FETCH_ASSOC);

        if ($existing_lock) {
            $rem_seconds = max(1, strtotime($existing_lock['expires_at']) - time());
            echo json_encode([
                'success' => false,
                'error' => "Koltuk {$seat_number} şu anda başka bir yolcu tarafından işlemde (Geçici Kilitli).",
                'is_locked' => true,
                'remaining_seconds' => $rem_seconds
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        // C. Kendi kilidimiz varsa süresini 10 dakika uzat, yoksa yeni kilit oluştur
        $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 dakika (600 saniye)

        $stmt_my_lock = $pdo->prepare("SELECT id FROM Seat_Locks WHERE trip_id = ? AND seat_number = ? AND user_id = ?");
        $stmt_my_lock->execute([$trip_id, $seat_number, $user_id]);
        $my_lock_id = $stmt_my_lock->fetchColumn();

        if ($my_lock_id) {
            $stmt_update = $pdo->prepare("UPDATE Seat_Locks SET expires_at = ?, session_id = ? WHERE id = ?");
            $stmt_update->execute([$expires_at, $session_id, $my_lock_id]);
        } else {
            $new_lock_id = generate_uuid();
            $stmt_insert = $pdo->prepare("
                INSERT INTO Seat_Locks (id, trip_id, seat_number, user_id, session_id, locked_at, expires_at)
                VALUES (?, ?, ?, ?, ?, datetime('now'), ?)
            ");
            $stmt_insert->execute([$new_lock_id, $trip_id, $seat_number, $user_id, $session_id, $expires_at]);
        }

        echo json_encode([
            'success' => true,
            'action' => 'locked',
            'seat_number' => $seat_number,
            'expires_at' => $expires_at,
            'duration_seconds' => 600
        ], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'unlock') {
        if ($seat_number > 0) {
            $stmt_del = $pdo->prepare("DELETE FROM Seat_Locks WHERE trip_id = ? AND seat_number = ? AND user_id = ?");
            $stmt_del->execute([$trip_id, $seat_number, $user_id]);
        } else {
            // Koltuk numarası verilmemişse kullanıcının bu seferdeki tüm kilitlerini kaldır
            $stmt_del = $pdo->prepare("DELETE FROM Seat_Locks WHERE trip_id = ? AND user_id = ?");
            $stmt_del->execute([$trip_id, $user_id]);
        }

        echo json_encode(['success' => true, 'action' => 'unlocked', 'seat_number' => $seat_number], JSON_UNESCAPED_UNICODE);
        exit();

    } elseif ($action === 'status') {
        // Seferdeki tüm aktif kilitleri çek
        $stmt_locks = $pdo->prepare("
            SELECT seat_number, user_id, expires_at
            FROM Seat_Locks
            WHERE trip_id = ? AND expires_at >= datetime('now')
        ");
        $stmt_locks->execute([$trip_id]);
        $all_locks = $stmt_locks->fetchAll(PDO::FETCH_ASSOC);

        $locked_by_others = [];
        $locked_by_me = [];

        foreach ($all_locks as $l) {
            $s_num = intval($l['seat_number']);
            $rem = max(1, strtotime($l['expires_at']) - time());
            if ($l['user_id'] === $user_id) {
                $locked_by_me[] = ['seat_number' => $s_num, 'remaining_seconds' => $rem];
            } else {
                $locked_by_others[] = ['seat_number' => $s_num, 'remaining_seconds' => $rem];
            }
        }

        echo json_encode([
            'success' => true,
            'locked_by_others' => $locked_by_others,
            'locked_by_me' => $locked_by_me
        ], JSON_UNESCAPED_UNICODE);
        exit();
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Bilinmeyen işlem.'], JSON_UNESCAPED_UNICODE);
        exit();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Sunucu hatası: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
