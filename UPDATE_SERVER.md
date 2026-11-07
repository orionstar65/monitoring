# Built-in MonitorClient update server

The same Apache/PHP backend used for monitoring now serves native application
updates. No separate `python -m http.server` instance or port 8765 is needed.

## How the OS release builders publish

- Ubuntu: `cd Ubuntu && ./create_deb_package.sh --version 1.5.3` produces
  `MonitorClient-1.5.3-ubuntu-amd64.deb`.
- Windows: `powershell -ExecutionPolicy Bypass -File Windows/release.ps1 -Version 1.5.3`
  produces `MonitorClient-1.5.3-windows-x64.zip`.
- macOS: `cd Mac && bash build_sign.sh` produces a versioned `.pkg` for
  macos-arm64 or macos-x64, according to the builder machine.

All three scripts call `scripts/publish_update.py` and place files in the
**repository-root `release-updates/` folder**, updating its shared
`manifest.json`. They do not upload releases to remote servers automatically.

If all three releases were built in **one shared checkout**, you may
synchronize that checkout's complete `release-updates/` directory to the
server after verifying the manifest. However, if Ubuntu, Windows and macOS
releases are built on **separate machines**, do not copy the old
`manifest.json` from each one: it can overwrite other platform entries.

Instead upload each versioned artifact to the backend machine, then register
it there using the one shared manifest:

```bash
# On build host: copy the platform package (example: Ubuntu)
scp Ubuntu/MonitorClient-1.5.3-ubuntu-amd64.deb \
  server@YOUR_SERVER:/tmp/

# On the Ubuntu backend server:
cd /var/monitor
bash Backend/publish_upgrade.sh ubuntu-amd64 1.5.3 \
  /tmp/MonitorClient-1.5.3-ubuntu-amd64.deb
```

Repeat on the backend with `windows-x64`, `macos-arm64` or
`macos-x64` and the matching artifact extension. The same script uses
`scripts/publish_update.py` to update only that platform's manifest
entry. Register releases sequentially; do not start concurrent writers.

The PHP endpoint uses a fixed, read-only path:
`Backend/upgrades/index.php` defines
`MONITOR_UPDATE_RELEASE_ROOT = __DIR__.'/../../release-updates'`.
Do **not** make this storage root writable by the HTTP worker, and do not
configure it as an arbitrary DB value.

Verify permissions on the server:

```bash
sudo chown -R server:root /var/monitor/release-updates
sudo find /var/monitor/release-updates -type d -exec chmod 755 {} +
sudo find /var/monitor/release-updates -type f -exec chmod 644 {} +
```

Replace `server:root` above with the account/group that owns your deployments,
if different. The Apache worker only needs read access.

## HTTP API

The existing Apache backend on port 8924 (HTTP) or 8925 (HTTPS) serves:

```text
GET /upgrades/              manifest.json
GET /upgrades/manifest.json manifest.json
GET /upgrades/MonitorClient-<version>-<platform>.<deb|pkg|zip>
```

For example:

```bash
curl -fS http://YOUR_SERVER:8924/upgrades/manifest.json
curl -fSI http://YOUR_SERVER:8924/upgrades/MonitorClient-1.5.3-ubuntu-amd64.deb
```

The route has a fixed release directory, checks the manifest's schema,
validates platform, filename, hash metadata and artifact size, and denies all
unpublished files and arbitrary paths. There is no authentication for reading
the public release manifest or package; keep it on a trusted LAN or configure
HTTPS for wider distribution.

The server needs Apache `mod_rewrite` and `AllowOverride All` for
`Backend/upgrades/.htaccess` (the repository's Ubuntu server setup already
configures these). `/upgrades` without a slash is normalized by Apache's
directory handling.

The update URL is derived on each client, not configured separately:

```text
scheme = configured_port == 8924 ? http : https
base = scheme://configured_server_ip:configured_port/upgrades/
manifest = base + manifest.json
download = base + manifest_file
```

The Updates tab displays the read-only API path `/upgrades/`. Both
**Check new version** and **Update** use the same generated base URL.
Users only configure server IP and port in their existing connection tab.
Legacy builds configured for a separate update URL require a new client
release before they can use this automatic backend path.

The release publisher rejects a different binary with an unchanged version
number. Increment the patch version for each new build, and retain older files
if legacy clients still depend on them.

## DB-managed operational settings

Run the migration on the backend before using the new Super Admin page:

```bash
cd /var/monitor
mysql -u monitoring_app -p monitoring_db < Backend/migrations/2026_11_07_application_settings.sql
```

If the `monitoring_app` database user lacks DDL permissions, execute
the migration as a MySQL administrator. Never put the DB password in the
command line, application settings table, or client configuration.

Visit `/html/settings` as **Super Admin**. It exposes typed, validated
key/value pairs in the `app_settings` table. Edits are restricted by a
catalog in `Backend/config/AppSettings.php`; arbitrary credentials, SQL,
file paths and API endpoints are not accepted as keys.

| Key | Default | Used by |
| --- | --- | --- |
| `interval.screenshot.seconds` | 60 | Screenshot capture schedule |
| `interval.heartbeat.seconds` | 300 | Heartbeat/tic schedule |
| `interval.browser.seconds` | 300 | Browser history sync schedule |
| `interval.keyboard.seconds` | 300 | Keyboard log sync schedule |
| `interval.usb.seconds` | 300 | USB log sync schedule |
| `interval.config_refresh.seconds` | 300 | Client config polling schedule |
| `platform.{ubuntu,windows,macos}.interval.{screenshot,heartbeat,browser,keyboard,usb,config_refresh}.seconds` | Inherit global | Optional platform schedule override |
| `logging.api_requests.enabled` | 1 | API metadata logging across client endpoints |
| `retention.default_months` | 3 | Default cleanup age in Database Management |
| `server.max_retry_attempts` | 3 | Client-config API networking defaults |
| `server.connection_timeout.seconds` | 10 | Client-config API networking defaults |
| `server.request_timeout.seconds` | 30 | Client-config API networking defaults |
| `server.chunk_size` | 1000 | Client-config API batching default |
| `server.compression.enabled` | 1 | Client-config API compression default |
| `feature.{screenshot,browser_history,keyboard,usb,debug_logging,auto_update}.enabled` | Defined in catalog | Client-config API feature flags |

**Important:** Windows, macOS, and Ubuntu already apply interval values from
`/webapi_config.php`. Feature flags and network tuning defaults are exposed
by the API, but individual clients may not yet enforce every one. Do not
treat a flag as an authoritative mechanism to stop capture until all target
client versions implement it. The API logging switch is implemented in
`DatabaseService::logApiRequest`; the DB cleanup default is implemented in
the Database Management form.

Values are validated by key and bounded by minimum/maximum values. If the
migration is missing, client API defaults continue operating and the Settings
page displays a warning. Never put secrets (database passwords, application
tokens, TLS keys, backup signing keys) into the settings table: those remain
in protected environment files or secret storage.

## Verification

```bash
php Backend/tests/upgrades_and_settings_contract.php
php Backend/tests/ubuntu_upgrade_contract.php
python3 scripts/test_publish_update.py
```

Perform native Windows/macOS/Ubuntu build and an end-to-end update against
the actual Apache host before rolling this out to customers. SHA-256 detects
download corruption, but does not authenticate a manifest served over
unencrypted HTTP. Prefer trusted HTTPS and signed/notarized installers for
production customer environments.
