<?php
/**
 * Veritabanı Güncelleme ve Migrasyon Scripti (v2)
 * - System_Logs tablosunu oluşturur.
 * - Trips tablosuna bus_type (2+1 / 2+2) alanını ekler.
 */

define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/config/database.php';

echo "🚀 Veritabanı v2 migrasyonu başlatılıyor...\n";

try {
    // 1. System_Logs Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS System_Logs (
            id TEXT PRIMARY KEY,
            user_id TEXT,
            user_email TEXT,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE SET NULL
        );
        CREATE INDEX IF NOT EXISTS idx_logs_created_at ON System_Logs(created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_logs_user_id ON System_Logs(user_id);
        CREATE INDEX IF NOT EXISTS idx_logs_action ON System_Logs(action);
    ");
    echo "✓ System_Logs tablosu ve indeksleri hazırlandı.\n";

    // 2. Trips tablosuna bus_type kolonu ekleme kontrolü
    $columns = $pdo->query("PRAGMA table_info(Trips)")->fetchAll(PDO::FETCH_ASSOC);
    $has_bus_type = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'bus_type') {
            $has_bus_type = true;
            break;
        }
    }

    if (!$has_bus_type) {
        $pdo->exec("ALTER TABLE Trips ADD COLUMN bus_type TEXT DEFAULT '2+1';");
        echo "✓ Trips tablosuna 'bus_type' kolonu eklendi (varsayılan: 2+1).\n";
    } else {
        echo "✓ 'bus_type' kolonu zaten mevcut.\n";
    }

    // Mevcut seferlere gerçekçi bus_type dağıtımı yap
    $pdo->exec("UPDATE Trips SET bus_type = '2+1' WHERE capacity <= 30;");
    $pdo->exec("UPDATE Trips SET bus_type = '2+2' WHERE capacity > 30;");
    echo "✓ Mevcut seferlerin otobüs tipleri (2+1 ve 2+2) güncellendi.\n";

    echo "🎉 Migrasyon başarıyla tamamlandı!\n";
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
