-- Monitoring v1.5: administrator-entered User No and two roles.
-- BACK UP monitoring_db first. Run after deploying the matching PHP changes,
-- during a brief maintenance window. MySQL DDL commits implicitly.
-- Existing viewer accounts become user accounts (aggregate-only access).
UPDATE users SET role='user' WHERE role='viewer';
ALTER TABLE users MODIFY COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user';
ALTER TABLE users DROP COLUMN email;
-- No User No generation is performed. Existing numbers are retained.
