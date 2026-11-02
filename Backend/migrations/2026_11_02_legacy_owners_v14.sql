-- ACC Monitor v1.4: one-time legacy monitor ownership backfill.
-- REQUIREMENTS:
--   1. Back up monitoring_db.
--   2. Apply identity, client_v14, accounts_v14 migrations first.
--   3. Review owner_user_id and account names after migration.
-- Safe for existing assigned monitors: their owner_user_id is never overwritten.
-- A distinct locked account is created for each distinct legacy monitor username.
-- Blank legacy names are treated as individual unidentified machines.
-- Passwords are deliberately unusable until an administrator assigns a password.
-- Re-running after successful import is a no-op (no remaining unowned monitors).
START TRANSACTION;
CREATE TEMPORARY TABLE legacy_monitor_accounts (
  legacy_name VARCHAR(255) NOT NULL,
  import_username VARCHAR(50) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  PRIMARY KEY (import_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO legacy_monitor_accounts(legacy_name,import_username,full_name)
SELECT legacy_name,CONCAT('legacy_monitor_',MIN(id)),LEFT(legacy_name,150)
FROM (
 SELECT id,COALESCE(NULLIF(TRIM(username),''),CONCAT('Unidentified monitor #',id)) AS legacy_name
 FROM tbl_monitor_ips WHERE owner_user_id IS NULL
) names
GROUP BY legacy_name;

-- Abort rather than attaching a legacy device to an existing unrelated account.
-- MySQL errors on a duplicate username due to the unique constraint.
INSERT INTO users (name,username,email,password,role,must_change_password)
SELECT full_name,import_username,NULL,
       CONCAT('!UNUSABLE_MIGRATION_',UUID()),'viewer',1
FROM legacy_monitor_accounts;

UPDATE tbl_monitor_ips m
JOIN legacy_monitor_accounts mapping
 ON mapping.legacy_name = COALESCE(NULLIF(TRIM(m.username),''),
                         CONCAT('Unidentified monitor #',m.id))
JOIN users u ON u.username=mapping.import_username
SET m.owner_user_id=u.id,m.requested_user_id=NULL
WHERE m.owner_user_id IS NULL;

COMMIT;
DROP TEMPORARY TABLE IF EXISTS legacy_monitor_accounts;

-- Review these results in the web portal and edit each imported username.
-- The legacy monitor username column intentionally remains for compatibility
-- until the old reporting views and event ingestion writers have been migrated.
SELECT u.id,u.name,u.username,u.must_change_password,
       COUNT(m.id) AS assigned_monitors
FROM users u JOIN tbl_monitor_ips m ON m.owner_user_id=u.id
WHERE u.username LIKE 'legacy_monitor_%'
GROUP BY u.id,u.name,u.username,u.must_change_password
ORDER BY u.id;
