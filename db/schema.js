const Database = require('better-sqlite3');
const path = require('path');
const bcrypt = require('bcryptjs');

const DB_PATH = path.join(__dirname, '..', 'stock.db');

function initializeDatabase() {
  const db = new Database(DB_PATH);

  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');

  db.exec(`
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
  `);

  // Seed default shopkeeper if none exists
  const count = db.prepare('SELECT COUNT(*) as cnt FROM shopkeeper').get();
  if (count.cnt === 0) {
    const hashedPassword = bcrypt.hashSync('Admin@123', 10);
    db.prepare(`
      INSERT INTO shopkeeper (full_name, email, phone, password)
      VALUES (?, ?, ?, ?)
    `).run('Admin Shopkeeper', 'admin@shop.com', '+250780000000', hashedPassword);
  }

  return db;
}

module.exports = { initializeDatabase };
