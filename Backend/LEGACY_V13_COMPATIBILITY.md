# Monitoring v1.3 and v1.5 ingestion compatibility (November 2026)

## Historical baseline

The last August repository commit is `f9b067eb` (2026-08-13). The legacy Windows/Ubuntu clients posted `Event: Tic | KeyLog | BrowserHistory | USBLog` with `MacAddress` and usually `Username`, but **without Authorization** or `MonitorId`. The August Windows source reports `APP_VERSION = "1.0"` even if the deployed application is labeled v1.3. Its multipart screenshot uploader also omitted the URL query `?type=upload` from WinINet requests.

Current clients authenticate with a persistent revocable Bearer token bound to the selected server-side monitor ID, and send that `MonitorId` with events. The backend now accepts both request structures, but legacy access is **disabled by default**.

## Enable only for existing managed legacy devices

First back up your database and verify the v1.3 machines appear in `tbl_monitor_ips` with an assigned `owner_user_id`, accurate `ip_address`, and **unique IP address per monitor**. Legacy requests never create new monitors.

In the Apache/PHP-FPM service environment (not just the shell):
```ini
MONITOR_ENABLE_LEGACY_V13=1
MONITOR_LEGACY_V13_IPS=192.168.2.101,192.168.2.102
```

Those are examples: replace with your known devices' exact current LAN IPs. Each address must be a private-network IP, and the **actual TCP peer** must match. Requests with forwarded headers or any Authorization header are not eligible. If an event supplies a MAC address, it must also match the monitor's registered MAC. If two monitors have the same IP, the legacy request is rejected rather than assigned unpredictably.

**Security:** Legacy applications have no cryptographic identity. Source IP and a reported MAC are not equivalent to authentication. These controls merely confine the risk to known, administrator-allowlisted LAN addresses. Restrict the listening vhost/firewall to your management network, reserve DHCP IPs, preferably use TLS/8925, and remove these environment variables after upgrading all devices. Never enable the bridge on a public-facing or proxied endpoint. HTTP/8924 requires a separate explicit `MONITOR_ALLOW_HTTP=1` LAN opt-in and transmits sensitive telemetry unencrypted; use HTTPS instead.

Because the August Windows build omitted `?type=upload`, the legacy bridge also recognizes the specific multipart `imagefile` request with no type parameter **only** for a verified legacy-mapped device. Modern bearer-authenticated uploads require the normal `?type=upload` request and never fall back.

## v1.5 Windows fixes

- WinINet multipart request includes the extra URL query component (`dwExtraInfoLength`).
- Immediate heartbeat and keyboard/USB monitor initialization follow Save or Add New.
- HTTP rejection status and WinINet failures are written to `monitor_debug.log` without logging the token.
- Backend error messages are surfaced in the diagnostics.
- Failed screenshots are not deleted prematurely.

After building Windows, open Settings, Sign In, choose a monitor and **Save**, then check `monitor_debug.log` (in the application's working directory) for HTTP status. HTTP 401 means the token was rejected or not bound to an existing monitor; 403 may mean an ID mismatch; 426 means HTTPS is required; 500 indicates a server-side problem.

## Ubuntu reliability corrections

- Backend maximum for BrowserHistory and USBLog is 500 events per request; Ubuntu now sends batches of up to 500.
- Failed key, USB and browser history chunks are requeued in memory for subsequent retry, rather than silently discarded. This is an at-least-once design: a network failure after server commit can cause duplicates without server-side deduplication.
- Screen unavailability affects screenshots only. Heartbeats and other telemetry can continue.
- Sign In and monitor selection use the same modern Bearer-token server workflow as Windows/macOS.

**Remaining operational limitation:** The Ubuntu retry queue is in memory. A forced app exit or reboot during network downtime can still lose pending events; durable offline storage would be required for at-least-once recovery across restarts.

## Deployment checks

```bash
cd /var/monitor
git pull --ff-only origin main
php -l Backend/services/ClientIdentity.php
php -l Backend/eventhandler.php
php -l Backend/webapi.php
php Backend/tests/legacy_ingest_compat_contract.php
sudo systemctl reload apache2
```

Restart PHP-FPM as well if that's how PHP is hosted and environment variables have changed. Inspect web-server error logs for 401/403/426/5xx and confirm the selected monitor's `last_monitor_tic` advances. Test one allowlisted v1.3 machine and one v1.5 token-authenticated machine before enabling more legacy IPs. Monitor log counts and screenshot thumbnails.

No GitHub Actions should run for this project.
