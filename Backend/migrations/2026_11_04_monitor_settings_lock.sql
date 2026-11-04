-- Monitor settings lock. Apply ONCE after a verified backup and schema revision 17.
-- Database schema revision only: MonitorClient application version stays v1.5.
-- Separate from assignment_locked: the admin can control each lock independently.
ALTER TABLE tbl_monitor_ips
 ADD COLUMN settings_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_locked;
