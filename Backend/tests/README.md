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

## Dual-protocol tests

After setup, verify from a trusted LAN device:

```bash
curl -I http://SERVER_IP:8924/html/auth/login
curl --cacert /path/to/monitoring-lan-root.crt \
  -I https://SERVER_IP:8925/html/auth/login
bash Backend/tests/manual_smoke.sh http://SERVER_IP:8924
bash Backend/tests/manual_smoke.sh https://SERVER_IP:8925
```

Never use insecure `-k` to mask certificate problems. The HTTPS smoke test
requires a trusted certificate on the test machine.

## Unified monitoring workspace (November 2026)

The administrator **Monitoring** page has a single, persistent monitor selector on the left and
Screenshots, Keyboard, Browser, USB and Settings tabs on the right. Older
`/html/view/logs` and admin `/html/logbrowse` routes redirect into the workspace;
the old endpoints remain for API compatibility. Viewer-only aggregate permissions
remain unchanged.

Run targeted tests after pulling main (no GitHub Actions):

```bash
php -l Backend/html/app/views/view/index.php
php -l Backend/html/app/views/view/logbrowse.php
php -l Backend/html/app/controllers/View.php
php -l Backend/html/app/controllers/Logbrowse.php
php -l Backend/html/app/controllers/Merge.php
php -l Backend/html/app/controllers/Monitors.php
php -l Backend/html/app/services/DatabaseService.php
php Backend/tests/unified_monitoring_workspace_contract.php
php Backend/tests/portal_timeline_and_account_actions_contract.php
php Backend/tests/monitor_selector_sort_contract.php
php Backend/tests/client_tray_session_contract.php
php Backend/tests/ubuntu_keyboard_helper_status_contract.php
php Backend/tests/ubuntu_tray_lifecycle_contract.php
php Backend/tests/ubuntu_upgrade_contract.php
node Backend/tests/timeline_geometry_test.js
node Backend/tests/monitoring_date_transcript_test.js
node Backend/tests/screenshot_navigation_range_test.js
```

Before production rollout, smoke-test in a browser with an admin account:

1. Check sidebar **User No: Username** ordering, scroll, compact status pills,
   keyboard navigation, and no radio inputs. Select a monitor and All Devices;
   confirm screenshots, log statistics, and tab filters track that selection.
2. Inspect Screenshot History: drag the red playhead, drag the selected range,
   resize each border, click sampled thumbnails, use +/- and mouse wheel to zoom,
   Shift+wheel to pan, and navigate through screenshots with Left/Right arrow keys.
   Confirm every navigation updates the bottom From/To inputs, selected ruler
   range, playhead, captured time and keyboard transcript. Repeat across midnight
   so the history domain reloads. Confirm the transcript shows the chosen window
   and distinguishes an older nearest screenshot from the playhead timestamp.
3. Check Keyboard reconstructed transcript, Browser and USB table pagination,
   date filters, and screenshot cleanup (dry-run confirmation and final counts).
4. Open Settings for an assigned monitor, change a safe noncritical field and
   verify saving returns to the same tab. Verify merge target options contain only
   the selected monitor owner's other devices; test a merge on staging with a
   database backup, confirming records and statistics transfer before source removal.
5. Rebuild Windows, macOS and Ubuntu native clients and confirm the signed-in
   account shows only **User No**, not full name, in Settings and the tray.
   On Ubuntu, restart with a remembered session and verify the account appears
   once (no repeated sign-in identity). Monitor labels still end in `#NN`;
   version labels remain 1.5.
6. Test ordinary user permissions separately: users retain aggregate-only access,
   not administrator log details or merge tools.

**Important:** the source contracts and geometry checks do not verify a real
database, browser behavior, Win32/Qt/Swift compilation or an existing v1.3
client. These still require local/manual verification. Do not trigger Actions.

## Selected-monitor log browsing and date presets

The unified Monitoring page defaults to **Today**. The date-range menu supports
Today, Yesterday, This/Last Week, This/Last Month, Last 7/30 Days and Custom.
Date ranges are inclusive, with Monday as the first day of the week; Custom
requires both dates before Apply. Browser/Keyboard/USB log tabs require a device
from the left panel before querying logs or aggregated log counts. When a device
is selected, the existing APIs paginate in SQL with LIMIT/OFFSET (20 per page by
default), not by sending the entire log stream to the browser. Activity ruler and
transcript context endpoints remain capped; browser-side code discards stale
requests after filters or selections change.

Screenshot History now uses one compact toolbar and an initial 30-minute
inspection range. The keyboard drawer shows actual key events for its selected
monitor and time range, or an explicit no-activity message. A nearest-activity
jump is available when timeline activity is known outside that range. Special
keys are displayed only once; hover to see the source database event ID. If two
identical keys have *different* event IDs, both records exist in the database
and should be investigated at ingestion rather than silently removed.

Smoke-test all date presets, monitor/no-monitor transitions, server-side table
pagination, and Screenshot History transcripts using populated staging data.

## Ubuntu keyboard-reader health diagnostics

The Ubuntu tray includes a **Keyboard reader** process-health line after an
account and monitor are selected. It distinguishes a missing binary, failed
launch, immediate exit, inaccessible input devices, crashes and a started
process. This reports process status only and never prints typed content.
A **started** process is not proof that events are stored in the backend.

For a diagnostic check on the Ubuntu desktop (without reading or displaying
keyboard content):

```bash
stat -c '%n %U:%G %a' /opt/monitorclient/monitorclient-keyreader
pgrep -a -f monitorclient-keyreader
journalctl --user -u monitorclient -n 100 --no-pager | grep -E 'KeyLog|keyreader|MonitorUpload'
php Backend/tests/ubuntu_keyboard_helper_status_contract.php
```

The direct `Ubuntu/install.sh` path currently refers to a helper inside an
extra `build/` subdirectory after `cd build`; this should be treated as an
installer defect. The `.deb` packaging path is separate. Do not run the reader
manually to diagnose it: its standard output contains actual typed content.
Any change that enables global input collection should first be reviewed for
informed consent, password-field exclusion, least-privilege access and secure
storage. Native compilation and desktop testing are still required.

## Ubuntu duplicate screenshot and updater-warning regression (November 2026)

The Ubuntu capture timer calls `getScreen()` for scheduled screenshots;
**Take Screenshot** and **Send All Logs** call `captureScreen(true)`.
Only one capture/upload can be in progress at a time. Manual bursts within
two seconds and scheduled ticks inside the configured screenshot interval are
coalesced, including timer ticks immediately after a manual capture. A second
portal-ready callback cannot start another POST. Uploaded timestamps are taken
at successful capture in UTC, not when the server reply arrives.

```bash
php Backend/tests/ubuntu_screenshot_capture_contract.php
php Backend/tests/manual_send_all_contract.php
php Backend/tests/ubuntu_settings_source_contract.php
```

Both Ubuntu updater requests now use `QNetworkRequest::RedirectPolicyAttribute`
with `ManualRedirectPolicy`, replacing the deprecated
`FollowRedirectsAttribute` while keeping redirects disabled. This protects the
update manifest and downloaded package from automatic redirecting.

After rebuilding and restarting the Ubuntu client, watch the screenshot log
without collecting any screen content:

```bash
journalctl --user -u monitorclient -f --no-pager | grep -E 'Screenshot|SendAll'
pgrep -a MonitorClient
```

Verify one `Stored on backend` acknowledgement per scheduled interval; two
manual clicks within two seconds should not result in two uploads. Compare
screenshots near midnight for correct UTC storage and browser-local display.
If duplicates persist even with one client process, check whether an older
client or a different OS user/session is also uploading to the same monitor:
the per-user single-instance lock cannot block a process under another UID.
These are source contracts, not substitutes for native Qt compilation or a live
backend/browser smoke test. Do not run GitHub Actions.

## Ubuntu background/tray window lifecycle

The Qt application sets `app.setQuitOnLastWindowClosed(false)`. The Settings
`ConfigDialog` is a `Qt::Tool` window with `Qt::WA_QuitOnClose` disabled:
its Close button or title-bar X dismisses only Settings, not the application.
The monitor timer, network uploads and tray icon continue running without a
taskbar window. Use the tray's **Settings...** entry or double-click the icon
to reopen Settings; use **Quit MonitorClient** to stop the application.

On GNOME desktops where the tray icon is absent, ensure a compatible
AppIndicator/StatusNotifier host or shell extension is enabled. The client logs
`[Tray] No system tray host detected` if Qt cannot find one. Running the
MonitorClient launcher again now reopens Settings in the original process
rather than creating another monitor process; the original process remains
protected by its single-instance lock.

```bash
php Backend/tests/ubuntu_tray_lifecycle_contract.php
pgrep -af '/opt/monitorclient/MonitorClient'
```

Manually verify on Ubuntu X11 and Wayland: open Settings, click Close; confirm
MonitorClient stays running and background heartbeats continue. Reopen Settings
from the tray and via the application launcher; close using title-bar X too.
Confirm that Settings does not create a taskbar entry on the target desktop.
Finally use the tray's explicit Quit to exit and verify that the process stops.
Qt window decorations/taskbar behavior depend on the desktop compositor; source
contracts cannot establish native behavior without a running Ubuntu session.

### Persistent Settings and verifying the actual installed build

Settings is now a single modeless, persistent `ConfigDialog`: Close, Escape,
and the titlebar X hide it without running `QDialog::finished`, releasing
network callbacks, or deleting the dialog. The only normal shutdown is the
tray's explicit **Quit MonitorClient** (or an approved updater/system shutdown).
The app logs `[Lifecycle] Settings hidden ...` on window dismissal and
`[Lifecycle] Qt event loop exiting ...` only when the process is exiting.

Rebuild and install the new source on the Ubuntu **client machine**, not just
the backend host. For an existing source checkout with dependencies installed:

```bash
cd /path/to/monitoring
git pull --ff-only origin main
cmake -S Ubuntu -B Ubuntu/build -DCMAKE_BUILD_TYPE=Release
cmake --build Ubuntu/build -j"$(nproc)"
# Quit MonitorClient from the tray before replacing a running executable.
sudo install -m 755 Ubuntu/build/MonitorClient /opt/monitorclient/MonitorClient
```

Then start the rebuilt binary
from the interactive desktop session; do not use `sudo` to launch a GUI
monitor client. Check that the process PID survives closing the Settings
window and that API heartbeats continue. The direct `Ubuntu/install.sh` path
now correctly copies its input-reader helper from the active build directory.
Run `php Backend/tests/ubuntu_tray_lifecycle_contract.php` to verify source
contracts; a native build and GUI test are still necessary.

## Debian upgrade fixes

Ubuntu packages must now be published with an incremented patch version.
The self-hosted update manifest rejects different bytes under the same
version. `Ubuntu/create_deb_package.sh` verifies that the native executable's
`--version` matches the requested package version and embedded dpkg version,
then installs a constrained restart helper at
`/opt/monitorclient/upgrade-restart.sh`. At package configure time, the
helper detects older running executable inodes belonging **only** to the
package-managed binary and restarts the affected active desktop user session.
It does not force-kill stuck clients or touch unknown installation paths.
The in-app updater waits for `pkexec/dpkg` to finish and reports declined or
failed installs rather than quitting immediately. A legacy updater that quits
before authorization has completed requires one manual desktop launch on its
first upgrade.

See `Ubuntu/UPGRADES.md` for package build commands, installed versus running
binary verification, and startup migration instructions. Run:

```bash
bash Ubuntu/tests/check-upgrade-scripts.sh
php Backend/tests/ubuntu_upgrade_contract.php
python3 scripts/test_publish_update.py
```

Native Qt builds and real Ubuntu desktop upgrades remain required; these
source contracts alone cannot prove process handoff on the target machine.
