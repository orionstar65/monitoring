<?php
/**
 * Status Check Script
 * Cron job entry point for checking client status and sending Slack notifications
 * 
 * Usage:
 * - Manual execution: php check_status.php
 * - Cron job: 
 * *\/5 * * * * cd /path/to/Backend && php check_status.php >> logs/slack_notifications.log 2>&1
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Asia/Vladivostok');

// Include config file first to check server type
require_once './html/app/config/slack_config.php';

// Check if server type is configured - exit early if not set
if (!defined('SLACK_SERVER_TYPE') || empty(SLACK_SERVER_TYPE)) {
    echo "[" . date('Y-m-d H:i:s') . "] SLACK_SERVER_TYPE is not configured. Exiting without action.\n";
    exit(0); // Exit successfully (not an error, just no action needed)
}

// Include required files
require_once './services/StatusMonitorService.php';

// Command line argument handling
$action = $argv[1] ?? 'check';

try {
    $statusMonitor = new StatusMonitorService();
    
    switch ($action) {
        case 'check':
            // Default action - check status and notify
            echo "[" . date('Y-m-d H:i:s') . "] Starting status check...\n";
            
            $success = $statusMonitor->checkAndNotify();
            
            if ($success) {
                echo "[" . date('Y-m-d H:i:s') . "] Status check completed successfully\n";
                exit(0);
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Status check failed\n";
                exit(1);
            }
            break;
            
        case 'test':
            // Test Slack connection
            echo "[" . date('Y-m-d H:i:s') . "] Testing Slack connection...\n";
            
            $success = $statusMonitor->testSlackConnection();
            
            if ($success) {
                echo "[" . date('Y-m-d H:i:s') . "] Slack connection test successful\n";
                exit(0);
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Slack connection test failed\n";
                exit(1);
            }
            break;
            
        case 'summary':
            // Get status summary
            echo "[" . date('Y-m-d H:i:s') . "] Getting status summary...\n";
            
            $summary = $statusMonitor->getStatusSummary();
            
            if ($summary) {
                echo "Status Summary:\n";
                echo "===============\n";
                echo "Check Time: " . $summary['check_time'] . "\n";
                echo "Threshold: " . $summary['threshold_minutes'] . " minutes\n";
                echo "Users with inactive clients: " . $summary['total_users_with_inactive_clients'] . "\n";
                echo "Total inactive clients: " . $summary['total_inactive_clients'] . "\n\n";
                
                if (!empty($summary['users'])) {
                    foreach ($summary['users'] as $user) {
                        echo "User: " . $user['username'] . " (" . $user['inactive_count'] . " inactive clients)\n";
                        foreach ($user['clients'] as $client) {
                            echo "  - " . $client['name'] . " (" . $client['ip_address'] . ") - " . $client['status'] . " - Last update: " . $client['last_update'] . "\n";
                        }
                        echo "\n";
                    }
                } else {
                    echo "No inactive clients found.\n";
                }
                
                exit(0);
            } else {
                echo "[" . date('Y-m-d H:i:s') . "] Failed to get status summary\n";
                exit(1);
            }
            break;
            
        case 'help':
        default:
            // Show help
            echo "Slack Status Check Script\n";
            echo "========================\n\n";
            echo "Usage: php check_status.php [action]\n\n";
            echo "Actions:\n";
            echo "  check   - Check status and send notifications (default)\n";
            echo "  test    - Test Slack webhook connection\n";
            echo "  summary - Show detailed status summary\n";
            echo "  help    - Show this help message\n\n";
            echo "Examples:\n";
            echo "  php check_status.php\n";
            echo "  php check_status.php check\n";
            echo "  php check_status.php test\n";
            echo "  php check_status.php summary\n\n";
            echo "Cron job setup:\n";
            echo "  */5 * * * * cd /path/to/Backend && php check_status.php >> logs/slack_notifications.log 2>&1\n";
            exit(0);
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    error_log("Status check script error: " . $e->getMessage());
    exit(1);
}
?>
