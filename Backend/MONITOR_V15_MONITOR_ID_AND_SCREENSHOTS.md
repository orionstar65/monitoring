# MonitorClient v1.5 — independent device locks and screenshot media repair

## Behavior

- One account can own **multiple monitors**. Each installation explicitly uses
  **Add new...** to create its own monitor record, session, and monitor ID.
  Locking monitor #1046, for example, does not lock the *user account* and
  does not stop that user registering a different machine.
- A locked monitor cannot be selected from another installation. The lock
  remains associated with `tbl_monitor_ips.id`.
- No custom monitor **Label** field is needed. After the one-time migration,
  `tbl_monitor_ips.name` no longer exists. UI shows `Device #<id>`, OS,
  machine and IP instead; the real-time sidebar's primary title is the
  **assigned user's Full Name**.
- Historical screenshots, browser history and activity logs retain their
  original `monitor_ip_id` foreign keys. Nothing is reattributed by IP/MAC.

**Security:** The existing User No enrollment API is LAN-restricted but
unauthenticated. Knowledge of a User No is not identity proof. Restrict the
endpoint with firewall/VPN and consider admin-approved pairing before using
it in untrusted networks.

## Deployment (backend first, then native clients)

```bash
cd /var/monitor
BACKUP="$HOME/monitoring-before-monitor-id-$(date +%Y%m%d-%H%M%S).sql"
mysqldump -u root -p --single-transaction monitoring_db > "$BACKUP"
test -s "$BACKUP" || exit 1
git pull --ff-only origin main

# Verify old schema state; the drop is a ONE-TIME migration:
mysql -u root -p monitoring_db -e "SHOW COLUMNS FROM tbl_monitor_ips LIKE 'name';"

# Run ONLY if name currently exists. Revision 17 and the preceding monitor
# migrations (including remark and settings_locked) must already be applied.
mysql -u root -p monitoring_db < Backend/migrations/2026_11_04_drop_monitor_name_v15.sql

sudo apache2ctl configtest && sudo systemctl reload apache2
```

If the `name` column does **not** exist already, SKIP the DROP migration.
Never run the legacy `2026_11_02_client_v14.sql` migration after dropping
`name`, because its historical `ADD remark AFTER name` expects that column.
Do not run `Backend/database_setup.sql` on your existing database.

Rebuild/install the new Windows/macOS/Ubuntu sources on machines that are
enrolling or selecting monitors. Client version remains **v1.5**.

## Verify screenshot monitor #1046

The web URL may legitimately look like:

```text
http://192.168.2.241:8924/html/view/view_file?path=data%2Fthumbnails%2Fid_1046%2F2026-11-04%2F1046_2026-11-04_17.22.01_fc5b5497a624df59.jpg
```

The URL encoding (`%2F` for a slash) is **normal**. The endpoint requires a
valid logged-in admin session. When a screenshot is stored on the server but
the page cannot show it, the likely causes include wrong working-directory
storage roots, unreadable files, or database metadata referencing a missing
thumbnail.

New uploads now use absolute `Backend/html/data/` paths. The authenticated
viewer checks both this directory and the historical repository-root
`html/data/` location, verifies canonical paths and readable files, and
falls back from an absent thumbnail to its original screenshot.

Check the exact filename and the database rows from the backend host:

```bash
cd /var/monitor
php Backend/diagnose_screenshot_media.php \
  --path='data/thumbnails/id_1046/2026-11-04/1046_2026-11-04_17.22.01_fc5b5497a624df59.jpg'

sudo /usr/local/sbin/monitor-with-db-env \
  php Backend/diagnose_screenshot_media.php --monitor-id=1046

php Backend/tests/monitor_media_paths_test.php
php Backend/tests/monitor_settings_v15_contract.php
php Backend/tests/v15_unified_settings_and_live_view_contract.php
```

Inspect the media request in browser Developer Tools → Network:
- **200 + image/jpeg:** backend served the file; refresh browser cache
- **302 to login:** browser session/cookie is missing or expired
- **403:** authorization or proxy configuration issue
- **404:** screenshot or thumbnail cannot be resolved/read; use diagnostics
- **500:** inspect Apache/PHP error log and `php -l` for the updated backend

Typical log command (read-only):
```bash
sudo tail -n 100 /var/log/apache2/error.log
```

If a file exists but the PHP process cannot read it, check its directory
permissions for the Apache worker account (`www-data` on most Ubuntu
setups). Do **not** recursively `chmod 777` private screenshots.

## Validation

Verify User No selection on a second installation creates a *new* monitor,
not an association with an existing locked monitor. Verify the old monitor
remains locked and maintains its screenshot identity. Confirm the new
machine's screenshots appear under its own ID, and refresh the live page
to see Full Name titles and thumbnails.

The previous one-time metadata alignment tool remains available at
`Backend/migrate_legacy_monitor_data_once.php`, but it does not move files
or reassign monitor IDs. Avoid running it unnecessarily: use the
diagnostic above to identify missing/incorrect paths first.
