# MonitorClient v1.6 — code-free User No selection

## Changes
- Desktop apps no longer display or submit pairing codes. Choose **User No** below **Test Connection**, then **Select User No**.
- The server registers a device session bound to the first monitor assigned to that user. Other assigned monitors are visible under Monitor Settings; selecting one changes the active monitor, with a server-side ownership check.
- Admins can edit **User No** in **Users → Edit**. It must be unique and numeric (1–15 digits, no leading zero). Existing desktop sessions resolve the updated number on refresh.
- Admins still control **Lock User No assignment** on each monitor. Locked assignments reject `switch_user` and `logout` with HTTP 423. Changing the owner through the admin portal invalidates sessions for that monitor.
- The legacy pairing-code generation action and the admin pairing panel are removed.

## Migration (from v1.5)
1. Back up the entire MySQL database and verify restore access.
2. Apply all prerequisite v1.4 and v1.5 migrations if not already applied. The v1.5 migration is still the source of `user_no`, `assignment_locked`, and device sessions.
3. Deploy the v1.6 PHP backend and admin portal. Rebuild Windows, macOS, and Ubuntu desktop clients from this commit.
4. Apply **once**:
   ```bash
   mysql -u root -p monitoring_db < Backend/migrations/2026_11_02_remove_pairing_v16.sql
   ```
5. Set `MONITOR_ENABLE_USER_NO_ENROLL=1` **only on a server reachable through your trusted LAN or VPN**. Restart PHP/web services so the environment variable is loaded. Without this explicit server-side opt-in, directory/enroll requests return HTTP 503.
6. Test from a non-production user and monitor: load the User No list, select a user, view all owned monitors, activate another owned monitor, change user while unlocked, and verify HTTP 423 for a locked monitor.

## Important security limitation
A User No is a public identifier, **not an authentication credential**. With no pairing code, device certificate, or other identity check, a client capable of reaching the enrollment endpoint can select another listed User No. The assignment lock protects changes by an existing session, but **cannot stop a new installation from selecting another User No**. For sensitive event and screenshot data, do not expose code-free enrollment to the public internet. Use firewall restrictions and a trusted LAN/VPN; for stronger protection, require administrator-approved device registration or managed client certificates without a user-visible pairing code.

## Backward compatibility
- Previous desktop clients that still require a pairing code are incompatible with v1.6 enrollment; upgrade them.
- Pairing database columns are removed; the migration is deliberately not reversible without restoring your pre-v1.6 database backup.
- The older `MIGRATION_V15.md` describes the superseded historical pairing workflow and should not be used as the current client onboarding instructions.
- Do not run GitHub Actions; validate using local staging and smoke tests.
