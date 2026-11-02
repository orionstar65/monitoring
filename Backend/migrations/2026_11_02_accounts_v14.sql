-- Apply AFTER the 2026-11-02 identity and client v1.4 migrations.
-- Back up monitoring_db before running. One-time schema migration.
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password;
CREATE INDEX idx_monitor_owner_activity ON tbl_monitor_ips(owner_user_id,last_monitor_tic);
