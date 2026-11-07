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
