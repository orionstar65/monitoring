#!/bin/bash

# Ubuntu MonitorClient Debian Package Builder
# Version: prompted at release time

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Configuration
PACKAGE_NAME="monitorclient"
# Prompt by default; accept --version for scripted releases.
PACKAGE_VERSION=""
if [[ "${1:-}" == "--version" ]]; then PACKAGE_VERSION="${2:-}"; else
    read -r -p "Package version (MAJOR.MINOR.PATCH, e.g. 1.5.1): " PACKAGE_VERSION
fi
if [[ ! "$PACKAGE_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    print_error "Version must be MAJOR.MINOR.PATCH"; exit 2
fi
PACKAGE_ARCH="amd64"
PACKAGE_MAINTAINER="MonitorClient Team"
PACKAGE_DESCRIPTION="System monitoring client for Ubuntu"

print_status "Building Debian package for MonitorClient..."

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Check if we're in the right directory
if [[ ! -f "CMakeLists.txt" ]]; then
    print_error "CMakeLists.txt not found. Please run this script from the Ubuntu MonitorClient directory."
    exit 1
fi

# Clean previous builds
print_status "Cleaning previous builds..."
rm -rf build package

# Build the application
print_status "Building MonitorClient..."
mkdir -p build
cd build
cmake .. -DCMAKE_BUILD_TYPE=Release -DMONITORCLIENT_VERSION="$PACKAGE_VERSION"
cmake --build . -j"$(nproc)"
cd ..

print_success "MonitorClient built successfully!"

# Create package structure
print_status "Creating package structure..."
PACKAGE_DIR="package/${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}"

mkdir -p "${PACKAGE_DIR}/DEBIAN"
mkdir -p "${PACKAGE_DIR}/opt/monitorclient"
mkdir -p "${PACKAGE_DIR}/opt/monitorclient/config"
mkdir -p "${PACKAGE_DIR}/opt/monitorclient/logs"
mkdir -p "${PACKAGE_DIR}/usr/share/applications"
mkdir -p "${PACKAGE_DIR}/etc/xdg/autostart"
mkdir -p "${PACKAGE_DIR}/etc/systemd/user"
mkdir -p "${PACKAGE_DIR}/etc/logrotate.d"

# Copy executable and files
print_status "Copying files to package..."
cp build/MonitorClient "${PACKAGE_DIR}/opt/monitorclient/"
cp settings.ini "${PACKAGE_DIR}/opt/monitorclient/config/"
cp build/monitorclient-keyreader "${PACKAGE_DIR}/opt/monitorclient/"
chmod +x "${PACKAGE_DIR}/opt/monitorclient/MonitorClient"

# Create desktop file
cat > "${PACKAGE_DIR}/usr/share/applications/MonitorClient.desktop" << EOF
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

# Create autostart file
cp "${PACKAGE_DIR}/usr/share/applications/MonitorClient.desktop" "${PACKAGE_DIR}/etc/xdg/autostart/"

# Create systemd service
cat > "${PACKAGE_DIR}/etc/systemd/user/monitorclient.service" << EOF
[Unit]
Description=MonitorClient System Monitoring Service
After=network.target

[Service]
Type=simple
User=\${USER}
Group=\${USER}
WorkingDirectory=/opt/monitorclient
ExecStart=/opt/monitorclient/MonitorClient
Restart=always
RestartSec=10
Environment=DISPLAY=:0

[Install]
WantedBy=multi-user.target
EOF

# Create logrotate configuration
cat > "${PACKAGE_DIR}/etc/logrotate.d/monitorclient" << EOF
/opt/monitorclient/logs/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 644 root root
}
EOF

# Create launcher script
cat > "${PACKAGE_DIR}/opt/monitorclient/launch.sh" << 'EOF'
#!/bin/bash
cd /opt/monitorclient
./MonitorClient
EOF

chmod +x "${PACKAGE_DIR}/opt/monitorclient/launch.sh"

# Create uninstall script
cat > "${PACKAGE_DIR}/opt/monitorclient/uninstall.sh" << 'EOF'
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
sudo rm -f /etc/logrotate.d/monitorclient
sudo rm -f /etc/udev/rules.d/70-monitorclient-input.rules
sudo rm -rf /opt/monitorclient

# Reload systemd & udev
sudo systemctl daemon-reload
sudo udevadm control --reload-rules 2>/dev/null || true

print_success "MonitorClient uninstalled successfully!"
EOF

chmod +x "${PACKAGE_DIR}/opt/monitorclient/uninstall.sh"

# Create README
cat > "${PACKAGE_DIR}/opt/monitorclient/README.txt" << EOF
Ubuntu MonitorClient v${PACKAGE_VERSION}
=========================

Installation completed successfully!

Files installed:
- Executable: /opt/monitorclient/MonitorClient
- Configuration: /opt/monitorclient/config/settings.ini
- Desktop shortcut: /usr/share/applications/MonitorClient.desktop
- Autostart: /etc/xdg/autostart/MonitorClient.desktop
- Systemd service: /etc/systemd/user/monitorclient.service
- Keyboard helper: /opt/monitorclient/monitorclient-keyreader (setgid input)

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

# Create control file
cat > "${PACKAGE_DIR}/DEBIAN/control" << EOF
Package: ${PACKAGE_NAME}
Version: ${PACKAGE_VERSION}
Architecture: ${PACKAGE_ARCH}
Maintainer: ${PACKAGE_MAINTAINER}
Depends: libc6, libqt5core5a, libqt5widgets5, libqt5network5, libqt5sql5-sqlite, libqt5dbus5, libudev1, libx11-6, libxext6, libxrandr2, libsqlite3-0, libdbus-1-3
Section: utils
Priority: optional
Description: ${PACKAGE_DESCRIPTION}
 System monitoring client for Ubuntu that provides:
 - Screenshot monitoring
 - USB device monitoring
 - System information gathering
 - Network communication to backend server
 - Browser history monitoring
 - Keyboard activity monitoring
 .
 This package provides a complete monitoring solution
 for Ubuntu systems with automatic startup and
 system integration.
EOF

# Create postinst script
cat > "${PACKAGE_DIR}/DEBIAN/postinst" << 'EOF'
#!/bin/bash
set -e

# Set proper permissions
chmod +x /opt/monitorclient/MonitorClient
chmod +x /opt/monitorclient/launch.sh
chmod +x /opt/monitorclient/uninstall.sh

# Create logs directory
mkdir -p /opt/monitorclient/logs
chown -R root:root /opt/monitorclient/logs

# Reload systemd
systemctl daemon-reload

# Update desktop database
update-desktop-database /usr/share/applications

# Setgid "input" on the keyreader helper so it can read /dev/input/event* devices.
getent group input >/dev/null 2>&1 || groupadd --system input
chown root:input /opt/monitorclient/monitorclient-keyreader
chmod 2755 /opt/monitorclient/monitorclient-keyreader

echo ""
echo "MonitorClient installed successfully!"
echo "To start: systemctl --user enable monitorclient.service"
echo "To configure: edit /opt/monitorclient/config/settings.ini"
EOF

chmod +x "${PACKAGE_DIR}/DEBIAN/postinst"

# Create prerm script
cat > "${PACKAGE_DIR}/DEBIAN/prerm" << 'EOF'
#!/bin/bash
set -e

# During upgrade, preserve the user's service enablement and data.
case "${1:-}" in
  remove|deconfigure)
    systemctl --user stop monitorclient.service 2>/dev/null || true
    systemctl --user disable monitorclient.service 2>/dev/null || true
    ;;
esac
systemctl daemon-reload
EOF

chmod +x "${PACKAGE_DIR}/DEBIAN/prerm"

# Create postrm script
cat > "${PACKAGE_DIR}/DEBIAN/postrm" << 'EOF'
#!/bin/bash
set -e

# Debian invokes an old version's postrm during upgrades. Never remove
# files from a freshly unpacked new version in that transition.
case "${1:-}" in
  remove|purge)
    rm -f /usr/share/applications/MonitorClient.desktop 2>/dev/null || true
    rm -f /etc/xdg/autostart/MonitorClient.desktop 2>/dev/null || true
    rm -f /etc/systemd/user/monitorclient.service 2>/dev/null || true
    rm -f /etc/logrotate.d/monitorclient 2>/dev/null || true
    update-desktop-database /usr/share/applications 2>/dev/null || true
    ;;
esac
EOF

chmod +x "${PACKAGE_DIR}/DEBIAN/postrm"

# Build the package
print_status "Building Debian package..."
cd package
dpkg-deb --build "${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}"

# Move to parent directory
mv "${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}.deb" ..

print_success "Debian package built successfully!"

# Clean up
cd ..
rm -rf package

print_success "Package created: ${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}.deb"

echo ""
echo "=========================================="
echo "  Debian Package Build Complete!"
echo "=========================================="
echo ""
echo "Package: ${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}.deb"
echo "Size: $(du -h ${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}.deb | cut -f1)"
echo ""
echo "To install the package:"
echo "sudo dpkg -i ${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}.deb"
echo "sudo apt-get install -f  # Install dependencies if needed"
echo ""
echo "To uninstall:"
echo "sudo dpkg -r ${PACKAGE_NAME}"
echo "" 
# The updater expects this exact OS/version filename. Keep the .deb
# contents and dpkg's internal package name unchanged.
UPDATE_PACKAGE="MonitorClient-${PACKAGE_VERSION}-ubuntu-amd64.deb"
cp "${PACKAGE_NAME}_${PACKAGE_VERSION}_${PACKAGE_ARCH}.deb" "$UPDATE_PACKAGE"
python3 "$SCRIPT_DIR/../scripts/publish_update.py" \
  --platform ubuntu-amd64 --version "$PACKAGE_VERSION" \
  --package "$UPDATE_PACKAGE" --output "$SCRIPT_DIR/../release-updates"
echo "Update package published: ../release-updates/$UPDATE_PACKAGE"
