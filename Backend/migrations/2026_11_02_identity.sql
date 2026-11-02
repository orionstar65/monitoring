-- Run once, after a verified backup. Existing users are not deleted.
ALTER TABLE users ADD COLUMN name VARCHAR(150) NULL AFTER id;
UPDATE users SET name=username WHERE name IS NULL OR name='';
ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);
ALTER TABLE users ADD UNIQUE KEY uq_users_email (email);
ALTER TABLE tbl_monitor_ips DROP INDEX uk_ip_address, DROP INDEX uk_mac_address;
ALTER TABLE tbl_monitor_ips ADD COLUMN owner_user_id INT NULL AFTER id;
ALTER TABLE tbl_monitor_ips ADD COLUMN requested_user_id INT NULL AFTER owner_user_id;
ALTER TABLE tbl_monitor_ips ADD INDEX idx_monitor_owner (owner_user_id);
ALTER TABLE tbl_monitor_ips ADD INDEX idx_monitor_requested (requested_user_id);
ALTER TABLE tbl_monitor_ips ADD CONSTRAINT fk_monitor_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE tbl_monitor_ips ADD CONSTRAINT fk_monitor_request FOREIGN KEY (requested_user_id) REFERENCES users(id) ON DELETE SET NULL;
CREATE TABLE client_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 token_hash CHAR(64) NOT NULL UNIQUE,
 user_id INT NOT NULL,
 monitor_id INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 revoked_at DATETIME NULL,
 INDEX idx_session_user (user_id),
 INDEX idx_session_expiry (expires_at),
 CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_session_monitor FOREIGN KEY (monitor_id) REFERENCES tbl_monitor_ips(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 attempt_key CHAR(64) NOT NULL,
 attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_login_attempts (attempt_key, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
