<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
require_once APP_ROOT . '/config/init.php';

$page_title = $page_title ?? 'Bilet Satın Alma & Çoklu Firma Bilet Platformu';
$current_user_balance = null;

if (isset($_SESSION['user_id'])) {
    $stmt_nav_balance = $pdo->prepare("SELECT balance FROM User WHERE id = ?");
    $stmt_nav_balance->execute([$_SESSION['user_id']]);
    $current_user_balance = $stmt_nav_balance->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <!-- Google Fonts & Modern UI Kit -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="app-container navbar-inner">
            <a href="/index.php" class="brand-logo">
                <span style="font-size: 1.6rem;">🚌</span>
                <span>Bilet<span style="color: #3b82f6;">Platformu</span></span>
                <span class="brand-badge">PRO</span>
            </a>

            <nav>
                <ul class="nav-links">
                    <li><a href="/index.php" class="nav-link">Seferler</a></li>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                            <li><a href="/admin/index.php" class="btn btn-sm btn-outline">🛡️ Admin Paneli</a></li>
                        <?php elseif (($_SESSION['user_role'] ?? '') === 'company_admin'): ?>
                            <li><a href="/company_admin/index.php" class="btn btn-sm btn-outline">🏢 Firma Paneli</a></li>
                        <?php endif; ?>

                        <li>
                            <div class="user-pill">
                                <span style="font-size: 0.9rem; font-weight: 600;">👤 <?= htmlspecialchars($_SESSION['user_full_name'] ?? 'Kullanıcı', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="user-balance">₺<?= number_format($current_user_balance ?? 0, 2); ?></span>
                            </div>
                        </li>
                        <li><a href="/account.php" class="nav-link">Biletlerim</a></li>
                        <li><a href="/logout.php" class="btn btn-sm btn-secondary" style="padding: 0.35rem 0.75rem;">Çıkış</a></li>
                    <?php else: ?>
                        <li><a href="/login.php" class="nav-link">Giriş Yap</a></li>
                        <li><a href="/register.php" class="btn btn-sm btn-primary">Üye Ol</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main style="flex: 1 0 auto;">
