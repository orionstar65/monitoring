# ACC Monitor v1.4 — account migration and rollout

**Never run `Backend/database_setup.sql` on an existing installation.** It contains a database drop and will destroy data.

1. Back up the database, and keep the backup outside the web root:
   `mysqldump -u root -p --single-transaction monitoring_db > monitoring_db-before-v14.sql`
2. Apply the existing `Backend/migrations/2026_11_02_identity.sql` **only if not already applied**.
3. Apply `Backend/migrations/2026_11_02_client_v14.sql` **only if not already applied**.
4. Apply `Backend/migrations/2026_11_02_accounts_v14.sql` **only if not already applied**.
5. Run `mysql -u root -p monitoring_db < Backend/migrations/2026_11_02_legacy_owners_v14.sql`.
6. Deploy all updated PHP code (including `client_auth.php`) together. Restart PHP/Apache if opcache is enabled.
7. Review newly imported accounts in User Management. Imported usernames use `legacy_monitor_ID` and must be edited by the administrator. Each imported account has an *unusable, random, locked* initial password; administrators can reset it and require the user to choose a new password.
8. Validate login, signup, new monitor auto assignment, owner restrictions, password changes, and merge operations using both administrator and viewer accounts before production use.

## Data retention and compatibility

This backfill intentionally **does not drop** `tbl_monitor_ips.username` yet. Several legacy reporting and ingestion paths still reference it, and dropping it without a complete consumer audit would break production. The new UI and ownership enforcement use `owner_user_id` and `users.name`, never the monitor's legacy username. A separate migration can remove the column after all legacy reporting and ingestion queries have been updated.

**Important:** The requested common temporary password `1234567890` is weak. Treat it as a one-time bootstrap secret and require immediate change. Admin resets revoke all desktop sessions. Consider replacing common temporary passwords with random, single-use tokens before making this service internet-accessible.

## Table controls

The User Management and Monitors pages use DataTables v3.1.5 and ColReorder v3.1.2 with state saved per browser, account and table; network access to cdn.datatables.net is required until those files are self-hosted. Tables with existing custom dynamic pagination have not yet been converted. Review those separately to avoid conflicting JavaScript paging behavior.
