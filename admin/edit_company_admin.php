<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$user_id_to_edit = $_GET['id'] ?? null;
if (!$user_id_to_edit) {
    set_flash('error', 'Düzenlenecek kullanıcı belirtilmedi.');
    header("Location: index.php");
    exit();
}

$stmt_user = $pdo->prepare("SELECT id, full_name, email, company_id FROM User WHERE id = ? AND role = 'company_admin'");
$stmt_user->execute([$user_id_to_edit]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    set_flash('error', 'Firma yöneticisi bulunamadı.');
    header("Location: index.php");
    exit();
}

$stmt_companies = $pdo->query("SELECT id, name FROM Bus_Company ORDER BY name");
$companies = $stmt_companies->fetchAll(PDO::FETCH_ASSOC);

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $company_id = trim($_POST['company_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($full_name) || empty($email)) {
            $error_message = "Ad Soyad ve E-posta alanları zorunludur.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Lütfen geçerli bir e-posta adresi girin.";
        } else {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM User WHERE email = ? COLLATE NOCASE AND id != ?");
            $stmt_check->execute([$email, $user_id_to_edit]);
            if ($stmt_check->fetchColumn() > 0) {
                $error_message = "Bu e-posta adresi zaten başka bir kullanıcı tarafından kullanılıyor.";
            } else {
                $password_update_sql = '';
                $params = [$full_name, $email, (!empty($company_id) ? $company_id : null)];
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error_message = "Yeni parola en az 6 karakter olmalıdır.";
                    } else {
                        $password_update_sql = ", password = ?";
                        $params[] = password_hash($password, PASSWORD_BCRYPT);
                    }
                }

                if (empty($error_message)) {
                    $params[] = $user_id_to_edit;
                    $sql = "UPDATE User SET full_name = ?, email = ?, company_id = ? {$password_update_sql} WHERE id = ?";
                    $stmt_update = $pdo->prepare($sql);
                    try {
                        $stmt_update->execute($params);
                        set_flash('success', 'Firma yöneticisi bilgileri başarıyla güncellendi.');
                        header("Location: index.php");
                        exit();
                    } catch (PDOException $e) {
                        $error_message = "Kullanıcı güncellenirken bir veritabanı hatası oluştu.";
                    }
                }
            }
        }
    }
}
$page_title = 'Firma Admin Düzenle - Admin Paneli';
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
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem; margin-bottom: 0.25rem;">Yönetici Bilgilerini Düzenle</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Firma yöneticisinin profilini veya atandığı firmayı güncelleyin.</p>
        </div>

        <form method="POST">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="full_name" class="form-label">Ad Soyad</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? $user['full_name'], ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="email" class="form-label">E-posta Adresi</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="company_id" class="form-label">Atanan Firma</label>
                <select id="company_id" name="company_id" class="form-control">
                    <option value="">-- Firma Seçin (Boş bırakılabilir) --</option>
                    <?php 
                    $selected_company = $_POST['company_id'] ?? $user['company_id'];
                    foreach ($companies as $company): ?>
                        <option value="<?= htmlspecialchars($company['id'], ENT_QUOTES, 'UTF-8'); ?>" <?= ($selected_company == $company['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="password" class="form-label">Yeni Parola (İsteğe Bağlı)</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Değiştirmek istemiyorsanız boş bırakın">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Değişiklikleri Kaydet →
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>