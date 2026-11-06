# Desktop Settings and Server Diagnosis (Ubuntu, Windows, macOS)

The November 2026 changes keep the desktop client version at **1.5**.

## Settings layout

Ubuntu, Windows and macOS Settings use section headings and spacing rather than horizontal divider lines. When the client is authenticated, **Signed-in user** shows both User No and full name, and User No/password input controls disappear. Signing out restores the credential fields.

**Show status notifications** applies immediately on toggle. It only controls OS-level notifications, not the tray/menu connection indicator, monitoring, storage, or debug logs. Windows avoids reusing `NIF_INFO` in its persistent tray icon, macOS removes pending/delivered notifications when switched off, and Ubuntu reads the canonical preference or the legacy INI fallback.

## Diagnose Ubuntu screenshot upload failure

The new Ubuntu client displays detailed errors such as `HTTP 401`, `HTTP 400`, `HTTP 503`, server storage errors, and network/TLS errors. **Rebuild the Ubuntu binary** after pulling `main`; old binaries still show the generic "Upload failed."

From the *client repository root*:

```bash
git pull --ff-only origin main
cmake -S Ubuntu -B Ubuntu/build -DCMAKE_BUILD_TYPE=Release
cmake --build Ubuntu/build -j"$(nproc)"
./Ubuntu/build/MonitorClient
```

From the Ubuntu **server** (not necessarily the same machine):

```bash
cd /var/monitor
sudo apt update
sudo apt install -y php-gd php-mysql
sudo systemctl reload apache2

php -m | grep -Ei '^(gd|mysqli|pdo_mysql)$'
sudo -u www-data test -w /var/monitor/Backend/html/data/screens \
  && echo "Screenshot storage writable" \
  || echo "Screenshot storage NOT writable"
sudo journalctl -u apache2 -n 80 --no-pager
```

If screenshot storage is not writable, inspect its ownership and ACLs before changing anything:

```bash
ls -ld /var/monitor/Backend/html/data /var/monitor/Backend/html/data/screens
namei -l /var/monitor/Backend/html/data/screens
df -h /var/monitor
```

Do **not** use `chmod -R 777`. Fix only the relevant paths using the web server's configured user/group and appropriate access controls.

## Diagnose login redirects / pending role migration

The login POST now includes an explicit CSRF token, and navigating to `/html/auth/login` while signed in redirects to the account's permitted page. If the backend PHP release expects **Super Admin** but the SQL migration is missing, the login page displays a clear setup error instead of silently routing a legacy admin to the delegated Admin view.

**Back up the database before applying the migration:**

```bash
cd /var/monitor
sudo mysqldump --single-transaction monitoring_db \
  | gzip > "$HOME/monitoring-pre-role-$(date +%Y%m%d_%H%M%S).sql.gz"
sudo mysql monitoring_db -e \
  "SHOW COLUMNS FROM users LIKE 'role'; SELECT role,COUNT(*) FROM users GROUP BY role; SHOW TABLES LIKE 'admin_user_assignments';"
```

If the schema is still the old `enum('admin','user')`, apply the migration once with the matching PHP code:

```bash
sudo mysql monitoring_db < Backend/migrations/2026_11_06_super_admin_scoped_admin.sql
sudo systemctl reload apache2
```

The migration detects the legacy schema **before** changing the enum and only promotes legacy admins when applicable. Never bulk-promote all `admin` accounts if the new three-role schema already exists, as those may be delegated Admin accounts.

Perform these checks in a private browser session after deploying matching PHP and SQL versions. Run manually; no GitHub Actions are required.

## Quick source regression checks

```bash
php Backend/tests/desktop_settings_notification_contract.php
php -l Backend/html/app/controllers/Auth.php
php -l Backend/webapi.php
php -l Backend/html/app/views/auth/login.php
```

Native Windows/macOS builds and real OS notification deliveries need to be tested on those platforms. Source checks cannot prove their runtime behavior.
