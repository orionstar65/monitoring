<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', getenv('MONITOR_DB_USER') ?: 'monitoring_app');
define('DB_PASS', getenv('MONITOR_DB_PASSWORD') ?: '');
define('DB_NAME', getenv('MONITOR_DB_NAME') ?: 'monitoring_db');

// Application configuration
define('APP_NAME', 'ACC-Monitor');
define('APP_VERSION', '1.0.0');
define('BASE_URL', getenv('MONITOR_BASE_URL') ?: '/html/');
// define('BASE_URL', getenv('MONITOR_BASE_URL') ?: '/html/');

// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Session configuration
define('SESSION_TIMEOUT', 3600); // 1 hour

// File paths - Updated to use data folder inside html
define('SCREENS_DIR', 'data/screens/');
define('THUMBNAILS_DIR', 'data/thumbnails/');
define('LOGS_DIR', 'data/logs/');

// Backend integration
define('BACKEND_CONFIG_PATH', '../config.inc.php');
define('BACKEND_INCLUDES_PATH', '../includes/');

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Include backend configuration for IP-based paths
if (file_exists(BACKEND_CONFIG_PATH)) {
    require_once BACKEND_CONFIG_PATH;
}
?> 