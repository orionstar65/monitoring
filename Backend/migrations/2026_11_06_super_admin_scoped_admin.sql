-- Apply once during maintenance after backing up monitoring_db.
-- Deploy this migration with the matching PHP/UI changes in one release.
-- Existing admins are elevated to the new unrestricted Super Admin role.
-- Capture the pre-migration enum before changing it. Re-running the migration
-- must NEVER promote newly assigned delegated 'admin' accounts to Super Admin.
-- MySQL ENUM column_type is lowercase for this schema.
SET @monitor_legacy_admin_role_schema = (
    SELECT IF(COUNT(*) = 1, 1, 0)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role'
      AND COLUMN_TYPE = 'enum(''admin'',''user'')'
);

ALTER TABLE users MODIFY COLUMN role ENUM('super-admin','admin','user') NOT NULL DEFAULT 'user';
UPDATE users SET role='super-admin'
WHERE role='admin' AND @monitor_legacy_admin_role_schema = 1;

-- A delegated Admin sees only users explicitly assigned by a Super Admin.
-- Removing an assignment revokes access on the next request (never trust session copies).
CREATE TABLE IF NOT EXISTS admin_user_assignments (
    admin_user_id INT NOT NULL,
    managed_user_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(admin_user_id,managed_user_id),
    KEY idx_managed_user_id(managed_user_id),
    CONSTRAINT fk_admin_assignments_admin FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_assignments_managed FOREIGN KEY(managed_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
