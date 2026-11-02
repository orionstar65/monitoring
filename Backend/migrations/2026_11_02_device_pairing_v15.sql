-- v1.5 device pairing: apply only once, AFTER all v1.4 migrations and a verified backup.
-- User No is stable, numeric, unique. Do not use a generated column based on
-- AUTO_INCREMENT id: MySQL disallows referencing it from generated expressions.
ALTER TABLE users ADD COLUMN user_no BIGINT UNSIGNED NULL;
UPDATE users SET user_no=id+100000 WHERE user_no IS NULL;
ALTER TABLE users ADD UNIQUE KEY uq_users_user_no (user_no);
-- All account creation routes assign (id+100000) immediately after INSERT.
ALTER TABLE tbl_monitor_ips
 ADD COLUMN assignment_locked TINYINT(1) NOT NULL DEFAULT 0,
 ADD COLUMN pairing_code_hash CHAR(64) NULL,
 ADD COLUMN pairing_expires_at DATETIME NULL;
ALTER TABLE client_sessions
 ADD COLUMN session_type ENUM('legacy','device') NOT NULL DEFAULT 'legacy',
 ADD INDEX idx_device_monitor (monitor_id, session_type);
-- All old account/password desktop sessions are invalidated at cutover. The new
-- application receives a new token only through admin-generated pairing codes.
UPDATE client_sessions SET revoked_at=NOW() WHERE revoked_at IS NULL;
