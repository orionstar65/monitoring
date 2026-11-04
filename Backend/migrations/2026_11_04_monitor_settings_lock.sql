-- Schema-only change; MonitorClient stays v1.5.
-- Apply ONCE after a verified backup and schema revision 17.
-- Monitor identity is the database monitor ID bound to a bearer session,
-- not an IP or MAC address. DHCP/NAT may yield shared/reassigned IPs.
ALTER TABLE tbl_monitor_ips
 ADD COLUMN settings_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_locked,
 ADD COLUMN last_seen_ip VARCHAR(45) NULL AFTER ip_address;

-- Existing installations used unique IP as their monitor identity.
-- Drop that legacy uniqueness so multiple real devices may share an IP.
-- Preserve MAC uniqueness for optional inventory metadata.
ALTER TABLE tbl_monitor_ips DROP INDEX uk_ip_address;
