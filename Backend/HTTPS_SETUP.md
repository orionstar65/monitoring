> **Current server configuration:** For both HTTP and HTTPS, use
> [DUAL_PROTOCOL_SETUP.md](DUAL_PROTOCOL_SETUP.md). It configures HTTP on
> **8924** (restricted LAN) and HTTPS on **8925**. The single-port
> `setup_https.sh` instructions below are for legacy installations only;
> do not run it after enabling the dual-port configuration.

# Enable HTTPS on the monitoring server

This project runs an Ubuntu/Debian Apache backend on **port 8924**. The updated
PHP server now requires HTTPS; opening an old `http://` URL produces
`HTTPS required`. This script converts the **existing Apache virtual host**
to HTTPS on the *same port*. It does not touch the MySQL database or perform
a database migration.

## Quick start: LAN server (private IP)

From the server checkout:

```bash
cd ~/monitoring
git pull --ff-only origin main
sudo bash Backend/setup_https.sh --host 192.168.2.241
```

Replace `192.168.2.241` with the IP you actually use to reach this server.
Or run `sudo bash Backend/setup_https.sh` to auto-detect the primary IPv4.

Then open **https://192.168.2.241:8924/html/**. The previous
`http://192.168.2.241:8924/html/` will no longer work. Existing firewall
port and LAN-only Apache allowlists are preserved.

### Trust the LAN root CA on each client

This is **not** an internet-trusted certificate. On the server, the script
creates a private certificate authority and issues a certificate with the
specified IP in the Subject Alternative Name. The **public** root CA file is:

```text
/etc/monitoring/tls/ca/monitoring-lan-root.crt
```

The `/etc/monitoring/tls` directory is root-only, so first export **only the
public** CA certificate to your server user's home directory:

```bash
sudo install -m 0644 /etc/monitoring/tls/ca/monitoring-lan-root.crt \
  "$HOME/monitoring-lan-root.crt"
openssl x509 -in "$HOME/monitoring-lan-root.crt" -noout -fingerprint -sha256
```

Copy this public `~/monitoring-lan-root.crt` to client machines using a trusted
method (e.g. SCP), placing it at `~/Downloads/monitoring-lan-root.crt` on
macOS/Ubuntu or a known local file path on Windows. Compare the certificate's
SHA-256 fingerprint with the server's fingerprint using a trusted channel
before installing it. **Never distribute files from the `private/` directory.**

**Windows:** run an elevated Command Prompt on the client:

```powershell
certutil -addstore -f Root "C:\path\to\monitoring-lan-root.crt"
```

**macOS:** run on the client (using the actual copied file path):

```bash
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain ~/Downloads/monitoring-lan-root.crt
```

**Ubuntu:** run on the client:

```bash
sudo install -m 644 ~/Downloads/monitoring-lan-root.crt \
  /usr/local/share/ca-certificates/monitoring-lan-root.crt
sudo update-ca-certificates
```

After trusting the CA, close/reopen browser or native monitor client.
Set each desktop client to **Server IP** `192.168.2.241` (or your selected
hostname) and **Port** `8924`, then sign in.

Keep **/etc/monitoring/tls/private/monitoring-lan-root.key** root-only and
never copy it to clients. This setup stores the CA signing key on the server for
convenient renewal; for stronger security use an offline CA instead.

## Existing public certificate

If you already have a domain, a valid public CA certificate (for example
from Let's Encrypt), and its full-chain PEM/private key, you can use:

```bash
sudo bash Backend/setup_https.sh \
  --host monitor.example.com --mode existing \
  --cert /etc/letsencrypt/live/monitor.example.com/fullchain.pem \
  --key /etc/letsencrypt/live/monitor.example.com/privkey.pem
```

The script checks that the certificate matches the host and the key. A
public certificate requires a properly controlled DNS name, and may require
internet-based domain validation. This script **does not** create or renew
Let's Encrypt certificates; rerun it after updating the source certificate.
Using a plain private IPv4 address cannot ordinarily obtain a publicly
trusted certificate from Let's Encrypt.

## Verify

```bash
sudo apache2ctl configtest
sudo systemctl status apache2 --no-pager
sudo ss -lntp | grep ':8924'
# LAN server: test with your public CA explicitly
curl --cacert /etc/monitoring/tls/ca/monitoring-lan-root.crt \
  -I https://192.168.2.241:8924/html/auth/login
```

The anonymous login page should return HTTP 200 once PHP/MySQL configuration
is healthy. To confirm the certificate itself without a browser, run:

```bash
openssl s_client -connect 192.168.2.241:8924 -CAfile \
  /etc/monitoring/tls/ca/monitoring-lan-root.crt </dev/null
```

Run the anonymous API smoke tests separately as documented in
`Backend/tests/README.md`. The HTTPS script does not install DB credentials
or apply the new identity migration.

## Re-run, renew, troubleshoot

Re-running the same command **reuses the existing LAN CA** and issues a fresh
one-year server certificate. Existing clients that trust that CA remain trusted.
Set a calendar reminder to renew before expiration; it does not auto-renew.

The script backs up `/etc/apache2/sites-available/mybackend.conf` to
`/etc/monitoring/tls/backups`, checks `apache2ctl configtest`, and attempts
to restore the previous Apache config if a step fails. The current `setup_ubuntu_server.sh` preserves HTTPS when it finds this script's managed Apache configuration. Do not deploy an older copy of the installer, which may overwrite TLS settings.

If your user sees **403** after successful HTTPS, the existing Apache
`<Directory>` allowlist may restrict the client subnet. Adjust it for your
actual LAN range rather than opening the monitoring server publicly.

**Security:** Only install a CA certificate you generated and verified. Do not
disable TLS verification, use `curl -k` as a fix, or import the CA private key.


## Reverse proxy trust boundary

When PHP is behind an HTTPS-terminating reverse proxy, set
`MONITOR_TRUST_PROXY_HTTPS=1`. The HTTPS forwarding header will now be
accepted **only when** the immediate peer is an explicitly trusted proxy.
Localhost (`127.0.0.1`, `::1`) is trusted by default. For a different
reverse-proxy host, set `MONITOR_TRUSTED_PROXY_IPS` to its comma-separated
**IP addresses**. Configure the proxy to overwrite incoming
`X-Forwarded-Proto` and block direct access to the application backend.
Never add a general LAN or wildcard IP range here.

This prevents a caller on an untrusted interface from faking
`X-Forwarded-Proto: https` to bypass the HTTPS requirement.
