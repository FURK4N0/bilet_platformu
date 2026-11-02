<?php

$db_path = APP_ROOT . '/database.db';
$needs_init = !file_exists($db_path) || filesize($db_path) === 0;

try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    if ($needs_init && file_exists(APP_ROOT . '/database/schema.sql')) {
        $schemaSql = file_get_contents(APP_ROOT . '/database/schema.sql');
        $pdo->exec($schemaSql);
    }

    // Veritabanı boşsa (ilk deploy anında) otomatik demo verilerini tohumla
    try {
        $userCount = (int)$pdo->query("SELECT COUNT(*) FROM User")->fetchColumn();
        if ($userCount === 0) {
            auto_seed_database($pdo);
        }
    } catch (Exception $ex) {
        // Hata durumunda sessizce geç
    }
} catch (PDOException $e) {
    die("Veritabanı bağlantısı kurulamadı: " . $e->getMessage());
}

function auto_seed_database($pdo) {
    if (!function_exists('generate_uuid')) {
        function generate_uuid() {
            $data = random_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }
    }
    if (!function_exists('generate_pnr')) {
        function generate_pnr() {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $pnr = '';
            for ($i = 0; $i < 6; $i++) {
                $pnr .= $chars[random_int(0, strlen($chars) - 1)];
            }
            return $pnr;
        }
    }

    $pdo->beginTransaction();

    $companies = [
        'kamilkoc'  => ['id' => generate_uuid(), 'name' => 'Kâmil Koç'],
        'pamukkale' => ['id' => generate_uuid(), 'name' => 'Pamukkale Turizm'],
        'metro'     => ['id' => generate_uuid(), 'name' => 'Metro Turizm']
    ];

    $stmt_comp = $pdo->prepare("INSERT INTO Bus_Company (id, name) VALUES (?, ?)");
    foreach ($companies as $comp) {
        $stmt_comp->execute([$comp['id'], $comp['name']]);
    }

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
    foreach ($trips_data as $t) {
        $trip_id = generate_uuid();
        $dep_timestamp = strtotime("+{$t['days']} days {$t['hour']}", $now);
        $arr_timestamp = $dep_timestamp + ($t['dur'] * 3600);
        $stmt_trip->execute([
            $trip_id,
            $companies[$t['company']]['id'],
            $t['from'],
            $t['to'],
            date('Y-m-d H:i:s', $dep_timestamp),
            date('Y-m-d H:i:s', $arr_timestamp),
            $t['price'],
            $t['cap'],
            $t['type']
        ]);
    }

    $coupons = [
        ['code' => 'HOSGELDIN20', 'discount' => 0.20, 'company_id' => null, 'limit' => 100, 'days' => 60],
        ['code' => 'YAZ15',        'discount' => 0.15, 'company_id' => null, 'limit' => 50,  'days' => 90],
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

    $pdo->commit();
}