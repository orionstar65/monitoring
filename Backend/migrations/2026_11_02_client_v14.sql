-- Apply once AFTER the 2026_11_02_identity.sql migration and a verified backup.
-- NULL expiration: remembered client sessions remain usable until explicitly revoked.
ALTER TABLE client_sessions MODIFY COLUMN expires_at DATETIME NULL DEFAULT NULL;
ALTER TABLE tbl_monitor_ips ADD COLUMN remark VARCHAR(500) NULL AFTER name;
