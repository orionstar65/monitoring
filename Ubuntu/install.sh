#!/bin/bash

# Ubuntu MonitorClient Installation Script
# Version: 1.4

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root. Please run as a regular user."
   exit 1
fi

# Never let the legacy direct installer overwrite a package-managed release
# with its separately configured (possibly older) source-build version.
if command -v dpkg-query >/dev/null 2>&1 && \
   dpkg-query -W -f='${Status}' monitorclient 2>/dev/null | grep -Fxq 'install ok installed'; then
    print_error "MonitorClient is managed by dpkg. Upgrade with: sudo apt install ./monitorclient_VERSION_amd64.deb"
    exit 2
fi

print_status "Starting Ubuntu MonitorClient Installation..."

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Check if we're in the right directory
if [[ ! -f "CMakeLists.txt" ]]; then
    print_error "CMakeLists.txt not found. Please run this script from the Ubuntu MonitorClient directory."
    exit 1
fi

print_status "Installing system dependencies..."

# Update package list
sudo apt update

# Install required packages
sudo apt install -y \
    build-essential \
    cmake \
    pkg-config \
    qtbase5-dev \
    qtbase5-dev-tools \
    libqt5sql5-sqlite \
    libqt5network5t64 \
    libqt5dbus5t64 \
    libqt5widgets5t64 \
    libqt5core5t64 \
    libudev-dev \
    libsecret-tools \
    libx11-dev \
    libxext-dev \
    libxrandr-dev \
    libsqlite3-dev \
    libdbus-1-3 \
    libdbus-1-dev

print_success "System dependencies installed successfully!"

# Create build directory
print_status "Building MonitorClient..."
rm -rf build
mkdir -p build
cd build

# Configure and build
cmake ..
make -j$(nproc)

print_success "MonitorClient built successfully!"

# Create installation directories
print_status "Creating installation directories..."
sudo mkdir -p /opt/monitorclient
sudo mkdir -p /opt/monitorclient/config
sudo mkdir -p /opt/monitorclient/logs

# Replace the binary atomically. Directly overwriting a running ELF can
# raise ETXTBSY or leave a still-running process on the previous version.
print_status "Installing MonitorClient..."
sudo install -m 0755 MonitorClient /opt/monitorclient/.MonitorClient.new
sudo mv -f /opt/monitorclient/.MonitorClient.new /opt/monitorclient/MonitorClient
sudo install -m 0755 ../upgrade-restart.sh /opt/monitorclient/upgrade-restart.sh
# Preserve existing machine defaults. User authentication is stored separately
# under the user's XDG config and secure session keyring.
if ! sudo test -f /opt/monitorclient/config/settings.ini; then
    sudo install -m 0644 ../settings.ini /opt/monitorclient/config/settings.ini
fi
sudo chown root:root /opt/monitorclient /opt/monitorclient/MonitorClient /opt/monitorclient/upgrade-restart.sh

print_success "MonitorClient installed to /opt/monitorclient/"

# Create desktop file
print_status "Creating desktop integration..."
cat > MonitorClient.desktop << EOF
[Desktop Entry]
Version=1.0
Type=Application
Name=MonitorClient
Comment=System Monitoring Client
Exec=/opt/monitorclient/MonitorClient
Icon=monitor
Terminal=false
Categories=System;Utility;
StartupNotify=false
EOF

# Install desktop file
sudo cp MonitorClient.desktop /usr/share/applications/
sudo cp MonitorClient.desktop /etc/xdg/autostart/

# Install the setgid keyreader helper for keyboard monitoring
print_status "Setting up keyboard monitoring helper..."
# The build script already entered Ubuntu/build above.
sudo getent group input >/dev/null 2>&1 || sudo groupadd --system input
sudo install -m 0755 monitorclient-keyreader /opt/monitorclient/.monitorclient-keyreader.new
sudo chown root:input /opt/monitorclient/.monitorclient-keyreader.new
sudo chmod 2755 /opt/monitorclient/.monitorclient-keyreader.new
sudo mv -f /opt/monitorclient/.monitorclient-keyreader.new /opt/monitorclient/monitorclient-keyreader

print_success "Keyboard helper installed (setgid input — no user group change needed)!"

# Create systemd service
print_status "Creating systemd service..."
cat > monitorclient.service << EOF
[Unit]
Description=MonitorClient System Monitoring Service
After=network.target

[Service]
Type=simple
User=$USER
Group=$USER
WorkingDirectory=/opt/monitorclient
ExecStart=/opt/monitorclient/MonitorClient
Restart=always
RestartSec=10
Environment=DISPLAY=:0

[Install]
WantedBy=multi-user.target
EOF

# Install systemd service
sudo cp monitorclient.service /etc/systemd/user/
sudo systemctl daemon-reload

print_success "Systemd service created!"

# Create uninstall script
print_status "Creating uninstall script..."
cat > /opt/monitorclient/uninstall.sh << 'EOF'
#!/bin/bash

# Ubuntu MonitorClient Uninstall Script

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_status "Uninstalling MonitorClient..."

# Stop and disable service
sudo systemctl --user stop monitorclient.service 2>/dev/null || true
sudo systemctl --user disable monitorclient.service 2>/dev/null || true

# Remove files
sudo rm -f /usr/share/applications/MonitorClient.desktop
sudo rm -f /etc/xdg/autostart/MonitorClient.desktop
sudo rm -f /etc/systemd/user/monitorclient.service
sudo rm -rf /opt/monitorclient

# Reload systemd
sudo systemctl daemon-reload

print_success "MonitorClient uninstalled successfully!"
EOF

sudo chmod +x /opt/monitorclient/uninstall.sh

# Create README
cat > /opt/monitorclient/README.txt << 'EOF'
Ubuntu MonitorClient v1.31
=========================

Installation completed successfully!

Files installed:
- Executable: /opt/monitorclient/MonitorClient
- Configuration: /opt/monitorclient/config/settings.ini
- Desktop shortcut: /usr/share/applications/MonitorClient.desktop
- Autostart: /etc/xdg/autostart/MonitorClient.desktop
- Systemd service: /etc/systemd/user/monitorclient.service

Usage:
1. Manual start: /opt/monitorclient/MonitorClient
2. Desktop shortcut: Search for "MonitorClient" in applications
3. Autostart: Application will start automatically on login
4. Systemd service: systemctl --user start monitorclient.service

Configuration:
Edit /opt/monitorclient/config/settings.ini to configure server settings.

Uninstall:
Run /opt/monitorclient/uninstall.sh to remove the application.

Logs:
Check /opt/monitorclient/logs/ for application logs.

Keyboard monitoring:
A small setgid helper (monitorclient-keyreader) reads keyboard events.
Only this helper runs with the "input" group; your user account does
not need any extra permissions.
EOF

print_success "Documentation created!"

# Set up log rotation
print_status "Setting up log rotation..."
sudo tee /etc/logrotate.d/monitorclient > /dev/null << EOF
/opt/monitorclient/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 644 $USER $USER
}
EOF

print_success "Log rotation configured!"

# Create a simple launcher script
cat > /opt/monitorclient/launch.sh << 'EOF'
#!/bin/bash
cd /opt/monitorclient
./MonitorClient
EOF

sudo chmod +x /opt/monitorclient/launch.sh

# Final setup
print_status "Performing final setup..."

# Create logs directory with proper permissions
sudo mkdir -p /opt/monitorclient/logs
sudo chown -R $USER:$USER /opt/monitorclient/logs

# Desktop autostart is already installed in /etc/xdg/autostart.
# Do not enable a second user-service startup path, which could race with the
# graphical session and keep a headless old process running in the background.
# Existing service users may keep managing it explicitly if desired.

print_success "Installation completed successfully!"

echo ""
echo "=========================================="
echo "  Ubuntu MonitorClient Installation Complete!"
echo "=========================================="
echo ""
echo "Installation Summary:"
echo "- Executable: /opt/monitorclient/MonitorClient"
echo "- Configuration: /opt/monitorclient/config/settings.ini"
echo "- Desktop shortcut: Available in Applications menu"
echo "- Autostart: Enabled (starts on login)"
echo "- Systemd service: monitorclient.service"
echo "- Uninstall: /opt/monitorclient/uninstall.sh"
echo ""
echo "To start the application:"
echo "1. Desktop: Search for 'MonitorClient' in Applications"
echo "2. Terminal: /opt/monitorclient/MonitorClient"
echo "3. Service: systemctl --user start monitorclient.service"
echo ""
echo "Configuration: Edit /opt/monitorclient/config/settings.ini"
echo "Logs: Check /opt/monitorclient/logs/"
echo ""
print_success "Keyboard monitoring uses a setgid helper — no group changes to your user needed."
# Restart only the old package-path binary; keep the same logged-in desktop
# user/session and leave unrelated MonitorClient executables untouched.
sudo /opt/monitorclient/upgrade-restart.sh --restart-running || true
echo "Installed: $(/opt/monitorclient/MonitorClient --version)"
echo "" 