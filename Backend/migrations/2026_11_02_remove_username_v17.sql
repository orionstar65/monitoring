-- v1.7: retire the users.username column after switching web login to User No/email.
-- Requires all earlier identity, v1.4, v1.5 and v1.6 migrations.
-- Coordinate this with a backend maintenance-window deployment; older PHP
-- controllers that still select users.username will fail after this migration.
-- Keep tbl_monitor_ips.username, which contains legacy imported monitor data.
ALTER TABLE users DROP COLUMN username;
