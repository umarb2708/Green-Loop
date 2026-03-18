-- Green Loop Database Schema (QR-on-Website Version)
-- Run this entire file to set up the database from scratch.

CREATE DATABASE IF NOT EXISTS greenloop_db2;
USE greenloop_db;

-- ─────────────────────────────────────────
-- Users
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(100) NOT NULL,
    username          VARCHAR(50)  UNIQUE NOT NULL,
    password          VARCHAR(255) NOT NULL,
    is_admin          TINYINT(1)   DEFAULT 0,
    rewards_collected INT          DEFAULT 0,
    created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- Product Data  (QR IDs registered by admin)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS product_data (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    qr_id        VARCHAR(20) UNIQUE NOT NULL,
    manufacturer VARCHAR(100) NOT NULL,
    type         ENUM('PET','HDPE','PP','Others') NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- Bin Data  (physical bins)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bin_data (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    location       VARCHAR(200) NOT NULL,
    current_status VARCHAR(4)   DEFAULT '0000',  -- 4 chars: PET/HDPE/PP/Others (0=ok,1=full)
    weight         FLOAT        DEFAULT 0.0,
    last_updated   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- Sync Sessions
-- Registered by firmware when START is pressed.
-- Links a 6-char SYNC CODE to the physical bin.
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sync_sessions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sync_code  VARCHAR(6) UNIQUE NOT NULL,
    bin_id     INT,
    active     TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bin_id) REFERENCES bin_data(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- Disposal Requests
-- Website inserts a row after scanning QR.
-- Hardware polls this table and sets confirmed=1 after physical disposal.
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS disposal (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    sync_code  VARCHAR(6)                      NOT NULL,
    type       ENUM('PET','HDPE','PP','Others') NOT NULL,
    confirmed  TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP
);

-- ─────────────────────────────────────────
-- Rewards Data  (generated at end of session by hardware)
-- ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rewards_data (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    unique_code VARCHAR(6)  UNIQUE NOT NULL,
    points      INT         NOT NULL,
    collected   TINYINT(1)  DEFAULT 0,
    bin_id      INT,
    user_id     INT,
    created_at  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bin_id)  REFERENCES bin_data(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ─────────────────────────────────────────
-- Indexes for polling performance
-- ─────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_disposal_sync   ON disposal    (sync_code, confirmed);
CREATE INDEX IF NOT EXISTS idx_sync_sessions   ON sync_sessions (sync_code, active);

-- ─────────────────────────────────────────
-- Default admin user  (password: admin123)
-- Change the password after first login!
-- ─────────────────────────────────────────
INSERT INTO users (name, username, password, is_admin)
VALUES ('Administrator', 'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE id=id;

-- ─────────────────────────────────────────
-- Sample bin (update location to match your setup)
-- ─────────────────────────────────────────
INSERT INTO bin_data (location, current_status, weight)
VALUES ('Main Entrance', '0000', 0.0)
ON DUPLICATE KEY UPDATE id=id;

-- ─────────────────────────────────────────
-- Sample product data for testing
-- ─────────────────────────────────────────
INSERT INTO product_data (qr_id, manufacturer, type) VALUES
    ('ABC1234567', 'Coca Cola', 'PET'),
    ('XYZ9876543', 'Pepsi',     'PET'),
    ('DEF4567890', 'Nestle',    'HDPE'),
    ('GHI7890123', 'Unilever',  'PP')
ON DUPLICATE KEY UPDATE id=id;
