# Database Management and Keyboard Logging — Ubuntu rollout

This update adds **/html/databasemanagement** for the **Super Admin**
role. Normal Users and delegated Admins cannot access the controller
or any of its backup, restore, and cleanup endpoints.

## Prepare your server

Back up the SQL database *and* your screenshot directory before deploying:

\`\`\`bash
cd /var/monitor
which mysql mysqldump
php -m | grep -Ei '^(mysqli|pdo_mysql|zlib)$'
sudo apt update
sudo apt install -y default-mysql-client php-mysql
\`\`\`

Make sure the PHP web process can run \`mysql\` and \`mysqldump\` and write
to its configured temporary directory. The new page uses the existing
\`MONITOR_DB_HOST\`, \`MONITOR_DB_USER\`, \`MONITOR_DB_PASSWORD\` and
\`MONITOR_DB_NAME\` from the portal configuration. It **does not**
store credentials in your web-accessible backup directory.

For restoring larger SQL archives, adjust the PHP web runtime settings,
not just the command-line PHP settings:

\`\`\`ini
upload_max_filesize = 512M
post_max_size = 520M
max_input_time = 600
\`\`\`

Then reload Apache or PHP-FPM. Ensure \`/tmp\` has enough free space for
both the uploaded archive and expanded SQL, potentially several gigabytes.
The feature caps compressed restores at 512 MiB and expanded SQL at 3 GiB.
For larger databases, use audited command-line \`mysqldump\`/\`mysql\`
maintenance procedures rather than increasing limits arbitrarily.

## Optional retention performance migration

For large keyboard and USB event tables, apply the idempotent composite
`created_at,id` indexes during a **database maintenance window**:

```bash
cd /var/monitor
sudo mysql monitoring_db < Backend/migrations/2026_11_06_retention_created_at_indexes.sql
```

Building these indexes can temporarily consume substantial disk, CPU and
database I/O. The browser history table already has an index on
`created_at`. The cleanup feature works without the additional indexes,
but may be slower on large tables.

## Database Management page

The status table lists every base table in the configured database:
table name, approximate InnoDB row count, data size, index size, total
size, engine and whether it has a \`created_at\` column.

**Clean DB** opens a confirmation modal with **3 months** as the default.
The text explains the actual cutoff date. The operator must type
\`DELETE OLD LOGS\`. Each request deletes at most 2,000 records
from eligible \`browser_history_logs\`, \`key_logs\`, or \`usb_device_logs\`.
Monthly \`storage_statistics\` counts and byte estimates for deleted
rows are updated in the same transaction. Cleanup uses **created_at**
(the time the server received the record), not the event's \`key_date\`
or \`visit_date\`.

Accounts, monitors, user assignments, client tokens/sessions and settings
are never age-deleted. **Screenshot metadata is not age-deleted by this
page**, because that would orphan actual screenshot files. Use the
existing screenshot deletion/cleanup workflow to remove both files and
metadata safely.

**Download backup** creates a compressed SQL dump including schema and
data. It runs with the configured MySQL application credentials and
may require extra privileges for routines/triggers or views. Backups can
contain private activity, account data and password hashes. Store and
transmit them securely.

**Restore from backup** requires:

1. A fresh safety backup downloaded in the current portal session within the past two hours.
2. An authenticated MonitorClient SQL backup (`.sql.gz`) signed by this deployment. Older signed backups may also be restored; new v2 archives use HMAC-SHA256 to authenticate the database name and SQL contents. Old unsigned v1 backups may only be restored if byte-identical to the freshly downloaded backup in this session.
3. The phrase `RESTORE DATABASE` and a checked acknowledgement.

For reliable long-term restores, configure `MONITOR_BACKUP_SIGNING_KEY` as a private random secret of at least 32 bytes on the web server and retain it securely across deployments and database password changes. If omitted, the backup signature uses the configured database password, so rotating that password can prevent validation of older signed backups. Never commit the signing key to Git or expose it in the update server directory.

Restoring SQL is **not transactional** across schema changes. Do this
during a scheduled maintenance window after stopping client ingests.
If a restore fails, the database may be partially replaced. Recover
using the downloaded backup or a separately verified server snapshot.
After successful restore, log in again.

## Keyboard events: current and legacy clients

The Ubuntu v1.5 timer now invokes \`sendKeyLogs()\` again; it was
disconnected previously. macOS v1.5 now retains buffered key events
until the server confirms the **whole batch** was stored.
The backend atomically inserts each keyboard batch, updates monthly
statistics in groups, and refuses to return success when a nonempty
upload was not stored.

The existing v1.3 compatibility bridge remains on. It only accepts
**already registered** monitors on a direct private LAN (without
X-Forwarded-For or Authorization headers), with exactly one matching
monitor IP or last server-observed IP. If the client supplied a MAC,
it must match the registered MAC. This intentionally does not allow
registration or impersonation based only on an unverified MAC or
username. \`MONITOR_DISABLE_LEGACY_V13=1\` explicitly disables the bridge.

To diagnose a v1.3 client that still cannot upload, check its registered
monitor IP and owner:

\`\`\`sql
SELECT id,ip_address,last_seen_ip,mac_address,owner_user_id,app_version
FROM tbl_monitor_ips ORDER BY id DESC LIMIT 50;
SELECT monitor_ip_id,COUNT(*) AS events,MAX(key_date) AS most_recent
FROM key_logs GROUP BY monitor_ip_id ORDER BY most_recent DESC LIMIT 30;
\`\`\`

Check the backend's HTTP status for \`eventhandler.php\`:
- **200 + inserted_count**: keyboard events accepted and stored.
- **400/422**: unsupported/malformed keyboard records.
- **401**: selected monitor token missing, or legacy device did not pass
  the registered LAN identity check.
- **403**: explicit monitor claim differs from authenticated selection.
- **500**: storage or database failure; inspect PHP/MySQL logs.

On Ubuntu, also verify that the keyboard helper is running with
the required OS permissions; no network fix can recover events that
were never captured. With troubleshooting enabled, use
\`MONITORCLIENT_KEYLOG_DEBUG=1\` for detailed, non-payload activity
messages and check both capture counters and upload acknowledgements.

The transcript UI renders an application header only when the app changes
in the chronological event sequence. Repeated adjacent special key
events are condensed visually, e.g. \`UP ×8\`, but are preserved in SQL.

## Checks before production

Run locally after pulling main. These are **manual tests**, not Actions:

\`\`\`bash
php -l Backend/html/app/controllers/Databasemanagement.php
php -l Backend/html/app/views/databasemanagement/index.php
php -l Backend/html/app/services/DatabaseService.php
php -l Backend/eventhandler.php
php -l Backend/services/ClientIdentity.php
php Backend/tests/databasemanagement_and_keyboard_contract.php
node Backend/tests/key_transcript_grouping_test.js
\`\`\`

On a staging database, verify role access, confirmation validation,
small cleanup batches, backup gzip download, restore from a real backup,
re-login after restore, and correct screenshot exclusion. On Windows,
Ubuntu and macOS, rebuild the native clients and verify keyboard event
upload, including an existing v1.3 device on an approved LAN.
