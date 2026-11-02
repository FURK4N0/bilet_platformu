<?php
/**
 * Veritabanı v4 Migrasyonu:
 * Coupons tablosuna min_spend ve max_discount kolonlarını ekler.
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/helpers.php';

echo "🚀 Veritabanı v4 (Kupon Alt Limit & Maksimum İndirim) migrasyonu başlatılıyor...\n";

try {
    $columns_coupons = $pdo->query("PRAGMA table_info(Coupons)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($columns_coupons, 'name');

    if (!in_array('min_spend', $col_names)) {
        $pdo->exec("ALTER TABLE Coupons ADD COLUMN min_spend REAL DEFAULT 0.0;");
        echo "✓ Coupons tablosuna 'min_spend' kolonu eklendi.\n";
    } else {
        echo "• 'min_spend' kolonu zaten mevcut.\n";
    }

    if (!in_array('max_discount', $col_names)) {
        $pdo->exec("ALTER TABLE Coupons ADD COLUMN max_discount REAL DEFAULT NULL;");
        echo "✓ Coupons tablosuna 'max_discount' kolonu eklendi.\n";
    } else {
        echo "• 'max_discount' kolonu zaten mevcut.\n";
    }

    echo "✅ v4 Migrasyonu başarıyla tamamlandı!\n";
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
