<?php
/**
 * Veritabanı v3 Migrasyonu:
 * - Tickets tablosuna pnr_code kolonu ve benzersiz indeks ekler.
 * - Booked_Seats tablosuna passenger_name, passenger_tc, gender kolonlarını ekler.
 * - Mevcut biletlere PNR üretir ve koltuk cinsiyetlerini atar.
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/helpers.php';

echo "🚀 Veritabanı v3 migrasyonu başlatılıyor...\n";

try {
    // 1. Tickets tablosunda pnr_code kontrolü
    $columns_tickets = $pdo->query("PRAGMA table_info(Tickets)")->fetchAll(PDO::FETCH_ASSOC);
    $has_pnr = false;
    foreach ($columns_tickets as $col) {
        if ($col['name'] === 'pnr_code') {
            $has_pnr = true;
            break;
        }
    }

    if (!$has_pnr) {
        $pdo->exec("ALTER TABLE Tickets ADD COLUMN pnr_code TEXT;");
        echo "✓ Tickets tablosuna 'pnr_code' kolonu eklendi.\n";
    }

    // 2. Booked_Seats tablosunda yeni kolonlar kontrolü
    $columns_seats = $pdo->query("PRAGMA table_info(Booked_Seats)")->fetchAll(PDO::FETCH_ASSOC);
    $seat_col_names = array_column($columns_seats, 'name');

    if (!in_array('passenger_name', $seat_col_names)) {
        $pdo->exec("ALTER TABLE Booked_Seats ADD COLUMN passenger_name TEXT;");
        echo "✓ Booked_Seats tablosuna 'passenger_name' eklendi.\n";
    }
    if (!in_array('passenger_tc', $seat_col_names)) {
        $pdo->exec("ALTER TABLE Booked_Seats ADD COLUMN passenger_tc TEXT;");
        echo "✓ Booked_Seats tablosuna 'passenger_tc' eklendi.\n";
    }
    if (!in_array('gender', $seat_col_names)) {
        $pdo->exec("ALTER TABLE Booked_Seats ADD COLUMN gender TEXT DEFAULT 'male';");
        echo "✓ Booked_Seats tablosuna 'gender' eklendi.\n";
    }

    // 3. Mevcut PNR'ı olmayan biletlere PNR ata
    $tickets = $pdo->query("SELECT id FROM Tickets WHERE pnr_code IS NULL OR pnr_code = ''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt_up_pnr = $pdo->prepare("UPDATE Tickets SET pnr_code = ? WHERE id = ?");
    foreach ($tickets as $t) {
        $pnr = 'TR' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        $stmt_up_pnr->execute([$pnr, $t['id']]);
    }
    echo "✓ Mevcut biletlerin PNR kodları güncellendi.\n";

    // PNR İndeksi
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_tickets_pnr ON Tickets(pnr_code);");
    echo "✓ PNR indeksi oluşturuldu.\n";

    // 4. Mevcut Booked_Seats kayıtlarını kullanıcı adlarıyla eşleştir
    $pdo->exec("
        UPDATE Booked_Seats 
        SET passenger_name = (
            SELECT U.full_name FROM Tickets T JOIN User U ON T.user_id = U.id WHERE T.id = Booked_Seats.ticket_id
        ),
        gender = 'male',
        passenger_tc = '10000000146'
        WHERE passenger_name IS NULL;
    ");
    echo "✓ Mevcut koltuk yolcu ve cinsiyet verileri dolduruldu.\n";

    echo "🎉 Migrasyon v3 başarıyla tamamlandı!\n";

} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
