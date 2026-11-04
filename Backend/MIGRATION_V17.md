# Database schema revision 17 — MonitorClient stays v1.5

## Behavior
- Accounts use **User No** (unique administrator-editable number) and **Full Name**. The `users.username` field is retired.
- Web portal sign-in accepts **User No or email** + password. Full Name is for display, not a unique credential. An optional email remains supported; users without email must retain their assigned User No.
- User dropdown options in the web portal and desktop apps display **UserNo/Full Name/IP**. Users and Monitors tables instead show **User No, Full Name and IP Address as separate columns**. IP is the **oldest assigned monitor's IP**, ordered by monitor ID. Users with no monitor show `—`. Every owned monitor still appears after user selection.
- Windows/macOS/Ubuntu user selectors apply changes immediately, without Select or Change buttons or a User Selection header. The server preserves existing assignment locks.
- Legacy `tbl_monitor_ips.username` is **not** removed; it is a separate historical column used by the older import scripts.

## Deploying (existing schema-16 database)
1. Back up and verify the MySQL database. Stop web sign-in, account writes and desktop enrollment during cutover.
2. Deploy the schema-17-compatible backend, admin portal and rebuilt Windows/macOS/Ubuntu **MonitorClient v1.5** apps together. Do not change the application version for this schema update.
3. Apply **once**:
   ```bash
   mysql -u root -p monitoring_db < Backend/migrations/2026_11_02_remove_username_v17.sql
   ```
4. Restart PHP/web services if opcode caching is enabled. Existing web sessions are keyed by `users.id` and will refresh the account's full name and User No.
5. Validate sign-in with a User No and with a configured email, account create/edit, empty email behavior, monitor ownership, locked/unlocked user changes and all desktop user dropdowns.
6. Use a direct trusted LAN connection for desktop user-directory access. `MONITOR_ENABLE_USER_NO_ENROLL=1` is now needed only for explicitly approved non-LAN/VPN reverse-proxy deployments. Do not expose the directory to the public internet.

### Note on older scripts
The historical `Backend/database_setup.sql` and older migration files represent pre-v1.7 schema states, not a fresh v1.7 standalone schema. For new databases apply the historical migrations **in order** through v1.7. Do not run the destructive baseline setup against an existing deployment.

### Rollback
Revert the code and restore a consistent pre-v1.7 database backup. Reintroducing a `username` column alone does not restore old login identities. Do not roll back only the PHP code.

## User list is empty in a desktop settings window

The directory response is intentionally not treated as a successful empty list when there is a network or authorization error. Windows, macOS and Ubuntu show the server's response under **User:**.

1. In the settings modal, enter the correct server host and port and click **Test Connection**.
2. Verify that the PHP backend serving `/client_auth.php` is updated. The web portal login page responding **200** does *not* guarantee the client directory API is reachable.
3. Use the server's **private LAN IP** on ports 8924 (restricted HTTP) or 8925 (trusted HTTPS), not `127.0.0.1` or a public relay. A direct private-LAN client is now allowed to list/select users without the old enrollment flag. A server behind a reverse proxy needs an explicitly trusted proxy configuration or a tightly restricted `MONITOR_ENABLE_USER_NO_ENROLL=1` deployment opt-in. `MONITOR_DISABLE_USER_NO_ENROLL=1` forces it off.
4. Test the endpoint from a machine on the trusted network (use HTTPS and the real certificate):
   ```bash
   curl --fail-with-body --silent --show-error \
     -H 'Content-Type: application/json' \
     -d '{"action":"directory"}' https://SERVER:8925/client_auth.php
   ```
   A valid response has `{"success":true,"users":[...]}`; HTTP 503 means enrollment is disabled, HTTP 426 means HTTPS is required, and a network/TLS failure means the client cannot reach the endpoint securely.
5. The directory now returns all accounts with a User No, including `admin` accounts. It shows the UserNo/Full Name/IP label; users with no assigned monitor show `—`. A user without an assigned monitor cannot activate desktop monitoring until an administrator assigns one.
6. Users without monitors still appear as `UserNo/Full Name/—`. Assign a monitor before selecting them for monitoring. A complete native build must be installed to see these UI changes; they do not appear by updating only the PHP backend.

**Security:** The directory reveals users' full names and first assigned monitor IP to clients permitted to reach it. Do not expose this unauthenticated endpoint to the internet. A User No alone does not authenticate a client, and the assignment lock does not protect against impersonation from a new installation.

## Recovery of existing web accounts

After usernames were retired, sign in with **User No or registered email**, not the previous username. Use the CLI-only `Backend/recover_web_login.php --list-admins` command to find existing administrator numbers. If you need a password reset, use the per-admin recovery flow in [LOGIN_RECOVERY.md](LOGIN_RECOVERY.md). It preserves monitor assignments and uses a new administrator-chosen password of at least 12 characters.

Do not assign the same publicly known temporary password to every account. If a credential is shared, an unauthorised person may take over an account before the real user completes the forced password change.
