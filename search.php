<?php
$page_title = 'Arama Sonuçları - Bilet Platformu';
require_once 'includes/header.php';

$departure = trim($_GET['departure'] ?? '');
$arrival = trim($_GET['arrival'] ?? '');
$date = trim($_GET['date'] ?? '');

$trips = [];
if (!empty($departure) && !empty($arrival)) {
    $sql = "SELECT T.*, BC.name as company_name,
                (T.capacity - (SELECT COUNT(*) FROM Booked_Seats BS JOIN Tickets TK ON BS.ticket_id = TK.id WHERE TK.trip_id = T.id AND TK.status = 'ACTIVE')) as available_seats
          FROM Trips T 
          JOIN Bus_Company BC ON T.company_id = BC.id
          WHERE T.departure_city LIKE ? 
            AND T.destination_city LIKE ?
            AND T.departure_time > datetime('now', 'localtime')";
    $params = ["%$departure%", "%$arrival%"];

    if (!empty($date)) {
        $sql .= " AND date(T.departure_time) = date(?)";
        $params[] = $date;
    }

    $sql .= " ORDER BY T.departure_time ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="app-container" style="padding-top: 2rem; padding-bottom: 4rem;">
    <?php display_flash(); ?>

    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                <?= htmlspecialchars($departure, ENT_QUOTES, 'UTF-8'); ?> → <?= htmlspecialchars($arrival, ENT_QUOTES, 'UTF-8'); ?> Seferleri
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                Toplam <strong><?= count($trips); ?></strong> adet uygun sefer bulundu. <?= !empty($date) ? ' (Tarih: ' . date('d.m.Y', strtotime($date)) . ')' : ''; ?>
            </p>
        </div>
        <div>
            <a href="/index.php" class="btn btn-sm btn-outline">🔄 Yeni Arama Yap</a>
        </div>
    </div>

    <?php if (empty($departure) || empty($arrival)): ?>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3rem; text-align: center;">
            <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 1.5rem;">Lütfen arama yapmak için kalkış ve varış şehirlerini belirtin.</p>
            <a href="/index.php" class="btn btn-primary">Ana Sayfaya Dön</a>
        </div>
    <?php elseif (empty($trips)): ?>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3.5rem; text-align: center;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">Üzgünüz, Uygun Sefer Bulunamadı</h3>
            <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 450px; margin: 0 auto 1.5rem auto;">
                Belirttiğiniz güzergahta yaklaşan aktif sefer bulunmuyor. Farklı tarihler veya alternatif güzergahlar aramayı deneyebilirsiniz.
            </p>
            <a href="/index.php" class="btn btn-primary">Farklı Sefer Ara</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php foreach ($trips as $trip): 
                $is_full = ($trip['available_seats'] <= 0);
                $dep_time = strtotime($trip['departure_time']);
                $arr_time = strtotime($trip['arrival_time']);
                $duration_hours = round(($arr_time - $dep_time) / 3600, 1);
            ?>
                <div class="trip-search-item">
                    <!-- Firma ve Süre Bilgisi -->
                    <div class="trip-search-company">
                        <div class="trip-search-company-name">
                            <span>🚍</span>
                            <span><?= htmlspecialchars($trip['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="trip-search-badges">
                            <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                                <?= htmlspecialchars($trip['bus_type'] ?? '2+1', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="badge" style="background: #f1f5f9; color: #475569;">
                                ⏱️ <?= $duration_hours; ?> Saat
                            </span>
                        </div>
                    </div>

                    <!-- Saatler ve Güzergah Çizgisi -->
                    <div class="trip-search-route">
                        <div class="trip-search-stop">
                            <div class="trip-search-time"><?= date('H:i', $dep_time); ?></div>
                            <div class="trip-search-city"><?= htmlspecialchars($trip['departure_city'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <small class="trip-search-date"><?= date('d.m.Y', $dep_time); ?></small>
                        </div>

                        <div class="trip-search-arrow-box">
                            <span class="trip-search-duration-pill"><?= $duration_hours; ?>s</span>
                            <div class="trip-search-arrow-line">
                                <span style="opacity: 0.5;">──</span>
                                <span>🚌</span>
                                <span style="opacity: 0.8;">──▶</span>
                            </div>
                            <span class="trip-search-direct" style="font-size: 0.72rem; color: var(--success); font-weight: 700;">Direkt</span>
                        </div>

                        <div class="trip-search-stop">
                            <div class="trip-search-time"><?= date('H:i', $arr_time); ?></div>
                            <div class="trip-search-city"><?= htmlspecialchars($trip['destination_city'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <small class="trip-search-date"><?= date('d.m.Y', $arr_time); ?></small>
                        </div>
                    </div>

                    <!-- Fiyat ve Buton -->
                    <div class="trip-search-pricing">
                        <div class="trip-search-price-row">
                            <div class="trip-search-price">
                                ₺<?= number_format($trip['price'], 2); ?>
                            </div>
                            <div class="trip-search-seats" style="color: <?= $is_full ? 'var(--danger)' : 'var(--success)'; ?>;">
                                <?= $is_full ? '❌ Kontenjan Dolu' : '✅ ' . $trip['available_seats'] . ' Koltuk Kaldı'; ?>
                            </div>
                        </div>

                        <?php if ($is_full): ?>
                            <button class="btn btn-secondary btn-block" disabled>Tükendi</button>
                        <?php else: ?>
                            <a href="/buy_ticket.php?trip_id=<?= urlencode($trip['id']); ?>" class="btn btn-primary btn-block">
                                Koltuk Seç →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>