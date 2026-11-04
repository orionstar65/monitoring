# MonitorClient v1.5 — Device selection, monitor settings lock, and telemetry identity

**The application version remains v1.5.** This release changes the database
schema and native client behavior; the desktop build/version numbers are not bumped.

## What changed

- Desktop User selector: `User No: Full Name (N Devices)`. The directory
  counts monitors owned by each account, including administrators.
- The selected user's monitor list is repopulated on switching users.
  **Switching user does not transfer existing monitor ownership.**
- Monitor selector includes **Add new...**. Saving that choice creates a monitor
  owned by the selected user and selects it for this device session.
- A user with zero devices can choose **Add new...** and explicitly create their
  first monitor. This uses the existing trusted-LAN enrollment gate.
- OS is a radio group (Windows / Mac / Ubuntu); Machine is a separate radio
  group (Laptop / Desktop).
- **Lock Settings** saves the visible monitor fields and permanently locks
  future client-side edits to that monitor, including forged API requests.
  Only an administrator can unlock the monitor in **Web → Monitors → Edit**.
- **Lock User No assignment** is a separate setting that controls changing the
  selected user. The two locks are independent.
- Every telemetry upload resolves by the **server-validated device session's
  monitor ID**, not MAC address, ARP, or IP matching. The last observed network
  IP is stored separately from the configured IP.

## Deployment sequence

1. Stop clients / pause ingestion and make a **verified MySQL backup**:
   ```bash
   cd /var/monitor
   mysqldump -u root -p --single-transaction monitoring_db > ~/monitoring-before-settings-v15.sql
   ```
2. Update the checkout:
   ```bash
   git pull --ff-only origin main
   ```
3. Confirm schema revision 17 is already applied. The new migration assumes
   `tbl_monitor_ips.assignment_locked` and existing owner IDs are present.
4. Apply the **new one-time migration**:
   ```bash
   mysql -u root -p monitoring_db < Backend/migrations/2026_11_04_monitor_settings_lock.sql
   ```
   It creates `settings_locked` and `last_seen_ip` columns. The earlier
   v1.4 identity migration already dropped unique IP/MAC indexes; this
   migration intentionally does **not** drop indexes a second time.
5. Restart/reload the PHP application as applicable:
   ```bash
   sudo apache2ctl configtest && sudo systemctl reload apache2
   ```
6. Rebuild and install the updated Windows, macOS and Ubuntu clients. No GitHub
   Actions are required. Install the new backend and the new client builds
   together; old client builds cannot show or enforce the new controls.
7. Verify on a trusted LAN:
   - User selector lists `100001: Example Name (2 Devices)` style labels.
   - Switching users changes the monitor list, but not monitor ownership.
   - Selecting **Add new...** creates and activates a new monitor for that
     user. Repeat with a newly created user who has zero existing monitors.
   - After saving, **Lock Settings**. Saving again should fail with HTTP 423.
   - Unlock through the web Monitors Edit page; saving should then succeed.
   - A new device with the same network IP must retain a **different monitor
     ID**, and screenshot/event records must use the active session's ID.
   - Old device tokens that were revoked or had their owner changed must fail.
   - Confirm authenticated heartbeat updates `last_seen_ip`, without
     replacing the configured `ip_address`.

## Read-only database checks

```sql
SHOW COLUMNS FROM tbl_monitor_ips LIKE 'settings_locked';
SHOW COLUMNS FROM tbl_monitor_ips LIKE 'last_seen_ip';
SELECT id, owner_user_id, name, ip_address, last_seen_ip,
       assignment_locked, settings_locked
FROM tbl_monitor_ips ORDER BY id LIMIT 30;
```

## Security and identity limitations

A session token is the server-side authorization key, and the monitor primary
key is the stable log target. Network IP, host name, MAC, and OS are not
trusted identity proofs. A device may change network IP without becoming
another monitor. The selected monitor can be changed among monitors owned by
the same user; therefore operators must select the correct device to prevent
logs from the current installation being attributed to another device.

**Important:** The project deliberately supports no-password User No
enrollment on restricted LANs. User No is not a secure authentication
credential; someone who can reach the trusted enrollment API and knows a User
No can potentially register under that account. An assignment or settings
lock does not solve impersonation from a new installation. For sensitive
monitoring, restrict the API using firewall/VPN and implement administrator-
approved pairing or cryptographically authenticated installation identity.

## Rollback

Do not rollback the PHP code without the matching clients; older versions
may revert to MAC/IP-based assumptions. Stop ingestion and restore the
verified database backup and compatible backend/client set together. The
settings lock is stored in the database and remains until an admin unlocks it.
