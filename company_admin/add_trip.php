<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company_admin') {
    header("Location: /index.php");
    exit();
}

$stmt_user = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$company_id = $stmt_user->fetchColumn();

if (!$company_id) {
    set_flash('error', 'Bu kullanıcı bir şirkete atanmamış.');
    header("Location: /index.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error_message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
    } else {
        $departure_city = trim($_POST['departure_city'] ?? '');
        $destination_city = trim($_POST['destination_city'] ?? '');
        $departure_time = $_POST['departure_time'] ?? '';
        $arrival_time = $_POST['arrival_time'] ?? '';
        $price = $_POST['price'] ?? null;
        $capacity = $_POST['capacity'] ?? null;

        $bus_type = in_array($_POST['bus_type'] ?? '', ['2+1', '2+2']) ? $_POST['bus_type'] : '2+1';

        if (empty($departure_city) || empty($destination_city) || empty($departure_time) || empty($arrival_time) || $price === null || empty($capacity)) {
            $error_message = "Tüm alanların doldurulması zorunludur.";
        } elseif (mb_strtolower($departure_city) === mb_strtolower($destination_city)) {
            $error_message = "Kalkış ve varış şehirleri aynı olamaz.";
        } elseif (!is_numeric($price) || floatval($price) <= 0) {
            $error_message = "Fiyat pozitif bir sayı olmalıdır.";
        } elseif (!ctype_digit(strval($capacity)) || intval($capacity) <= 0) {
            $error_message = "Kapasite pozitif bir tam sayı olmalıdır.";
        } elseif (strtotime($arrival_time) <= strtotime($departure_time)) {
            $error_message = "Varış zamanı, kalkış zamanından sonra olmalıdır.";
        } else {
            $stmt_insert = $pdo->prepare(
                "INSERT INTO Trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity, bus_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            try {
                $formatted_departure_time = date('Y-m-d H:i:s', strtotime($departure_time));
                $formatted_arrival_time = date('Y-m-d H:i:s', strtotime($arrival_time));
                $new_trip_id = generate_uuid();

                $stmt_insert->execute([
                    $new_trip_id,
                    $company_id,
                    $departure_city,
                    $destination_city,
                    $formatted_departure_time,
                    $formatted_arrival_time,
                    intval($price),
                    intval($capacity),
                    $bus_type
                ]);

                log_activity('SEFER_EKLEME', "Yeni sefer planlandı: {$departure_city} → {$destination_city} ({$bus_type}, Kapasite: {$capacity}, ₺{$price})");

                set_flash('success', 'Yeni sefer başarıyla eklendi.');
                header("Location: index.php");
                exit();
            } catch (PDOException $e) {
                $error_message = "Sefer eklenirken bir veritabanı hatası oluştu.";
            }
        }
    }
}
$page_title = 'Yeni Sefer Planla - Firma Paneli';
require_once '../includes/header.php';
?>

<div class="app-container" style="padding-top: 3rem; padding-bottom: 5rem; max-width: 650px;">
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
            <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-top: 0.5rem; margin-bottom: 0.25rem;">Yeni Sefer Planla</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Şirketiniz adına yeni bir otobüs seferi oluşturun.</p>
        </div>

        <form method="POST">
            <?= csrf_field(); ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="departure_city" class="form-label">Kalkış Şehri</label>
                    <input type="text" id="departure_city" name="departure_city" class="form-control" placeholder="Örn: İstanbul" value="<?= htmlspecialchars($_POST['departure_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="destination_city" class="form-label">Varış Şehri</label>
                    <input type="text" id="destination_city" name="destination_city" class="form-control" placeholder="Örn: Ankara" value="<?= htmlspecialchars($_POST['destination_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="departure_time" class="form-label">Kalkış Tarih & Saat</label>
                    <input type="datetime-local" id="departure_time" name="departure_time" class="form-control" value="<?= htmlspecialchars($_POST['departure_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="arrival_time" class="form-label">Varış Tarih & Saat</label>
                    <input type="datetime-local" id="arrival_time" name="arrival_time" class="form-control" value="<?= htmlspecialchars($_POST['arrival_time'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group">
                    <label for="bus_type" class="form-label">Otobüs Tipi</label>
                    <select id="bus_type" name="bus_type" class="form-control" required>
                        <option value="2+1" <?= (($_POST['bus_type'] ?? '2+1') === '2+1') ? 'selected' : ''; ?>>2+1 VIP / Rahat</option>
                        <option value="2+2" <?= (($_POST['bus_type'] ?? '') === '2+2') ? 'selected' : ''; ?>>2+2 Standart</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price" class="form-label">Bilet Fiyatı (TL)</label>
                    <input type="number" id="price" name="price" class="form-control" step="1" min="1" placeholder="Örn: 450" value="<?= htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="capacity" class="form-label">Koltuk Sayısı</label>
                    <input type="number" id="capacity" name="capacity" class="form-control" min="1" max="60" placeholder="Örn: 38" value="<?= htmlspecialchars($_POST['capacity'] ?? '38', ENT_QUOTES, 'UTF-8'); ?>" required>
                    <small style="color: var(--text-muted); font-size: 0.78rem;">2+1 için standart 38, 2+2 için 46 koltuktur.</small>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                Seferi Yayınla →
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('bus_type').addEventListener('change', function() {
    const capInput = document.getElementById('capacity');
    if (this.value === '2+1' && (capInput.value === '46' || capInput.value === '30' || !capInput.value)) {
        capInput.value = '38';
    } else if (this.value === '2+2' && (capInput.value === '38' || capInput.value === '30' || !capInput.value)) {
        capInput.value = '46';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>