# Employee Monitoring System - Server Installation Guide

## 📋 Overview

This is a comprehensive employee monitoring system built with CodeIgniter 4 that tracks browser history, keyboard activity, USB device usage, and screenshots. The system includes advanced features like video slideshow generation from screenshots using FFmpeg.

## 🎯 Features

- **Real-time Monitoring**: Live screenshot viewing with CCTV-style interface
- **Comprehensive Logging**: Browser history, key logs, USB device logs
- **Data Export**: CSV exports and organized file exports with video slideshows
- **Usage Reports**: Daily/weekly/monthly monitoring statistics
- **Video Archiving**: Automatic conversion of screenshots to MP4 videos using FFmpeg
- **Multi-device Support**: Monitor multiple devices simultaneously
- **Responsive Web Interface**: Modern, mobile-friendly dashboard

## 🔧 System Requirements

### Minimum Requirements
- **PHP**: 8.0 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **FFmpeg**: For video slideshow generation (required for screenshot export)
- **Memory**: 2GB RAM minimum, 4GB recommended
- **Storage**: 10GB minimum for logs and screenshots

### PHP Extensions Required
- `pdo_mysql` - Database connectivity
- `gd` or `imagick` - Image processing
- `mbstring` - Multibyte string support
- `curl` - HTTP requests
- `json` - JSON processing
- `fileinfo` - File type detection

## 📦 Installation Guide

### 🍎 macOS Installation

#### 1. Install XAMPP
```bash
# Download and install XAMPP from https://www.apachefriends.org/
# Or use Homebrew
brew install --cask xampp
```

#### 2. Install FFmpeg
```bash
# Using Homebrew (recommended)
brew install ffmpeg

# Verify installation
ffmpeg -version
```

#### 3. Setup Project
```bash
# Navigate to XAMPP htdocs
cd /Applications/XAMPP/htdocs

# Clone or copy your project
git clone <your-repo> monitoring-system
cd monitoring-system

# Set permissions
sudo chown -R daemon:daemon html/
sudo chmod -R 755 html/
```

#### 4. Database Setup
```bash
# Start XAMPP services
sudo /Applications/XAMPP/xamppfiles/xampp start

# Access phpMyAdmin at http://localhost/phpmyadmin
# Create database: monitoring_db
# Import the SQL schema file
```

#### 5. Configure Environment
```bash
# Copy environment file
cp html/app/Config/App.php.example html/app/Config/App.php

# Edit database configuration
nano html/app/Config/Database.php
```

#### 6. FFmpeg Integration (Important!)
Since XAMPP on macOS may have library conflicts with system FFmpeg, create a wrapper:

```bash
# Create FFmpeg wrapper for XAMPP compatibility
sudo nano /usr/local/bin/ffmpeg_xampp

# Add this content:
#!/bin/bash
export DYLD_LIBRARY_PATH="/usr/local/lib:$DYLD_LIBRARY_PATH"
exec /usr/local/bin/ffmpeg "$@"

# Make executable
sudo chmod +x /usr/local/bin/ffmpeg_xampp
```

### 🪟 Windows Installation

#### 1. Install XAMPP
1. Download XAMPP from https://www.apachefriends.org/
2. Install with default settings
3. Start Apache and MySQL services

#### 2. Install FFmpeg
**Option A: Using Chocolatey (Recommended)**
```powershell
# Install Chocolatey first (if not installed)
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

# Install FFmpeg
choco install ffmpeg
```

**Option B: Manual Installation**
1. Download FFmpeg from https://ffmpeg.org/download.html#build-windows
2. Extract to `C:\ffmpeg`
3. Add `C:\ffmpeg\bin` to Windows PATH:
   - Open System Properties → Environment Variables
   - Add `C:\ffmpeg\bin` to PATH variable

**Verify Installation:**
```cmd
ffmpeg -version
```

#### 3. Setup Project
```cmd
# Navigate to XAMPP htdocs
cd C:\xampp\htdocs

# Copy your project files
# Set permissions (Windows handles this automatically)
```

#### 4. Database Setup
1. Access phpMyAdmin at http://localhost/phpmyadmin
2. Create database: `monitoring_db`
3. Import SQL schema file

#### 5. Configure Environment
1. Copy `html/app/Config/App.php.example` to `html/app/Config/App.php`
2. Edit `html/app/Config/Database.php` with your database credentials

#### 6. Windows-Specific FFmpeg Configuration
The system will automatically detect FFmpeg in these locations:
- `C:\ffmpeg\bin\ffmpeg.exe`
- `C:\ProgramData\chocolatey\bin\ffmpeg.exe`
- System PATH `ffmpeg`

### 🐧 Ubuntu/Debian Installation

#### 1. Install LAMP Stack
```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache, MySQL, PHP
sudo apt install apache2 mysql-server php8.1 php8.1-mysql php8.1-gd php8.1-mbstring php8.1-curl php8.1-json php8.1-fileinfo -y

# Enable Apache modules
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### 2. Install FFmpeg
```bash
# Install FFmpeg
sudo apt install ffmpeg -y

# Verify installation
ffmpeg -version
```

#### 3. Setup Project
```bash
# Navigate to web root
cd /var/www/html

# Copy your project
sudo cp -r /path/to/your/project .

# Set permissions
sudo chown -R www-data:www-data monitoring-system/
sudo chmod -R 755 monitoring-system/html/
```

#### 4. Database Setup
```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database
sudo mysql -u root -p
CREATE DATABASE monitoring_db;
CREATE USER 'monitoring_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON monitoring_db.* TO 'monitoring_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 5. Configure Apache Virtual Host
```bash
# Create virtual host
sudo nano /etc/apache2/sites-available/monitoring.conf

# Add this configuration:
<VirtualHost *:80>
    ServerName monitoring.local
    DocumentRoot /var/www/html/monitoring-system/html
    
    <Directory /var/www/html/monitoring-system/html>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/monitoring_error.log
    CustomLog ${APACHE_LOG_DIR}/monitoring_access.log combined
</VirtualHost>

# Enable site
sudo a2ensite monitoring.conf
sudo systemctl reload apache2
```

## 🔧 Configuration

### Database Configuration
Edit `html/app/Config/Database.php`:
```php
public $default = [
    'DSN'      => '',
    'hostname' => 'localhost',
    'username' => 'monitoring_user',
    'password' => 'your_password',
    'database' => 'monitoring_db',
    'DBDriver' => 'MySQLi',
    'DBPrefix' => '',
    'pConnect' => false,
    'DBDebug'  => true,
    'charset'  => 'utf8',
    'DBCollat' => 'utf8_general_ci',
    'swapPre'  => '',
    'encrypt'  => false,
    'compress' => false,
    'strictOn' => false,
    'failover' => [],
    'saveQueries' => true,
];
```

### FFmpeg Configuration
The system automatically detects FFmpeg in these locations:

**macOS:**
- `/usr/local/bin/ffmpeg_xampp` (XAMPP wrapper - recommended)
- `/usr/local/bin/ffmpeg`
- `/opt/homebrew/bin/ffmpeg`

**Windows:**
- `C:\ffmpeg\bin\ffmpeg.exe`
- `C:\ProgramData\chocolatey\bin\ffmpeg.exe`
- System PATH `ffmpeg`

**Linux:**
- `/usr/bin/ffmpeg`
- `/usr/local/bin/ffmpeg`

### Video Export Settings
The system creates MP4 videos with these optimized settings:
- **Resolution**: 1280x720 (720p)
- **Frame Rate**: 1 FPS (1 second per screenshot)
- **Codec**: H.264 with CRF 28 (good compression)
- **Format**: MP4 for maximum compatibility

## 🚀 Usage

### 1. Access the System
- **URL**: `http://localhost/monitoring-system/html/`
- **Default Login**: Configure in your authentication system

### 2. Key Features

#### Real-time Monitoring
- View live screenshots from all monitored devices
- CCTV-style full-screen view with auto-refresh
- Device status indicators

#### Data Export
- **Selective Export**: Choose specific devices or all devices
- **Date Range**: Export data for any date range (max 31 days)
- **Video Slideshows**: Screenshots automatically converted to MP4 videos
- **Organized Structure**: Data organized by device and date

#### Reports
- **Usage Statistics**: Daily, weekly, monthly reports
- **Activity Analysis**: Captured hours, offline hours, activity gaps
- **CSV Export**: Download reports as CSV files

### 3. File Structure
```
exports/
├── export_2025-10-03_14-30-15/
│   ├── progress.txt                    # Export progress log
│   ├── User1_192_168_1_100/           # Device folder (sanitized names)
│   │   ├── screenshots/
│   │   │   ├── 2025-10-03/
│   │   │   │   └── User1_192_168_1_100_screenshots_2025-10-03.mp4
│   │   ├── keylog/
│   │   │   └── keylog_2025-10-03.csv
│   │   ├── usb_log/
│   │   │   └── usb_log_2025-10-03.csv
│   │   └── browser_history/
│   │       └── browser_history_2025-10-03.csv
```

## 🔧 Troubleshooting

### Common Issues

#### 1. FFmpeg Not Found
**Error**: `FFmpeg is not available`
**Solution**:
```bash
# Check if FFmpeg is installed
ffmpeg -version

# If not found, install according to your OS above
# For XAMPP on macOS, ensure the wrapper is created
```

#### 2. Permission Issues
**Error**: `Failed to create export directory`
**Solution**:
```bash
# macOS/Linux
sudo chown -R daemon:daemon html/exports/
sudo chmod -R 755 html/exports/

# Windows - Run as Administrator or check folder permissions
```

#### 3. Video Creation Fails
**Error**: `Video creation failed: Failed to create temporary directory`
**Solution**:
- Check PHP `sys_get_temp_dir()` permissions
- Ensure FFmpeg is properly installed and accessible
- Verify PHP `exec()` function is enabled

#### 4. Database Connection Issues
**Error**: `Database connection failed`
**Solution**:
- Verify database credentials in `Database.php`
- Ensure MySQL/MariaDB service is running
- Check database exists and user has proper permissions

### Debug Mode
Enable debug mode in `html/app/Config/App.php`:
```php
public $CI_ENVIRONMENT = 'development';
```

## 📊 Performance Optimization

### 1. Database Optimization
```sql
-- Add indexes for better performance
ALTER TABLE screenshot_metadata ADD INDEX idx_monitor_datetime (monitor_ip_id, capture_datetime);
ALTER TABLE key_logs ADD INDEX idx_monitor_datetime (monitor_ip_id, created_at);
ALTER TABLE browser_history_logs ADD INDEX idx_monitor_datetime (monitor_ip_id, created_at);
ALTER TABLE usb_logs ADD INDEX idx_monitor_datetime (monitor_ip_id, created_at);
```

### 2. PHP Configuration
```ini
# Increase PHP limits in php.ini
max_execution_time = 300
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M
```

### 3. Storage Management
- Regularly clean old export files
- Monitor disk space for screenshot storage
- Consider implementing automatic cleanup policies

## 🔒 Security Considerations

### 1. Access Control
- Implement proper authentication and authorization
- Restrict access to sensitive endpoints
- Use HTTPS in production environments

### 2. Data Protection
- Encrypt sensitive data in transit and at rest
- Implement proper backup strategies
- Follow data retention policies

### 3. Network Security
- Use firewall rules to restrict access
- Monitor network traffic
- Implement intrusion detection

## 📞 Support

For technical support or questions:
1. Check this README for common solutions
2. Review the troubleshooting section
3. Check PHP error logs: `html/writable/logs/`
4. Verify FFmpeg installation and permissions

## 📄 License

This project is proprietary software. Please ensure compliance with local laws and regulations regarding employee monitoring.

---

**Last Updated**: October 2025  
**Version**: 1.0.0  
**Compatible OS**: macOS, Windows, Ubuntu/Debian
