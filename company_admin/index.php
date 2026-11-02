<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/config/init.php';

// Güvenlik Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company_admin') {
    header("Location: /index.php");
    exit();
}

$page_title = 'Firma Yönetim Paneli';
require_once APP_ROOT . '/includes/header.php';

// Firma yetkilisinin şirket ve profil bilgilerini al
$stmt_user = $pdo->prepare(
    "SELECT U.*, BC.name as company_name 
     FROM User U 
     LEFT JOIN Bus_Company BC ON U.company_id = BC.id 
     WHERE U.id = ?"
);
$stmt_user->execute([$_SESSION['user_id']]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
$company_id = $user_data['company_id'] ?? null;

if (!$company_id) {
    set_flash('error', 'Hesabınız henüz herhangi bir otobüs firmasına atanmamıştır.');
    header("Location: /index.php");
    exit();
}

// Bu şirkete ait seferleri ve satılan koltuk sayılarını listele
$stmt_trips = $pdo->prepare("
    SELECT TR.*,
        (SELECT COUNT(*) FROM Booked_Seats BS JOIN Tickets TK ON BS.ticket_id = TK.id WHERE TK.trip_id = TR.id AND TK.status = 'ACTIVE') as sold_count
    FROM Trips TR 
    WHERE TR.company_id = ? 
    ORDER BY TR.departure_time DESC
");
$stmt_trips->execute([$company_id]);
$trips = $stmt_trips->fetchAll(PDO::FETCH_ASSOC);

// Bu şirkete ait kuponları listele
$stmt_coupons = $pdo->prepare("SELECT * FROM Coupons WHERE company_id = ? ORDER BY expire_date DESC");
$stmt_coupons->execute([$company_id]);
$coupons = $stmt_coupons->fetchAll(PDO::FETCH_ASSOC);

$total_company_trips = count($trips);
$total_company_coupons = count($coupons);
?>

<div class="app-container" style="padding-top: 2rem; padding-bottom: 4rem;">
    <?php display_flash(); ?>

    <!-- Üst Başlık & Butonlar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px;">
                Firma Yönetim Paneli
            </div>
            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin-top: 0.2rem;">
                🚍 <?= htmlspecialchars($user_data['company_name'] ?? 'Firma', ENT_QUOTES, 'UTF-8'); ?>
            </h1>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="add_trip.php" class="btn btn-sm btn-primary">➕ Yeni Sefer Planla</a>
            <a href="add_coupon.php" class="btn btn-sm btn-outline">🎟️ Yeni Kupon Tanımla</a>
        </div>
    </div>

    <!-- İstatistik Sayaçları -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Toplam Sefer</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--primary); margin-top: 0.25rem;"><?= $total_company_trips; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Tanımlı Kuponlar</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;"><?= $total_company_coupons; ?></div>
        </div>
    </div>

    <!-- 1. Seferler Tablosu -->
    <div style="margin-bottom: 3rem;">
        <div class="section-header">
            <h2 class="section-title">🛣️ Planlanan Seferler</h2>
            <a href="add_trip.php" class="btn btn-sm btn-primary">Sefer Ekle</a>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Güzergah & Tip</th>
                        <th>Kalkış Zamanı</th>
                        <th>Varış Zamanı</th>
                        <th>Bilet Fiyatı</th>
                        <th>Doluluk / Kapasite</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trips)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Firmanıza ait henüz aktif sefer bulunmamaktadır.</td></tr>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 1.05rem;">
                                        <?= htmlspecialchars($trip['departure_city'], ENT_QUOTES, 'UTF-8'); ?> → <?= htmlspecialchars($trip['destination_city'], ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>
                                    <div style="margin-top: 0.25rem;">
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 4px;">
                                            🚌 <?= htmlspecialchars($trip['bus_type'] ?? '2+1', ENT_QUOTES, 'UTF-8'); ?> Otobüs
                                        </span>
                                    </div>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($trip['departure_time'])); ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($trip['arrival_time'])); ?></td>
                                <td><strong style="color: var(--primary); font-size: 1.05rem;">₺<?= number_format($trip['price'], 2); ?></strong></td>
                                <td>
                                    <?php 
                                    $sold = intval($trip['sold_count'] ?? 0);
                                    $cap = intval($trip['capacity']);
                                    $percent = $cap > 0 ? round(($sold / $cap) * 100) : 0;
                                    ?>
                                    <div style="font-weight: 700; font-size: 0.9rem; color: <?= $sold > 0 ? 'var(--primary)' : 'var(--text-muted)'; ?>;">
                                        <?= $sold; ?> / <?= $cap; ?> Dolu (%<?= $percent; ?>)
                                    </div>
                                    <div style="background: #e2e8f0; height: 6px; border-radius: 3px; width: 100px; margin-top: 0.25rem; overflow: hidden;">
                                        <div style="background: var(--primary); height: 100%; width: <?= $percent; ?>%;"></div>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                        <a href="trip_manifest.php?id=<?= urlencode($trip['id']); ?>" class="btn btn-sm btn-primary" title="Yolcu ve Koltuk Listesi">
                                            👥 Yolcular
                                        </a>
                                        <a href="edit_trip.php?id=<?= urlencode($trip['id']); ?>" class="btn btn-sm btn-outline">Düzenle</a>
                                        <form method="POST" action="delete_trip.php" style="display:inline; margin:0;" onsubmit="return confirm('Bu seferi silmek istediğinize emin misiniz?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($trip['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger-outline">Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. Kuponlar Tablosu -->
    <div>
        <div class="section-header">
            <h2 class="section-title">🎟️ Firmaya Özel İndirim Kuponları</h2>
            <a href="add_coupon.php" class="btn btn-sm btn-primary">Kupon Ekle</a>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Kupon Kodu</th>
                        <th>İndirim</th>
                        <th>Kullanım Şartları</th>
                        <th>Kalan Limit</th>
                        <th>Son Geçerlilik</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Firmanıza ait henüz kupon oluşturulmamış.</td></tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $coupon): ?>
                            <tr>
                                <td>
                                    <span style="background: var(--bg-subtle); padding: 0.25rem 0.65rem; border-radius: var(--radius-sm); font-weight: 800; color: var(--primary); border: 1px solid var(--border-color); font-family: monospace;">
                                        <?= htmlspecialchars($coupon['code'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td><strong style="color: var(--success); font-size: 1.05rem;">%<?= intval(round($coupon['discount'] * 100)); ?></strong></td>
                                <td>
                                    <div style="font-size: 0.85rem; line-height: 1.4;">
                                        <div>
                                            <span style="color: var(--text-muted);">Alt Limit:</span>
                                            <?= (!empty($coupon['min_spend']) && $coupon['min_spend'] > 0) ? '<strong>₺' . number_format($coupon['min_spend'], 2, ',', '.') . '</strong>' : '<span style="color: var(--text-muted);">Yok</span>'; ?>
                                        </div>
                                        <div>
                                            <span style="color: var(--text-muted);">Maks İndirim:</span>
                                            <?= (!empty($coupon['max_discount']) && $coupon['max_discount'] > 0) ? '<strong style="color: #ea580c;">₺' . number_format($coupon['max_discount'], 2, ',', '.') . '</strong>' : '<span style="color: var(--text-muted);">Limitsiz</span>'; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?= intval($coupon['usage_limit']); ?> Adet</td>
                                <td style="color: var(--text-muted);"><?= date('d.m.Y', strtotime($coupon['expire_date'])); ?></td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.5rem;">
                                        <a href="edit_coupon.php?id=<?= urlencode($coupon['id']); ?>" class="btn btn-sm btn-outline">Düzenle</a>
                                        <form method="POST" action="delete_coupon.php" style="display:inline; margin:0;" onsubmit="return confirm('Bu kuponu silmek istediğinize emin misiniz?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($coupon['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger-outline">Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>