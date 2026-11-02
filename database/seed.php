<?php
/**
 * Kurumsal Demo Veri Tohumlama (Database Seeder)
 * Çalıştırma: php database/seed.php
 */

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/helpers.php';

echo "🚀 Veritabanı kurumsal demo verileriyle hazırlanıyor...\n";

try {
    $pdo->beginTransaction();

    // 1. Tabloları temizle
    $tables = ['System_Logs', 'User_Coupons', 'Booked_Seats', 'Tickets', 'Coupons', 'Trips', 'User', 'Bus_Company'];
    foreach ($tables as $tbl) {
        $pdo->exec("DELETE FROM {$tbl};");
    }
    echo "✓ Eski test verileri temizlendi.\n";

    // 2. Firmaları Ekle
    $companies = [
        'kamilkoc'  => ['id' => generate_uuid(), 'name' => 'Kâmil Koç'],
        'pamukkale' => ['id' => generate_uuid(), 'name' => 'Pamukkale Turizm'],
        'metro'     => ['id' => generate_uuid(), 'name' => 'Metro Turizm']
    ];

    $stmt_comp = $pdo->prepare("INSERT INTO Bus_Company (id, name) VALUES (?, ?)");
    foreach ($companies as $comp) {
        $stmt_comp->execute([$comp['id'], $comp['name']]);
    }
    echo "✓ 3 kurumsal otobüs firması eklendi.\n";

    // 3. Kullanıcıları Ekle
    // Parolalar:
    // Admin: Admin123!
    // Firma Adminler: Firma123!
    // Demo Yolcu: Yolcu123!
    $users = [
        [
            'id' => generate_uuid(),
            'full_name' => 'Sistem Yöneticisi',
            'email' => 'admin@biletplatformu.com',
            'password' => password_hash('Admin123!', PASSWORD_BCRYPT),
            'role' => 'admin',
            'company_id' => null,
            'balance' => 0.0
        ],
        [
            'id' => generate_uuid(),
            'full_name' => 'Kâmil Koç Yetkilisi',
            'email' => 'yetkili@kamilkoc.com',
            'password' => password_hash('Firma123!', PASSWORD_BCRYPT),
            'role' => 'company_admin',
            'company_id' => $companies['kamilkoc']['id'],
            'balance' => 0.0
        ],
        [
            'id' => generate_uuid(),
            'full_name' => 'Pamukkale Turizm Yetkilisi',
            'email' => 'yetkili@pamukkale.com',
            'password' => password_hash('Firma123!', PASSWORD_BCRYPT),
            'role' => 'company_admin',
            'company_id' => $companies['pamukkale']['id'],
            'balance' => 0.0
        ],
        [
            'id' => generate_uuid(),
            'full_name' => 'Ahmet Yılmaz (Demo Yolcu)',
            'email' => 'yolcu@demo.com',
            'password' => password_hash('Yolcu123!', PASSWORD_BCRYPT),
            'role' => 'user',
            'company_id' => null,
            'balance' => 2500.0
        ]
    ];

    $stmt_user = $pdo->prepare("INSERT INTO User (id, full_name, email, password, role, company_id, balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        $stmt_user->execute([$u['id'], $u['full_name'], $u['email'], $u['password'], $u['role'], $u['company_id'], $u['balance']]);
    }
    echo "✓ Kurumsal demo hesapları eklendi (Admin, 2x Firma Yetkilisi, Demo Yolcu).\n";

    // 4. Seferleri Ekle (Gelecek tarihli dinamik seferler)
    $now = time();
    $trips_data = [
        ['company' => 'kamilkoc',  'from' => 'İstanbul', 'to' => 'Ankara',  'days' => 1, 'hour' => '09:00', 'dur' => 6, 'price' => 450, 'cap' => 38, 'type' => '2+1'],
        ['company' => 'kamilkoc',  'from' => 'Ankara',  'to' => 'İstanbul', 'days' => 2, 'hour' => '14:30', 'dur' => 6, 'price' => 450, 'cap' => 38, 'type' => '2+1'],
        ['company' => 'pamukkale', 'from' => 'İstanbul', 'to' => 'İzmir',   'days' => 1, 'hour' => '10:00', 'dur' => 7, 'price' => 520, 'cap' => 38, 'type' => '2+1'],
        ['company' => 'pamukkale', 'from' => 'İzmir',   'to' => 'İstanbul', 'days' => 3, 'hour' => '16:00', 'dur' => 7, 'price' => 520, 'cap' => 38, 'type' => '2+1'],
        ['company' => 'metro',     'from' => 'Ankara',  'to' => 'Antalya',  'days' => 2, 'hour' => '22:00', 'dur' => 8, 'price' => 480, 'cap' => 46, 'type' => '2+2'],
        ['company' => 'metro',     'from' => 'Antalya', 'to' => 'Ankara',  'days' => 4, 'hour' => '11:00', 'dur' => 8, 'price' => 480, 'cap' => 46, 'type' => '2+2'],
        ['company' => 'kamilkoc',  'from' => 'İstanbul', 'to' => 'Bursa',   'days' => 1, 'hour' => '13:00', 'dur' => 2, 'price' => 260, 'cap' => 38, 'type' => '2+1'],
        ['company' => 'pamukkale', 'from' => 'Bursa',   'to' => 'İstanbul', 'days' => 5, 'hour' => '18:00', 'dur' => 2, 'price' => 260, 'cap' => 46, 'type' => '2+2']
    ];

    $stmt_trip = $pdo->prepare("INSERT INTO Trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity, bus_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $created_trips = [];
    foreach ($trips_data as $t) {
        $trip_id = generate_uuid();
        $dep_timestamp = strtotime("+{$t['days']} days {$t['hour']}", $now);
        $arr_timestamp = $dep_timestamp + ($t['dur'] * 3600);

        $dep_str = date('Y-m-d H:i:s', $dep_timestamp);
        $arr_str = date('Y-m-d H:i:s', $arr_timestamp);

        $stmt_trip->execute([
            $trip_id,
            $companies[$t['company']]['id'],
            $t['from'],
            $t['to'],
            $dep_str,
            $arr_str,
            $t['price'],
            $t['cap'],
            $t['type']
        ]);
        $created_trips[] = $trip_id;
    }
    echo "✓ 8 adet gelecek tarihli aktif sefer oluşturuldu.\n";

    // 5. İndirim Kuponlarını Ekle
    $coupons = [
        // Genel kuponlar (tüm firmalarda geçerli)
        ['code' => 'HOSGELDIN20', 'discount' => 0.20, 'company_id' => null, 'limit' => 100, 'days' => 60],
        ['code' => 'YAZ15',        'discount' => 0.15, 'company_id' => null, 'limit' => 50,  'days' => 90],
        // Firmaya özel kuponlar
        ['code' => 'KAMILKOC10',  'discount' => 0.10, 'company_id' => $companies['kamilkoc']['id'],  'limit' => 30, 'days' => 45],
        ['code' => 'PAMUKKALE10', 'discount' => 0.10, 'company_id' => $companies['pamukkale']['id'], 'limit' => 30, 'days' => 45]
    ];

    $stmt_coupon = $pdo->prepare("INSERT INTO Coupons (id, code, discount, company_id, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($coupons as $c) {
        $stmt_coupon->execute([
            generate_uuid(),
            $c['code'],
            $c['discount'],
            $c['company_id'],
            $c['limit'],
            date('Y-m-d', strtotime("+{$c['days']} days"))
        ]);
    }
    echo "✓ 4 adet indirim kuponu (2 genel, 2 firmaya özel) eklendi.\n";

    // 6. Demo Yolcu İçin 1 Örnek Bilet Satın Alımı
    $demo_user_id = $users[3]['id'];
    $sample_trip_id = $created_trips[0]; // İstanbul -> Ankara seferi
    $ticket_id = generate_uuid();
    $seat_number = 5;
    $pnr_code = generate_pnr();

    $stmt_ticket = $pdo->prepare("INSERT INTO Tickets (id, user_id, trip_id, status, total_price, pnr_code) VALUES (?, ?, ?, 'ACTIVE', 450, ?)");
    $stmt_ticket->execute([$ticket_id, $demo_user_id, $sample_trip_id, $pnr_code]);

    $stmt_seat = $pdo->prepare("INSERT INTO Booked_Seats (id, ticket_id, seat_number, passenger_name, passenger_tc, gender) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_seat->execute([generate_uuid(), $ticket_id, $seat_number, 'Ahmet Yılmaz', '10000000146', 'male']);

    echo "✓ Demo yolcu için 1 örnek bilet (PNR: {$pnr_code}, Koltuk: 5, Erkek) oluşturuldu.\n";

    $pdo->commit();
    echo "\n✨ Başarılı! Veritabanı kurumsal demo verileriyle hazırlandı.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
