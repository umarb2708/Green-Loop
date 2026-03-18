-- Green Loop Database Schema
-- MySQL Database for Smart Plastic Collection Bin System

CREATE DATABASE IF NOT EXISTS greenloop_db;
USE greenloop_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    rewards_collected INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Product Data Table
CREATE TABLE IF NOT EXISTS product_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_id VARCHAR(10) UNIQUE NOT NULL,
    manufacturer VARCHAR(100) NOT NULL,
    type ENUM('PET', 'HDPE', 'PP', 'Others') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bin Data Table
CREATE TABLE IF NOT EXISTS bin_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(200) NOT NULL,
    current_status VARCHAR(4) DEFAULT '0000',
    weight FLOAT DEFAULT 0.0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Rewards Data Table
CREATE TABLE IF NOT EXISTS rewards_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_code VARCHAR(6) UNIQUE NOT NULL,
    points INT NOT NULL,
    collected TINYINT(1) DEFAULT 0,
    bin_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bin_id) REFERENCES bin_data(id)
);

-- Insert default admin user (username: admin, password: admin123)
-- Password is hashed using PHP password_hash() - you should change this after first login
INSERT INTO users (name, username, password, is_admin) 
VALUES ('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Sample product data for testing
INSERT INTO product_data (qr_id, manufacturer, type) 
VALUES 
    ('ABC1234567', 'Coca Cola', 'PET'),
    ('XYZ9876543', 'Pepsi', 'PET'),
    ('DEF4567890', 'Nestle', 'HDPE'),
    ('GHI7890123', 'Unilever', 'PP');
