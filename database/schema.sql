-- ==============================================================================
-- Bilet Satın Alma Platformu - Veritabanı Şeması (SQLite 3)
-- ==============================================================================

PRAGMA foreign_keys = ON;

-- 1. Otobüs Firmaları Tablosu
CREATE TABLE IF NOT EXISTS Bus_Company (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    logo_path TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- 2. Kullanıcılar Tablosu (Admin, Firma Yetkilisi, Yolcu)
CREATE TABLE IF NOT EXISTS User (
    id TEXT PRIMARY KEY,
    full_name TEXT NOT NULL COLLATE NOCASE,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin', 'company_admin', 'user')),
    company_id TEXT,
    balance REAL DEFAULT 1500.0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES Bus_Company(id) ON DELETE SET NULL
);

-- 3. Seferler Tablosu
CREATE TABLE IF NOT EXISTS Trips (
    id TEXT PRIMARY KEY,
    company_id TEXT NOT NULL,
    departure_city TEXT NOT NULL COLLATE NOCASE,
    destination_city TEXT NOT NULL COLLATE NOCASE,
    departure_time TEXT NOT NULL,
    arrival_time TEXT NOT NULL,
    price REAL NOT NULL CHECK(price > 0),
    capacity INTEGER NOT NULL CHECK(capacity > 0),
    bus_type TEXT DEFAULT '2+1' CHECK(bus_type IN ('2+1', '2+2')),
    created_date TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES Bus_Company(id) ON DELETE CASCADE
);

-- 4. Biletler Tablosu
CREATE TABLE IF NOT EXISTS Tickets (
    id TEXT PRIMARY KEY,
    pnr_code TEXT UNIQUE,
    trip_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    status TEXT DEFAULT 'ACTIVE' NOT NULL CHECK(status IN ('ACTIVE', 'CANCELLED')),
    total_price REAL NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES Trips(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
);

-- 5. Satın Alınan Koltuklar Tablosu
CREATE TABLE IF NOT EXISTS Booked_Seats (
    id TEXT PRIMARY KEY,
    ticket_id TEXT NOT NULL,
    seat_number INTEGER NOT NULL CHECK(seat_number > 0),
    passenger_name TEXT,
    passenger_tc TEXT,
    gender TEXT DEFAULT 'male' CHECK(gender IN ('male', 'female')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES Tickets(id) ON DELETE CASCADE
);

-- 6. İndirim Kuponları Tablosu
CREATE TABLE IF NOT EXISTS Coupons (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE COLLATE NOCASE,
    discount REAL NOT NULL CHECK(discount > 0 AND discount <= 1),
    company_id TEXT,
    usage_limit INTEGER NOT NULL CHECK(usage_limit >= 0),
    expire_date TEXT NOT NULL,
    min_spend REAL DEFAULT 0.0,
    max_discount REAL DEFAULT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES Bus_Company(id) ON DELETE CASCADE
);

-- 7. Kullanıcı Kupon Geçmişi Tablosu (Mükerrer Kullanımı Önler)
CREATE TABLE IF NOT EXISTS User_Coupons (
    id TEXT PRIMARY KEY,
    coupon_id TEXT NOT NULL,
    user_id TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES Coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
    UNIQUE(coupon_id, user_id)
);

-- 8. Sistem Denetim ve İşlem Logları Tablosu (Audit Logs)
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

-- 9. Geçici Koltuk Kilitleme Tablosu (10 Dakikalık Rezervasyon)
CREATE TABLE IF NOT EXISTS Seat_Locks (
    id TEXT PRIMARY KEY,
    trip_id TEXT NOT NULL,
    seat_number INTEGER NOT NULL,
    user_id TEXT NOT NULL,
    session_id TEXT NOT NULL,
    locked_at TEXT DEFAULT CURRENT_TIMESTAMP,
    expires_at TEXT NOT NULL,
    FOREIGN KEY (trip_id) REFERENCES Trips(id) ON DELETE CASCADE
);

-- İndeksler (Sorgu Performansı İçin)
CREATE INDEX IF NOT EXISTS idx_trips_cities_time ON Trips(departure_city, destination_city, departure_time);
CREATE INDEX IF NOT EXISTS idx_tickets_user ON Tickets(user_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_tickets_pnr ON Tickets(pnr_code);
CREATE INDEX IF NOT EXISTS idx_booked_seats_ticket ON Booked_Seats(ticket_id);
CREATE INDEX IF NOT EXISTS idx_logs_created_at ON System_Logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_logs_user_id ON System_Logs(user_id);
CREATE INDEX IF NOT EXISTS idx_logs_action ON System_Logs(action);
CREATE INDEX IF NOT EXISTS idx_seat_locks_trip ON Seat_Locks(trip_id, seat_number);
CREATE INDEX IF NOT EXISTS idx_seat_locks_expires ON Seat_Locks(expires_at);
