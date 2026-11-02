<?php
require_once 'config/init.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: /admin/index.php");
        exit();
    } elseif ($_SESSION['user_role'] === 'company_admin') {
        header("Location: /company_admin/index.php");
        exit();
    }
    header("Location: /index.php");
    exit();
}

$error_message = '';

if (isset($_GET['status']) && $_GET['status'] === 'success') {
    set_flash('success', 'Kaydınız başarıyla oluşturuldu! Şimdi giriş yapabilirsiniz.');
}
if (isset($_GET['redirect_message'])) {
    $error_message = htmlspecialchars($_GET['redirect_message'], ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error_message = "E-posta ve parola alanları zorunludur.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM User WHERE email = ? COLLATE NOCASE");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['company_id'] = $user['company_id'] ?? null;

                log_activity('GİRİŞ', "Kullanıcı sisteme giriş yaptı ({$user['role']})", $user['id'], $user['email']);

                if ($user['role'] === 'admin') {
                    header("Location: /admin/index.php");
                    exit();
                } elseif ($user['role'] === 'company_admin') {
                    header("Location: /company_admin/index.php");
                    exit();
                } else {
                    header("Location: /index.php");
                    exit();
                }
            } else {
                log_activity('BAŞARISIZ_GİRİŞ', "Hatalı giriş denemesi yapıldı: {$email}", null, $email);
                $error_message = "E-posta veya parola hatalı.";
            }
        }
    }
}
$page_title = 'Giriş Yap - Bilet Platformu';
require_once 'includes/header.php';
?>

<div class="app-container" style="padding-top: 3.5rem; padding-bottom: 5rem; max-width: 480px;">
    <?php display_flash(); ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <span>⚠️</span>
            <span><?= $error_message; ?></span>
        </div>
    <?php endif; ?>

    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem; box-shadow: var(--shadow-md);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👋</div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">Tekrar Hoş Geldiniz</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Hesabınıza giriş yaparak biletlerinizi yönetin.</p>
        </div>

        <form method="POST" action="login.php">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="email" class="form-label">E-posta Adresi</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="ornek@demo.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="password" class="form-label">Parola</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.85rem; font-size: 1.05rem;">
                Giriş Yap →
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-muted);">
            Hesabınız yok mu? <a href="/register.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Hemen Kayıt Olun</a>
        </div>
    </div>

    <!-- Hızlı Demo Giriş İpuçları -->
    <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; margin-top: 1.5rem; font-size: 0.82rem; color: var(--text-muted);">
        <strong>💡 Hazır Demo Giriş Bilgileri:</strong><br>
        • <strong>Yolcu:</strong> `yolcu@demo.com` / `Yolcu123!` (2500 TL Bakiye)<br>
        • <strong>Firma Admin:</strong> `yetkili@kamilkoc.com` / `Firma123!`<br>
        • <strong>Sistem Admin:</strong> `admin@biletplatformu.com` / `Admin123!`
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>