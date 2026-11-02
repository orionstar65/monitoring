# Dual protocol server setup — November 2026

Desired ports:
- **HTTP**: `http://SERVER_IP:8924/html/`, restricted to trusted LAN only
- **HTTPS**: `https://SERVER_IP:8925/html/`, signed by your private LAN CA

These are two separate Apache virtual hosts. A single TCP port cannot serve
both plain HTTP and TLS without special protocol detection, which this project
does not use.

## Apply on an Ubuntu server

```bash
cd /var/monitor
git pull --ff-only origin main
sudo bash Backend/setup_dual_protocol.sh --host 192.168.2.241
sudo bash Backend/setup_database_access.sh
```

Use your actual checkout path and server IP. The first command reuses the LAN
CA created by the previous HTTPS setup, if present. If missing, it creates a
private CA and server certificate. Re-running the script is supported.

By default both HTTP and HTTPS use the original Apache directory restrictions
(127.0.0.1, 192.168.1.0/24 and 192.168.2.0/24). You may specify an additional
HTTP subnet using `--lan-cidr 10.0.0.0/24`. The script keeps the original
DocumentRoot, stored files, and vhost logging configuration. It backs up
the original Apache vhost and `ports.conf` under
`/etc/monitoring/tls/backups/` before changing them.

**Do not run the older single-port `setup_https.sh` after this.**
Its newer version refuses to overwrite a dual-protocol virtual host.
The full Ubuntu installer is also updated to preserve the dual-vhost setup.

## Fix "Database service unavailable"

The new code defaults to a restricted MySQL user named `monitoring_app`.
Older installations may not have this user or `MONITOR_DB_PASSWORD` available
to Apache. The second script:

- Connects as MySQL root (prompts securely for the root password if needed).
- Requires the existing `monitoring_db` database; **never creates/drops it**.
- Creates or resets `monitoring_app@localhost` with a random password.
- Grants database SELECT, INSERT, UPDATE and DELETE permissions only.
- Writes a local, root-readable Apache environment config to
  `/etc/monitoring/apache-db.conf`, not to Git.
- Includes the config in the monitoring Apache vhosts and reloads Apache.

It **does not** run the identity migration or create users. If the login page
starts loading but signup/sign-in complains about missing `client_sessions`,
`login_attempts` or `name`, back up MySQL and run the one-time migration
described in [MIGRATION_2026_11_02.md](MIGRATION_2026_11_02.md).

The scripts assume the existing Ubuntu installer uses **Apache mod_php**.
If you customized the deployment to PHP-FPM, configure the corresponding
pool environment separately and keep the database password out of the webroot.

## Verify

```bash
sudo apache2ctl configtest
sudo ss -lntp | grep -E ':8924|:8925'
curl -I http://127.0.0.1:8924/html/auth/login
# Use the server's LAN CA to verify HTTPS, not curl -k
curl --cacert /etc/monitoring/tls/ca/monitoring-lan-root.crt \
  -I https://192.168.2.241:8925/html/auth/login
```

When the database is correctly configured, both login pages should return
HTTP 200. The client authentication endpoint will reject unauthenticated
telemetry (HTTP 401), which is expected.

## Windows/macOS/Ubuntu clients

Rebuild the latest clients. The updated client source automatically selects
HTTP for port `8924` and HTTPS for port `8925`. Set the matching **server
port** in the settings dialog or Windows `settings.ini`. Do not reuse
old v1.3 installers, which have not been rebuilt with the new protocol logic.

To use HTTPS on client machines, install and trust **only the public** LAN CA
certificate. Follow [HTTPS_SETUP.md](HTTPS_SETUP.md) for the CA import steps.
Never distribute the CA private key.

## Security warning

Using HTTP for sign-in and monitoring is unsafe on an untrusted network.
Passwords, tokens, screenshots, keyboard logs, browser history and device
activity can be intercepted or modified in transit. The script explicitly
limits HTTP to LAN addresses and opt-in Apache requests, but that does not
make unencrypted connections private. Use **HTTPS 8925 whenever possible**.
Never forward HTTP 8924 to the public internet.

No GitHub Actions are required; deploy and test manually.
