<?php
require_once 'config/init.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_message = "Güvenlik doğrulaması başarısız oldu. Lütfen formu tekrar gönderin.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($full_name) || empty($email) || empty($password)) {
            $error_message = "Tüm alanların doldurulması zorunludur.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Lütfen geçerli bir e-posta adresi girin.";
        } elseif (strlen($password) < 6) {
            $error_message = "Parola en az 6 karakter olmalıdır.";
        } elseif ($password !== $password_confirm) {
            $error_message = "Parolalar eşleşmiyor.";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM User WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Bu e-posta adresi zaten kullanımda.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $pdo->prepare(
                    "INSERT INTO User (id, full_name, email, password) VALUES (?, ?, ?, ?)"
                );
                
                try {
                    $new_id = generate_uuid();
                    $stmt->execute([$new_id, $full_name, $email, $hashed_password]);
                    log_activity('KAYIT', "Yeni yolcu hesabı oluşturuldu ({$full_name})", $new_id, $email);
                    set_flash('success', 'Kaydınız başarıyla tamamlandı. Şimdi giriş yapabilirsiniz.');
                    header("Location: login.php");
                    exit();
                } catch (PDOException $e) {
                    $error_message = "Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.";
                }
            }
        }
    }
}
$page_title = 'Kayıt Ol - Bilet Platformu';
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
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🎉</div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">Hesap Oluşturun</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Hemen üye olun, avantajlı bilet fırsatlarını yakalayın.</p>
        </div>

        <form method="POST" action="register.php">
            <?= csrf_field(); ?>
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="full_name" class="form-label">Ad Soyad</label>
                <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Adınız Soyadınız" value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="email" class="form-label">E-posta Adresi</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="adiniz@ornek.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="password" class="form-label">Parola</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="En az 6 karakter" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="password_confirm" class="form-label">Parola Tekrar</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Parolanızı tekrar girin" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.85rem; font-size: 1.05rem;">
                Hesap Oluştur →
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-muted);">
            Zaten bir hesabınız var mı? <a href="/login.php" style="color: var(--primary); font-weight: 700; text-decoration: none;">Giriş Yapın</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>