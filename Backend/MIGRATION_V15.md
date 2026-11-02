# MonitorClient v1.5 — User No / device pairing rollout

## Before you deploy

**This is a coordinated migration, not a drop-in desktop-only update.**
New clients do not offer sign-in or sign-up. Old desktop password sessions
are deliberately revoked and the old desktop login/signup API returns HTTP 410.

1. Take and verify an offline MySQL backup:
   `mysqldump -u root -p --single-transaction monitoring_db > monitoring_db_before_v15.sql`
2. Ensure all v1.4 schema migrations listed in `Backend/MIGRATION_V14.md` have been
   applied. The legacy username column drop is optional.
3. Schedule a maintenance window. Stop the old desktop clients or server ingestion.
4. Apply **once**:
   `mysql -u root -p monitoring_db < Backend/migrations/2026_11_02_device_pairing_v15.sql`
5. Deploy the updated PHP backend and web portal. Restart PHP if opcode caching
   is active. Rebuild and install the v1.5 Windows, macOS and Ubuntu clients.
6. Run the read-only `bash Backend/tests/v14_preflight.sh` in your repo checkout,
   then validate using a staging environment before resuming production uploads.
7. Keep HTTPS port 8925 and trusted TLS certificates for pairing codes and device
   bearer tokens. Plain HTTP 8924 should only be used in an explicitly isolated LAN.

The schema migration adds `users.user_no` (unique number = ID + 100000),
`tbl_monitor_ips.assignment_locked`, a hashed one-time pairing code and its
expiry, and the `client_sessions.session_type` discriminator. The database
backfills User No for existing accounts; all account-creation routes assign it
after inserting new users. The new client API accepts only `device` sessions.

## Administrator workflow

1. Open **User Management** to see each user's automatically assigned **User No**.
2. Create or assign a monitor in **Monitors → Edit**. Choose the desired user.
3. Choose **Lock User No assignment** when users should not be allowed to
   reassign that monitor themselves. Save the monitor.
4. In the same monitor's Edit page, click **Generate pairing code**.
   This produces a **16-character hexadecimal code**, shown exactly once.
   It expires after 24 hours, is stored only as a SHA-256 hash, and is
   invalidated when redeemed. Share it securely with the intended device user.
5. To re-enroll a replacement device, generate a fresh code. On redemption,
   the previous installation's bearer sessions for that monitor are revoked.
6. To change a locked monitor's owner, edit the assignment in the portal.
   Changing owner revokes previously paired sessions and invalidates any
   outstanding pairing code. Generate a new code for the new owner.

## Desktop application workflow

1. Enter the server address and port, then click **Test Connection**.
2. Choose **User No** from the dropdown directly beneath Server Settings.
   The unauthenticated directory contains user numbers only (not names,
   IPs or monitor details).
3. Enter the administrator's one-time pairing code and click **Pair Device**.
4. After pairing, **Monitor Settings** shows the chosen user's monitors with
   label, OS, machine and IP. The installation may collect or upload data
   only for the monitor to which that pairing code belonged.
5. While unlocked, **Change User No** transfers this paired monitor to the
   selected non-admin user. The server rotates the device bearer token and
   updates ownership atomically. The monitor list refreshes.
6. When locked, the selector is disabled and a message says:
   **"User assignment is locked. Ask your administrator to edit this monitor."**
   The server also returns HTTP 423 to a forged reassignment attempt.
7. Selecting another monitor from the displayed list does **not** give access
   to its events. To pair this installation to a different monitor, ask
   your administrator for that monitor's pairing code.
8. **Disconnect Device** revokes the local bearer token. It does not remove
   the administrator's lock or change the monitor's owner.

## Essential end-to-end checks

- No code: User No list displays without revealing employee names or IPs.
- Invalid code: Enrollment fails; no monitoring token is issued.
- Valid code: Enrollment succeeds once, token survives restart in Windows
  Credential Manager, macOS Keychain, or Linux Secret Service.
- Code reuse: Enrollment fails on the second attempt.
- Paired token: Can upload only to its bound monitor ID. An unpaired token
  must not upload screenshots or log events.
- Locked: Desktop switch fails with HTTP 423, even when invoking the API directly.
- Unlocked: Switch succeeds, user ownership updates, and old token is revoked.
- Admin reassignment: Existing device is invalidated and requires a new code.
- Credentials: Legacy desktop login and signup actions return HTTP 410;
  web portal sign-in and account management continue unchanged.
- Admin: Only the web administrator can generate codes and set assignment locks.
- Notifications: Connection indicator remains visible with notifications off.

## Rollback

Do not simply reinstall an old client over a v1.5 backend. First stop ingestion,
restore a **consistent** pre-v1.5 database backup, and redeploy the matching
backend and old clients together. Restoring invalidated desktop sessions may
re-enable older credentials, so restrict network exposure during rollback.
