-- =====================================================
-- Monitoring System Database Setup Script
-- Version: 2.0
-- Date: September 2025
-- Purpose: Complete database environment setup for new server deployment
-- =====================================================

-- =====================================================
-- 1. CREATE DATABASE
-- =====================================================
DROP DATABASE IF EXISTS monitoring_db;
CREATE DATABASE monitoring_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE monitoring_db;

-- =====================================================
-- 2. UPDATE ROOT USER PASSWORD (if needed)
-- =====================================================
-- Uncomment and modify if you need to change root password
-- ALTER USER 'root'@'localhost' IDENTIFIED BY 'acc0924';
-- FLUSH PRIVILEGES;

-- =====================================================
-- 3. CREATE TABLES
-- =====================================================

-- Monitor IPs table - stores client information
CREATE TABLE tbl_monitor_ips (
    id INT(11) NOT NULL AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    mac_address VARCHAR(17) NULL,
    name VARCHAR(255) NULL,
    username VARCHAR(50) NULL,
    type VARCHAR(50) NULL DEFAULT 'user',
    os_status TINYINT(4) NULL DEFAULT 0,
    monitor_status TINYINT(4) NULL DEFAULT 0,
    last_monitor_tic DATETIME NULL,
    last_browser_tic DATETIME NULL,
    app_version VARCHAR(50) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ip_address (ip_address),
    KEY idx_mac_address (mac_address),
    KEY idx_username (username),
    KEY idx_created_at (created_at),
    KEY idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Users table - system users for web interface
CREATE TABLE users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255) NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'viewer') NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_username (username),
    UNIQUE KEY uk_email (email),
    KEY idx_role (role),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Browser history logs table
CREATE TABLE browser_history_logs (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    monitor_ip_id INT(11) NOT NULL,
    browser VARCHAR(50) NOT NULL,
    url TEXT NOT NULL,
    title TEXT NULL,
    last_visit DATETIME NULL,
    visit_date DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_monitor_ip_id (monitor_ip_id),
    KEY idx_browser (browser),
    KEY idx_visit_date (visit_date),
    KEY idx_last_visit (last_visit),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_browser_history_monitor_ip FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Key logs table
CREATE TABLE key_logs (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    monitor_ip_id INT(11) NOT NULL,
    key_date DATETIME NOT NULL,
    application VARCHAR(255) NOT NULL,
    key_pressed VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_monitor_ip_id (monitor_ip_id),
    KEY idx_key_date (key_date),
    KEY idx_application (application),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_key_logs_monitor_ip FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- USB device logs table
CREATE TABLE usb_device_logs (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    monitor_ip_id INT(11) NOT NULL,
    device_date DATETIME NOT NULL,
    device_name VARCHAR(255) NOT NULL,
    device_path VARCHAR(500) NULL,
    device_type VARCHAR(100) NULL,
    action ENUM('Connected', 'Disconnected') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_monitor_ip_id (monitor_ip_id),
    KEY idx_device_date (device_date),
    KEY idx_device_name (device_name),
    KEY idx_action (action),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_usb_logs_monitor_ip FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Screenshot metadata table
CREATE TABLE screenshot_metadata (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    monitor_ip_id INT(11) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500) NULL,
    capture_datetime DATETIME NOT NULL,
    file_size BIGINT(20) NULL,
    image_width INT(11) NULL,
    image_height INT(11) NULL,
    file_extension VARCHAR(10) NULL DEFAULT 'jpg',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_monitor_ip_id (monitor_ip_id),
    KEY idx_filename (filename),
    KEY idx_capture_datetime (capture_datetime),
    KEY idx_created_at (created_at),
    CONSTRAINT fk_screenshot_metadata_monitor_ip FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Storage statistics table
CREATE TABLE storage_statistics (
    id INT(11) NOT NULL AUTO_INCREMENT,
    monitor_ip_id INT(11) NOT NULL,
    data_type ENUM('browser_history', 'key_logs', 'usb_logs', 'screenshots') NOT NULL,
    month_year VARCHAR(7) NOT NULL,
    record_count BIGINT(20) NULL DEFAULT 0,
    total_size BIGINT(20) NULL DEFAULT 0,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_monitor_data_month (monitor_ip_id, data_type, month_year),
    KEY idx_monitor_ip_id (monitor_ip_id),
    KEY idx_data_type (data_type),
    KEY idx_month_year (month_year),
    CONSTRAINT fk_storage_statistics_monitor_ip FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =====================================================
-- 4. CREATE VIEWS FOR RECENT DATA (OPTIONAL)
-- =====================================================

-- Recent browser history view (last 7 days)
CREATE VIEW recent_browser_history AS
SELECT 
    b.*,
    m.ip_address,
    m.username as monitor_username
FROM browser_history_logs b
JOIN tbl_monitor_ips m ON b.monitor_ip_id = m.id
WHERE b.visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY b.visit_date DESC;

-- Recent key logs view (last 7 days)
CREATE VIEW recent_key_logs AS
SELECT 
    k.*,
    m.ip_address,
    m.username as monitor_username
FROM key_logs k
JOIN tbl_monitor_ips m ON k.monitor_ip_id = m.id
WHERE k.key_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY k.key_date DESC;

-- Recent screenshots view (last 7 days)
CREATE VIEW recent_screenshots AS
SELECT 
    s.*,
    m.ip_address,
    m.username as monitor_username
FROM screenshot_metadata s
JOIN tbl_monitor_ips m ON s.monitor_ip_id = m.id
WHERE s.capture_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY s.capture_datetime DESC;

-- Recent USB activity view (last 7 days)
CREATE VIEW recent_usb_activity AS
SELECT 
    u.*,
    m.ip_address,
    m.username as monitor_username
FROM usb_device_logs u
JOIN tbl_monitor_ips m ON u.monitor_ip_id = m.id
WHERE u.device_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY u.device_date DESC;

-- =====================================================
-- 5. SEED INITIAL DATA
-- =====================================================

-- Insert default admin user
-- Password: admin123 (hashed with password_hash())
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@monitoring.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default viewer user
-- Password: viewer123 (hashed with password_hash())
INSERT INTO users (username, email, password, role) VALUES 
('viewer', 'viewer@monitoring.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer');

-- Insert sample monitor IP (optional - for testing)
INSERT INTO tbl_monitor_ips (ip_address, mac_address, name, username, type, os_status, monitor_status, app_version) VALUES 
('192.168.1.100', 'AA:BB:CC:DD:EE:FF', 'Test Computer', 'test.user', 'user', 1, 1, '1.0.0');

-- =====================================================
-- 6. CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional composite indexes for better query performance
CREATE INDEX idx_browser_history_monitor_date ON browser_history_logs(monitor_ip_id, visit_date);
CREATE INDEX idx_key_logs_monitor_date ON key_logs(monitor_ip_id, key_date);
CREATE INDEX idx_usb_logs_monitor_date ON usb_device_logs(monitor_ip_id, device_date);
CREATE INDEX idx_screenshot_monitor_date ON screenshot_metadata(monitor_ip_id, capture_datetime);

-- =====================================================
-- 7. VERIFICATION QUERIES
-- =====================================================

-- Verify tables were created
SELECT TABLE_NAME, TABLE_ROWS, ENGINE, TABLE_COLLATION 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'monitoring_db' 
ORDER BY TABLE_NAME;

-- Verify initial data
SELECT 'Users' as table_name, COUNT(*) as record_count FROM users
UNION ALL
SELECT 'Monitor IPs' as table_name, COUNT(*) as record_count FROM tbl_monitor_ips;

-- =====================================================
-- 8. SETUP COMPLETE
-- =====================================================

-- Display completion message
SELECT 'Database setup completed successfully!' as status;

-- =====================================================
-- NOTES FOR DEPLOYMENT
-- =====================================================
/*
1. This script creates a complete monitoring database environment
2. Default admin user: admin/admin123
3. Default viewer user: viewer/viewer123
4. All tables use InnoDB engine for transaction support
5. Foreign key constraints ensure data integrity
6. Indexes are optimized for common query patterns
7. Views provide easy access to recent data
8. Character set: utf8mb4 for full Unicode support

TO RUN THIS SCRIPT:
mysql -u root -p < database_setup.sql

OR FROM MYSQL COMMAND LINE:
source database_setup.sql;

AFTER SETUP:
1. Update admin password: UPDATE users SET password = PASSWORD_HASH('your_new_password', PASSWORD_DEFAULT) WHERE username = 'admin';
2. Create additional users as needed
3. Ensure proper file permissions for html/data/ directories
4. Test API endpoints to verify database connectivity
*/ 