<?php
require_once 'config/init.php';

// Güvenlik: Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!isset($_SESSION['user_id'])) {
    set_flash('error', 'Bilet satın almak için lütfen önce giriş yapın.');
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$trip_id = trim($_POST['trip_id'] ?? '');
$seats_raw = $_POST['seats'] ?? [];
$passenger_names = $_POST['passenger_name'] ?? [];
$passenger_tcs = $_POST['passenger_tc'] ?? [];
$genders = $_POST['gender'] ?? [];
$coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));

// CSRF Doğrulaması
if (!verify_csrf_token()) {
    set_flash('error', 'Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.');
    header("Location: " . (!empty($trip_id) ? "buy_ticket.php?trip_id=" . urlencode($trip_id) : "index.php"));
    exit();
}

if (empty($trip_id) || empty($seats_raw) || !is_array($seats_raw)) {
    set_flash('error', 'Lütfen en az bir koltuk seçimi yapınız.');
    header("Location: " . (!empty($trip_id) ? "buy_ticket.php?trip_id=" . urlencode($trip_id) : "index.php"));
    exit();
}

// Koltukları temizle ve tamsayı yap
$selected_seats = [];
foreach ($seats_raw as $s) {
    $s_int = filter_var($s, FILTER_VALIDATE_INT);
    if ($s_int !== false && $s_int > 0) {
        $selected_seats[] = $s_int;
    }
}
$selected_seats = array_unique($selected_seats);

if (empty($selected_seats) || count($selected_seats) > 5) {
    set_flash('error', 'Lütfen 1 ile 5 arasında geçerli koltuk seçiniz.');
    header("Location: buy_ticket.php?trip_id=" . urlencode($trip_id));
    exit();
}

try {
    // Sefer bilgilerini çek
    $stmt_trip = $pdo->prepare("SELECT * FROM Trips WHERE id = ?");
    $stmt_trip->execute([$trip_id]);
    $trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        set_flash('error', 'Seçilen sefer bulunamadı.');
        header("Location: index.php");
        exit();
    }

    $capacity = intval($trip['capacity']);
    $bus_type = $trip['bus_type'] ?? '2+1';

    // Sefer tarihi geçmiş mi kontrolü
    if (strtotime($trip['departure_time']) <= time()) {
        set_flash('error', 'Bu seferin kalkış saati geçtiği için bilet satın alınamaz.');
        header("Location: index.php");
        exit();
    }

    // Her koltuğun kapasite sınırlarında olduğunu ve yolcu bilgilerini doğrula
    foreach ($selected_seats as $seat) {
        if ($seat < 1 || $seat > $capacity) {
            throw new Exception("Geçersiz koltuk numarası: Koltuk {$seat} otobüs kapasitesinin dışındadır.");
        }

        $p_name = trim($passenger_names[$seat] ?? '');
        $p_tc   = trim($passenger_tcs[$seat] ?? '');
        $p_gen  = trim($genders[$seat] ?? '');

        if (empty($p_name)) {
            throw new Exception("Lütfen Koltuk {$seat} için yolcu adı ve soyadını eksiksiz giriniz.");
        }

        if (!validate_tc_no($p_tc)) {
            throw new Exception("Koltuk {$seat} için girilen T.C. Kimlik Numarası ({$p_tc}) algoritma kurallarına göre geçersizdir.");
        }

        if (!in_array($p_gen, ['male', 'female'])) {
            throw new Exception("Lütfen Koltuk {$seat} için geçerli bir cinsiyet seçiniz.");
        }
    }

    // Komşuluk Haritası (Cinsiyet Yan Yana Oturma Kuralı)
    $adjacency_map = [];
    $rear_limit = max(1, $capacity - 4);

    if ($bus_type === '2+1') {
        $rows = ceil($rear_limit / 3);
        for ($r = 0; $r < $rows; $r++) {
            $s_d1 = ($r * 3) + 2;
            $s_d2 = ($r * 3) + 3;
            if ($s_d1 <= $rear_limit && $s_d2 <= $rear_limit) {
                $adjacency_map[$s_d1] = [$s_d2];
                $adjacency_map[$s_d2] = [$s_d1];
            }
        }
    } else {
        $rows = ceil($rear_limit / 4);
        for ($r = 0; $r < $rows; $r++) {
            $s1 = ($r * 4) + 1;
            $s2 = ($r * 4) + 2;
            $s3 = ($r * 4) + 3;
            $s4 = ($r * 4) + 4;
            if ($s1 <= $rear_limit && $s2 <= $rear_limit) {
                $adjacency_map[$s1] = [$s2];
                $adjacency_map[$s2] = [$s1];
            }
            if ($s3 <= $rear_limit && $s4 <= $rear_limit) {
                $adjacency_map[$s3] = [$s4];
                $adjacency_map[$s4] = [$s3];
            }
        }
    }

    // Arka dörtlünün komşuluk ilişkisi
    if ($capacity >= 4) {
        $r1 = $capacity - 3;
        $r2 = $capacity - 2;
        $r3 = $capacity - 1;
        $r4 = $capacity;

        $adjacency_map[$r1] = [$r2];
        $adjacency_map[$r2] = [$r1, $r3];
        $adjacency_map[$r3] = [$r2, $r4];
        $adjacency_map[$r4] = [$r3];
    }

    // --- TRANSACTION BAŞLANGICI ---
    $pdo->beginTransaction();

    // 1. Seçilen koltukların daha önceden alınıp alınmadığını kontrol et
    $placeholders = implode(',', array_fill(0, count($selected_seats), '?'));
    $stmt_check_sold = $pdo->prepare("
        SELECT BS.seat_number 
        FROM Booked_Seats BS
        JOIN Tickets T ON BS.ticket_id = T.id
        WHERE T.trip_id = ? AND T.status = 'ACTIVE' AND BS.seat_number IN ($placeholders)
    ");
    $stmt_check_sold->execute(array_merge([$trip_id], $selected_seats));
    $already_sold = $stmt_check_sold->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($already_sold)) {
        throw new Exception("Seçtiğiniz (" . implode(', ', $already_sold) . ") numaralı koltuklar başka bir yolcu tarafından rezerve edilmiş.");
    }

    // 1.B. Seçilen koltukların BAŞKA bir kullanıcı tarafından kilitli (işlemde) olup olmadığını kontrol et
    $stmt_check_locked = $pdo->prepare("
        SELECT seat_number 
        FROM Seat_Locks 
        WHERE trip_id = ? AND seat_number IN ($placeholders) AND expires_at >= datetime('now') AND user_id != ?
    ");
    $stmt_check_locked->execute(array_merge([$trip_id], $selected_seats, [$user_id]));
    $locked_by_other_users = $stmt_check_locked->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($locked_by_other_users)) {
        throw new Exception("Seçtiğiniz (" . implode(', ', $locked_by_other_users) . ") numaralı koltuklar şu anda başka bir yolcu tarafından işlemde (Geçici Kilitli). Lütfen başka koltuk seçiniz.");
    }

    // 2. Cinsiyet Yan Yana Oturma Kuralını Kontrol Et
    // Halihazırda sistemde aktif satılmış tüm koltukları ve cinsiyetlerini çek
    $stmt_existing_booked = $pdo->prepare("
        SELECT BS.seat_number, BS.gender 
        FROM Booked_Seats BS
        JOIN Tickets T ON BS.ticket_id = T.id
        WHERE T.trip_id = ? AND T.status = 'ACTIVE'
    ");
    $stmt_existing_booked->execute([$trip_id]);
    $existing_seats_map = [];
    foreach ($stmt_existing_booked->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing_seats_map[intval($row['seat_number'])] = $row['gender'];
    }

    foreach ($selected_seats as $seat) {
        $my_gender = $genders[$seat];
        $neighbors = $adjacencyMap[$seat] ?? [];

        foreach ($neighbors as $n) {
            // Eğer komşu koltuk DAHA ÖNCEDEN satılmışsa
            if (isset($existing_seats_map[$n])) {
                $neighbor_gender = $existing_seats_map[$n];
                if ($neighbor_gender !== $my_gender) {
                    $gender_tr = ($neighbor_gender === 'male') ? 'Erkek' : 'Kadın';
                    throw new Exception("Koltuk {$seat}'nin yanındaki koltuk (No: {$n}) {$gender_tr} bir yolcuya aittir. Yanına farklı cinsiyette bilet alınamaz!");
                }
            }
        }
    }

    // 3. Fiyat Hesaplama ve Kupon Uygulama
    $trip_price = floatval($trip['price']);
    $base_total = $trip_price * count($selected_seats);
    $final_total = $base_total;
    $applied_coupon = null;

    if (!empty($coupon_code)) {
        $stmt_coupon = $pdo->prepare("SELECT * FROM Coupons WHERE code = ? AND expire_date >= date('now') AND usage_limit > 0");
        $stmt_coupon->execute([$coupon_code]);
        $coupon = $stmt_coupon->fetch(PDO::FETCH_ASSOC);

        if (!$coupon) {
            throw new Exception("Girdiğiniz kupon kodu geçersiz, süresi dolmuş veya kullanım limiti tükenmiş.");
        }

        if ($coupon['company_id'] !== null && $coupon['company_id'] !== $trip['company_id']) {
            throw new Exception("Bu kupon kodu yalnızca ilgili firmanın seferlerinde geçerlidir.");
        }

        $stmt_check_user_coupon = $pdo->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = ? AND user_id = ?");
        $stmt_check_user_coupon->execute([$coupon['id'], $user_id]);
        if ($stmt_check_user_coupon->fetchColumn() > 0) {
            throw new Exception("Bu indirim kuponunu daha önce kullandınız. Her kupon tek seferliktir.");
        }

        $applied_coupon = $coupon;

        // Alt Limit (Minimum Sepet Tutarı) Kontrolü
        $min_spend = floatval($coupon['min_spend'] ?? 0);
        if ($min_spend > 0 && $base_total < $min_spend) {
            throw new Exception("Bu kupon kodu en az ₺" . number_format($min_spend, 2) . " tutarındaki bilet alımlarında geçerlidir. Mevcut sepet tutarınız: ₺" . number_format($base_total, 2));
        }

        // İndirim Oranı ve Maksimum İndirim Miktarı Kontrolü
        $discount_amount = $base_total * floatval($coupon['discount']);
        $max_discount = !empty($coupon['max_discount']) ? floatval($coupon['max_discount']) : null;
        if ($max_discount !== null && $max_discount > 0 && $discount_amount > $max_discount) {
            $discount_amount = $max_discount;
        }

        $final_total = max(0, $base_total - $discount_amount);
    }

    // 4. Bakiye Kontrolü
    $stmt_user = $pdo->prepare("SELECT balance FROM User WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user_balance = floatval($stmt_user->fetchColumn());

    if ($user_balance < $final_total) {
        throw new Exception("Yetersiz bakiye. Toplam bilet tutarı: ₺" . number_format($final_total, 2) . ", Mevcut bakiyeniz: ₺" . number_format($user_balance, 2) . ".");
    }

    // 5. Kupon limitini düş ve kullanım geçmişine ekle
    if ($applied_coupon) {
        $stmt_update_coupon = $pdo->prepare("UPDATE Coupons SET usage_limit = usage_limit - 1 WHERE id = ?");
        $stmt_update_coupon->execute([$applied_coupon['id']]);

        $stmt_log_coupon = $pdo->prepare("INSERT INTO User_Coupons (id, coupon_id, user_id) VALUES (?, ?, ?)");
        $stmt_log_coupon->execute([generate_uuid(), $applied_coupon['id'], $user_id]);
    }

    // 6. Kullanıcı bakiyesini düş
    $stmt_update_balance = $pdo->prepare("UPDATE User SET balance = balance - ? WHERE id = ?");
    $stmt_update_balance->execute([$final_total, $user_id]);

    // 7. Bilet kaydı ve benzersiz PNR kodu üretimi
    $ticket_id = generate_uuid();
    $pnr_code = generate_pnr();

    $stmt_insert_ticket = $pdo->prepare("
        INSERT INTO Tickets (id, pnr_code, trip_id, user_id, status, total_price) 
        VALUES (?, ?, ?, ?, 'ACTIVE', ?)
    ");
    $stmt_insert_ticket->execute([$ticket_id, $pnr_code, $trip_id, $user_id, $final_total]);

    // 8. Koltuk kayıtlarını yolcu detaylarıyla birlikte ekle
    $stmt_insert_seat = $pdo->prepare("
        INSERT INTO Booked_Seats (id, ticket_id, seat_number, passenger_name, passenger_tc, gender)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($selected_seats as $seat) {
        $stmt_insert_seat->execute([
            generate_uuid(),
            $ticket_id,
            $seat,
            trim($passenger_names[$seat]),
            trim($passenger_tcs[$seat]),
            trim($genders[$seat])
        ]);
    }

    // 9. Satın alınan koltukların geçici kilitlerini kaldır
    $stmt_unlock = $pdo->prepare("DELETE FROM Seat_Locks WHERE trip_id = ? AND seat_number IN ($placeholders)");
    $stmt_unlock->execute(array_merge([$trip_id], $selected_seats));

    // Transaction onayla
    $pdo->commit();

    // Sistem Aktivite Logu
    log_activity(
        'BİLET_SATIN_ALMA', 
        "Bilet kesildi. PNR: {$pnr_code}, Koltuklar: (" . implode(', ', $selected_seats) . "), Güzergah: {$trip['departure_city']} → {$trip['destination_city']}, Tutar: ₺" . number_format($final_total, 2) . ($applied_coupon ? " (Kupon: {$applied_coupon['code']})" : "")
    );

    set_flash('success', "🎉 Tebrikler! Biletiniz başarıyla oluşturuldu. <strong>PNR Kodunuz: {$pnr_code}</strong>. Koltuklar: " . implode(', ', $selected_seats));
    header("Location: account.php");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
    header("Location: buy_ticket.php?trip_id=" . urlencode($trip_id));
    exit();
}