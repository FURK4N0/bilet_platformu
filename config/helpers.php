<?php
/**
 * Merkezi Yardımcı Fonksiyonlar ve Güvenlik Modülleri
 */

/**
 * Kriptografik olarak güvenli UUID v4 üretir.
 */
function generate_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Aktif oturum için CSRF belirteci (token) üretir veya mevcut olanı döner.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Formlar için gizli CSRF input elemanı üretir.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Gönderilen CSRF belirtecini doğrular.
 */
function verify_csrf_token(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Tek seferlik bildirim (flash message) kaydeder.
 * @param string $type 'success', 'error', 'info', 'warning'
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Varsa flash bildirimi ekrana basar ve oturumdan temizler.
 */
function display_flash(): void {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        $colors = [
            'success' => ['bg' => '#d1e7dd', 'color' => '#0f5132', 'border' => '#badbcc'],
            'error'   => ['bg' => '#f8d7da', 'color' => '#842029', 'border' => '#f5c2c7'],
            'warning' => ['bg' => '#fff3cd', 'color' => '#664d03', 'border' => '#ffecb5'],
            'info'    => ['bg' => '#cff4fc', 'color' => '#055160', 'border' => '#b6effb']
        ];

        $style = $colors[$flash['type']] ?? $colors['info'];
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');

        echo '<div style="background-color: ' . $style['bg'] . '; color: ' . $style['color'] . '; border: 1px solid ' . $style['border'] . '; border-radius: 6px; padding: 0.85rem 1.15rem; margin: 1rem 0; font-size: 0.95rem; line-height: 1.4;">' . $message . '</div>';
    }
}

/**
 * HTML çıktıları için güvenli kaçırma (escape) fonksiyonu.
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sistem üzerinde gerçekleşen tüm kritik işlemleri System_Logs tablosuna kaydeder.
 *
 * @param string $action İşlem tipi (Örn: GİRİŞ, BİLET_SATIN_ALMA, BAKİYE_YÜKLEME, SEFER_EKLEME)
 * @param string $details Detaylı açıklama
 * @param string|null $userId İsteğe bağlı kullanıcı ID'si (null ise aktif oturumdan alınır)
 * @param string|null $userEmail İsteğe bağlı e-posta (null ise aktif oturumdan alınır)
 */
function log_activity(string $action, string $details = '', ?string $userId = null, ?string $userEmail = null): void {
    global $pdo;

    try {
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }
        if ($userEmail === null) {
            $userEmail = $_SESSION['user_email'] ?? null;
            if (!$userEmail && $userId && $pdo) {
                $stmt = $pdo->prepare("SELECT email FROM User WHERE id = ?");
                $stmt->execute([$userId]);
                $userEmail = $stmt->fetchColumn() ?: null;
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $timestamp = date('Y-m-d H:i:s');
        $logId = generate_uuid();

        // 1. Dosya Tabanlı Günlükleme (logs/activity.log)
        // Bu dosya .gitignore ile korunur ve GitHub'a yüklenmez.
        $logDir = defined('APP_ROOT') ? APP_ROOT . '/logs' : dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/activity.log';
        $userIdentifier = $userEmail ? "{$userEmail} (" . ($userId ?: 'anon') . ")" : ($userId ?: 'Misafir');
        $logLine = sprintf("[%s] [%s] [IP: %s] [User: %s] %s%s", $timestamp, $action, $ip, $userIdentifier, $details, PHP_EOL);
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

        // 2. Veritabanı Sistem Günlüğü (System_Logs)
        if ($pdo) {
            $stmtLog = $pdo->prepare("
                INSERT INTO System_Logs (id, user_id, user_email, action, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, datetime('now', 'localtime'))
            ");
            $stmtLog->execute([$logId, $userId, $userEmail, $action, $details, $ip]);
        }
    } catch (Throwable $e) {
        error_log("Logging error: " . $e->getMessage());
    }
}

/**
 * Benzersiz alfa-nümerik PNR rezervasyon kodu üretir (Örn: TR8F92K).
 */
function generate_pnr(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'TR' . $code;
}

/**
 * Türkiye Cumhuriyeti Kimlik Numarası (TCKN) resmi doğrulama algoritması.
 */
function validate_tc_no(string $tc): bool {
    $tc = trim($tc);
    if (!preg_match('/^[1-9][0-9]{10}$/', $tc)) {
        return false;
    }

    $d = array_map('intval', str_split($tc));

    $odd_sum = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
    $even_sum = $d[1] + $d[3] + $d[5] + $d[7];
    $d10 = (($odd_sum * 7) - $even_sum) % 10;
    if ($d10 < 0) {
        $d10 += 10;
    }
    if ($d10 !== $d[9]) {
        return false;
    }

    $first10_sum = array_sum(array_slice($d, 0, 10));
    if (($first10_sum % 10) !== $d[10]) {
        return false;
    }

    return true;
}

/**
 * Otobüs seferi için güneşin vuracağı yönü (sağ / sol / gece) hesaplar.
 */
function get_trip_sun_direction(string $from, string $to, string $dep_time): array {
    $hour = (int)date('H', strtotime($dep_time));
    
    // Gece seferi kontrolü (20:00 - 05:30)
    if ($hour >= 20 || $hour < 6) {
        return [
            'icon' => '🌙',
            'text' => 'Gece seferi olduğundan yolculuk boyunca güneş ışığı etkisi yoktur.',
            'side' => 'none'
        ];
    }

    $from = mb_strtolower(trim($from), 'UTF-8');
    $to = mb_strtolower(trim($to), 'UTF-8');
    $is_morning = ($hour >= 6 && $hour < 13);
    
    // Batıdan Doğuya Seyahat (Örn: İstanbul/İzmir -> Ankara)
    if (in_array($from, ['istanbul', 'i̇stanbul', 'bursa', 'izmir', 'i̇zmir']) && in_array($to, ['ankara', 'konya', 'sivas', 'kayseri', 'erzurum'])) {
        $side = 'sağ';
    }
    // Doğudan Batıya Seyahat (Örn: Ankara -> İstanbul/İzmir)
    elseif (in_array($from, ['ankara', 'konya', 'sivas', 'kayseri', 'erzurum']) && in_array($to, ['istanbul', 'i̇stanbul', 'bursa', 'izmir', 'i̇zmir'])) {
        $side = 'sol';
    }
    // Kuzeyden Güneye (Örn: İstanbul/Ankara -> Antalya/Muğla)
    elseif (in_array($to, ['antalya', 'adana', 'mersin', 'muğla', 'mugla'])) {
        $side = $is_morning ? 'sol' : 'sağ';
    }
    // Güneyden Kuzeye (Örn: Antalya -> İstanbul/Ankara)
    elseif (in_array($from, ['antalya', 'adana', 'mersin', 'muğla', 'mugla'])) {
        $side = $is_morning ? 'sağ' : 'sol';
    }
    else {
        $side = $is_morning ? 'sağ' : 'sol';
    }

    return [
        'icon' => '☀️',
        'text' => "Bu seferde güneş çoğunlukla {$side} taraftan vuracaktır.",
        'side' => $side
    ];
}

/**
 * 00:00 - 04:30 arası gece kalkışları için 'Pazartesi'yi Salı'ya bağlayan gece kalkacaktır' uyarısı üretir.
 */
function get_midnight_departure_info(string $dep_time): ?string {
    $ts = strtotime($dep_time);
    $hour = (int)date('H', $ts);
    $min = (int)date('i', $ts);
    
    if ($hour >= 0 && $hour <= 4 && ($hour < 4 || $min <= 30)) {
        $days_tr = [
            1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba',
            4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'
        ];
        
        $day_num = (int)date('N', $ts);
        $prev_day_num = $day_num === 1 ? 7 : ($day_num - 1);
        
        $prev_day = $days_tr[$prev_day_num];
        $curr_day = $days_tr[$day_num];
        
        $prev_suffix = in_array($prev_day, ['Pazartesi', 'Perşembe']) ? "'yi" : "'yı";
        if ($prev_day === 'Pazar') $prev_suffix = "'ı";
        
        $curr_suffix = in_array($curr_day, ['Salı', 'Çarşamba']) ? "'ya" : "'ye";
        if ($curr_day === 'Pazar') $curr_suffix = "'a";
        
        return "{$prev_day}{$prev_suffix} {$curr_day}{$curr_suffix} bağlayan gece kalkacaktır.";
    }
    return null;
}

/**
 * Modern dikey otobüs planı ve koltuk komşuluk haritası üretir.
 * Çiftli koltukları bölmeden, sağ tarafta tek bir orta kapı ve en arkada kesintisiz 4'lü koltuk düzeniyle eksiksiz yerleşim sağlar.
 */
function generate_bus_layout(string $bus_type, int $capacity): array {
    $rows = [];
    $adjacency_map = [];

    // Her koltuğun komşuluk listesini boş başlat
    for ($i = 1; $i <= $capacity; $i++) {
        $adjacency_map[$i] = [];
    }

    if ($bus_type === '2+1') {
        $rear_count = 4;
        $front_capacity = $capacity - $rear_count;

        // Tek bir orta kapı (Sağ tarafta, otobüsün tam ortasında)
        $door_row_idx = (int)ceil(($front_capacity / 3) / 2) + 1;
        $curr = 1;
        $r_idx = 1;

        while ($curr <= $front_capacity) {
            if ($r_idx === $door_row_idx) {
                // Tek bir orta kapı: Solda tekli koltuk, sağda orta kapı
                $s = ($curr <= $front_capacity) ? $curr++ : null;
                $rows[] = [
                    'row' => $r_idx,
                    'single' => $s,
                    'd1' => null,
                    'd2' => null,
                    'is_door' => true
                ];
                $r_idx++;
                continue;
            }

            $s  = ($curr <= $front_capacity) ? $curr++ : null;
            $d1 = ($curr <= $front_capacity) ? $curr++ : null;
            $d2 = ($curr <= $front_capacity) ? $curr++ : null;

            if ($d1 !== null && $d2 !== null) {
                $adjacency_map[$d1][] = $d2;
                $adjacency_map[$d2][] = $d1;
            }

            $rows[] = [
                'row' => $r_idx,
                'single' => $s,
                'd1' => $d1,
                'd2' => $d2,
                'is_door' => false
            ];
            $r_idx++;
        }

        // Arka 4'lü sıra (cap-3, cap-2, cap-1, cap)
        $rear_seats = range($capacity - 3, $capacity);
        for ($i = 0; $i < 3; $i++) {
            $adjacency_map[$rear_seats[$i]][] = $rear_seats[$i + 1];
            $adjacency_map[$rear_seats[$i + 1]][] = $rear_seats[$i];
        }

        return [
            'rows' => $rows,
            'rear' => $rear_seats,
            'adjacency_map' => $adjacency_map,
            'bus_type' => $bus_type,
            'capacity' => $capacity
        ];
    } else {
        // 2+2 Standart Düzeni
        $rear_count = 4;
        $front_capacity = $capacity - $rear_count;
        $door_row_idx = (int)ceil(($front_capacity / 4) / 2) + 1;
        $curr = 1;
        $r_idx = 1;

        while ($curr <= $front_capacity) {
            if ($r_idx === $door_row_idx) {
                $s1 = ($curr <= $front_capacity) ? $curr++ : null;
                $s2 = ($curr <= $front_capacity) ? $curr++ : null;
                if ($s1 && $s2) {
                    $adjacency_map[$s1][] = $s2;
                    $adjacency_map[$s2][] = $s1;
                }
                $rows[] = [
                    'row' => $r_idx,
                    's1' => $s1,
                    's2' => $s2,
                    's3' => null,
                    's4' => null,
                    'is_door' => true
                ];
                $r_idx++;
                continue;
            }

            $s1 = ($curr <= $front_capacity) ? $curr++ : null;
            $s2 = ($curr <= $front_capacity) ? $curr++ : null;
            $s3 = ($curr <= $front_capacity) ? $curr++ : null;
            $s4 = ($curr <= $front_capacity) ? $curr++ : null;

            if ($s1 && $s2) {
                $adjacency_map[$s1][] = $s2;
                $adjacency_map[$s2][] = $s1;
            }
            if ($s3 && $s4) {
                $adjacency_map[$s3][] = $s4;
                $adjacency_map[$s4][] = $s3;
            }

            $rows[] = [
                'row' => $r_idx,
                's1' => $s1,
                's2' => $s2,
                's3' => $s3,
                's4' => $s4,
                'is_door' => false
            ];
            $r_idx++;
        }

        $rear_seats = range($capacity - 3, $capacity);
        for ($i = 0; $i < 3; $i++) {
            $adjacency_map[$rear_seats[$i]][] = $rear_seats[$i + 1];
            $adjacency_map[$rear_seats[$i + 1]][] = $rear_seats[$i];
        }

        return [
            'rows' => $rows,
            'rear' => $rear_seats,
            'adjacency_map' => $adjacency_map,
            'bus_type' => $bus_type,
            'capacity' => $capacity
        ];
    }
}
