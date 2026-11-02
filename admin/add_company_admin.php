<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
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
        $password = $_POST['password'] ?? '';
        $company_id = trim($_POST['company_id'] ?? '');

        if (empty($full_name) || empty($email) || empty($password)) {
            $error_message = "Ad Soyad, E-posta ve Parola alanları zorunludur.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Lütfen geçerli bir e-posta adresi girin.";
        } elseif (strlen($password) < 6) {
            $error_message = "Parola en az 6 karakter olmalıdır.";
        } else {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM User WHERE email = ? COLLATE NOCASE");
            $stmt_check->execute([$email]);
            if ($stmt_check->fetchColumn() > 0) {
                $error_message = "Bu e-posta adresi zaten kullanılıyor.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $company_id_to_insert = !empty($company_id) ? $company_id : null;

                $stmt_insert = $pdo->prepare(
                    "INSERT INTO User (id, full_name, email, password, role, company_id)
                     VALUES (?, ?, ?, ?, 'company_admin', ?)"
                );
                try {
                    $stmt_insert->execute([generate_uuid(), $full_name, $email, $hashed_password, $company_id_to_insert]);
                    set_flash('success', 'Firma yöneticisi başarıyla eklendi.');
                    header("Location: /admin/index.php");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Kullanıcı eklenirken bir veritabanı hatası oluştu.";
                }
            }
        }
    }
}
$page_title = 'Yeni Firma Yöneticisi Ekle - Admin Paneli';
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
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem; margin-bottom: 0.25rem;">Yeni Firma Yöneticisi Ekle</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Sisteme yeni bir yetkili tanımlayın ve firmasına atayın.</p>
        </div>

        <form method="POST">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="full_name" class="form-label">Ad Soyad</label>
                <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Örn: Mehmet Özkan" value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="email" class="form-label">E-posta Adresi</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="yetkili@firma.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="password" class="form-label">Giriş Parolası</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="En az 6 karakter" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="company_id" class="form-label">Atanacak Firma</label>
                <select id="company_id" name="company_id" class="form-control">
                    <option value="">-- Firma Seçin (İsteğe Bağlı) --</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= htmlspecialchars($company['id'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Firma Yöneticisini Kaydet →
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>