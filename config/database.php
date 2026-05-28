<?php

function getDB(): PDO {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/../data/stock.db';
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
    }
    return $db;
}

function initializeDatabase(): void {
    $db = getDB();

    $db->exec("
        CREATE TABLE IF NOT EXISTS shopkeeper (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            phone TEXT,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS product (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            category TEXT,
            unit_price REAL NOT NULL CHECK(unit_price >= 0),
            quantity_in_stock INTEGER NOT NULL DEFAULT 0 CHECK(quantity_in_stock >= 0),
            shopkeeper_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (shopkeeper_id) REFERENCES shopkeeper(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS product_in (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL CHECK(quantity > 0),
            unit_price REAL NOT NULL CHECK(unit_price >= 0),
            supplier TEXT,
            notes TEXT,
            shopkeeper_id INTEGER NOT NULL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE,
            FOREIGN KEY (shopkeeper_id) REFERENCES shopkeeper(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS product_out (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL CHECK(quantity > 0),
            unit_price REAL NOT NULL CHECK(unit_price >= 0),
            customer TEXT,
            notes TEXT,
            shopkeeper_id INTEGER NOT NULL,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE,
            FOREIGN KEY (shopkeeper_id) REFERENCES shopkeeper(id) ON DELETE CASCADE
        );
    ");

    // Seed default shopkeeper
    $stmt = $db->query('SELECT COUNT(*) as cnt FROM shopkeeper');
    $count = $stmt->fetch()['cnt'];
    if ($count == 0) {
        $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO shopkeeper (full_name, email, phone, password) VALUES (?, ?, ?, ?)');
        $stmt->execute(['Admin Shopkeeper', 'admin@shop.com', '+250780000000', $hash]);
    }
}
