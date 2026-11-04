# v1.5 legacy monitoring data alignment — one-time procedure

This procedure is **not** an application version bump. It reconciles the old
assignment and settings lock flags and normalizes *resolvable* screenshot
metadata paths for the updated real-time page.

The script **never moves or deletes files**, does not infer identity from a MAC
or IP address, and never reassigns historical screenshot, browser, key or USB
rows to a different monitor ID. Legacy `ip_...` folders remain supported by the
media viewer when their file paths still resolve under the approved data tree.

## Before running

Ensure the updated backend is deployed and that schema revision 17 plus
`2026_11_04_monitor_settings_lock.sql` have already been applied. Apply that
one-time schema migration **only if** the `settings_locked` and
`last_seen_ip` fields are absent. Do not rerun an already applied ALTER TABLE.

Make a real database backup and preserve it outside the web document root:

```bash
cd /var/monitor
git pull --ff-only origin main
BACKUP="$HOME/monitoring-before-alignment-$(date +%Y%m%d-%H%M%S).sql"
mysqldump -u root -p --single-transaction monitoring_db > "$BACKUP"
test -s "$BACKUP" && echo "Backup exists: $BACKUP"
```

You can review current migration state without editing data:

```bash
mysql -u root -p monitoring_db -e "
 SHOW COLUMNS FROM tbl_monitor_ips LIKE 'settings_locked';
 SHOW COLUMNS FROM tbl_monitor_ips LIKE 'last_seen_ip';
 SELECT COUNT(*) AS screenshot_rows FROM screenshot_metadata;
 SELECT COUNT(*) AS unassigned FROM tbl_monitor_ips WHERE owner_user_id IS NULL;"
```

## Preview (strictly read-only)

Use the root-owned environment helper installed during login recovery. It
reads your Apache database environment without printing credentials and then
drops privileges before executing PHP.

```bash
sudo /usr/local/sbin/monitor-with-db-env \
 php Backend/migrate_legacy_monitor_data_once.php --dry-run
```

The output reports screenshot rows, paths that can be aligned, missing or
ambiguous files, missing thumbnails, mismatched legacy locks and monitors with
no owner. **Review missing or unassigned records manually**. No records or
filesystem paths are changed in preview mode.

## Apply once (backup required)

Use the same backup path created above:

```bash
sudo /usr/local/sbin/monitor-with-db-env \
 php Backend/migrate_legacy_monitor_data_once.php --apply --backup="$BACKUP"
```

The script updates stored screenshot metadata to portable `data/screens/...`
and `data/thumbnails/...` paths **only where the physical file exists inside
the permitted folders**. It combines any legacy assignment/settings locks:
if either was locked, the unified lock becomes locked, preserving the more
restrictive state. A marker prevents a second apply.

The update is transactional for database rows. Missing media, unreadable
metadata, and unowned legacy records are not guessed or deleted. The script
does not rename `ip_...` folders to `id_...`, since doing so could break
historical ownership and references. The web viewer now understands both
path styles and falls back to the full screenshot when a thumbnail is missing.

After applying, restart Apache if needed, reload the live dashboard, and
verify monitor IDs and historical logs. Never run the `database_setup.sql`
file as a migration: that is a fresh-install database script.

## Confirm

```sql
SELECT COUNT(*) AS inconsistent_locks FROM tbl_monitor_ips
 WHERE assignment_locked <> settings_locked;
SELECT COUNT(*) AS missing_owners FROM tbl_monitor_ips
 WHERE owner_user_id IS NULL;
SELECT COUNT(*) AS screens FROM screenshot_metadata;
SELECT COUNT(*) AS browser_logs FROM browser_history_logs;
SELECT COUNT(*) AS key_logs FROM key_logs;
SELECT COUNT(*) AS usb_logs FROM usb_device_logs;
```

If the script reports missing media, restore the actual files from backups or
preserve those rows for investigation. Changing an IP or MAC field is not a
substitute for correct device ownership.
