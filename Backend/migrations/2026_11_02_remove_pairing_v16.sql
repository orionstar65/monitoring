-- v1.6: remove the retired pairing-code mechanism.
-- Requires v1.5 migration (2026_11_02_device_pairing_v15.sql) first.
-- Back up the database and take a maintenance window before altering tables.
ALTER TABLE tbl_monitor_ips
  DROP COLUMN pairing_code_hash,
  DROP COLUMN pairing_expires_at;

-- Preserve users.user_no, assignment_locked and session_type.
-- Existing device sessions continue to work; no matching pairing codes remain.
