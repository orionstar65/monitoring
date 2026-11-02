> **Updated November 2026:** The old HTTP-only server instructions below are retained for
> historical reference. New monitoring clients and the PHP dashboard require HTTPS.
> After installing the Apache backend, run `sudo bash Backend/setup_https.sh --host YOUR_IP`
> and follow [HTTPS_SETUP.md](HTTPS_SETUP.md) to trust the public LAN CA
> certificate on each client. The code no longer contains a default database password;
> database credentials must be configured with `MONITOR_DB_*` environment variables.
> **Do not** rerun `database_setup.sql` on an existing database; it drops tables.

# Ubuntu Server Setup (ACC-Monitor Backend)

Quick guide to deploy the monitoring backend on Ubuntu with Apache on port **8924**.

## Requirements

- Ubuntu 22.04+ (tested on 24.04)
- Project at `/var/monitor/Backend`
- Ports: **8924** (backend), **3306** (MySQL, local)

## 1. Install packages

On a fresh server, the setup script installs these automatically (skips if already present):

```bash
sudo apt update
sudo apt install -y apache2 mysql-server php php-mysql php-gd php-mbstring php-curl php-xml php-zip libapache2-mod-php ffmpeg git wget ufw openssh-server
sudo a2enmod rewrite
```

The script also fixes DNS (uses `8.8.8.8` / `1.1.1.1`) if name resolution is broken before running `apt`.

## 2. Apache virtual host

Create `/etc/apache2/sites-available/mybackend.conf`:

```apache
<VirtualHost *:8924>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/monitor/Backend

    <Directory /var/monitor/Backend>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        <RequireAny>
            Require ip 127.0.0.1
            Require ip 192.168.1.0/24
            Require ip 192.168.2.0/24
        </RequireAny>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/backend_error.log
    CustomLog ${APACHE_LOG_DIR}/backend_access.log combined
</VirtualHost>
```

Enable site and port:

```bash
echo "Listen 8924" | sudo tee -a /etc/apache2/ports.conf
sudo a2ensite mybackend.conf
sudo systemctl restart apache2
```

> **Note:** `DocumentRoot` must be `/var/monitor/Backend` (not `mointor`).

## 3. Database

The application reads its database settings from environment variables
`MONITOR_DB_HOST`, `MONITOR_DB_USER`, `MONITOR_DB_PASSWORD`, and
`MONITOR_DB_NAME`. Use an application-specific MySQL account with minimum
required permissions. There are no safe hardcoded default credentials.

**Existing server:** back up the database and run the identity migration as
documented in [MIGRATION_2026_11_02.md](MIGRATION_2026_11_02.md). Do not run
the original `database_setup.sql` on a live installation because it recreates
the database.

**New server:** create an empty database, apply the initial schema and then
the identity migration. Create an administrator using `Backend/create_admin.php`
with environment-provided credentials and no known default password.

## 4. One-command setup

The setup script installs packages, clones/updates the app, configures MySQL, Apache, firewall, and app URL:

```bash
sudo bash /var/monitor/Backend/setup_ubuntu_server.sh
```

Fresh server (no repo yet):

```bash
sudo apt install -y git
sudo git clone http://192.168.1.245:5000/orionstar65/monitor.git /var/monitor
sudo bash /var/monitor/Backend/setup_ubuntu_server.sh
```

The script sets `www-data` ownership on the app and `755`/`644` permissions (writable data dirs use `775`). The `.git` folder is excluded and kept owned by the user running `sudo`.

Optional environment variables:

```bash
sudo SERVER_IP=192.168.2.241 GIT_URL=http://192.168.1.245:5000/orionstar65/monitor.git bash setup_ubuntu_server.sh
```

Custom port (default `8924`):

```bash
sudo bash setup_ubuntu_server.sh 9000
sudo bash setup_ubuntu_server.sh --port 9000
sudo BACKEND_PORT=9000 bash setup_ubuntu_server.sh
```

## 5. Configure app URL

Edit `html/app/config/config.php` and set your server IP:

```php
define('BASE_URL', getenv('MONITOR_BASE_URL') ?: '/html/');
```

## 6. Local network access (firewall)

Allow port 8924 only from local subnets:

```bash
sudo ufw allow from 192.168.1.0/24 to any port 8924 proto tcp
sudo ufw allow from 192.168.2.0/24 to any port 8924 proto tcp
```

Apache is also restricted to `192.168.1.*` and `192.168.2.*` (plus localhost) in the virtual host above.

## 7. Autostart on boot

The setup script enables these services to start automatically on system boot:

- `apache2` — serves the backend on port 8924
- `mysql` — database
- `ssh` (`openssh-server`) — remote SSH access on port 22
- `ufw` — firewall rules

Verify:

```bash
systemctl is-enabled apache2 mysql ssh
```

## 8. Verify

```bash
ss -tlnp | grep 8924
wget -qO- http://YOUR_SERVER_IP:8924/webapi_config.php
```

Expected URLs:

| Service | URL |
|---------|-----|
| Web dashboard | `https://YOUR_SERVER_IP:8924/html/` |
| Client API | `https://YOUR_SERVER_IP:8924/webapi.php` |
| Config API | `https://YOUR_SERVER_IP:8924/webapi_config.php` |

## 9. Client configuration

Point monitor clients to:

```
server_ip=YOUR_SERVER_IP
port=8924
```

## Troubleshooting

| Issue | Fix |
|-------|-----|
| Port 8924 not listening | Add `Listen 8924` to `ports.conf`, restart Apache |
| 403/404 errors | Check `DocumentRoot` path and `www-data` permissions |
| DB connection failed | Configure `MONITOR_DB_*` in PHP process environment, check migration and least-privilege MySQL account |
| Cannot reach from other PCs | Open UFW for 8924 from `192.168.1.0/24` and `192.168.2.0/24` |
