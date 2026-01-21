<?php
class JConfig {
    public $dbhost = 'localhost';
    public $dbuser = 'root';
    public $dbpassword = 'acc0924';
    public $dbname = 'monitoring_db';
    
    // Directory paths for storing data
    public $screens_dir_path = './html/data/screens/';
    public $thumbnails_dir_path = './html/data/thumbnails/';
    public $logs_dir_path = './html/data/logs/';
}

// Global variables for backward compatibility
$screens_dir_path = './html/data/screens/';
$thumbnails_dir_path = './html/data/thumbnails/';
$logs_dir_path = './html/data/logs/';

// Create directories if they don't exist
$directories = [$screens_dir_path, $thumbnails_dir_path, $logs_dir_path];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Function to get per-device directory paths
// Uses ONLY id_{monitor_id} format - no fallbacks
function getMonitorPaths($monitor_id) {
    global $screens_dir_path, $thumbnails_dir_path, $logs_dir_path;
    
    if (!$monitor_id) {
        error_log("getMonitorPaths: monitor_id is required");
        return null;
    }
    
    // SINGLE FORMAT ONLY: id_{monitor_id}
    $identifier = "id_{$monitor_id}";
    
    return [
        'screens' => $screens_dir_path . $identifier . '/',
        'thumbnails' => $thumbnails_dir_path . $identifier . '/',
        'logs' => $logs_dir_path . $identifier . '/'
    ];
}

// Legacy function name for backward compatibility (deprecated)
function getIPBasedPaths($ip_id, $ip_address = null, $mac_address = null) {
    // Redirect to new function - only monitor_id is used
    return getMonitorPaths($ip_id);
}
?> 