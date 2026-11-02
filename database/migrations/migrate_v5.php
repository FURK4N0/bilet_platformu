<?php
require_once dirname(__DIR__, 2) . '/config/init.php';

try {
    echo "Running v5 migration: Creating Seat_Locks table...\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Seat_Locks (
            id VARCHAR(36) PRIMARY KEY,
            trip_id VARCHAR(36) NOT NULL,
            seat_number INTEGER NOT NULL,
            user_id VARCHAR(36) NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            locked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            FOREIGN KEY (trip_id) REFERENCES Trips(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_seat_locks_trip ON Seat_Locks(trip_id, seat_number);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_seat_locks_expires ON Seat_Locks(expires_at);");

    echo "✅ v5 Migrasyonu başarıyla tamamlandı! Seat_Locks tablosu oluşturuldu.\n";
} catch (Exception $e) {
    echo "❌ Hata: " . $e->getMessage() . "\n";
    exit(1);
}
