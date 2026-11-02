<?php
require_once '../config/init.php';

// Güvenlik Kontrolü: Yalnızca platform admini görebilir
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$logFilePath = APP_ROOT . '/logs/activity.log';

// İşlem 1: Dosya Logunu İndir (.log dosyası olarak)
if (isset($_GET['action']) && $_GET['action'] === 'download_file') {
    if (file_exists($logFilePath) && filesize($logFilePath) > 0) {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="activity_' . date('Y-m-d_His') . '.log"');
        header('Content-Length: ' . filesize($logFilePath));
        readfile($logFilePath);
        exit();
    } else {
        set_flash('error', 'İndirilecek log dosyası henüz oluşmamış veya boş.');
        header("Location: logs.php");
        exit();
    }
}

// İşlem 2: Tüm Logları Sıfırla / Temizle (GitHub öncesi kişisel kalıntı bırakmamak için)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all_logs'])) {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Güvenlik doğrulaması başarısız oldu.');
        header("Location: logs.php");
        exit();
    }

    $pdo->exec("DELETE FROM System_Logs;");
    if (file_exists($logFilePath)) {
        @file_put_contents($logFilePath, '');
    }

    log_activity('SİSTEM', 'Yönetici tarafından tüm denetim ve dosya logları temizlendi.');
    set_flash('success', 'Tüm sistem ve dosya logları başarıyla temizlendi.');
    header("Location: logs.php");
    exit();
}

$page_title = 'Sistem Denetim Logları - Admin Paneli';
require_once '../includes/header.php';

// Filtreler
$filter_action = trim($_GET['action'] ?? '');
$search_query = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM System_Logs WHERE 1=1";
$params = [];

if (!empty($filter_action)) {
    $sql .= " AND action = ?";
    $params[] = $filter_action;
}

if (!empty($search_query)) {
    $sql .= " AND (user_email LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler
$total_logs = $pdo->query("SELECT COUNT(*) FROM System_Logs")->fetchColumn();
$today_logs = $pdo->query("SELECT COUNT(*) FROM System_Logs WHERE date(created_at) = date('now', 'localtime')")->fetchColumn();
$purchase_logs = $pdo->query("SELECT COUNT(*) FROM System_Logs WHERE action = 'BİLET_SATIN_ALMA'")->fetchColumn();
$auth_logs = $pdo->query("SELECT COUNT(*) FROM System_Logs WHERE action IN ('GİRİŞ', 'BAŞARISIZ_GİRİŞ')")->fetchColumn();

// Mevcut aksiyon listesi (Filtre açılır menüsü için)
$action_types = [
    'GİRİŞ' => 'Giriş Başarılı',
    'BAŞARISIZ_GİRİŞ' => 'Hatalı Giriş Denemesi',
    'ÇIKIŞ' => 'Çıkış Yapıldı',
    'KAYIT' => 'Yeni Kullanıcı Kaydı',
    'BİLET_SATIN_ALMA' => 'Bilet Satın Alma',
    'BİLET_İPTAL' => 'Bilet İptali & İade',
    'BAKİYE_YÜKLEME' => 'Bakiye Yükleme',
    'SEFER_EKLEME' => 'Yeni Sefer Ekleme',
    'SEFER_GÜNCELLEME' => 'Sefer Güncelleme',
    'SEFER_SİLME' => 'Sefer Silme',
    'KUPON_OLUŞTURMA' => 'Kupon Tanımlama',
    'FİRMA_YÖNETİMİ' => 'Firma Yönetimi',
    'SİSTEM' => 'Sistem Yönetimi'
];
?>

<div class="app-container" style="padding-top: 2rem; padding-bottom: 5rem;">
    <?php display_flash(); ?>

    <!-- Başlık ve İşlem Butonları -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <a href="/admin/index.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-decoration: none;">← Yönetim Paneline Geri Dön</a>
            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin-top: 0.35rem; margin-bottom: 0.25rem;">
                📜 Sistem Denetim & Güvenlik Logları (Audit Log)
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                Platform üzerindeki bilet alımları, bakiye hareketleri, kullanıcı girişleri ve kritik idari işlemler.
            </p>
        </div>
        <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
            <?php if (file_exists($logFilePath) && filesize($logFilePath) > 0): ?>
                <a href="logs.php?action=download_file" class="btn btn-sm btn-secondary" title="activity.log dosyasını bilgisayarınıza indirin">
                    📄 Log Dosyasını İndir (.log)
                </a>
            <?php endif; ?>

            <form method="POST" style="margin: 0;" onsubmit="return confirm('DİKKAT: Tüm geçmiş log kayıtları ve activity.log dosyası tamamen silinecektir. Emin misiniz?');">
                <?= csrf_field(); ?>
                <input type="hidden" name="clear_all_logs" value="1">
                <button type="submit" class="btn btn-sm btn-danger" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;">
                    🗑️ Logları Temizle
                </button>
            </form>

            <a href="logs.php" class="btn btn-sm btn-outline">🔄 Yenile</a>
        </div>
    </div>

    <!-- Gizlilik & GitHub Koruma Bilgilendirme Kartı -->
    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-md); padding: 0.9rem 1.25rem; margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.65rem; font-size: 0.88rem; color: #334155;">
            <span style="font-size: 1.25rem;">🔒</span>
            <div>
                <strong>Gizlilik & Güvenlik Koruması:</strong> Tüm günlük kayıtları <code>logs/activity.log</code> dosyasında toplanır ve <code>.gitignore</code> kurallarıyla korunarak GitHub'a <u>kesinlikle yüklenmez</u>. Bilgisayarınıza veya yerel testlerinize ait kişisel IP ve veriler repoya sızmaz.
            </div>
        </div>
        <div style="font-size: 0.8rem; font-weight: 700; color: #64748b; background: #ffffff; padding: 0.35rem 0.75rem; border-radius: 6px; border: 1px solid #cbd5e1;">
            📁 Log Dosyası: <?= file_exists($logFilePath) ? round(filesize($logFilePath) / 1024, 2) . ' KB' : 'Henüz Yok'; ?>
        </div>
    </div>

    <!-- Log İstatistik Sayaçları -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Toplam Log Kaydı</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--primary); margin-top: 0.25rem;"><?= $total_logs; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Bugünkü Hareketler</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #0284c7; margin-top: 0.25rem;"><?= $today_logs; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Bilet İşlemleri</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: var(--success); margin-top: 0.25rem;"><?= $purchase_logs; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Oturum Hareketleri</div>
            <div style="font-size: 1.75rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;"><?= $auth_logs; ?></div>
        </div>
    </div>

    <!-- Filtreleme ve Arama Çubuğu -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm);">
        <form method="GET" action="logs.php" style="display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 1rem; align-items: flex-end;">
            <div class="form-group">
                <label for="q" class="form-label">Detay veya E-posta Ara</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="E-posta, IP veya anahtar kelime..." value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group">
                <label for="action" class="form-label">İşlem Tipine Göre Filtrele</label>
                <select id="action" name="action" class="form-control">
                    <option value="">-- Tüm İşlem Tipleri --</option>
                    <?php foreach ($action_types as $act_key => $act_label): ?>
                        <option value="<?= $act_key; ?>" <?= ($filter_action === $act_key) ? 'selected' : ''; ?>>
                            <?= $act_key; ?> (<?= $act_label; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="height: 48px;">
                🔍 Filtrele
            </button>

            <?php if (!empty($filter_action) || !empty($search_query)): ?>
                <a href="logs.php" class="btn btn-secondary" style="height: 48px;">
                    Temizle
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Log Kayıtları Tablosu -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow-sm);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0;">
                Son 100 Sistem Hareketi
            </h3>
            <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                Listelenen: <?= count($logs); ?> Kayıt
            </span>
        </div>

        <?php if (empty($logs)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">📜</div>
                <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem;">Kayıtlı log bulunamadı.</div>
                <p style="font-size: 0.85rem; margin: 0;">Arama kriterlerinizi değiştirerek tekrar deneyebilirsiniz.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="custom-table" style="font-size: 0.88rem;">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Zaman Damgası</th>
                            <th style="width: 160px;">İşlem Türü</th>
                            <th style="width: 220px;">Kullanıcı / E-posta</th>
                            <th>İşlem Detayları</th>
                            <th style="width: 120px; text-align: right;">IP Adresi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): 
                            $act = $log['action'];
                            // Rozet renk belirleme
                            $badge_style = 'background: #f1f5f9; color: #475569;';
                            if (strpos($act, 'BİLET_SATIN') !== false) {
                                $badge_style = 'background: #dcfce7; color: #15803d; border: 1px solid #86efac;';
                            } elseif (strpos($act, 'BAKİYE') !== false) {
                                $badge_style = 'background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe;';
                            } elseif (strpos($act, 'İPTAL') !== false || strpos($act, 'SİLME') !== false || strpos($act, 'BAŞARISIZ') !== false) {
                                $badge_style = 'background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;';
                            } elseif (strpos($act, 'GİRİŞ') !== false || strpos($act, 'KAYIT') !== false) {
                                $badge_style = 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;';
                            } elseif (strpos($act, 'SEFER') !== false) {
                                $badge_style = 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;';
                            }
                        ?>
                            <tr>
                                <td style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;">
                                    <?= date('d.m.Y H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td>
                                    <span class="badge" style="<?= $badge_style; ?> font-weight: 800; font-size: 0.75rem; padding: 0.25rem 0.6rem; border-radius: 4px; display: inline-block;">
                                        <?= htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['user_email'])): ?>
                                        <strong style="color: var(--text-main); font-size: 0.88rem;">
                                            <?= htmlspecialchars($log['user_email'], ENT_QUOTES, 'UTF-8'); ?>
                                        </strong>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-style: italic;">Misafir / Anonim</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="color: var(--text-main); line-height: 1.4;">
                                        <?= htmlspecialchars($log['details'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right; color: var(--text-muted); font-family: monospace; font-size: 0.8rem;">
                                    <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
