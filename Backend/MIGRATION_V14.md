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

## Final legacy column removal

The backfill preserves the original `tbl_monitor_ips.username` until the
new server code is deployed and verified. The application now resolves owner names
through `users` instead of relying on this legacy field.

After a staging validation and a fresh backup, run the **final one-time cleanup**:

```bash
mysql -u root -p monitoring_db < Backend/migrations/2026_11_02_drop_legacy_username_v14.sql
```

It recreates the four recent-log reporting views using the assigned account
name, then drops `tbl_monitor_ips.username`. **Do not run this against an old
server build or third-party integrations that still read the legacy column.**
This final DDL operation cannot be undone without restoring a backup.

## Table controls

The User Management and Monitors pages use DataTables v3.1.5 and ColReorder v3.1.2 with state saved per browser, account and table; network access to cdn.datatables.net is required until those files are self-hosted. Tables with existing custom dynamic pagination have not yet been converted. Review those separately to avoid conflicting JavaScript paging behavior.
