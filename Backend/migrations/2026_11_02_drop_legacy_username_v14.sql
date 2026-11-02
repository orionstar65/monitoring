-- ACC Monitor v1.4: FINAL optional one-time legacy username cleanup.
-- Apply AFTER the identity, client_v14, accounts_v14 and legacy_owners_v14
-- migrations AND after deploying the corresponding PHP code.
-- Verify a current database backup and test these queries in staging first.
-- This migration intentionally drops the monitor.username column.
-- If any custom scripts / external integrations still refer to it, update them first.

CREATE OR REPLACE VIEW recent_browser_history AS
SELECT b.*,m.ip_address,owner.name AS monitor_username
FROM browser_history_logs b
JOIN tbl_monitor_ips m ON b.monitor_ip_id=m.id
LEFT JOIN users owner ON owner.id=m.owner_user_id
WHERE b.visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY);

CREATE OR REPLACE VIEW recent_key_logs AS
SELECT k.*,m.ip_address,owner.name AS monitor_username
FROM key_logs k
JOIN tbl_monitor_ips m ON k.monitor_ip_id=m.id
LEFT JOIN users owner ON owner.id=m.owner_user_id
WHERE k.key_date >= DATE_SUB(NOW(), INTERVAL 7 DAY);

CREATE OR REPLACE VIEW recent_screenshots AS
SELECT s.*,m.ip_address,owner.name AS monitor_username
FROM screenshot_metadata s
JOIN tbl_monitor_ips m ON s.monitor_ip_id=m.id
LEFT JOIN users owner ON owner.id=m.owner_user_id
WHERE s.capture_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY);

CREATE OR REPLACE VIEW recent_usb_activity AS
SELECT u.*,m.ip_address,owner.name AS monitor_username
FROM usb_device_logs u
JOIN tbl_monitor_ips m ON u.monitor_ip_id=m.id
LEFT JOIN users owner ON owner.id=m.owner_user_id
WHERE u.device_date >= DATE_SUB(NOW(), INTERVAL 7 DAY);

-- A MySQL column drop is DDL and auto-commits: back up before running.
ALTER TABLE tbl_monitor_ips DROP COLUMN username;

SELECT m.id,owner.name AS full_name,owner.username AS account_username
FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id
ORDER BY m.id LIMIT 20;
