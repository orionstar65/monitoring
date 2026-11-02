# Manual verification (no GitHub Actions)

1. On the server or a staging checkout, run PHP lint:
   `find Backend -name '*.php' -print0 | xargs -0 -n1 php -l`
2. After setting up a valid TLS certificate and applying the migration, run:
   `bash Backend/tests/manual_smoke.sh https://monitor.example.com`
3. In a separate browser session register two viewer accounts. An admin should assign one monitor to each and confirm both accounts can only see their own monitor status.
4. With a new native client build sign in as Viewer A. Verify it can select only Viewer A's monitor and heartbeat/screenshot updates the **same monitor id**; Viewer B's monitor must remain untouched. Test client requests without Authorization (401) and attempts to select someone else's id (403).
5. Validate wrong password rate limiting, missing/malformed JPEG rejection, POST-only deletions, CSRF, encrypted HTTPS traffic, admin ZIP download, and Slack inactivity checks.
6. Verify re-signing, expired token (seven days), disconnected server, offline client, and a new monitor pending admin approval on Windows, macOS, and Ubuntu separately.

These scripts intentionally do not run Actions or mutate live data. They are **not** substitutes for platform builds, integration testing, or a security audit. Do not treat installed v1.x binaries as updated: rebuild installers from source.
