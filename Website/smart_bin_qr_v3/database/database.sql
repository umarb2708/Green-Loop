-- Green Loop Database Schema
-- Version 3.0

-- Create database
CREATE DATABASE IF NOT EXISTS green_loop;
USE green_loop;

-- Table 1: Users
-- Stores user login information and reward points
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0 COMMENT '0: Normal User, 1: Admin, 2: Manufacturer',
    rewards_collected INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_is_admin (is_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: Product Data
-- Stores product information with QR codes
CREATE TABLE IF NOT EXISTS product_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_id VARCHAR(10) NOT NULL UNIQUE,
    manufacturer VARCHAR(100) NOT NULL,
    type ENUM('PET', 'HDPE', 'PP', 'Others') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_qr_id (qr_id),
    INDEX idx_type (type),
    INDEX idx_manufacturer (manufacturer),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: Bin Data
-- Stores smart bin location and status information
CREATE TABLE IF NOT EXISTS bin_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(200) NOT NULL,
    current_status VARCHAR(4) DEFAULT '0000' COMMENT 'Format: PET,HDPE,PP,Others (1=full, 0=empty)',
    weight DECIMAL(10, 3) DEFAULT 0.000 COMMENT 'Weight in kilograms',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: Rewards Data
-- Stores generated reward codes and points
CREATE TABLE IF NOT EXISTS rewards_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_code VARCHAR(6) NOT NULL UNIQUE,
    points INT NOT NULL,
    bin_id INT NOT NULL,
    collected TINYINT(1) DEFAULT 0 COMMENT '0: Not Collected, 1: Collected',
    collected_by INT DEFAULT NULL,
    collected_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_unique_code (unique_code),
    INDEX idx_collected (collected),
    INDEX idx_bin_id (bin_id),
    FOREIGN KEY (bin_id) REFERENCES bin_data(id) ON DELETE CASCADE,
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5: Disposal Data
-- Stores disposal requests from users
CREATE TABLE IF NOT EXISTS disposal_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bin_id INT NOT NULL,
    type ENUM('PET', 'HDPE', 'PP', 'Others') NOT NULL,
    qr_id VARCHAR(10) NOT NULL,
    user_id INT DEFAULT NULL,
    confirmed TINYINT(1) DEFAULT 0 COMMENT '0: Pending, 1: Confirmed by hardware',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_bin_confirmed (bin_id, confirmed),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (bin_id) REFERENCES bin_data(id) ON DELETE CASCADE,
    FOREIGN KEY (qr_id) REFERENCES product_data(qr_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 6: Bin History (Optional - for analytics)
-- Stores historical bin status changes
CREATE TABLE IF NOT EXISTS bin_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bin_id INT NOT NULL,
    status VARCHAR(4) NOT NULL,
    weight DECIMAL(10, 3) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bin_date (bin_id, recorded_at),
    FOREIGN KEY (bin_id) REFERENCES bin_data(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 7: Activity Log (Optional - for tracking)
-- Stores user and system activities
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create views for easier data retrieval

-- View: Active Disposal Requests
CREATE OR REPLACE VIEW active_disposal_requests AS
SELECT 
    d.id,
    d.bin_id,
    b.location,
    d.type,
    d.qr_id,
    p.manufacturer,
    d.user_id,
    u.username,
    d.confirmed,
    d.created_at
FROM disposal_data d
LEFT JOIN bin_data b ON d.bin_id = b.id
LEFT JOIN product_data p ON d.qr_id = p.qr_id
LEFT JOIN users u ON d.user_id = u.id
WHERE d.confirmed = 0
ORDER BY d.created_at ASC;

-- View: Available Rewards
CREATE OR REPLACE VIEW available_rewards AS
SELECT 
    r.id,
    r.unique_code,
    r.points,
    r.bin_id,
    b.location,
    r.collected,
    r.created_at
FROM rewards_data r
LEFT JOIN bin_data b ON r.bin_id = b.id
WHERE r.collected = 0
ORDER BY r.created_at DESC;

-- View: Bin Status Summary
CREATE OR REPLACE VIEW bin_status_summary AS
SELECT 
    b.id,
    b.location,
    b.current_status,
    b.weight,
    CASE WHEN SUBSTRING(b.current_status, 1, 1) = '1' THEN 'Full' ELSE 'Available' END AS pet_status,
    CASE WHEN SUBSTRING(b.current_status, 2, 1) = '1' THEN 'Full' ELSE 'Available' END AS hdpe_status,
    CASE WHEN SUBSTRING(b.current_status, 3, 1) = '1' THEN 'Full' ELSE 'Available' END AS pp_status,
    CASE WHEN SUBSTRING(b.current_status, 4, 1) = '1' THEN 'Full' ELSE 'Available' END AS others_status,
    b.last_updated
FROM bin_data b;

-- View: User Leaderboard
CREATE OR REPLACE VIEW user_leaderboard AS
SELECT 
    u.id,
    u.name,
    u.username,
    u.rewards_collected,
    COUNT(DISTINCT r.id) AS total_collections,
    u.created_at AS member_since
FROM users u
LEFT JOIN rewards_data r ON r.collected_by = u.id
WHERE u.is_admin = 0
GROUP BY u.id, u.name, u.username, u.rewards_collected, u.created_at
ORDER BY u.rewards_collected DESC;

-- Insert initial admin user (password: admin123)
-- Password is hashed using PHP password_hash()
-- Default password: admin123
INSERT INTO users (name, username, password, is_admin) VALUES
('System Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE username=username;

-- Success message
SELECT 'Database schema created successfully!' AS message;
