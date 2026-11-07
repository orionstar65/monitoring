<?php
require_once __DIR__.'/AppSettings.php';
/**
 * Client Configuration Constants
 * 
 * This file contains default configuration settings for all monitoring clients.
 * Clients will fetch these settings via API on startup and periodically refresh them.
 */

class ClientConfig {
    
    // Default time intervals (in seconds)
    const DEFAULT_INTERVALS = [
        'screenshot' => 60,        // Screenshot capture interval (60 seconds)
        'tic' => 300,                // Heartbeat/tic interval (300 seconds)
        'history' => 300,            // Browser history sync interval (300 seconds)
        'keylog' => 300,             // Key logging interval (300 seconds)
        'usb' => 300,                // USB monitoring interval (300 seconds)
        'config_refresh' => 300    // Configuration refresh interval (5 minutes = 300 seconds)
    ];
    
    // Platform-specific overrides (optional)
    const PLATFORM_OVERRIDES = [
        // 'macos' => [
        //     'screenshot' => 60,    // macOS optimized intervals
        //     'tic' => 300,
        //     'history' => 600,
        //     'keylog' => 300,
        //     'usb' => 300
        // ],
        // 'windows' => [
        //     'screenshot' => 5,      // Windows intervals
        //     'tic' => 30,
        //     'history' => 120,
        //     'keylog' => 60,
        //     'usb' => 30
        // ],
        // 'ubuntu' => [
        //     'screenshot' => 10,     // Ubuntu intervals (no screenshot by default)
        //     'tic' => 30,
        //     'history' => 120,
        //     'keylog' => 60,
        //     'usb' => 30
        // ]
    ];
    
    // Server configuration
    const SERVER_CONFIG = [
        'max_retry_attempts' => 3,
        'connection_timeout' => 10,
        'request_timeout' => 30,
        'chunk_size' => 1000,      // For large data uploads
        'compression_enabled' => true
    ];
    
    // Feature flags
    const FEATURES = [
        'screenshot_enabled' => true,
        'browser_history_enabled' => true,
        'keylog_enabled' => true,
        'usb_monitoring_enabled' => true,
        'debug_logging_enabled' => false,
        'auto_update_enabled' => false
    ];
    
    /**
     * Get configuration for a specific platform
     * 
     * @param string $platform Platform name (macos, windows, ubuntu)
     * @return array Configuration array
     */
    public static function getConfig($platform = 'default', $db = null) {
        if(!in_array($platform,['ubuntu','windows','macos','default'],true))$platform='default';
        $config = [
            'intervals' => self::DEFAULT_INTERVALS,
            'server' => self::SERVER_CONFIG,
            'features' => self::FEATURES,
            'platform' => $platform,
            'version' => '1.5',
            'last_updated' => date('Y-m-d H:i:s')
        ];
        // Safe typed defaults if the migration is not installed yet.
        $settings=AppSettings::values($db);
        foreach([
            'screenshot'=>'screenshot','heartbeat'=>'tic',
            'browser'=>'history','keyboard'=>'keylog','usb'=>'usb',
            'config_refresh'=>'config_refresh'
        ] as $key=>$field){
            $config['intervals'][$field]=$settings["interval.$key.seconds"];
            if($platform!=='default'){
                $override=$settings["platform.$platform.interval.$key.seconds"]??null;
                if($override!==null)$config['intervals'][$field]=$override;
            }
        }
        foreach([
            'screenshot'=>'screenshot_enabled',
            'browser_history'=>'browser_history_enabled',
            'keyboard'=>'keylog_enabled','usb'=>'usb_monitoring_enabled',
            'debug_logging'=>'debug_logging_enabled',
            'auto_update'=>'auto_update_enabled'
        ] as $key=>$field){
            $config['features'][$field]=$settings["feature.$key.enabled"];
        }
        foreach([
            'max_retry_attempts'=>'max_retry_attempts',
            'connection_timeout.seconds'=>'connection_timeout',
            'request_timeout.seconds'=>'request_timeout',
            'chunk_size'=>'chunk_size',
            'compression.enabled'=>'compression_enabled'
        ] as $key=>$field){
            $config['server'][$field]=$settings["server.$key"];
        }
        return $config;
    }
    
    /**
     * Get specific interval value
     * 
     * @param string $interval Interval name
     * @param string $platform Platform name
     * @return int Interval value in seconds
     */
    public static function getInterval($interval, $platform = 'default') {
        $config = self::getConfig($platform);
        return $config['intervals'][$interval] ?? self::DEFAULT_INTERVALS[$interval] ?? 60;
    }
    
    /**
     * Validate configuration
     * 
     * @param array $config Configuration to validate
     * @return array Validation result with errors if any
     */
    public static function validateConfig($config) {
        $errors = [];
        
        // Validate intervals
        if (isset($config['intervals'])) {
            foreach ($config['intervals'] as $key => $value) {
                if (!is_numeric($value) || $value < 1) {
                    $errors[] = "Invalid interval value for '$key': $value";
                }
            }
        }
        
        // Validate features
        if (isset($config['features'])) {
            foreach ($config['features'] as $key => $value) {
                if (!is_bool($value)) {
                    $errors[] = "Invalid feature flag for '$key': $value";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>
