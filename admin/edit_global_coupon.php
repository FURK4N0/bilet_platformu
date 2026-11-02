<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$coupon_id_to_edit = $_GET['id'] ?? null;
if (!$coupon_id_to_edit) {
    set_flash('error', 'Düzenlenecek kupon belirtilmedi.');
    header("Location: index.php");
    exit();
}

$stmt_coupon = $pdo->prepare("SELECT * FROM Coupons WHERE id = ? AND company_id IS NULL");
$stmt_coupon->execute([$coupon_id_to_edit]);
$coupon = $stmt_coupon->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    set_flash('error', 'Genel kupon bulunamadı.');
    header("Location: index.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
    } else {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $discount_percentage = $_POST['discount'] ?? null;
        $usage_limit = $_POST['usage_limit'] ?? null;
        $expire_date = $_POST['expire_date'] ?? null;
        $min_spend = !empty($_POST['min_spend']) ? floatval($_POST['min_spend']) : 0.0;
        $max_discount = (!empty($_POST['max_discount']) && floatval($_POST['max_discount']) > 0) ? floatval($_POST['max_discount']) : null;

        if (empty($code) || $discount_percentage === null || empty($usage_limit) || empty($expire_date)) {
            $error_message = "Tüm zorunlu alanların doldurulması gereklidir.";
        } elseif (!is_numeric($discount_percentage) || $discount_percentage <= 0 || $discount_percentage > 100) {
            $error_message = "İndirim oranı 1 ile 100 arasında bir sayı olmalıdır.";
        } elseif (!ctype_digit(strval($usage_limit)) || intval($usage_limit) <= 0) {
            $error_message = "Kullanım limiti pozitif bir tam sayı olmalıdır.";
        } elseif (strtotime($expire_date) < strtotime(date('Y-m-d'))) {
            $error_message = "Son kullanma tarihi geçmiş bir tarih olamaz.";
        } elseif ($min_spend < 0) {
            $error_message = "Minimum sepet tutarı negatif olamaz.";
        } else {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Coupons WHERE code = ? AND id != ?");
            $stmt_check->execute([$code, $coupon_id_to_edit]);
            if ($stmt_check->fetchColumn() > 0) {
                $error_message = "Bu kupon kodu zaten başka bir kupon tarafından kullanılıyor.";
            } else {
                $discount_db_value = floatval($discount_percentage) / 100.0;
                $formatted_expire_date = date('Y-m-d', strtotime($expire_date));

                $stmt_update = $pdo->prepare(
                    "UPDATE Coupons SET
                        code = ?,
                        discount = ?,
                        usage_limit = ?,
                        expire_date = ?,
                        min_spend = ?,
                        max_discount = ?
                     WHERE id = ? AND company_id IS NULL"
                );
                try {
                    $stmt_update->execute([
                        $code,
                        $discount_db_value,
                        intval($usage_limit),
                        $formatted_expire_date,
                        $min_spend,
                        $max_discount,
                        $coupon_id_to_edit
                    ]);
                    $limit_desc = ($min_spend > 0 ? "Min ₺{$min_spend}" : "Alt limitsiz") . ($max_discount ? ", Maks ₺{$max_discount}" : "");
                    log_activity('KUPON_GÜNCELLEME', "Genel kupon güncellendi: {$code} (%{$discount_percentage} indirim, Limit: {$usage_limit}, {$limit_desc})");
                    set_flash('success', 'Genel kupon başarıyla güncellendi.');
                    header("Location: index.php");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Kupon güncellenirken bir veritabanı hatası oluştu: " . $e->getMessage();
                }
            }
        }
    }
}
$page_title = 'Genel Kupon Düzenle - Admin Paneli';
require_once '../includes/header.php';
?>

<div class="app-container" style="padding-top: 3rem; padding-bottom: 5rem; max-width: 580px;">
    <?php display_flash(); ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
            <span><?= $error_message; ?></span>
        </div>
    <?php endif; ?>

    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; box-shadow: var(--shadow-md);">
        <div style="margin-bottom: 1.5rem;">
            <a href="/admin/index.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-decoration: none;">← Panele Geri Dön</a>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem; margin-bottom: 0.25rem;">Genel Kuponu Düzenle</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Kupon indirim oranını, limitini ve kurallarını güncelleyin.</p>
        </div>

        <form method="POST">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="code" class="form-label">Kupon Kodu *</label>
                <input type="text" id="code" name="code" class="form-control" style="text-transform: uppercase; font-weight: 700; letter-spacing: 1px;" value="<?= htmlspecialchars($_POST['code'] ?? $coupon['code'], ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="discount" class="form-label">İndirim Oranı (%) *</label>
                    <input type="number" id="discount" name="discount" class="form-control" min="1" max="100" value="<?= htmlspecialchars($_POST['discount'] ?? round($coupon['discount'] * 100), ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="usage_limit" class="form-label">Kullanım Limiti (Kişi) *</label>
                    <input type="number" id="usage_limit" name="usage_limit" class="form-control" min="1" value="<?= htmlspecialchars($_POST['usage_limit'] ?? $coupon['usage_limit'], ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <!-- Gelişmiş İndirim Kuralları: Alt Limit & Maks İndirim -->
            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1.25rem;">
                <div style="margin-bottom: 0.85rem;">
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                        <span>🛡️</span> <span>Gelişmiş İndirim Şartları</span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem;">
                        İsteğe bağlı kurallardır. Boş bırakılan alanlar için herhangi bir sınır uygulanmaz.
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="min-width: 0; margin-bottom: 0;">
                        <label for="min_spend" style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); text-transform: none;">
                            🛒 Alt Limit (Min Sepet)
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted); pointer-events: none;">₺</span>
                            <input type="number" id="min_spend" name="min_spend" class="form-control" min="0" step="1" placeholder="Örn: 500" style="padding-left: 2.2rem;" value="<?= htmlspecialchars($_POST['min_spend'] ?? (floatval($coupon['min_spend'] ?? 0) > 0 ? $coupon['min_spend'] : ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <small style="color: var(--text-muted); font-size: 0.75rem; line-height: 1.3;">Kupon için gereken asgari sepet tutarı.</small>
                    </div>

                    <div class="form-group" style="min-width: 0; margin-bottom: 0;">
                        <label for="max_discount" style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); text-transform: none;">
                            🎯 Maks İndirim Tavanı
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted); pointer-events: none;">₺</span>
                            <input type="number" id="max_discount" name="max_discount" class="form-control" min="1" step="1" placeholder="Örn: 150" style="padding-left: 2.2rem;" value="<?= htmlspecialchars($_POST['max_discount'] ?? ($coupon['max_discount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <small style="color: var(--text-muted); font-size: 0.75rem; line-height: 1.3;">Uygulanabilecek en yüksek indirim tutarı.</small>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="expire_date" class="form-label">Son Kullanma Tarihi *</label>
                <input type="date" id="expire_date" name="expire_date" class="form-control" required min="<?= date('Y-m-d'); ?>" value="<?= htmlspecialchars($_POST['expire_date'] ?? $coupon['expire_date'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Değişiklikleri Kaydet →
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>