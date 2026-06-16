#!/bin/bash
set -euo pipefail

# ACC-Monitor backend - full Ubuntu server installer
# Usage: sudo bash setup_ubuntu_server.sh [PORT]
#        sudo bash setup_ubuntu_server.sh --port PORT

usage() {
    cat <<EOF
Usage: sudo bash $0 [PORT]
       sudo bash $0 --port PORT

Options:
  PORT, --port, -p   Backend port (default: 8924)
  --help, -h         Show this help

Environment variables:
  BACKEND_PORT, SERVER_IP, GIT_URL, DB_ROOT_PASS, INSTALL_DIR, BACKEND_DIR
EOF
}

if [[ "${EUID:-}" -ne 0 ]]; then
    echo "Run as root: sudo bash $0"
    exit 1
fi

BACKEND_PORT="${BACKEND_PORT:-8924}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        -h|--help)
            usage
            exit 0
            ;;
        -p|--port)
            if [[ -z "${2:-}" ]]; then
                echo "ERROR: --port requires a value"
                usage
                exit 1
            fi
            BACKEND_PORT="$2"
            shift 2
            ;;
        --port=*)
            BACKEND_PORT="${1#*=}"
            shift
            ;;
        *)
            if [[ "$1" =~ ^[0-9]+$ ]]; then
                BACKEND_PORT="$1"
                shift
            else
                echo "ERROR: Unknown option: $1"
                usage
                exit 1
            fi
            ;;
    esac
done

if ! [[ "$BACKEND_PORT" =~ ^[0-9]+$ ]] || (( BACKEND_PORT < 1 || BACKEND_PORT > 65535 )); then
    echo "ERROR: Invalid port: $BACKEND_PORT"
    exit 1
fi
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="${BACKEND_DIR:-$SCRIPT_DIR}"
INSTALL_DIR="${INSTALL_DIR:-$(dirname "$BACKEND_DIR")}"
GIT_URL="${GIT_URL:-http://192.168.1.245:5000/orionstar65/monitor.git}"
SERVER_IP="${SERVER_IP:-$(hostname -I | awk '{print $1}')}"
DB_ROOT_PASS="${DB_ROOT_PASS:-acc0924}"

echo "=== ACC-Monitor Ubuntu setup ==="
echo "Install dir : $INSTALL_DIR"
echo "Backend dir : $BACKEND_DIR"
echo "Server IP   : $SERVER_IP"
echo "Backend port: $BACKEND_PORT"
echo ""

echo "=== 1. Install system packages ==="
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y \
    apache2 \
    mysql-server \
    php \
    php-mysql \
    php-gd \
    php-mbstring \
    php-curl \
    php-xml \
    php-zip \
    libapache2-mod-php \
    ffmpeg \
    git \
    wget \
    ufw

a2enmod rewrite
PHP_MOD="$(php -r 'echo "php".PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
a2enmod "$PHP_MOD" 2>/dev/null || a2enmod php 2>/dev/null || true

systemctl enable --now mysql apache2

echo "=== 2. Install application files ==="
if [[ ! -f "$BACKEND_DIR/webapi.php" ]]; then
    echo "Backend not found at $BACKEND_DIR"
    if [[ -d "$INSTALL_DIR/.git" ]]; then
        echo "Updating existing repo at $INSTALL_DIR"
        git -C "$INSTALL_DIR" pull --ff-only || true
    else
        echo "Cloning from $GIT_URL"
        mkdir -p "$INSTALL_DIR"
        git clone "$GIT_URL" "$INSTALL_DIR"
        BACKEND_DIR="$INSTALL_DIR/Backend"
    fi
fi

if [[ ! -f "$BACKEND_DIR/webapi.php" ]]; then
    echo "ERROR: Backend files missing at $BACKEND_DIR"
    exit 1
fi

mkdir -p \
    "$BACKEND_DIR/html/data/screens" \
    "$BACKEND_DIR/html/data/thumbnails" \
    "$BACKEND_DIR/html/data/logs" \
    "$BACKEND_DIR/html/exports"

set_app_permissions() {
    echo "Setting ownership and permissions for www-data (excluding .git)..."
    DEPLOY_USER="${SUDO_USER:-${USER:-root}}"

    # chown app files only; leave .git for the deploy user so git commit/pull works
    find "$INSTALL_DIR" -mindepth 1 \
        \( -path "$INSTALL_DIR/.git" -o -path "$INSTALL_DIR/.git/*" \) -prune \
        -o -exec chown www-data:www-data {} +

    if [[ -d "$INSTALL_DIR/.git" ]]; then
        chown -R "$DEPLOY_USER:$(id -gn "$DEPLOY_USER")" "$INSTALL_DIR/.git"
    fi

    find "$BACKEND_DIR" -type d -exec chmod 755 {} \;
    find "$BACKEND_DIR" -type f -exec chmod 644 {} \;
    chmod 755 "$BACKEND_DIR/setup_ubuntu_server.sh" 2>/dev/null || true
    for dir in \
        "$BACKEND_DIR/html/data" \
        "$BACKEND_DIR/html/data/screens" \
        "$BACKEND_DIR/html/data/thumbnails" \
        "$BACKEND_DIR/html/data/logs" \
        "$BACKEND_DIR/html/exports"; do
        [[ -d "$dir" ]] && chmod 775 "$dir"
    done
}

set_app_permissions

echo "=== 3. Configure MySQL ==="
if mysql -e "SELECT 1" >/dev/null 2>&1; then
    mysql <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_ROOT_PASS}';
FLUSH PRIVILEGES;
SQL
else
    mysql -u root -p"${DB_ROOT_PASS}" -e "SELECT 1" >/dev/null 2>&1 || {
        echo "ERROR: Cannot connect to MySQL"
        exit 1
    }
fi

if ! mysql -u root -p"${DB_ROOT_PASS}" -e "USE monitoring_db;" >/dev/null 2>&1; then
    mysql -u root -p"${DB_ROOT_PASS}" < "$BACKEND_DIR/database_setup.sql"
    echo "Created monitoring_db"
else
    echo "monitoring_db already exists"
fi

echo "=== 4. Configure Apache on port ${BACKEND_PORT} ==="
cat > /etc/apache2/sites-available/mybackend.conf <<APACHE
<VirtualHost *:${BACKEND_PORT}>
    ServerAdmin webmaster@localhost
    DocumentRoot ${BACKEND_DIR}

    <Directory ${BACKEND_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        <RequireAny>
            Require ip 127.0.0.1
            Require ip 192.168.1.0/24
            Require ip 192.168.2.0/24
        </RequireAny>
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/backend_error.log
    CustomLog \${APACHE_LOG_DIR}/backend_access.log combined
</VirtualHost>
APACHE

if ! grep -q "^Listen ${BACKEND_PORT}" /etc/apache2/ports.conf; then
    sed -i "/^Listen 80/a Listen ${BACKEND_PORT}" /etc/apache2/ports.conf
fi

a2ensite mybackend.conf 2>/dev/null || true
pkill -f "php -S 0.0.0.0:${BACKEND_PORT}" 2>/dev/null || true

echo "=== 5. Configure app URL ==="
CONFIG_FILE="$BACKEND_DIR/html/app/config/config.php"
if [[ -f "$CONFIG_FILE" ]]; then
    sed -i "s|define('BASE_URL', 'http://[^']*');|define('BASE_URL', 'http://${SERVER_IP}:${BACKEND_PORT}/html/');|" "$CONFIG_FILE"
fi

set_app_permissions

echo "=== 6. Configure firewall for local network ==="
ufw --force enable 2>/dev/null || true
ufw allow OpenSSH 2>/dev/null || ufw allow 22/tcp 2>/dev/null || true
ufw allow from 192.168.1.0/24 to any port "${BACKEND_PORT}" proto tcp comment 'ACC-Monitor backend' 2>/dev/null || true
ufw allow from 192.168.2.0/24 to any port "${BACKEND_PORT}" proto tcp comment 'ACC-Monitor backend' 2>/dev/null || true

apache2ctl configtest
systemctl restart apache2

echo "=== 7. Verify ==="
ss -tlnp | grep "${BACKEND_PORT}" || true
ufw status | grep "${BACKEND_PORT}" || true
wget -qO- "http://127.0.0.1:${BACKEND_PORT}/webapi_config.php" 2>&1 | head -c 120 || true

echo ""
echo "Setup complete."
echo "Dashboard : http://${SERVER_IP}:${BACKEND_PORT}/html/"
echo "Client API: http://${SERVER_IP}:${BACKEND_PORT}/webapi.php"
