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
