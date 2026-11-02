# MonitorClient v1.7 — User No / Full Name / IP

## Behavior
- Accounts use **User No** (unique administrator-editable number) and **Full Name**. The `users.username` field is retired.
- Web portal sign-in accepts **User No or email** + password. Full Name is for display, not a unique credential. An optional email remains supported; users without email must retain their assigned User No.
- User directories and web selectors display **UserNo / Full Name / IP**. IP is the **oldest assigned monitor's IP**, ordered by monitor ID. Users with no monitor show `—`. Every owned monitor still appears after user selection.
- Windows/macOS/Ubuntu user selectors apply changes immediately, without Select or Change buttons or a User Selection header. The server preserves existing assignment locks.
- Legacy `tbl_monitor_ips.username` is **not** removed; it is a separate historical column used by the older import scripts.

## Deploying (existing v1.6 database)
1. Back up and verify the MySQL database. Stop web sign-in, account writes and desktop enrollment during cutover.
2. Deploy the v1.7 backend, admin portal and the rebuilt Windows/macOS/Ubuntu clients together.
3. Apply **once**:
   ```bash
   mysql -u root -p monitoring_db < Backend/migrations/2026_11_02_remove_username_v17.sql
   ```
4. Restart PHP/web services if opcode caching is enabled. Existing web sessions are keyed by `users.id` and will refresh the account's full name and User No.
5. Validate sign-in with a User No and with a configured email, account create/edit, empty email behavior, monitor ownership, locked/unlocked user changes and all desktop user dropdowns.
6. Confirm `MONITOR_ENABLE_USER_NO_ENROLL=1` is enabled **only on a trusted LAN/VPN**. The unauthenticated directory now includes full names and assigned monitor IPs; protect it accordingly.

### Note on older scripts
The historical `Backend/database_setup.sql` and older migration files represent pre-v1.7 schema states, not a fresh v1.7 standalone schema. For new databases apply the historical migrations **in order** through v1.7. Do not run the destructive baseline setup against an existing deployment.

### Rollback
Revert the code and restore a consistent pre-v1.7 database backup. Reintroducing a `username` column alone does not restore old login identities. Do not roll back only the PHP code.
