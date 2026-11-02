#!/usr/bin/env bash
# Configure dedicated MySQL account for Apache mod_php without altering monitoring data.
set -Eeuo pipefail
umask 077
die(){ echo "ERROR: $*" >&2;exit 1; }
[[ "$EUID" == 0 ]] || die "Use sudo"
for cmd in mysql openssl python3 apache2ctl systemctl;do command -v "$cmd" >/dev/null || die "Missing $cmd";done
db="$(printenv MONITOR_DB_NAME 2>/dev/null || echo monitoring_db)"
[[ "$db" =~ ^[a-zA-Z0-9_]+$ ]] || die "Invalid database name"
site=/etc/apache2/sites-available/mybackend.conf
[[ -f "$site" ]] || die "Apache site not found"
echo "This will NOT drop or change any monitoring database tables or records."
echo "Configuring monitoring_app@localhost in database $db."
if ! mysql -u root --protocol=socket -Nse 'SELECT 1' >/dev/null 2>&1;then
 read -rsp "MySQL root password (hidden): " MYSQL_PWD
 echo
 export MYSQL_PWD
fi
mysql -u root --protocol=socket -Nse 'SELECT 1' >/dev/null 2>&1 || die "Cannot authenticate to MySQL root"
found="$(mysql -u root --protocol=socket -Nse "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='$db'")"
[[ "$found" == 1 ]] || die "Database $db does not exist; no data changed"
password="$(openssl rand -hex 32)"
mysql -u root --protocol=socket <<SQL
CREATE USER IF NOT EXISTS 'monitoring_app'@'localhost' IDENTIFIED BY '$password';
ALTER USER 'monitoring_app'@'localhost' IDENTIFIED BY '$password';
GRANT SELECT, INSERT, UPDATE, DELETE ON $db.* TO 'monitoring_app'@'localhost';
SQL
unset MYSQL_PWD
mkdir -p /etc/monitoring
chmod 700 /etc/monitoring
config=/etc/monitoring/apache-db.conf
[[ ! -f "$config" ]] || cp -p "$config" "$config.backup.$(date -u +%Y%m%dT%H%M%S)"
cat > "$config" <<ENVFILE
# Apache mod_php environment, generated locally. Never commit this file.
SetEnv MONITOR_DB_HOST "localhost"
SetEnv MONITOR_DB_NAME "$db"
SetEnv MONITOR_DB_USER "monitoring_app"
SetEnv MONITOR_DB_PASSWORD "$password"
ENVFILE
chmod 600 "$config"
unset password
if ! grep -Fq "IncludeOptional $config" "$site";then
 cp -p "$site" "$site.backup.$(date -u +%Y%m%dT%H%M%S)"
 python3 - "$site" "$config" <<'PY'
import re,sys
from pathlib import Path
p=Path(sys.argv[1]);s=p.read_text()
s,n=re.subn(r'(?=</VirtualHost>)',"    IncludeOptional "+sys.argv[2]+"\n",s)
if n<1:raise SystemExit("No Apache virtual hosts found")
p.write_text(s)
PY
fi
apache2ctl configtest
systemctl reload apache2
echo "Database access configured. Existing MySQL data is unchanged."
echo "Try http://SERVER_IP:8924/html/auth/login or https://SERVER_IP:8925/html/auth/login"
echo "If login fails because tables are missing, apply the identity migration after a backup."
