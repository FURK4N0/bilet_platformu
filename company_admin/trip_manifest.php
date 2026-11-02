<?php
require_once '../config/init.php';

// Güvenlik Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company_admin') {
    header("Location: /index.php");
    exit();
}

$trip_id = $_GET['id'] ?? null;
if (!$trip_id) {
    set_flash('error', 'Görüntülenecek sefer belirtilmedi.');
    header("Location: index.php");
    exit();
}

// Şirket Yetkilisinin Şirket ID'sini al
$stmt_user = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$company_id = $stmt_user->fetchColumn();

// Sefer bilgilerini ve şirket doğrulaması yap
$stmt_trip = $pdo->prepare("
    SELECT TR.*, BC.name as company_name 
    FROM Trips TR 
    JOIN Bus_Company BC ON TR.company_id = BC.id 
    WHERE TR.id = ? AND TR.company_id = ?
");
$stmt_trip->execute([$trip_id, $company_id]);
$trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    set_flash('error', 'Sefer bulunamadı veya bu sefere erişim yetkiniz yok.');
    header("Location: index.php");
    exit();
}

// Seferdeki koltuk ve yolcu listesini çek
$stmt_passengers = $pdo->prepare("
    SELECT 
        BS.seat_number,
        BS.passenger_name,
        BS.passenger_tc,
        BS.gender,
        TK.id as ticket_id,
        TK.pnr_code,
        TK.status as ticket_status,
        TK.total_price,
        TK.created_at as purchase_date,
        U.email as buyer_email
    FROM Booked_Seats BS
    JOIN Tickets TK ON BS.ticket_id = TK.id
    JOIN User U ON TK.user_id = U.id
    WHERE TK.trip_id = ?
    ORDER BY BS.seat_number ASC
");
$stmt_passengers->execute([$trip_id]);
$passengers = $stmt_passengers->fetchAll(PDO::FETCH_ASSOC);

// Koltuk numarasına göre harita oluştur
$seat_map = [];
$active_passengers_count = 0;
$total_revenue = 0;

foreach ($passengers as $p) {
    if ($p['ticket_status'] === 'ACTIVE') {
        $seat_map[intval($p['seat_number'])] = $p;
        $active_passengers_count++;
        $total_revenue += floatval($p['total_price']);
    }
}

$capacity = intval($trip['capacity']);
$occupancy_rate = $capacity > 0 ? round(($active_passengers_count / $capacity) * 100) : 0;
$bus_type = $trip['bus_type'] ?? '2+1';

$bus_layout_data = generate_bus_layout($bus_type, $capacity);
$bus_rows        = $bus_layout_data['rows'];
$rear_seats      = $bus_layout_data['rear'];

$page_title = 'Yolcu ve Koltuk Manifestosu - ' . $trip['departure_city'] . ' - ' . $trip['destination_city'];
require_once '../includes/header.php';
?>

<div class="app-container" style="padding-top: 2rem; padding-bottom: 5rem;">
    <?php display_flash(); ?>

    <!-- Başlık & İşlem Butonları -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="/company_admin/index.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-decoration: none;">← Sefer Listesine Geri Dön</a>
            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin-top: 0.35rem; margin-bottom: 0.2rem;">
                📋 Yolcu & Koltuk Manifestosu
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                <strong><?= htmlspecialchars($trip['departure_city'], ENT_QUOTES, 'UTF-8'); ?> → <?= htmlspecialchars($trip['destination_city'], ENT_QUOTES, 'UTF-8'); ?></strong> 
                seferine ait detaylı yolcu yerleşim planı, cinsiyet dağılımı ve resmî manifesto.
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button onclick="window.print()" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem;">
                🖨️ Manifestoyu Yazdır / PDF
            </button>
            <a href="edit_trip.php?id=<?= urlencode($trip['id']); ?>" class="btn btn-outline">
                ✏️ Seferi Düzenle
            </a>
        </div>
    </div>

    <!-- Sefer Özet Metrikleri -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Kalkış Zamanı</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-top: 0.3rem;">
                <?= date('d.m.Y H:i', strtotime($trip['departure_time'])); ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Varış: <?= date('d.m.Y H:i', strtotime($trip['arrival_time'])); ?></div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Doluluk Durumu</div>
            <div style="font-size: 1.4rem; font-weight: 800; color: var(--primary); margin-top: 0.3rem;">
                <?= $active_passengers_count; ?> / <?= $capacity; ?> Koltuk
            </div>
            <div style="font-size: 0.8rem; color: var(--success); font-weight: 600; margin-top: 0.2rem;">%<?= $occupancy_rate; ?> Doluluk</div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Otobüs Tipi</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #0284c7; margin-top: 0.3rem;">
                <?= htmlspecialchars($bus_type, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                <?= $bus_type === '2+1' ? 'VIP Tekli / Çiftli' : 'Standart 2+2'; ?>
            </div>
        </div>

        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Toplam Hasılat</div>
            <div style="font-size: 1.4rem; font-weight: 800; color: var(--success); margin-top: 0.3rem;">
                ₺<?= number_format($total_revenue, 2); ?>
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Birim: ₺<?= number_format($trip['price'], 2); ?></div>
        </div>
    </div>

    <!-- OTOBÜS KOLTUK DOLULUK HARİTASI (DİK ŞASİ) -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.75rem; box-shadow: var(--shadow-sm); margin-bottom: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="font-size: 1.2rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    🚍 Koltuk Yerleşim & Doluluk Haritası
                </h3>
                <small style="color: var(--text-muted);">Yolcu detaylarını (Ad, TCKN, PNR, Cinsiyet) görmek için koltukların üzerine gelebilirsiniz.</small>
            </div>
            
            <!-- Lejant -->
            <div class="seat-legend-modern">
                <div class="legend-item">
                    <span class="legend-box" style="background: #eff6ff; border-color: #93c5fd;"></span>
                    <span>Erkek Yolcu</span>
                </div>
                <div class="legend-item">
                    <span class="legend-box" style="background: #fdf2f8; border-color: #f472b6;"></span>
                    <span>Kadın Yolcu</span>
                </div>
                <div class="legend-item">
                    <span class="legend-box" style="background: #ffffff; border-color: #cbd5e1;"></span>
                    <span>Boş Koltuk</span>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: center; padding: 1rem 0;">
            <!-- DİK OTOBÜS ÇERÇEVESİ -->
            <div class="v-coach-frame">
                <!-- Yan Aynalar -->
                <div class="v-mirror-l"></div>
                <div class="v-mirror-r"></div>

                <!-- Ön Kokpit & Şoför -->
                <div class="v-coach-front">
                    <div class="v-driver-box">
                        <div class="v-steering-icon" title="Şoför Kabini">
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
                <div class="v-cabin-rows">
                    <?php foreach ($bus_rows as $row): ?>
                        <div class="v-row">
                            <?php if ($bus_type === '2+1'): ?>
                                <!-- Sol Tekli Koltuk -->
                                <?php if (!empty($row['single'])): 
                                    $s = $row['single'];
                                    $is_s = isset($seat_map[$s]);
                                    $pi = $seat_map[$s] ?? null;
                                    $gen = $pi['gender'] ?? 'male';
                                    $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                                    $tooltip = $is_s 
                                        ? htmlspecialchars($pi['passenger_name'] . ' (' . ($gen === 'female' ? 'Kadın' : 'Erkek') . ') - TCKN: ' . $pi['passenger_tc'] . ' - PNR: ' . $pi['pnr_code']) 
                                        : 'Koltuk ' . $s . ' (Boş Tekli)';
                                ?>
                                    <div class="v-seat <?= $cls; ?>" title="<?= $tooltip; ?>">
                                        <?= $s; ?>
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
                                            $is_s = isset($seat_map[$s]);
                                            $pi = $seat_map[$s] ?? null;
                                            $gen = $pi['gender'] ?? 'male';
                                            $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                                            $tooltip = $is_s 
                                                ? htmlspecialchars($pi['passenger_name'] . ' (' . ($gen === 'female' ? 'Kadın' : 'Erkek') . ') - TCKN: ' . $pi['passenger_tc'] . ' - PNR: ' . $pi['pnr_code']) 
                                                : 'Koltuk ' . $s . ' (Boş)';
                                        ?>
                                            <div class="v-seat <?= $cls; ?>" title="<?= $tooltip; ?>">
                                                <?= $s; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Cam Kenarı Çiftli Koltuk -->
                                        <?php if (!empty($row['d2'])): 
                                            $s = $row['d2'];
                                            $is_s = isset($seat_map[$s]);
                                            $pi = $seat_map[$s] ?? null;
                                            $gen = $pi['gender'] ?? 'male';
                                            $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                                            $tooltip = $is_s 
                                                ? htmlspecialchars($pi['passenger_name'] . ' (' . ($gen === 'female' ? 'Kadın' : 'Erkek') . ') - TCKN: ' . $pi['passenger_tc'] . ' - PNR: ' . $pi['pnr_code']) 
                                                : 'Koltuk ' . $s . ' (Boş Cam Kenarı)';
                                        ?>
                                            <div class="v-seat <?= $cls; ?>" title="<?= $tooltip; ?>">
                                                <?= $s; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- 2+2 Düzeni -->
                                <div class="v-seat-pair">
                                    <?php if (!empty($row['s1'])): 
                                        $s = $row['s1'];
                                        $is_s = isset($seat_map[$s]);
                                        $gen = $seat_map[$s]['gender'] ?? 'male';
                                        $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                                    ?>
                                        <div class="v-seat <?= $cls; ?>" title="<?= $is_s ? htmlspecialchars($seat_map[$s]['passenger_name']) : 'Koltuk ' . $s; ?>"><?= $s; ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($row['s2'])): 
                                        $s = $row['s2'];
                                        $is_s = isset($seat_map[$s]);
                                        $gen = $seat_map[$s]['gender'] ?? 'male';
                                        $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                                    ?>
                                        <div class="v-seat <?= $cls; ?>" title="<?= $is_s ? htmlspecialchars($seat_map[$s]['passenger_name']) : 'Koltuk ' . $s; ?>"><?= $s; ?></div>
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
                                            $is_s = isset($seat_map[$s]);
                                            $gen = $seat_map[$s]['gender'] ?? 'male';
                                            $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                                        ?>
                                            <div class="v-seat <?= $cls; ?>" title="<?= $is_s ? htmlspecialchars($seat_map[$s]['passenger_name']) : 'Koltuk ' . $s; ?>"><?= $s; ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['s4'])): 
                                            $s = $row['s4'];
                                            $is_s = isset($seat_map[$s]);
                                            $gen = $seat_map[$s]['gender'] ?? 'male';
                                            $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                                        ?>
                                            <div class="v-seat <?= $cls; ?>" title="<?= $is_s ? htmlspecialchars($seat_map[$s]['passenger_name']) : 'Koltuk ' . $s; ?>"><?= $s; ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Arka 4'lü Sıra -->
                    <div class="v-rear-bench" title="Arka Koltuk Sırası">
                        <?php foreach ($rear_seats as $s): 
                            $is_s = isset($seat_map[$s]);
                            $pi = $seat_map[$s] ?? null;
                            $gen = $pi['gender'] ?? 'male';
                            $cls = $is_s ? ($gen === 'female' ? 'sold-female' : 'sold-male') : 'available';
                            $tooltip = $is_s 
                                ? htmlspecialchars($pi['passenger_name'] . ' (' . ($gen === 'female' ? 'Kadın' : 'Erkek') . ') - TCKN: ' . $pi['passenger_tc'] . ' - PNR: ' . $pi['pnr_code']) 
                                : 'Koltuk ' . $s . ' (Arka Sıra Boş)';
                        ?>
                            <div class="v-seat <?= $cls; ?>" title="<?= $tooltip; ?>" style="flex: 1;">
                                <?= $s; ?>
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

        <!-- Sağ: Detaylı Yolcu Manifestosu Tablosu -->
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.75rem; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-main);">
                    👥 Resmî Yolcu Seyahat Manifestosu
                </h3>
                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                    Toplam: <?= $active_passengers_count; ?> Biletli Yolcu
                </span>
            </div>

            <?php if (empty($seat_map)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); background: var(--bg-subtle); border-radius: var(--radius-md);">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">💺</div>
                    <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.25rem;">Henüz bilet satın alan yolcu bulunmuyor.</div>
                    <p style="font-size: 0.85rem; margin: 0;">Bu sefer için henüz bilet kesilmemiştir.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="custom-table" style="font-size: 0.88rem;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Koltuk</th>
                                <th>PNR Kodu</th>
                                <th>Yolcu Adı & Soyadı</th>
                                <th>Cinsiyet</th>
                                <th>T.C. Kimlik No</th>
                                <th>Bilet Tutarı</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seat_map as $seat_num => $p): 
                                $is_female = ($p['gender'] ?? '') === 'female';
                            ?>
                                <tr>
                                    <td>
                                        <span style="display: inline-block; width: 34px; height: 34px; line-height: 34px; text-align: center; background: <?= $is_female ? '#fdf2f8' : '#eff6ff'; ?>; color: <?= $is_female ? '#be185d' : '#1d4ed8'; ?>; font-weight: 800; border-radius: 8px; border: 1.5px solid <?= $is_female ? '#f472b6' : '#bfdbfe'; ?>;">
                                            <?= $seat_num; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-family: monospace; font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 0.2rem 0.5rem; border-radius: 4px;">
                                            <?= htmlspecialchars($p['pnr_code'] ?? 'TR-DEMO', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color: var(--text-main); font-size: 0.95rem;">
                                            <?= htmlspecialchars($p['passenger_name'] ?? 'Yolcu', ENT_QUOTES, 'UTF-8'); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?= $is_female ? '#fdf2f8' : '#eff6ff'; ?>; color: <?= $is_female ? '#be185d' : '#1d4ed8'; ?>; font-weight: 700;">
                                            <?= $is_female ? '👩 Kadın' : '👨 Erkek'; ?>
                                        </span>
                                    </td>
                                    <td style="font-family: monospace; color: var(--text-muted);">
                                        <?php 
                                        $tc = $p['passenger_tc'] ?? '';
                                        echo strlen($tc) === 11 ? substr($tc, 0, 3) . '*****' . substr($tc, -2) : '100*****146';
                                        ?>
                                    </td>
                                    <td>
                                        <strong style="color: var(--primary);">
                                            ₺<?= number_format($p['total_price'], 2); ?>
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
</div>

<style>
@media print {
    .app-header, .app-footer, .btn, a {
        display: none !important;
    }
    body {
        background: #ffffff !important;
    }
    .app-container {
        padding: 0 !important;
        max-width: 100% !important;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>
