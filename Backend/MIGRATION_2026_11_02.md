# Secure identity rollout

Before deployment: take a tested database and screenshot backup. Rotate any database password and Slack webhook committed previously; values remain in Git history even after removal.

1. Verify no duplicate usernames or email addresses exist:
   `SELECT username,COUNT(*) FROM users GROUP BY username HAVING COUNT(*)>1;`
   `SELECT email,COUNT(*) FROM users WHERE email IS NOT NULL GROUP BY email HAVING COUNT(*)>1;`
2. Run **once** after backup: `mysql -u root -p monitoring_db < Backend/migrations/2026_11_02_identity.sql`
   Do NOT run the original database_setup.sql against live data; it drops the database.
3. Create a least-privileged MySQL user (SELECT/INSERT/UPDATE/DELETE on monitoring_db).
   Configure `MONITOR_DB_USER`, `MONITOR_DB_PASSWORD`, `MONITOR_DB_NAME` and `MONITOR_SLACK_WEBHOOK` outside Git, available to PHP.
4. Deploy backend and all client versions together: old clients receive 401 until they sign in.
5. Set up trusted HTTPS, deny direct access to /html/data and /html/exports, and ensure PHP sees Authorization headers.
6. Users register at /html/auth/signup. Admin edits a monitor, assigns an account, and saves.
7. Desktop client signs in, chooses an assigned monitor, then posts with Bearer token. New monitor requests require admin mapping before they can be selected.
8. Check 401 without login, 403 for unassigned monitor, CSRF on dashboard mutations, screenshot access, last-activity status, expiry (7 days), exports and Slack.
9. Periodically purge expired client_sessions and login_attempts; establish a retention schedule and user consent process.

**Do not treat committed secrets as private simply because this repository is private. Rotate them and consider repository-history rewriting after coordination.**

## TLS and PHP environment checklist

All sign-in and telemetry requests now require HTTPS. Use a trusted hostname and certificate with a matching SAN, e.g. `monitor.example.com` on port 443. Configure the server's **webroot** to be the Backend folder and serve `/html/` through its existing front controller; do not publish backups, SQL, or secrets.

If TLS terminates at an *isolated and trusted reverse proxy*, set `MONITOR_TRUST_PROXY_HTTPS=1` in the **PHP server process environment**, and configure the proxy to overwrite `X-Forwarded-Proto: https`. The internal web server must not be directly reachable by untrusted clients in that mode. Without this setting the backend requires HTTPS at Apache itself. Do not use self-signed certificates without installing a properly trusted CA on all three clients.

Test the server after deployment using:
```bash
# From repo root. Lint all PHP files (requires php CLI).
find Backend -name '*.php' -print0 | xargs -0 -n1 php -l

# An unauthenticated request over HTTPS must be 401.
curl -i https://monitor.example.com/eventhandler.php \
  -H 'Content-Type: application/json' -d '{"Event":"Tic"}'
# A direct HTTP request must be 426.
curl -i http://monitor.example.com/eventhandler.php -d '{"Event":"Tic"}'
# Sensitive data must not be directly accessible (403/404).
curl -i https://monitor.example.com/html/data/
curl -i https://monitor.example.com/html/exports/
```

When bootstrapping a fresh database, first execute `database_setup.sql` **only on a newly created empty DB**; then run the migration. Create an initial admin without a default password:
```bash
MONITOR_ADMIN_NAME='Administrator' MONITOR_ADMIN_USERNAME='admin' \
MONITOR_ADMIN_EMAIL='admin@example.com' MONITOR_ADMIN_PASSWORD='<random-12+-char-password>' \
php Backend/create_admin.php
```
The command also needs the `MONITOR_DB_*` variables in the CLI environment. Do not paste actual passwords into shared logs or tickets. Unset the password from your shell after use.

Rebuild new native installers from these source directories: Windows/`build.bat` (Windows with MSYS2), Mac/`xcodebuild -project MonitorClient.xcodeproj -scheme MonitorClient -configuration Release build`, Ubuntu/`build.sh` (Qt/C++ toolchain). These are source changes, not new precompiled packages. Validate each client manually: signin, monitor select, heartbeat, screenshot, and 401 behavior before wide rollout. Existing Release binaries still contain legacy, unauthenticated client behavior.
