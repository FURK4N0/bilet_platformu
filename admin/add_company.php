<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
    } else {
        $company_name = trim($_POST['company_name'] ?? '');

        if (empty($company_name)) {
            $error_message = "Firma adı boş bırakılamaz.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Bus_Company WHERE name = ? COLLATE NOCASE");
            $stmt->execute([$company_name]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Bu isimde bir firma zaten mevcut.";
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO Bus_Company (id, name) VALUES (?, ?)");
                try {
                    $new_comp_id = generate_uuid();
                    $stmt_insert->execute([$new_comp_id, $company_name]);
                    log_activity('FİRMA_YÖNETİMİ', "Yeni otobüs firması sisteme eklendi: {$company_name}");
                    set_flash('success', 'Firma başarıyla eklendi.');
                    header("Location: /admin/index.php");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Firma eklenirken bir veritabanı hatası oluştu.";
                }
            }
        }
    }
}
$page_title = 'Yeni Firma Ekle - Admin Paneli';
require_once '../includes/header.php';
?>

<div class="app-container" style="padding-top: 3rem; padding-bottom: 5rem; max-width: 540px;">
    <?php display_flash(); ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
            <span><?= $error_message; ?></span>
        </div>
    <?php endif; ?>

    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; box-shadow: var(--shadow-md);">
        <div style="margin-bottom: 1.5rem;">
            <a href="/admin/index.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-decoration: none;">← Paneli Geri Dön</a>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem; margin-bottom: 0.25rem;">Yeni Otobüs Firması Ekle</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Sisteme yeni bir otobüs firması kaydedin.</p>
        </div>

        <form method="POST">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="company_name" class="form-label">Firma Adı</label>
                <input type="text" id="company_name" name="company_name" class="form-control" placeholder="Örn: Pamukkale Turizm" value="<?= htmlspecialchars($_POST['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Firmayı Kaydet →
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>