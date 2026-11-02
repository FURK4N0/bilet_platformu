<?php
$page_title = 'Sistem Yönetim Paneli - Bilet Platformu';
require_once '../includes/header.php';

// Güvenlik Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

// İstatistikler
$total_companies = $pdo->query("SELECT COUNT(*) FROM Bus_Company")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM User WHERE role = 'company_admin'")->fetchColumn();
$total_coupons = $pdo->query("SELECT COUNT(*) FROM Coupons WHERE company_id IS NULL")->fetchColumn();
$total_tickets = $pdo->query("SELECT COUNT(*) FROM Tickets WHERE status = 'ACTIVE'")->fetchColumn();
$total_logs = $pdo->query("SELECT COUNT(*) FROM System_Logs")->fetchColumn();

// Firma listesini çek
$stmt_companies = $pdo->query("SELECT * FROM Bus_Company ORDER BY name");
$companies = $stmt_companies->fetchAll(PDO::FETCH_ASSOC);

// Firma Admin kullanıcılarını çek
$stmt_company_admins = $pdo->query(
    "SELECT U.id, U.full_name, U.email, BC.name as company_name
     FROM User U
     LEFT JOIN Bus_Company BC ON U.company_id = BC.id
     WHERE U.role = 'company_admin'
     ORDER BY U.full_name"
);
$company_admins = $stmt_company_admins->fetchAll(PDO::FETCH_ASSOC);

// Genel kuponları listele
$stmt_global_coupons = $pdo->query("SELECT * FROM Coupons WHERE company_id IS NULL ORDER BY expire_date DESC");
$global_coupons = $stmt_global_coupons->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="app-container" style="padding-top: 2rem; padding-bottom: 4rem;">
    <?php display_flash(); ?>

    <!-- Başlık ve Hızlı Eylemler -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">
                🛡️ Sistem Yönetim Paneli
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">
                Otobüs firmaları, şirket yöneticileri ve platform genel kuponlarını buradan yönetebilirsiniz.
            </p>
        </div>
        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <a href="logs.php" class="btn btn-sm btn-outline" style="border-color: #64748b; color: #334155;">📜 Sistem Logları</a>
            <a href="add_company.php" class="btn btn-sm btn-primary">➕ Yeni Firma</a>
            <a href="add_company_admin.php" class="btn btn-sm btn-secondary">👤 Yeni Firma Admin</a>
            <a href="add_global_coupon.php" class="btn btn-sm btn-outline">🎟️ Yeni Kupon</a>
        </div>
    </div>

    <!-- İstatistik Sayaçları -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Toplam Firma</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--primary); margin-top: 0.25rem;"><?= $total_companies; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Firma Yöneticileri</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #0284c7; margin-top: 0.25rem;"><?= $total_admins; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Genel Kuponlar</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;"><?= $total_coupons; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Aktif Satılan Bilet</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--success); margin-top: 0.25rem;"><?= $total_tickets; ?></div>
        </div>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Denetim Logları</div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #64748b; margin-top: 0.25rem;">
                <a href="logs.php" style="color: inherit; text-decoration: none;"><?= $total_logs; ?></a>
            </div>
        </div>
    </div>

    <!-- 1. Firmalar Tablosu -->
    <div style="margin-bottom: 3rem;">
        <div class="section-header">
            <h2 class="section-title">🏢 Otobüs Firmaları</h2>
            <a href="add_company.php" class="btn btn-sm btn-primary">Firma Ekle</a>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Firma Adı</th>
                        <th>Kayıt Tarihi</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($companies)): ?>
                        <tr><td colspan="3" style="text-align: center; color: var(--text-muted);">Sistemde kayıtlı otobüs firması bulunmamaktadır.</td></tr>
                    <?php else: ?>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><strong style="font-size: 1.05rem;">🚍 <?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td style="color: var(--text-muted);"><?= date('d.m.Y H:i', strtotime($company['created_at'])); ?></td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.5rem;">
                                        <a href="edit_company.php?id=<?= urlencode($company['id']); ?>" class="btn btn-sm btn-outline">Düzenle</a>
                                        <form method="POST" action="delete_company.php" style="display:inline; margin:0;" onsubmit="return confirm('Bu firmayı ve ilişkili tüm seferlerini silmek istediğinize emin misiniz?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($company['id'], ENT_QUOTES, 'UTF-8'); ?>">
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

    <!-- 2. Firma Yöneticileri Tablosu -->
    <div style="margin-bottom: 3rem;">
        <div class="section-header">
            <h2 class="section-title">👥 Firma Yöneticileri</h2>
            <a href="add_company_admin.php" class="btn btn-sm btn-primary">Yönetici Ekle</a>
        </div>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Atanan Firma</th>
                        <th style="text-align: right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($company_admins)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">Sistemde kayıtlı Firma Yöneticisi bulunmamaktadır.</td></tr>
                    <?php else: ?>
                        <?php foreach ($company_admins as $ca): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ca['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                <td style="color: var(--text-muted);"><?= htmlspecialchars($ca['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge" style="background: #f1f5f9; color: #1e293b;">
                                        <?= htmlspecialchars($ca['company_name'] ?? 'Atanmamış', ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.5rem;">
                                        <a href="edit_company_admin.php?id=<?= urlencode($ca['id']); ?>" class="btn btn-sm btn-outline">Düzenle</a>
                                        <form method="POST" action="delete_company_admin.php" style="display:inline; margin:0;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?= htmlspecialchars($ca['id'], ENT_QUOTES, 'UTF-8'); ?>">
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

    <!-- 3. Genel Kuponlar Tablosu -->
    <div>
        <div class="section-header">
            <h2 class="section-title">🎟️ Genel İndirim Kuponları</h2>
            <a href="add_global_coupon.php" class="btn btn-sm btn-primary">Kupon Ekle</a>
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
                    <?php if (empty($global_coupons)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Sistemde genel kupon bulunmamaktadır.</td></tr>
                    <?php else: ?>
                        <?php foreach ($global_coupons as $coupon): ?>
                            <tr>
                                <td>
                                    <span style="background: var(--bg-subtle); padding: 0.25rem 0.65rem; border-radius: var(--radius-sm); font-weight: 800; color: var(--primary); border: 1px solid var(--border-color); font-family: monospace; font-size: 0.95rem;">
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
                                        <a href="edit_global_coupon.php?id=<?= urlencode($coupon['id']); ?>" class="btn btn-sm btn-outline">Düzenle</a>
                                        <form method="POST" action="delete_global_coupon.php" style="display:inline; margin:0;" onsubmit="return confirm('Bu kuponu silmek istediğinize emin misiniz?');">
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