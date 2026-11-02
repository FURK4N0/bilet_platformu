<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company_admin') {
    header("Location: /index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt_user = $pdo->prepare(
    "SELECT U.full_name, U.email, BC.name as company_name 
     FROM User U 
     LEFT JOIN Bus_Company BC ON U.company_id = BC.id 
     WHERE U.id = ?"
);
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: /login.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $stmt_check_pw = $pdo->prepare("SELECT password FROM User WHERE id = ?");
        $stmt_check_pw->execute([$user_id]);
        $hashed_password = $stmt_check_pw->fetchColumn();

        if (!$hashed_password || !password_verify($current_password, $hashed_password)) {
            $error_message = "Mevcut şifreniz hatalı.";
        } elseif (empty($new_password) || strlen($new_password) < 6) {
            $error_message = "Yeni şifre en az 6 karakter olmalıdır.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Yeni şifreler eşleşmiyor.";
        } else {
            $new_hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt_update_pw = $pdo->prepare("UPDATE User SET password = ? WHERE id = ?");
            try {
                $stmt_update_pw->execute([$new_hashed_password, $user_id]);
                set_flash('success', 'Şifreniz başarıyla güncellendi.');
                header("Location: account.php");
                exit();
            } catch (PDOException $e) {
                $error_message = "Şifre güncellenirken bir veritabanı hatası oluştu.";
            }
        }
    }
}
$page_title = 'Hesabım - Firma Paneli';
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
            <a href="/company_admin/index.php" style="color: var(--text-muted); font-size: 0.85rem; font-weight: 700; text-decoration: none;">← Panele Geri Dön</a>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem; margin-bottom: 0.25rem;">Yönetici Hesabım</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">
                <strong><?= htmlspecialchars($user['company_name'] ?? 'Firma', ENT_QUOTES, 'UTF-8'); ?></strong> Yetkili Profili
            </p>
        </div>

        <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.75rem; font-size: 0.9rem;">
            <div style="margin-bottom: 0.35rem;"><strong>Ad Soyad:</strong> <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div><strong>E-posta:</strong> <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 1rem;">Şifre Değiştir</h3>
        <form method="POST">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="current_password" class="form-label">Mevcut Şifre</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="new_password" class="form-label">Yeni Şifre (En az 6 karakter)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="confirm_password" class="form-label">Yeni Şifre Tekrar</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Şifreyi Güncelle →
            </button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>