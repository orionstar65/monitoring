<?php
/**
 * Slack Service Class
 * Handles Slack webhook integration and message formatting
 */

class SlackService {
    private $webhook_url;
    private $debug_mode;
    private $log_file;
    
    public function __construct() {
        $this->webhook_url = SLACK_WEBHOOK_URL;
        $this->debug_mode = SLACK_DEBUG_MODE;
        $this->log_file = SLACK_LOG_FILE;
        
        // Set timezone
        // date_default_timezone_set(SLACK_TIMEZONE);
    }
    
    /**
     * Send multiple messages to Slack with rate limiting
     * Returns array with success count and total count
     */
    public function sendMultipleMessages($message_payloads, $delay_ms = 500) {
        if (empty($message_payloads) || !is_array($message_payloads)) {
            return ['success' => 0, 'total' => 0];
        }
        
        $success_count = 0;
        $total_count = count($message_payloads);
        
        foreach ($message_payloads as $payload) {
            $success = $this->sendMessage($payload['message'], $payload['attachments']);
            
            if ($success) {
                $success_count++;
            } else {
                $this->log("Failed to send one Slack message");
            }
            
            // Add delay between messages to avoid rate limiting
            if ($delay_ms > 0) {
                usleep($delay_ms * 1000); // Convert ms to microseconds
            }
        }
        
        return [
            'success' => $success_count,
            'total' => $total_count
        ];
    }
    
    /**
     * Send message to Slack webhook
     */
    public function sendMessage($message, $attachments = null) {
        if (!SLACK_MESSAGE_ENABLED) {
            $this->log("Slack notifications are disabled");
            return false;
        }
        
        if (empty($this->webhook_url) || strpos($this->webhook_url, 'YOUR/SLACK/WEBHOOK') !== false) {
            $this->log("Slack webhook URL not configured");
            return false;
        }
        
        $server_type = strtoupper(SLACK_SERVER_TYPE ?? 'E');
        $payload = [
            'text' => $message,
            'username' => "[{$server_type}] Monitor Bot",
            'icon_emoji' => ':warning:'
        ];
        
        if (is_array($attachments) && !empty($attachments)) {
            $payload['attachments'] = $attachments;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->webhook_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log("cURL Error: " . $error);
            return false;
        }
        
        if ($http_code !== 200) {
            $this->log("HTTP Error: " . $http_code . " Response: " . $response);
            return false;
        }
        
        $this->log("Message sent successfully to Slack");
        return true;
    }
    
    /**
     * Build overall status summary message
     * Returns array with single message containing overall statistics
     */
    public function buildOverallStatusMessage($inactive_clients_by_user) {
        if (empty($inactive_clients_by_user)) {
            return null; // No inactive clients, don't send message
        }
        
        $current_time = date('H:i');
        $current_date = date('M j');
        
        // Calculate statistics
        $total_users = count($inactive_clients_by_user);
        $total_computers = 0;
        $total_offline = 0;
        $total_inactive = 0;
        
        $users_list = [];
        
        foreach ($inactive_clients_by_user as $group_key => $clients) {
            if (empty($clients)) { continue; }
            
            $total_computers += count($clients);
            
            // Count offline vs inactive
            foreach ($clients as $client) {
                if ($client['status'] === 'offline') {
                    $total_offline++;
                } elseif ($client['status'] === 'inactive') {
                    $total_inactive++;
                }
            }
            
            // Store user info for the list
            $display_name = $group_key;
            if (!empty($clients[0]['slack_id'])) {
                $display_name = $clients[0]['slack_id'];
            } elseif (!empty($clients[0]['username'])) {
                $display_name = $clients[0]['username'];
            }
            
            $users_list[] = $display_name;
        }
        
        // Build the summary message
        $message = "";
        $server_type = strtoupper(SLACK_SERVER_TYPE ?? 'E');
        
        $attachments = [[
            'color' => "#FF0000",
            'title' => "[{$server_type}] Monitoring System Status - {$current_date} {$current_time}",
            'fields' => [],
            'footer' => "Last check: {$current_time} • Total inactive: {$total_computers} computers",
            'ts' => time()
        ]];
        
        return [[
            'message' => $message,
            'attachments' => $attachments
        ]];
    }
    
    /**
     * Build individual user messages (without header)
     * Returns array of separate messages (one per user) to avoid Slack length limits
     */
    public function buildUserMessages($inactive_clients_by_user) {
        if (empty($inactive_clients_by_user)) {
            return []; // No inactive clients, return empty array
        }
        
        $current_time = date('H:i');
        $server_type = strtoupper(SLACK_SERVER_TYPE ?? 'E');
        $messages = [];
        
        foreach ($inactive_clients_by_user as $group_key => $clients) {
            if (empty($clients)) { continue; }
            
            $attachment_text_lines = [];
            
            foreach ($clients as $client) {
                $computer_name = $this->truncateString($client['username'] ?? $client['ip_address'], 30);
                $ip = $client['ip_address'] ?? '';
                $status = $this->getStatusText($client['status']);
                $statusIcon = $this->getStatusIconSimple($client['status']);
                $last_update = $this->formatTimeWithDiff($client['last_update']);
                $screenshot = $this->formatTimeWithDiff($client['last_screenshot']);
                $keylog = $this->formatTimeWithDiff($client['last_keylog']);
                $browser = $this->formatTimeWithDiff($client['last_browser']);
                $usb = $this->formatTimeWithDiff($client['last_usb']);
                
                $code_block = [];
                $code_block[] = "{$statusIcon} {$computer_name} ({$client['type']}): {$status} from {$last_update}";
                $code_block[] = "📸 {$screenshot}   ⌨️ {$keylog}   🌐 {$browser}   🔌 {$usb}";
                $code_block[] = ($ip ? " {$ip}" : "") . "    {$client['mac_address']}";

                
                $attachment_text_lines[] = "```\n" . implode("\n", $code_block) . "\n```";
            }
            
            // Determine display name for the group
            $display_name = $group_key;
            if (!empty($clients[0]['slack_id'])) {
                $display_name = $clients[0]['slack_id'];
            } elseif (!empty($clients[0]['username'])) {
                $display_name = $clients[0]['username'];
            }
            
            // Create individual message for each user (no header)
            $message = ""; // Empty message, just the attachment
            $attachments = [[
                'color' => $this->getAttachmentColor($clients),
                'title' => "[{$server_type}] 🫵🏻 <@{$display_name}>",
                'text' => implode("\n\n", $attachment_text_lines),
                'footer' => "Last checked: {$current_time}",
                'ts' => time()
            ]];
            
            $messages[] = [
                'message' => $message,
                'attachments' => $attachments
            ];
        }
        
        return $messages;
    }
    
    /**
     * Build complete message set: overall status + individual user messages
     * Returns array with overall status message first, then individual user messages
     */
    public function buildInactiveClientsMessage($inactive_clients_by_user) {
        if (empty($inactive_clients_by_user)) {
            return null; // No inactive clients, don't send message
        }
        
        $messages = [];
        
        // First message: Overall status summary
        $overall_message = $this->buildOverallStatusMessage($inactive_clients_by_user);
        if ($overall_message) {
            $messages = array_merge($messages, $overall_message);
        }
        
        // Subsequent messages: Individual user details
        $user_messages = $this->buildUserMessages($inactive_clients_by_user);
        $messages = array_merge($messages, $user_messages);
        
        return $messages;
    }
    
    /**
     * Determine attachment color based on overall status
     */
    private function getOverallAttachmentColor($total_offline, $total_inactive) {
        if ($total_offline > 0) {
            return '#D32F2F'; // Red - critical
        } elseif ($total_inactive > 0) {
            return '#FBC02D'; // Yellow - warning
        }
        return '#4CAF50'; // Green - all good (shouldn't happen here)
    }
    
    /**
     * Format timestamp to HH:MM format
     */
    private function formatTime($timestamp) {
        if (empty($timestamp) || $timestamp === '0000-00-00 00:00:00') {
            return 'Never';
        }
        
        return date('H:i', strtotime($timestamp));
    }
    
    /**
     * Format timestamp with time difference (e.g., "10:30 (2h 15m ago)")
     */
    private function formatTimeWithDiff($timestamp) {
        if (empty($timestamp) || $timestamp === '0000-00-00 00:00:00') {
            return 'Never';
        }
        
        $time_str = date('H:i', strtotime($timestamp));
        $time_diff = $this->calculateTimeDiff($timestamp);
        
        return "{$time_str} ({$time_diff})";
    }
    
    /**
     * Calculate and format time difference from now
     * Returns human-readable format like "2h 30m ago", "5m ago", "3d ago"
     */
    private function calculateTimeDiff($timestamp) {
        if (empty($timestamp) || $timestamp === '0000-00-00 00:00:00') {
            return 'Never';
        }
        
        $timestamp_unix = strtotime($timestamp);
        $now = time();
        $diff_seconds = $now - $timestamp_unix;
        
        if ($diff_seconds < 0) {
            return 'Future';
        }
        
        if ($diff_seconds < 60) {
            return $diff_seconds . 's ago';
        }
        
        $diff_minutes = floor($diff_seconds / 60);
        if ($diff_minutes < 60) {
            return $diff_minutes . 'm ago';
        }
        
        $diff_hours = floor($diff_minutes / 60);
        $remaining_minutes = $diff_minutes % 60;
        if ($diff_hours < 24) {
            if ($remaining_minutes > 0) {
                return $diff_hours . 'h ' . $remaining_minutes . 'm ago';
            }
            return $diff_hours . 'h ago';
        }
        
        $diff_days = floor($diff_hours / 24);
        $remaining_hours = $diff_hours % 24;
        if ($diff_days < 30) {
            if ($remaining_hours > 0) {
                return $diff_days . 'd ' . $remaining_hours . 'h ago';
            }
            return $diff_days . 'd ago';
        }
        
        $diff_months = floor($diff_days / 30);
        $remaining_days = $diff_days % 30;
        if ($remaining_days > 0) {
            return $diff_months . 'mo ' . $remaining_days . 'd ago';
        }
        return $diff_months . 'mo ago';
    }
    
    /**
     * Get status text based on last activity
     */
    private function getStatusText($status) {
        switch ($status) {
            case 'offline':
                return 'Offline';
            case 'inactive':
                return 'Inactive';
            default:
                return 'Unknown';
        }
    }

    /**
     * Minimal status icon for inline text
     */
    private function getStatusIconSimple($status) {
        switch ($status) {
            case 'offline':
                return '🔴';
            case 'inactive':
                return '🟡';
            default:
                return '⚪️';
        }
    }

    /**
     * Determine attachment color for a user's section
     */
    private function getAttachmentColor($clients) {
        $has_offline = false;
        $has_inactive = false;
        foreach ($clients as $c) {
            if (($c['status'] ?? '') === 'offline') { $has_offline = true; }
            if (($c['status'] ?? '') === 'inactive') { $has_inactive = true; }
        }
        if ($has_offline) { return '#D32F2F'; } // red
        if ($has_inactive) { return '#FBC02D'; } // yellow
        return '#9E9E9E'; // grey fallback
    }
    
    /**
     * Truncate string to specified length
     */
    private function truncateString($string, $length) {
        if (strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length - 3) . '...';
    }
    
    /**
     * Log messages
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}\n";
        
        if ($this->debug_mode) {
            error_log($log_entry);
        }
        
        // Write to log file
        @file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Test webhook connection
     */
    public function testConnection() {
        $test_message = "🧪 *Test Message* - " . date('H:i') . "\n\nThis is a test message from the monitoring system.";
        return $this->sendMessage($test_message);
    }
}
?>
