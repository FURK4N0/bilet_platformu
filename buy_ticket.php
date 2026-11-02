<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}
require_once APP_ROOT . '/config/init.php';

// Güvenlik: Kullanıcı giriş yapmamışsa yönlendir
if (!isset($_SESSION['user_id'])) {
    set_flash('error', 'Bilet satın alabilmek için lütfen önce giriş yapınız.');
    header("Location: /login.php");
    exit();
}

$trip_id = trim($_GET['trip_id'] ?? '');

if (empty($trip_id)) {
    set_flash('error', 'Geçersiz sefer seçimi.');
    header("Location: /index.php");
    exit();
}

$page_title = 'Koltuk Seçimi & Bilet Satın Al';
require_once APP_ROOT . '/includes/header.php';

// Sefer ve Firma Bilgilerini Getir
$stmt = $pdo->prepare("
    SELECT T.*, BC.name as company_name 
    FROM Trips T 
    JOIN Bus_Company BC ON T.company_id = BC.id 
    WHERE T.id = ?
");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    set_flash('error', 'Sefer bulunamadı veya iptal edilmiş.');
    header("Location: index.php");
    exit();
}

// Kullanıcının Mevcut Bakiyesini Getir
$stmt_user = $pdo->prepare("SELECT balance, full_name, email FROM User WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// Bu Seferde Satılmış Koltukları ve Cinsiyetlerini Getir
$stmt_booked = $pdo->prepare("
    SELECT BS.seat_number, BS.passenger_name, BS.gender 
    FROM Booked_Seats BS 
    JOIN Tickets TK ON BS.ticket_id = TK.id 
    WHERE TK.trip_id = ? AND TK.status = 'ACTIVE'
");
$stmt_booked->execute([$trip_id]);
$booked_list = $stmt_booked->fetchAll(PDO::FETCH_ASSOC);

$booked_seats = [];
foreach ($booked_list as $b) {
    $booked_seats[intval($b['seat_number'])] = [
        'gender' => $b['gender'] ?? 'male',
        'name' => $b['passenger_name'] ?? 'Yolcu'
    ];
}

// Süresi dolmuş geçici kilitleri temizle
$pdo->exec("DELETE FROM Seat_Locks WHERE expires_at < datetime('now')");

// Bu Seferdeki Aktif Koltuk Kilitlerini Getir
$stmt_locks = $pdo->prepare("
    SELECT seat_number, user_id, expires_at 
    FROM Seat_Locks 
    WHERE trip_id = ? AND expires_at >= datetime('now')
");
$stmt_locks->execute([$trip_id]);
$locks_list = $stmt_locks->fetchAll(PDO::FETCH_ASSOC);

$locked_by_others = [];
$locked_by_me = [];
foreach ($locks_list as $l) {
    $s_num = intval($l['seat_number']);
    $rem = max(1, strtotime($l['expires_at']) - time());
    if ($l['user_id'] === $_SESSION['user_id']) {
        $locked_by_me[$s_num] = $rem;
    } else {
        $locked_by_others[$s_num] = $rem;
    }
}

$capacity = intval($trip['capacity']);
$bus_type = $trip['bus_type'] ?? '2+1';

// Obilet standardında otobüs sıra yerleşimi ve komşuluk haritası
$bus_layout_data = generate_bus_layout($bus_type, $capacity);
$bus_rows        = $bus_layout_data['rows'];
$rear_seats      = $bus_layout_data['rear'];
$adjacency_map   = $bus_layout_data['adjacency_map'];

// Akıllı Yolculuk Bilgileri (Güneş Yönü ve Gece Kalkış Uyarısı)
$sun_info      = get_trip_sun_direction($trip['departure_city'], $trip['destination_city'], $trip['departure_time']);
$midnight_info = get_midnight_departure_info($trip['departure_time']);
?>

<div class="app-container" style="padding-top: 2rem; padding-bottom: 5rem;">
    <?php display_flash(); ?>

    <!-- Üst Gezinme & Sefer Başlığı Kartı -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                <span class="badge" style="background: #eff6ff; color: #1e40af; font-size: 0.9rem; padding: 0.4rem 0.85rem; font-weight: 700;">
                    🚍 <?= htmlspecialchars($trip['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">
                    <?= htmlspecialchars($bus_type, ENT_QUOTES, 'UTF-8'); ?> <?= $bus_type === '2+1' ? 'VIP Rahat' : 'Standart 2+2'; ?>
                </span>
                <button type="button" onclick="openTripDetailsModal()" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.25rem 0.65rem; font-weight: 700; border-radius: 6px;">
                    ℹ️ Sefer Detayları
                </button>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0;">
                <?= htmlspecialchars($trip['departure_city'], ENT_QUOTES, 'UTF-8'); ?> → <?= htmlspecialchars($trip['destination_city'], ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <div style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.35rem; display: flex; gap: 1.5rem; flex-wrap: wrap;">
                <span>📅 <strong><?= date('d.m.Y', strtotime($trip['departure_time'])); ?></strong></span>
                <span>🕒 Kalkış: <strong><?= date('H:i', strtotime($trip['departure_time'])); ?></strong></span>
                <span>🏁 Varış: <strong><?= date('H:i', strtotime($trip['arrival_time'])); ?></strong></span>
            </div>
        </div>

        <div style="text-align: right;">
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--primary); margin-bottom: 0.25rem;">
                ₺<?= number_format($trip['price'], 2); ?>
                <small style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">/ koltuk</small>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Kullanılabilir Bakiye: <strong style="color: var(--success);">₺<?= number_format($current_user['balance'] ?? 0, 2); ?></strong>
            </div>
        </div>
    </div>

    <!-- 2 SÜTUNLU MODERN DÜZEN: SOL (YOLCU FORMLARI & ÖDEME) | SAĞ (DİK OTOBÜS ŞASİSİ) -->
    <div class="booking-layout-grid">
        
        <!-- SOL SÜTUN: AKILLI ROZETLER, YOLCU BİLGİLERİ & ÖDEME -->
        <div class="booking-info-column">
            <!-- Akıllı Yolculuk İpuçları (Güneş Yönü & Gece Kalkış Uyarısı) -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; gap: 0.75rem;">
                <div class="sun-badge">
                    <span style="font-size: 1.35rem;"><?= $sun_info['icon']; ?></span>
                    <span><strong>Güneş Işığı Analizi:</strong> <?= htmlspecialchars($sun_info['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <?php if ($midnight_info): ?>
                    <div class="midnight-badge">
                        <span>🕒</span>
                        <span><?= htmlspecialchars($midnight_info, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Satın Alma Formu & Kartı -->
            <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm);">
                <form action="process_purchase.php" method="POST" id="purchase-form">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="trip_id" value="<?= htmlspecialchars($trip['id'], ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Yolcu Bilgi Kartları -->
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span>👤</span> Yolcu Bilgileri
                        </h3>

                        <div id="passengers-container">
                            <div id="no-seat-warning" style="text-align: center; padding: 2.5rem 1.5rem; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: var(--radius-md); color: var(--text-muted);">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💺</div>
                                <div style="font-weight: 800; font-size: 1.1rem; color: #334155;">Lütfen otobüs planından koltuk seçiniz.</div>
                                <small>Boş koltuğa tıkladığınızda bay / bayan yolcu seçim penceresi otomatik olarak açılacaktır.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Sipariş & Ödeme Alanı -->
                    <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                        <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span>💳</span> Sipariş Özeti & Ödeme
                        </h3>

                        <!-- Tutar Kartı -->
                        <div id="price-summary-box" style="display: none; background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 0.5rem;">
                                <span style="color: var(--text-muted);">Seçilen Koltuklar:</span>
                                <strong id="summary-seat-count" style="color: #1e40af;">0 Adet</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 1.35rem; font-weight: 800; color: var(--primary); border-top: 1px dashed #bfdbfe; padding-top: 0.5rem;">
                                <span>Toplam Tutar:</span>
                                <span id="summary-total-price">₺0.00</span>
                            </div>
                        </div>

                        <!-- Kupon Kodu -->
                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label for="coupon_code" class="form-label">🎟️ İndirim Kuponu (İsteğe Bağlı)</label>
                            <input type="text" name="coupon_code" id="coupon_code" class="form-control" placeholder="Örn: HOSGELDIN20" style="text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">
                            <small style="color: var(--text-muted); font-size: 0.8rem;">Geçerli kupon kodu varsa indirim anında uygulanır.</small>
                        </div>

                        <!-- Cinsiyet ve Koltuk Teyit Özeti (Yanlış alımı önleyici kutu) -->
                        <div id="confirmation-summary-box" style="display: none; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem;">
                        </div>

                        <!-- 10 Dakikalık Koltuk Kilitleme Sayacı -->
                        <div id="seat-lock-timer-box" style="display: none; background: #fffbeb; border: 1.5px solid #fde68a; border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.25rem; color: #92400e;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; gap: 0.4rem;">
                                    <span>⏱️</span> <span>Koltuk Rezervasyon Süresi:</span>
                                </span>
                                <span id="lock-countdown-badge" style="font-family: monospace; font-size: 1.15rem; font-weight: 800; background: #fef3c7; color: #b45309; padding: 0.2rem 0.6rem; border-radius: 6px; border: 1px solid #f59e0b;">
                                    10:00
                                </span>
                            </div>
                            <small style="display: block; margin-top: 0.35rem; font-size: 0.75rem; color: #b45309; line-height: 1.3;">
                                Seçtiğiniz koltuklar bu süre boyunca adınıza kilitlenmiştir. Süre bitiminde koltuklar tekrar boşa çıkar.
                            </small>
                        </div>

                        <!-- Ödeme / Satın Al Butonu -->
                        <button type="submit" id="submit-btn" class="btn btn-primary btn-block" style="padding: 1.1rem; font-size: 1.1rem; font-weight: 800; transition: all 0.25s ease;" disabled>
                            💺 Lütfen Otobüs Planından Koltuk Seçiniz
                        </button>

                        <div style="margin-top: 1rem; text-align: center;">
                            <a href="/index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.88rem; font-weight: 600;">
                                ← Sefer Listesine Geri Dön
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- SAĞ SÜTUN: DİK OTOBÜS ŞASİSİ (STICKY & KULLANICI DOSTU) -->
        <div class="booking-coach-sticky">
            <div class="v-coach-container">
                <!-- Renk Lejantı -->
                <div class="seat-legend-modern" style="margin-bottom: 1.25rem; justify-content: center; gap: 0.75rem; flex-wrap: wrap;">
                    <div class="legend-item">
                        <span class="legend-box" style="background: #eff6ff; border-color: #93c5fd;"></span>
                        <span style="font-size: 0.78rem;">Dolu (Erkek)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-box" style="background: #fdf2f8; border-color: #f472b6;"></span>
                        <span style="font-size: 0.78rem;">Dolu (Kadın)</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-box" style="background: #ffffff; border-color: #cbd5e1;"></span>
                        <span style="font-size: 0.78rem;">Boş</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-box" style="background: #dcfce7; border-color: #22c55e;"></span>
                        <span style="font-size: 0.78rem;">Seçilen</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-box" style="background: #fef3c7; border-color: #f59e0b; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem;">🔒</span>
                        <span style="font-size: 0.78rem;">İşlemde (Kilitli)</span>
                    </div>
                </div>

                <!-- DİK OTOBÜS ÇERÇEVESİ -->
                <div class="v-coach-frame">
                    <!-- Yan Aynalar -->
                    <div class="v-mirror-l"></div>
                    <div class="v-mirror-r"></div>

                    <!-- Ön Kokpit: Direksiyon & Ön Kapı -->
                    <div class="v-coach-front">
                        <div class="v-driver-box">
                            <div class="v-steering-icon" title="Şoför Kabini (Direksiyon)">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="2.5"/>
                                    <line x1="12" y1="2" x2="12" y2="9.5"/>
                                    <line x1="5.5" y1="17.5" x2="10.2" y2="13.8"/>
                                    <line x1="18.5" y1="17.5" x2="13.8" y2="13.8"/>
                                </svg>
                            </div>
                            <span style="font-size: 0.72rem; font-weight: 800; color: #475569;">KAPTAN</span>
                        </div>
                        <div class="v-front-door" title="Ön Biniş Kapısı">
                            <span>🚪</span>
                            <span>Ön Giriş</span>
                        </div>
                    </div>

                    <!-- Koltuk Sıraları -->
                    <?php
                    $get_seat_meta = function($s) use ($booked_seats, $locked_by_others) {
                        if (isset($booked_seats[$s])) {
                            $bg = $booked_seats[$s]['gender'];
                            $bn = $booked_seats[$s]['name'];
                            return [
                                'cls' => ($bg === 'female' ? 'sold-female' : 'sold-male'),
                                'title' => htmlspecialchars($bn . ' (' . ($bg === 'female' ? 'Kadın' : 'Erkek') . ')'),
                                'icon' => ''
                            ];
                        } elseif (isset($locked_by_others[$s])) {
                            return [
                                'cls' => 'locked-seat',
                                'title' => 'Koltuk ' . $s . ' başka bir yolcu tarafından işlemde (Geçici Kilitli)',
                                'icon' => '<span style="font-size:0.6rem;position:absolute;top:3px;right:4px;">🔒</span>'
                            ];
                        } else {
                            return [
                                'cls' => 'available',
                                'title' => 'Koltuk ' . $s,
                                'icon' => ''
                            ];
                        }
                    };
                    ?>
                    <div class="v-cabin-rows">
                        <?php foreach ($bus_rows as $row): ?>
                            <div class="v-row">
                                <?php if ($bus_type === '2+1'): ?>
                                    <!-- Sol Tekli Koltuk -->
                                    <?php if (!empty($row['single'])): 
                                        $s = $row['single'];
                                        $m = $get_seat_meta($s);
                                    ?>
                                        <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>">
                                            <?= $s; ?><?= $m['icon']; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="v-seat-placeholder"></div>
                                    <?php endif; ?>

                                    <!-- Orta Koridor -->
                                    <div class="v-aisle">•</div>

                                    <!-- Sağ Taraf: Çiftli Koltuklar VEYA Tek Orta Kapı -->
                                    <?php if (!empty($row['is_door'])): ?>
                                        <div class="v-door-step" title="Orta İniş Kapısı & Merdiven Boşluğu">
                                            <span>🚪</span>
                                            <span>Orta Kapı</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="v-seat-pair">
                                            <!-- Koridor Yanı Çiftli Koltuk -->
                                            <?php if (!empty($row['d1'])): 
                                                $s = $row['d1'];
                                                $m = $get_seat_meta($s);
                                            ?>
                                                <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>">
                                                    <?= $s; ?><?= $m['icon']; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Cam Kenarı Çiftli Koltuk -->
                                            <?php if (!empty($row['d2'])): 
                                                $s = $row['d2'];
                                                $m = $get_seat_meta($s);
                                            ?>
                                                <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>">
                                                    <?= $s; ?><?= $m['icon']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <!-- 2+2 Düzeni -->
                                    <div class="v-seat-pair">
                                        <?php if (!empty($row['s1'])): 
                                            $s = $row['s1'];
                                            $m = $get_seat_meta($s);
                                        ?>
                                            <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>"><?= $s; ?><?= $m['icon']; ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['s2'])): 
                                            $s = $row['s2'];
                                            $m = $get_seat_meta($s);
                                        ?>
                                            <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>"><?= $s; ?><?= $m['icon']; ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="v-aisle">•</div>

                                    <?php if (!empty($row['is_door'])): ?>
                                        <div class="v-door-step" title="Orta İniş Kapısı">
                                            <span>🚪</span>
                                            <span>Orta Kapı</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="v-seat-pair">
                                            <?php if (!empty($row['s3'])): 
                                                $s = $row['s3'];
                                                $m = $get_seat_meta($s);
                                            ?>
                                                <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>"><?= $s; ?><?= $m['icon']; ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['s4'])): 
                                                $s = $row['s4'];
                                                $m = $get_seat_meta($s);
                                            ?>
                                                <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>"><?= $s; ?><?= $m['icon']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Arka 4'lü Koltuk Sırası -->
                        <div class="v-rear-bench" title="Arka Koltuk Sırası">
                            <?php foreach ($rear_seats as $s): 
                                $m = $get_seat_meta($s);
                            ?>
                                <div class="v-seat <?= $m['cls']; ?>" id="seat-cell-<?= $s; ?>" onclick="toggleSeat(<?= $s; ?>)" title="<?= $m['title']; ?>" style="flex: 1;">
                                    <?= $s; ?><?= $m['icon']; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Arka Tampon & Stop Lambaları -->
                    <div class="v-coach-rear">
                        <div class="v-taillight"></div>
                        <span style="font-size: 0.68rem; letter-spacing: 0.5px;">ARKA MOTOR BÖLÜMÜ</span>
                        <div class="v-taillight"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- OBİLET / ENUYGUN STİLİ CİNSİYET SEÇİM MODALI -->
<div id="gender-modal" class="gender-modal-backdrop" style="display: none;">
    <div class="gender-modal-card">
        <div style="font-size: 2.75rem; margin-bottom: 0.25rem;">💺</div>
        <h3 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;" id="modal-title">
            Koltuk Seçimi
        </h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.25rem;">
            Lütfen bu koltukta seyahat edecek yolcunun cinsiyetini seçiniz:
        </p>

        <div class="gender-btn-group">
            <button type="button" class="gender-choice-card male" id="modal-btn-male" onclick="confirmGenderSelection('male')">
                <span style="font-size: 2.75rem;">👨</span>
                <span style="font-weight: 800; font-size: 1.15rem; color: #1d4ed8;">ERKEK</span>
                <span style="font-size: 0.75rem; color: #64748b;">Bay Yolcu</span>
            </button>

            <button type="button" class="gender-choice-card female" id="modal-btn-female" onclick="confirmGenderSelection('female')">
                <span style="font-size: 2.75rem;">👩</span>
                <span style="font-weight: 800; font-size: 1.15rem; color: #be185d;">KADIN</span>
                <span style="font-size: 0.75rem; color: #64748b;">Bayan Yolcu</span>
            </button>
        </div>

        <div id="modal-constraint-note" style="display: none; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 0.75rem; color: #c2410c; font-size: 0.82rem; font-weight: 600; margin-bottom: 1rem; text-align: left;">
        </div>

        <button type="button" onclick="closeGenderModal()" class="btn btn-secondary" style="width: 100%; padding: 0.75rem; font-size: 0.9rem; font-weight: 700;">
            ✕ Vazgeç / Koltuk Seçimini İptal Et
        </button>
    </div>
</div>

<!-- SEFER DETAYLARI MODALI -->
<div id="trip-details-modal" class="gender-modal-backdrop" style="display: none;">
    <div class="gender-modal-card" style="max-width: 520px; text-align: left;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
                ℹ️ Sefer Detayları & Donanım
            </h3>
            <button type="button" onclick="closeTripDetailsModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #64748b;">✕</button>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.92rem;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem;">
                <div style="font-weight: 800; color: #1e293b; margin-bottom: 0.35rem;">
                    🚍 <?= htmlspecialchars($trip['company_name'], ENT_QUOTES, 'UTF-8'); ?> • Mercedes-Benz Tourismo
                </div>
                <div style="color: var(--text-muted); font-size: 0.85rem;">
                    <?= htmlspecialchars($trip['departure_city'], ENT_QUOTES, 'UTF-8'); ?> Otogarı → <?= htmlspecialchars($trip['destination_city'], ENT_QUOTES, 'UTF-8'); ?> AŞTİ
                </div>
            </div>

            <div>
                <strong style="display: block; margin-bottom: 0.5rem; color: #334155;">✨ Araç İçi Konfor Özellikleri:</strong>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.88rem; color: #475569;">
                    <div>⚡ 220V Priz & USB Şarj</div>
                    <div>📶 Kesintisiz Ücretsiz Wi-Fi</div>
                    <div>☕ Sıcak & Soğuk İkram Servisi</div>
                    <div>📺 Koltuk Arkası Dokunmatik Ekran</div>
                    <div>❄️ Otomatik İklimlendirme / Klima</div>
                    <div>🧳 30 KG Ücretsiz Bagaj Hakkı</div>
                </div>
            </div>

            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 0.85rem; font-size: 0.85rem; color: #1e40af;">
                <strong>🛡️ İptal & Değişiklik Güvencesi:</strong>
                <div>Kalkış saatine 1 saat kalana kadar biletinizi sistem üzerinden kesintisiz iptal edebilir, tutarı bakiyenize anında geri alabilirsiniz.</div>
            </div>
        </div>

        <button type="button" onclick="closeTripDetailsModal()" class="btn btn-primary" style="width: 100%; margin-top: 1.25rem; padding: 0.75rem; font-weight: 700;">
            Anladım, Kapat
        </button>
    </div>
</div>

<script>
const tripId = <?= json_encode($trip_id); ?>;
const tripPrice = <?= floatval($trip['price']); ?>;
const bookedSeats = <?= json_encode($booked_seats); ?>;
const adjacencyMap = <?= json_encode($adjacency_map); ?>;
const loggedInUserName = <?= json_encode($current_user['full_name'] ?? ''); ?>;
let lockedByOthers = <?= json_encode($locked_by_others); ?>;

let selectedSeats = [];
let passengerData = {};
let pendingSeatNum = null;
let lockTimerInterval = null;
let lockSecondsRemaining = 0;

function toggleSeat(seatNum) {
    if (bookedSeats[seatNum]) return;

    if (lockedByOthers[seatNum]) {
        alert(`Koltuk ${seatNum} şu anda başka bir yolcu tarafından işlemde (Geçici Kilitli). Lütfen başka bir koltuk seçiniz.`);
        return;
    }

    const index = selectedSeats.indexOf(seatNum);
    if (index > -1) {
        deselectSeat(seatNum);
        return;
    }

    if (selectedSeats.length >= 5) {
        alert('Tek seferde en fazla 5 koltuk satın alabilirsiniz.');
        return;
    }

    // Komşuluk kontrolü: Yanındaki koltuk doluysa cinsiyet kısıtını yakala
    let forcedGender = null;
    const neighbors = adjacencyMap[seatNum] || [];
    for (const n of neighbors) {
        if (bookedSeats[n]) {
            forcedGender = bookedSeats[n].gender;
            break;
        }
    }

    pendingSeatNum = seatNum;
    openGenderModal(seatNum, forcedGender);
}

function openGenderModal(seatNum, forcedGender) {
    const modal = document.getElementById('gender-modal');
    const title = document.getElementById('modal-title');
    const note = document.getElementById('modal-constraint-note');
    const btnMale = document.getElementById('modal-btn-male');
    const btnFemale = document.getElementById('modal-btn-female');

    title.textContent = `Koltuk ${seatNum} - Cinsiyet Seçimi`;
    btnMale.classList.remove('disabled');
    btnMale.disabled = false;
    btnFemale.classList.remove('disabled');
    btnFemale.disabled = false;
    note.style.display = 'none';

    if (forcedGender === 'male') {
        btnFemale.classList.add('disabled');
        btnFemale.disabled = true;
        note.innerHTML = `⚠️ <strong>Yan Koltuk Kuralı:</strong> Yanındaki koltukta (${neighborsOf(seatNum)}) Erkek yolcu oturduğu için bu koltuk yalnızca <strong>ERKEK</strong> yolculara uygundur.`;
        note.style.display = 'block';
    } else if (forcedGender === 'female') {
        btnMale.classList.add('disabled');
        btnMale.disabled = true;
        note.innerHTML = `⚠️ <strong>Yan Koltuk Kuralı:</strong> Yanındaki koltukta (${neighborsOf(seatNum)}) Kadın yolcu oturduğu için bu koltuk yalnızca <strong>KADIN</strong> yolculara uygundur.`;
        note.style.display = 'block';
    }

    modal.style.display = 'flex';
}

function neighborsOf(seatNum) {
    const n = adjacencyMap[seatNum] || [];
    return n.filter(x => bookedSeats[x]).join(', ');
}

function closeGenderModal() {
    const modal = document.getElementById('gender-modal');
    modal.style.display = 'none';
    pendingSeatNum = null;
}

function confirmGenderSelection(gender) {
    if (!pendingSeatNum) return;
    const seatNum = pendingSeatNum;
    const cell = document.getElementById('seat-cell-' + seatNum);

    let forcedGender = null;
    const neighbors = adjacencyMap[seatNum] || [];
    for (const n of neighbors) {
        if (bookedSeats[n]) {
            forcedGender = bookedSeats[n].gender;
            break;
        }
    }

    selectedSeats.push(seatNum);
    passengerData[seatNum] = {
        name: selectedSeats.length === 1 ? loggedInUserName : '',
        tc: '',
        gender: gender,
        forcedGender: forcedGender
    };

    if (cell) {
        cell.classList.add('selected');
        cell.classList.remove('selected-male', 'selected-female');
        cell.classList.add('selected-' + gender);
    }

    closeGenderModal();
    renderPassengerForms();
    updateSummary();

    // 🔒 10 Dakikalık Geçici Koltuk Kilidi API İsteği
    fetch('/api/lock_seat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'lock', trip_id: tripId, seat_number: seatNum })
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success) {
            alert(res.error || 'Koltuk kilitlenemedi.');
            deselectSeat(seatNum, false);
            if (res.is_locked) {
                lockedByOthers[seatNum] = res.remaining_seconds || 600;
                if (cell) {
                    cell.className = 'v-seat locked-seat';
                    cell.title = `Koltuk ${seatNum} başka bir yolcu tarafından işlemde (Geçici Kilitli)`;
                }
            }
        } else {
            startOrRefreshTimer(res.duration_seconds || 600);
        }
    })
    .catch(err => {
        console.error('Koltuk kilitleme hatası:', err);
    });
}

function deselectSeat(seatNum, callApi = true) {
    const index = selectedSeats.indexOf(seatNum);
    if (index > -1) {
        selectedSeats.splice(index, 1);
        delete passengerData[seatNum];
    }

    const cell = document.getElementById('seat-cell-' + seatNum);
    if (cell) {
        cell.classList.remove('selected', 'selected-male', 'selected-female');
    }

    renderPassengerForms();
    updateSummary();

    // Koltuk kilidini serbest bırak
    if (callApi) {
        fetch('/api/lock_seat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'unlock', trip_id: tripId, seat_number: seatNum })
        }).catch(() => {});
    }

    if (selectedSeats.length === 0) {
        stopLockTimer();
    }
}

function startOrRefreshTimer(seconds) {
    lockSecondsRemaining = seconds;
    const timerBox = document.getElementById('seat-lock-timer-box');
    if (timerBox) timerBox.style.display = 'block';

    if (lockTimerInterval) clearInterval(lockTimerInterval);

    updateTimerDisplay();
    lockTimerInterval = setInterval(() => {
        lockSecondsRemaining--;
        if (lockSecondsRemaining <= 0) {
            clearInterval(lockTimerInterval);
            alert('⏱️ Koltuk rezervasyon süreniz (10 dakika) sona erdi. Koltuklar tekrar serbest bırakıldı.');
            while (selectedSeats.length > 0) {
                deselectSeat(selectedSeats[0], false);
            }
            refreshLockedSeats();
        } else {
            updateTimerDisplay();
        }
    }, 1000);
}

function updateTimerDisplay() {
    const badge = document.getElementById('lock-countdown-badge');
    if (!badge) return;
    const m = Math.floor(lockSecondsRemaining / 60);
    const s = lockSecondsRemaining % 60;
    badge.textContent = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
}

function stopLockTimer() {
    if (lockTimerInterval) clearInterval(lockTimerInterval);
    const timerBox = document.getElementById('seat-lock-timer-box');
    if (timerBox) timerBox.style.display = 'none';
}

function refreshLockedSeats() {
    fetch('/api/lock_seat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'status', trip_id: tripId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const newLocked = {};
            (data.locked_by_others || []).forEach(item => {
                newLocked[item.seat_number] = item.remaining_seconds;
            });
            lockedByOthers = newLocked;

            for (let i = 1; i <= <?= $capacity; ?>; i++) {
                if (bookedSeats[i] || selectedSeats.includes(i)) continue;
                const cell = document.getElementById('seat-cell-' + i);
                if (!cell) continue;

                if (lockedByOthers[i]) {
                    if (!cell.classList.contains('locked-seat')) {
                        cell.className = 'v-seat locked-seat';
                        cell.title = `Koltuk ${i} başka bir yolcu tarafından işlemde (Geçici Kilitli)`;
                    }
                } else {
                    if (cell.classList.contains('locked-seat')) {
                        cell.className = 'v-seat available';
                        cell.title = `Koltuk ${i}`;
                    }
                }
            }
        }
    })
    .catch(() => {});
}

// 8 saniyede bir diğer kullanıcıların koltuk kilitlerini senkronize et
setInterval(refreshLockedSeats, 8000);

function changeSeatGender(seatNum, newGender) {
    if (!passengerData[seatNum]) return;
    const p = passengerData[seatNum];

    if (p.forcedGender && p.forcedGender !== newGender) {
        alert(`UYARI: Yan koltuk bir ${p.forcedGender === 'male' ? 'erkek' : 'kadın'} yolcuya ait olduğu için bu koltuğun cinsiyeti değiştirilemez!`);
        return;
    }

    p.gender = newGender;

    const cell = document.getElementById('seat-cell-' + seatNum);
    if (cell) {
        cell.classList.remove('selected-male', 'selected-female');
        cell.classList.add('selected-' + newGender);
    }

    renderPassengerForms();
    updateSummary();
}

function updatePassenger(seatNum, field, value) {
    if (!passengerData[seatNum]) return;
    passengerData[seatNum][field] = value;
}

function validateGenderRules() {
    for (const seat of selectedSeats) {
        const data = passengerData[seat];
        const neighbors = adjacencyMap[seat] || [];
        for (const n of neighbors) {
            if (bookedSeats[n]) {
                if (bookedSeats[n].gender !== data.gender) {
                    alert(`UYARI: Koltuk ${seat}'nin yanındaki koltuk (${n}) bir ${bookedSeats[n].gender === 'male' ? 'erkek' : 'kadın'} yolcuya aittir. Yanına farklı cinsiyette bilet alınamaz!`);
                    data.gender = bookedSeats[n].gender;
                    renderPassengerForms();
                    updateSummary();
                    return false;
                }
            }
        }
    }
    return true;
}

function renderPassengerForms() {
    const container = document.getElementById('passengers-container');

    if (selectedSeats.length === 0) {
        container.innerHTML = `
            <div id="no-seat-warning" style="text-align: center; padding: 2.5rem 1.5rem; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: var(--radius-md); color: var(--text-muted);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💺</div>
                <div style="font-weight: 800; font-size: 1.1rem; color: #334155;">Lütfen otobüs planından koltuk seçiniz.</div>
                <small>Boş koltuğa tıkladığınızda cinsiyet seçim penceresi otomatik olarak açılacaktır.</small>
            </div>
        `;
        return;
    }

    let html = '';
    selectedSeats.forEach((seat, idx) => {
        const p = passengerData[seat];
        const isForced = p.forcedGender !== null;

        html += `
            <div style="background: #ffffff; border: 1.5px solid ${p.gender === 'male' ? '#bfdbfe' : '#fbcfe8'}; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1rem; box-shadow: var(--shadow-sm); transition: all 0.2s ease;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; border-bottom: 1px dashed #e2e8f0; padding-bottom: 0.5rem;">
                    <div style="font-weight: 800; font-size: 1.05rem; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                        <span style="background: ${p.gender === 'male' ? '#2563eb' : '#db2777'}; color: #fff; padding: 0.2rem 0.65rem; border-radius: 6px; font-size: 0.85rem;">Koltuk ${seat}</span>
                        <span>Yolcu ${idx + 1} (${p.gender === 'male' ? '👨 Erkek' : '👩 Kadın'})</span>
                    </div>
                    <button type="button" onclick="deselectSeat(${seat})" style="background: transparent; border: none; color: var(--danger); font-size: 0.85rem; font-weight: 700; cursor: pointer;">✕ Kaldır</button>
                </div>

                <input type="hidden" name="seats[]" value="${seat}">
                <input type="hidden" name="gender[${seat}]" value="${p.gender}">

                <div class="passenger-fields-grid">
                    <div class="form-group">
                        <label class="form-label">Yolcu Adı & Soyadı</label>
                        <input type="text" name="passenger_name[${seat}]" class="form-control" placeholder="Ad Soyad" value="${escapeHtml(p.name)}" required oninput="updatePassenger(${seat}, 'name', this.value)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">T.C. Kimlik Numarası</label>
                        <input type="text" name="passenger_tc[${seat}]" class="form-control" placeholder="11 Haneli TCKN" maxlength="11" pattern="[0-9]{11}" value="${escapeHtml(p.tc)}" required oninput="updatePassenger(${seat}, 'tc', this.value.replace(/\\D/g, ''))">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Seçilen Cinsiyet</label>
                    <div style="display: flex; gap: 0.75rem;">
                        <button type="button" onclick="changeSeatGender(${seat}, 'male')" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem; border-radius: 8px; font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease; border: 2px solid ${p.gender === 'male' ? '#2563eb' : '#e2e8f0'}; background: ${p.gender === 'male' ? '#eff6ff' : '#ffffff'}; color: ${p.gender === 'male' ? '#1d4ed8' : '#64748b'};" ${isForced && p.forcedGender !== 'male' ? 'disabled' : ''}>
                            👨 Erkek
                        </button>
                        <button type="button" onclick="changeSeatGender(${seat}, 'female')" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem; border-radius: 8px; font-weight: 700; font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease; border: 2px solid ${p.gender === 'female' ? '#db2777' : '#e2e8f0'}; background: ${p.gender === 'female' ? '#fdf2f8' : '#ffffff'}; color: ${p.gender === 'female' ? '#be185d' : '#64748b'};" ${isForced && p.forcedGender !== 'female' ? 'disabled' : ''}>
                            👩 Kadın
                        </button>
                    </div>
                    ${isForced ? `<small style="display: block; color: var(--primary); font-size: 0.75rem; margin-top: 0.35rem;">ℹ️ Yan koltuk ${p.forcedGender === 'male' ? 'Erkek' : 'Kadın'} yolcuya ait olduğu için cinsiyet kilitlendi.</small>` : ''}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function updateSummary() {
    const summaryBox = document.getElementById('price-summary-box');
    const seatCount = document.getElementById('summary-seat-count');
    const totalPrice = document.getElementById('summary-total-price');
    const submitBtn = document.getElementById('submit-btn');
    const confirmBox = document.getElementById('confirmation-summary-box');

    const count = selectedSeats.length;

    if (count > 0) {
        summaryBox.style.display = 'block';
        seatCount.textContent = count + ' Koltuk (' + selectedSeats.join(', ') + ')';
        const total = count * tripPrice;
        totalPrice.textContent = '₺' + total.toFixed(2);

        // Cinsiyet teyit özeti kutusu
        let confirmHtml = `
            <div style="font-weight: 800; font-size: 0.9rem; color: #1e293b; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                <span>✅ Koltuk ve Cinsiyet Seçimleriniz:</span>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
        `;
        selectedSeats.forEach(seat => {
            const p = passengerData[seat];
            const isMale = p.gender === 'male';
            confirmHtml += `
                <div style="display: flex; justify-content: space-between; align-items: center; background: ${isMale ? '#eff6ff' : '#fdf2f8'}; border: 1px solid ${isMale ? '#bfdbfe' : '#fbcfe8'}; padding: 0.45rem 0.75rem; border-radius: 6px; font-size: 0.85rem;">
                    <span style="font-weight: 700; color: ${isMale ? '#1d4ed8' : '#be185d'};">Koltuk ${seat}</span>
                    <span style="font-weight: 700; color: ${isMale ? '#1e40af' : '#9d174d'};">${isMale ? '👨 Erkek Yolcu' : '👩 Kadın Yolcu'}</span>
                </div>
            `;
        });
        confirmHtml += `
            </div>
            <small style="display: block; color: #64748b; font-size: 0.75rem; margin-top: 0.5rem;">
                ℹ️ Lütfen yolcu cinsiyetlerini kontrol ediniz. Yanlış alımları engellemek için bilet ve PNR kaydınıza bu bilgiler işlenecektir.
            </small>
        `;
        confirmBox.innerHTML = confirmHtml;
        confirmBox.style.display = 'block';

        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
        submitBtn.innerHTML = `💳 ${count} Koltuk İçin Ödemeye Geç (₺${total.toFixed(2)}) →`;
    } else {
        summaryBox.style.display = 'none';
        confirmBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
        submitBtn.innerHTML = '💺 Lütfen Otobüs Planından Koltuk Seçiniz';
    }
}

function openTripDetailsModal() {
    document.getElementById('trip-details-modal').style.display = 'flex';
}

function closeTripDetailsModal() {
    document.getElementById('trip-details-modal').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

document.getElementById('purchase-form').addEventListener('submit', function(e) {
    if (selectedSeats.length === 0) {
        e.preventDefault();
        alert('Lütfen en az 1 koltuk seçiniz.');
        return;
    }

    for (const seat of selectedSeats) {
        const name = (passengerData[seat].name || '').trim();
        if (!name) {
            e.preventDefault();
            alert(`Lütfen Koltuk ${seat} için Yolcu Adı ve Soyadı giriniz.`);
            return;
        }
        const tc = (passengerData[seat].tc || '').trim();
        if (!tc || tc.length !== 11 || !/^[1-9][0-9]{10}$/.test(tc)) {
            e.preventDefault();
            alert(`Lütfen Koltuk ${seat} için 11 haneli geçerli T.C. Kimlik Numarası giriniz.`);
            return;
        }
    }

    if (!validateGenderRules()) {
        e.preventDefault();
        return;
    }

    const summaryLines = selectedSeats.map(s => `• Koltuk ${s}: ${passengerData[s].gender === 'female' ? 'Kadın (Bayan)' : 'Erkek (Bay)'} - ${passengerData[s].name}`).join('\n');
    const isApproved = confirm(`Bilet Satın Alma Özeti:\n\n${summaryLines}\n\nCinsiyet ve yolcu bilgileri doğru mu? Satın almayı onaylıyor musunuz?`);
    if (!isApproved) {
        e.preventDefault();
        return;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>