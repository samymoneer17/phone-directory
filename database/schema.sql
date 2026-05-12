-- ============================================================
-- دليل الهاتف الدولي - Database Schema
-- International Phone Directory
-- Compatible with SQLite (Local Database)
-- ============================================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE NOT NULL,
    password TEXT,
    name TEXT NOT NULL,
    phone TEXT,
    avatar TEXT DEFAULT NULL,
    role TEXT DEFAULT 'USER' CHECK(role IN ('USER','ADMIN')),
    plan TEXT DEFAULT 'FREE' CHECK(plan IN ('FREE','PRO','PREMIUM')),
    subscription_expires_at TEXT DEFAULT NULL,
    is_phone_hidden INTEGER DEFAULT 0,
    reset_token TEXT DEFAULT NULL,
    reset_token_expires_at TEXT DEFAULT NULL,
    search_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

-- Search history table
CREATE TABLE IF NOT EXISTS search_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    query TEXT NOT NULL,
    query_type TEXT DEFAULT 'NUMBER' CHECK(query_type IN ('NAME','NUMBER')),
    country_code TEXT DEFAULT NULL,
    results_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    plan TEXT NOT NULL,
    amount REAL NOT NULL,
    currency TEXT DEFAULT 'YER',
    status TEXT DEFAULT 'PENDING' CHECK(status IN ('PENDING','APPROVED','REJECTED')),
    transaction_id TEXT UNIQUE NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    plan TEXT NOT NULL,
    started_at TEXT DEFAULT (datetime('now')),
    expires_at TEXT NOT NULL,
    is_active INTEGER DEFAULT 1,
    payment_id INTEGER,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id)
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    ip_address TEXT,
    user_agent TEXT,
    details TEXT,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Rate limits table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    endpoint TEXT NOT NULL,
    request_count INTEGER DEFAULT 1,
    window_start TEXT DEFAULT (datetime('now')),
    UNIQUE(ip_address, endpoint)
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_plan ON users(plan);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_payments_user ON payments(user_id);
CREATE INDEX IF NOT EXISTS idx_payments_transaction ON payments(transaction_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);
CREATE INDEX IF NOT EXISTS idx_search_history_user ON search_history(user_id);
CREATE INDEX IF NOT EXISTS idx_search_history_created ON search_history(created_at);
CREATE INDEX IF NOT EXISTS idx_logs_user ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_logs_action ON activity_logs(action);
CREATE INDEX IF NOT EXISTS idx_logs_created ON activity_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_rate_limits ON rate_limits(ip_address, endpoint);
CREATE INDEX IF NOT EXISTS idx_subscriptions_user ON subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_subscriptions_active ON subscriptions(is_active);
