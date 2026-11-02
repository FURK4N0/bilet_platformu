<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}
require_once APP_ROOT . '/config/init.php';

if (!isset($_SESSION['user_id'])) {
    set_flash('error', 'Hesabınızı görüntülemek için lütfen giriş yapın.');
    header("Location: /login.php");
    exit();
}

$page_title = 'Hesabım & Biletlerim - Bilet Platformu';
require_once APP_ROOT . '/includes/header.php';

$user_id = $_SESSION['user_id'];

// Kullanıcı bilgilerini çek
$stmt_user = $pdo->prepare("SELECT full_name, email, balance FROM User WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Kullanıcının biletlerini çek
$stmt_tickets = $pdo->prepare(
    "SELECT 
        T.id as ticket_id, 
        T.pnr_code,
        T.status, 
        T.total_price,
        T.created_at as purchase_time,
        TR.departure_city, 
        TR.destination_city, 
        TR.departure_time,
        BC.name as company_name
     FROM Tickets T
     JOIN Trips TR ON T.trip_id = TR.id
     JOIN Bus_Company BC ON TR.company_id = BC.id
     WHERE T.user_id = ?
     ORDER BY T.created_at DESC"
);
$stmt_tickets->execute([$user_id]);
$tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

// Biletlere ait koltuk ve yolcu bilgilerini çek
$ticket_seats = [];
if (!empty($tickets)) {
    $t_ids = array_column($tickets, 'ticket_id');
    $placeholders = implode(',', array_fill(0, count($t_ids), '?'));
    $stmt_seats = $pdo->prepare("
        SELECT ticket_id, seat_number, passenger_name, passenger_tc, gender
        FROM Booked_Seats
        WHERE ticket_id IN ($placeholders)
        ORDER BY seat_number ASC
    ");
    $stmt_seats->execute($t_ids);
    foreach ($stmt_seats->fetchAll(PDO::FETCH_ASSOC) as $s_row) {
        $ticket_seats[$s_row['ticket_id']][] = $s_row;
    }
}
?>

<div class="app-container" style="padding-top: 2.5rem; padding-bottom: 4rem;">
    <?php display_flash(); ?>

    <!-- Üst Profil & Bakiye Kartı -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.75rem; box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1.25rem;">
            <div style="width: 64px; height: 64px; border-radius: var(--radius-full); background: var(--primary-light); color: var(--primary); font-size: 2rem; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                <?= mb_substr($user['full_name'], 0, 1, 'UTF-8'); ?>
            </div>
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                    <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                    📧 <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </div>

        <div style="background: var(--primary-gradient); color: #ffffff; border-radius: var(--radius-lg); padding: 1.75rem; box-shadow: var(--shadow-md); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 0.85rem; font-weight: 700; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.35rem;">
                    Kullanılabilir Hesap Bakiyesi
                </div>
                <div style="font-size: 2.25rem; font-weight: 800; letter-spacing: -0.5px;">
                    ₺<?= number_format($user['balance'], 2); ?>
                </div>
                <div style="margin-top: 0.75rem;">
                    <button type="button" onclick="toggleTopUpCard()" class="btn btn-sm" style="background: rgba(255,255,255,0.22); color: #ffffff; border: 1.5px solid rgba(255,255,255,0.5); backdrop-filter: blur(4px);">
                        💳 + Bakiye Yükle
                    </button>
                </div>
            </div>
            <div style="font-size: 3rem; opacity: 0.9;">💳</div>
        </div>
    </div>

    <!-- Bakiye Yükleme Kartı (Varsayılan olarak gizlenebilir/açılabilir) -->
    <div id="top-up-card" style="display: none; background: #ffffff; border: 2px solid #bfdbfe; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); margin-bottom: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
            <div>
                <h3 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0 0 0.25rem 0;">
                    💳 Güvenli Bakiye Yükleme (Sanal POS)
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Hesabınıza dilediğiniz tutarda anında bakiye yükleyip bilet satın alabilirsiniz.</p>
            </div>
            <button type="button" onclick="toggleTopUpCard()" style="background: transparent; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">✕</button>
        </div>

        <form action="top_up_balance.php" method="POST" id="top-up-form">
            <?= csrf_field(); ?>

            <!-- Hızlı Tutar Seçimi -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label">Yüklenecek Tutar (TL)</label>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="setTopUpAmount(100)">+ ₺100</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="setTopUpAmount(250)">+ ₺250</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="setTopUpAmount(500)">+ ₺500</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="setTopUpAmount(1000)">+ ₺1000</button>
                </div>
                <input type="number" name="amount" id="top-up-amount" class="form-control" placeholder="Örn: 500" min="10" max="10000" step="10" required style="font-size: 1.2rem; font-weight: 700; color: var(--primary);">
                <small style="color: var(--text-muted); font-size: 0.8rem;">Minimum 10 TL, maksimum 10.000 TL yükleyebilirsiniz.</small>
            </div>

            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="card_name" class="form-label">Kart Üzerindeki İsim</label>
                    <input type="text" name="card_name" id="card_name" class="form-control" placeholder="Ad Soyad" value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="card_number" class="form-label">Kart Numarası</label>
                    <input type="text" name="card_number" id="card_number" class="form-control" placeholder="5555 4444 3333 2222" maxlength="19" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="expiry" class="form-label">Son Kullanma (AA/YY)</label>
                    <input type="text" name="expiry" id="expiry" class="form-control" placeholder="12/28" maxlength="5" required>
                </div>
                <div class="form-group">
                    <label for="cvv" class="form-label">Güvenlik Kodu (CVV)</label>
                    <input type="password" name="cvv" id="cvv" class="form-control" placeholder="123" maxlength="3" required>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <span style="font-size: 0.85rem; color: var(--text-muted);">
                    🔒 256-Bit SSL ile Uçtan Uca Şifreli & 3D Secure Doğrulamalı
                </span>
                <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2rem;">
                    Bakiyeyi Hesaba Yükle →
                </button>
            </div>
        </form>
    </div>

    <script>
        function toggleTopUpCard() {
            const card = document.getElementById('top-up-card');
            if (card) {
                const isHidden = card.style.display === 'none';
                card.style.display = isHidden ? 'block' : 'none';
                if (isHidden) {
                    card.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }

        function setTopUpAmount(val) {
            const input = document.getElementById('top-up-amount');
            if (input) {
                input.value = val;
            }
        }
    </script>

    <!-- Biletlerim Tablosu -->
    <div class="section-header">
        <div>
            <h2 class="section-title">🎫 Satın Alınan Biletlerim</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">Seyahat geçmişiniz, aktif biletleriniz ve PDF indirme bağlantıları.</p>
        </div>
        <a href="/index.php" class="btn btn-sm btn-primary">➕ Yeni Bilet Al</a>
    </div>

    <?php if (empty($tickets)): ?>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 3.5rem; text-align: center; box-shadow: var(--shadow-sm);">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🎫</div>
            <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">Henüz Satın Alınmış Biletiniz Yok</h3>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">
                Popüler güzergahlardaki aktif seferleri hemen keşfedin ve yolculuğunuzu planlayın.
            </p>
            <a href="/index.php" class="btn btn-primary">Seferleri İncele →</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>PNR & Firma</th>
                        <th>Güzergah</th>
                        <th>Koltuk & Yolcu Detayları</th>
                        <th>Kalkış Zamanı</th>
                        <th>Toplam Tutar</th>
                        <th>Durum</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): 
                        $departure_timestamp = strtotime($ticket['departure_time']);
                        $can_cancel = ($ticket['status'] === 'ACTIVE') && (($departure_timestamp - time()) > 3600);
                        $is_active = ($ticket['status'] === 'ACTIVE');
                        $seats = $ticket_seats[$ticket['ticket_id']] ?? [];
                    ?>
                        <tr>
                            <td>
                                <div style="display: inline-block; background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.55rem; border-radius: 4px; font-weight: 800; font-family: monospace; font-size: 0.9rem; margin-bottom: 0.3rem;">
                                    <?= htmlspecialchars($ticket['pnr_code'] ?? 'TR-DEMO', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                    🚍 <?= htmlspecialchars($ticket['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($ticket['departure_city'], ENT_QUOTES, 'UTF-8'); ?></strong> → 
                                <strong><?= htmlspecialchars($ticket['destination_city'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <?php foreach ($seats as $s): ?>
                                        <div style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                            <span style="background: var(--bg-subtle); padding: 0.15rem 0.45rem; border-radius: 4px; font-weight: 800; border: 1px solid var(--border-color); font-size: 0.8rem;">
                                                No <?= $s['seat_number']; ?>
                                            </span>
                                            <span><?= htmlspecialchars($s['passenger_name'] ?? 'Yolcu', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span><?= ($s['gender'] ?? 'male') === 'female' ? '👩' : '👨'; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <div><?= date('d.m.Y', $departure_timestamp); ?></div>
                                <small style="color: var(--text-muted); font-weight: 600;"><?= date('H:i', $departure_timestamp); ?></small>
                            </td>
                            <td>
                                <strong style="color: var(--primary); font-size: 1.05rem;">₺<?= number_format($ticket['total_price'], 2); ?></strong>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span class="badge badge-success">● Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">● İptal</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <a href="download_ticket.php?ticket_id=<?= urlencode($ticket['ticket_id']); ?>" class="btn btn-sm btn-outline" target="_blank" title="Biletinizi PDF olarak indirin">
                                        📄 PDF
                                    </a>

                                    <?php if ($can_cancel): ?>
                                        <form method="POST" action="cancel_ticket.php" style="display: inline; margin: 0;" onsubmit="return confirm('Bu bileti iptal etmek istediğinize emin misiniz? Toplam ₺<?= number_format($ticket['total_price'], 2); ?> tutarındaki bilet ücreti anında bakiyenize iade edilecektir.');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="ticket_id" value="<?= htmlspecialchars($ticket['ticket_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger-outline">
                                                İptal Et
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>