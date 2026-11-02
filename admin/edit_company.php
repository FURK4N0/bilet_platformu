<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$company_id_to_edit = $_GET['id'] ?? null;
if (!$company_id_to_edit) {
    set_flash('error', 'Düzenlenecek firma belirtilmedi.');
    header("Location: index.php");
    exit();
}

$stmt_company = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
$stmt_company->execute([$company_id_to_edit]);
$company = $stmt_company->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    set_flash('error', 'Firma bulunamadı.');
    header("Location: index.php");
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
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Bus_Company WHERE name = ? COLLATE NOCASE AND id != ?");
            $stmt_check->execute([$company_name, $company_id_to_edit]);
            if ($stmt_check->fetchColumn() > 0) {
                $error_message = "Bu isimde başka bir firma zaten mevcut.";
            } else {
                $stmt_update = $pdo->prepare("UPDATE Bus_Company SET name = ? WHERE id = ?");
                try {
                    $stmt_update->execute([$company_name, $company_id_to_edit]);
                    set_flash('success', 'Firma bilgileri başarıyla güncellendi.');
                    header("Location: index.php");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Firma güncellenirken bir veritabanı hatası oluştu.";
                }
            }
        }
    }
}
$page_title = 'Firma Düzenle - Admin Paneli';
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
            <a href="/admin/index.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-decoration: none;">← Panele Geri Dön</a>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem; margin-bottom: 0.25rem;">Firma Bilgilerini Düzenle</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Firma adını güncelleyin.</p>
        </div>

        <form method="POST">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="company_name" class="form-label">Firma Adı</label>
                <input type="text" id="company_name" name="company_name" class="form-control" value="<?= htmlspecialchars($_POST['company_name'] ?? $company['name'], ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Değişiklikleri Kaydet →
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>