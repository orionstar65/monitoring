-- Schema-only change; MonitorClient stays v1.5.
-- Apply ONCE after a verified backup and schema revision 17.
-- Monitor identity is the database monitor ID bound to a bearer session,
-- not an IP or MAC address. DHCP/NAT may yield shared/reassigned IPs.
-- The v1.4 identity migration already removed the unique-IP and unique-MAC
-- indexes; do not drop them again.
ALTER TABLE tbl_monitor_ips
 ADD COLUMN settings_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_locked,
 ADD COLUMN last_seen_ip VARCHAR(45) NULL AFTER ip_address;
