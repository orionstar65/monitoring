<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/LogParserService.php';

class View extends Base {
    private $logParser;
    
    public function __construct() {
        parent::__construct();
        $this->logParser = new LogParserService();
    }
    
    public function index() {
        // Get monitors from database only (no expensive file scanning)
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Add basic status based on database data only
        foreach ($monitors as &$monitor) {
            // Determine status based on last_monitor_tic
            $last_tic_value = $monitor['last_monitor_tic'] ?? null;
            $last_tic = ($last_tic_value !== null && $last_tic_value !== '') ? strtotime($last_tic_value) : 0;
            $time_diff = time() - $last_tic;
            
            if ($time_diff <= 300) { // 5 minutes
                $monitor['live_status'] = 'online';
                $monitor['status_class'] = 'success';
                $monitor['status_text'] = 'Online';
            } elseif ($time_diff <= 3600) { // 1 hour
                $monitor['live_status'] = 'inactive';
                $monitor['status_class'] = 'warning';
                $monitor['status_text'] = 'Inactive';
            } else {
                $monitor['live_status'] = 'offline';
                $monitor['status_class'] = 'danger';
                $monitor['status_text'] = 'Offline';
            }
            
            $monitor['latest_activity'] = $last_tic;
        }
        
        $this->view('view/index', ['monitors' => $monitors]);
    }
    

    
    // logs_detail method removed - use database-based logs() method instead
    
    public function api_screenshots() {
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? min(50, max(1, intval($_GET['per_page']))) : 50;
        $offset = ($page - 1) * $per_page;
        
        // Get monitor filter
        $selected_monitors = [];
        if (isset($_GET['monitors']) && !empty($_GET['monitors'])) {
            $selected_monitors = array_map('intval', explode(',', $_GET['monitors']));
        }
        
        // Get specific monitor ID for detail device view
        $specific_monitor_id = isset($_GET['monitor_id']) ? intval($_GET['monitor_id']) : null;
        
        if ($specific_monitor_id) {
            // DETAIL DEVICE VIEW: Load paginated screenshots for a specific monitor from database
            $monitor = $this->db->fetch(
                "SELECT * FROM tbl_monitor_ips WHERE id = :id",
                ['id' => $specific_monitor_id]
            );
            
            if (!$monitor) {
                $this->json(['error' => 'Monitor not found']);
                return;
            }
                
                // Get date filter parameters
                $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
                $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
                
            // Build WHERE clause for date filtering
            $where_conditions = ["monitor_ip_id = :monitor_id"];
            $params = ['monitor_id' => $monitor['id']];
            
            if ($start_date) {
                $where_conditions[] = "capture_datetime >= :start_date";
                $params['start_date'] = $start_date . ' 00:00:00';
            }
            
            if ($end_date) {
                $where_conditions[] = "capture_datetime <= :end_date";
                $params['end_date'] = $end_date . ' 23:59:59';
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Get total count
            $total_screenshots = $this->db->fetch(
                "SELECT COUNT(*) as total FROM screenshot_metadata WHERE {$where_clause}",
                $params
            )['total'];
            
                $total_pages = ceil($total_screenshots / $per_page);
                
            // Get paginated screenshots
            $screenshots = $this->db->fetchAll(
                "SELECT * FROM screenshot_metadata 
                 WHERE {$where_clause} 
                 ORDER BY capture_datetime DESC 
                 LIMIT :limit OFFSET :offset",
                array_merge($params, ['limit' => $per_page, 'offset' => $offset])
            );
            
            // Add monitor info and format data
            foreach ($screenshots as $index => &$screenshot) {
                    $screenshot['monitor'] = $monitor;
                $screenshot['thumbnail'] = $this->getRelativePath($screenshot['thumbnail_path'] ?: $screenshot['file_path']);
                $screenshot['screenshot'] = $this->getRelativePath($screenshot['file_path']);
                $screenshot['global_id'] = $this->generateScreenshotId($monitor['id'], $screenshot['file_path']);
                $screenshot['modified'] = $screenshot['capture_datetime']; // For compatibility
                // Convert UTC datetime to Unix timestamp (explicitly UTC to avoid server timezone issues)
                $screenshot['modified_time'] = (new DateTime($screenshot['capture_datetime'], new DateTimeZone('UTC')))->getTimestamp();
                }
                
                $this->json([
                'screenshots' => $screenshots,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $per_page,
                        'total_screenshots' => $total_screenshots,
                        'total_pages' => $total_pages,
                        'has_next' => $page < $total_pages,
                        'has_prev' => $page > 1
                ],
                'view_type' => 'detail_device',
                'monitor' => $monitor,
                'total_screenshots_for_navigation' => $total_screenshots
                ]);
        } else {
            // ENTIRE DEVICE VIEW: Load latest screenshots for all monitors from database
            $where_conditions = [];
            $params = [];
            
            // Filter monitors if specific ones are selected
            if (!empty($selected_monitors)) {
                $placeholders = str_repeat('?,', count($selected_monitors) - 1) . '?';
                $where_conditions[] = "s.monitor_ip_id IN ({$placeholders})";
                $params = array_merge($params, $selected_monitors);
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $latest_screenshots = $this->db->fetchAll(
                "SELECT s.*, m.ip_address, m.username, m.name as monitor_name
                 FROM screenshot_metadata s
                 JOIN tbl_monitor_ips m ON s.monitor_ip_id = m.id
                 {$where_clause}
                 AND s.id IN (
                     SELECT MAX(id) 
                     FROM screenshot_metadata 
                     GROUP BY monitor_ip_id
                 )
                 ORDER BY s.capture_datetime DESC",
                $params
            );
            
            // Calculate pagination info
            $total_screenshots = count($latest_screenshots);
            $total_pages = ceil($total_screenshots / $per_page);
            
            // Apply pagination
            $paginated_screenshots = array_slice($latest_screenshots, $offset, $per_page);
            
            // Format data for frontend
            foreach ($paginated_screenshots as $index => &$screenshot) {
                $screenshot['monitor'] = [
                    'id' => $screenshot['monitor_ip_id'],
                    'ip_address' => $screenshot['ip_address'],
                    'username' => $screenshot['username'],
                    'name' => $screenshot['monitor_name']
                ];
                $screenshot['thumbnail'] = $this->getRelativePath($screenshot['thumbnail_path'] ?: $screenshot['file_path']);
                $screenshot['screenshot'] = $this->getRelativePath($screenshot['file_path']);
                $screenshot['id'] = $offset + $index + 1;
                $screenshot['global_id'] = $this->generateScreenshotId($screenshot['monitor_ip_id'], $screenshot['file_path']);
                $screenshot['modified'] = $screenshot['capture_datetime']; // For compatibility
                // Convert UTC datetime to Unix timestamp (explicitly UTC to avoid server timezone issues)
                $screenshot['modified_time'] = (new DateTime($screenshot['capture_datetime'], new DateTimeZone('UTC')))->getTimestamp();
            }
            
            $this->json([
                'screenshots' => $paginated_screenshots,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_screenshots' => $total_screenshots,
                    'total_pages' => $total_pages,
                    'has_next' => $page < $total_pages,
                    'has_prev' => $page > 1
                ],
                'view_type' => 'entire_device'
            ]);
        }
    }
    
    // api_logs method removed - use database-based api_logs_paginated() method instead
    
    // api_all_screenshots method removed - use database-based api_screenshots() method instead
    
    public function api_parsed_log($ip_id, $log_type, $file_path) {
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $ip_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
        }
        
        // Decode the file path
        $file_path = urldecode($file_path);
        
        // Construct full path
        $full_path = BASEPATH . 'data/logs/ip_' . $monitor['ip_address'] . '/' . $file_path;
        
        if (!file_exists($full_path)) {
            $this->json(['error' => 'Log file not found']);
        }
        
        // Read file content
        $content = file_get_contents($full_path);
        if ($content === false) {
            $this->json(['error' => 'Unable to read log file']);
        }
        
        // Parse content based on log type
        $parsedData = [];
        $headers = [];
        
        switch ($log_type) {
            case 'browser_history':
                $parsedData = $this->logParser->parseBrowserHistory($content);
                break;
            case 'key_logs':
                $parsedData = $this->logParser->parseKeyLogs($content);
                break;
            case 'usb_logs':
                $parsedData = $this->logParser->parseUsbLogs($content);
                break;
            default:
                $parsedData = $this->logParser->parseGenericLog($content, $log_type);
                break;
        }
        
        $headers = $this->logParser->getTableHeaders($parsedData, $log_type);
        
        $this->json([
            'success' => true,
            'data' => $parsedData,
            'headers' => $headers,
            'log_type' => $log_type,
            'file_path' => $file_path
        ]);
    }
    
    public function api_live_status() {
        // Get monitors from database only (fast)
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Add status based on database data only
        foreach ($monitors as &$monitor) {
            // Use last_monitor_tic for real-time status (updated by client Tic events every 5 minutes)
            $last_tic_value = $monitor['last_monitor_tic'] ?? null;
            $last_tic = ($last_tic_value !== null && $last_tic_value !== '') ? strtotime($last_tic_value) : 0;
            $time_diff = time() - $last_tic;
            
            if ($last_tic === 0) {
                // No activity recorded
                $monitor['live_status'] = 'offline';
                $monitor['status_text'] = 'Never';
                $monitor['latest_activity'] = 0;
            } elseif ($time_diff <= 600) { // 10 minutes - very recent activity (within 2 Tic cycles)
                $monitor['live_status'] = 'online';
                $monitor['status_text'] = 'Online';
                $monitor['latest_activity'] = $last_tic;
            } elseif ($time_diff <= 1800) { // 30 minutes - recent activity (within 6 Tic cycles)
                $monitor['live_status'] = 'online';
                $monitor['status_text'] = 'Online';
                $monitor['latest_activity'] = $last_tic;
            } elseif ($time_diff <= 3600) { // 1 hour - some activity (within 12 Tic cycles)
                $monitor['live_status'] = 'inactive';
                $monitor['status_text'] = 'Inactive';
                $monitor['latest_activity'] = $last_tic;
            } else {
                // More than 1 hour - likely offline
                $monitor['live_status'] = 'offline';
                $monitor['status_text'] = 'Offline';
                $monitor['latest_activity'] = $last_tic;
            }
        }
        
        $this->json($monitors);
    }
    
    public function view_file($file_path) {
        // Security: Only allow viewing files from the data directories
        $file_path = urldecode($file_path);
        
        // Convert relative path to absolute path if needed
        $absolute_path = $file_path;
        if (!file_exists($file_path)) {
            // Try with BASEPATH prefix
            $absolute_path = BASEPATH . $file_path;
            if (file_exists($absolute_path)) {
                $file_path = $absolute_path;
            }
        }
        
        // Normalize paths for comparison
        $normalized_file_path = realpath($absolute_path);
        $normalized_base = realpath(BASEPATH);
        
        // Check if file exists and is within allowed directories
        $allowed_dirs = [SCREENS_DIR, THUMBNAILS_DIR, LOGS_DIR];
        
        $is_allowed = false;
        foreach ($allowed_dirs as $allowed_dir) {
            $allowed_absolute = realpath(BASEPATH . $allowed_dir);
            if ($allowed_absolute && $normalized_file_path && 
                strpos($normalized_file_path, $allowed_absolute) === 0) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed || !file_exists($absolute_path)) {
            http_response_code(404);
            echo "File not found or access denied";
            return;
        }
        
        $extension = pathinfo($absolute_path, PATHINFO_EXTENSION);
        
        // Set appropriate content type
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'txt':
                header('Content-Type: text/plain');
                break;
            default:
                header('Content-Type: application/octet-stream');
        }
        
        // Output file content
        readfile($absolute_path);
    }
    
    private function getThumbnail($ip_address, $screenshot_path) {
        // Fix: Use the actual directory format (dots preserved)
        $ip_identifier = 'ip_' . $ip_address;
        
        // Convert screenshot path to thumbnail path
        $thumbnail_path = str_replace(SCREENS_DIR, THUMBNAILS_DIR, $screenshot_path);
        
        // Check if thumbnail exists, if not return the original screenshot path
        if (file_exists($thumbnail_path)) {
            return $thumbnail_path;
        } else {
            // If thumbnail doesn't exist, return the original screenshot path
            return $screenshot_path;
        }
    }
    
    /**
     * Optimized method to get the latest screenshot for an IP
     * Database-based method for fast screenshot retrieval
     */
    private function getLatestScreenshotOptimized($ip_identifier) {
        $screens_dir = SCREENS_DIR . $ip_identifier;
        
        if (!is_dir($screens_dir)) {
            return null;
        }
        
        $latest_file = null;
        $latest_time = 0;
        
        // Use scandir instead of RecursiveIteratorIterator for better performance
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($screens_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['jpg', 'jpeg', 'png'])) {
                $file_time = $file->getMTime();
                if ($file_time > $latest_time) {
                    $latest_time = $file_time;
                    $latest_file = $file->getPathname();
                }
            }
        }
        
        if (!$latest_file) {
            return null;
        }
        
        // Get thumbnail path
        $thumbnail_path = str_replace(SCREENS_DIR, THUMBNAILS_DIR, $latest_file);
        
        return [
            'full_path' => $latest_file,
            'thumbnail_path' => file_exists($thumbnail_path) ? $thumbnail_path : $latest_file,
            'modified_time' => date('Y-m-d H:i:s', $latest_time)
        ];
    }
    
    /**
     * Convert absolute file path to relative URL path
     */
    private function getRelativePath($path) {
        // Handle null or empty paths
        if (empty($path)) {
            return '';
        }
        
        // Convert backslashes to forward slashes
        $path = str_replace('\\', '/', $path);
        
        // Remove leading './' if present
        $path = ltrim($path, './');
        
        // Remove leading '/' if present
        $path = ltrim($path, '/');
        
        // Remove 'html/data/' from the beginning if present (since BASE_URL already includes 'html/')
        $path = preg_replace('/^html\/data\//', 'data/', $path);
        
        return $path;
    }
    
    public function formatFileSize($bytes) {
        if ($bytes === 0) return "0 Bytes";
        $k = 1024;
        $sizes = ["Bytes", "KB", "MB", "GB"];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
    }
    
    /**
     * Generate unique screenshot ID for navigation
     */
    private function generateScreenshotId($monitor_id, $file_path) {
        return 'screenshot_' . $monitor_id . '_' . md5($file_path);
    }
    
    /**
     * API endpoint to get screenshot by ID for navigation
     */
    public function api_screenshot_by_id($screenshot_id) {
        // Parse screenshot ID to get monitor ID and file hash
        if (!preg_match('/^screenshot_(\d+)_(.+)$/', $screenshot_id, $matches)) {
            $this->json(['error' => 'Invalid screenshot ID']);
            return;
        }
        
        $monitor_id = intval($matches[1]);
        $file_hash = $matches[2];
        
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $monitor_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
            return;
        }
        
        // Find screenshot by file hash in database
        $screenshot = $this->db->fetch(
            "SELECT * FROM screenshot_metadata WHERE monitor_ip_id = :monitor_id AND MD5(file_path) = :file_hash",
            ['monitor_id' => $monitor_id, 'file_hash' => $file_hash]
        );
        
        if (!$screenshot) {
            $this->json(['error' => 'Screenshot not found']);
            return;
        }
        
        $screenshot['monitor'] = $monitor;
        $screenshot['thumbnail'] = $this->getRelativePath($screenshot['thumbnail_path'] ?: $screenshot['file_path']);
        $screenshot['screenshot'] = $this->getRelativePath($screenshot['file_path']);
        $screenshot['global_id'] = $screenshot_id;
        
        $this->json([
            'success' => true,
            'screenshot' => $screenshot
        ]);
    }
    
    /**
     * Optimized API endpoint for screenshot navigation
     */
    public function api_screenshot_navigation() {
        $current_id = isset($_GET['cur_id']) ? $_GET['cur_id'] : null;
        $action = isset($_GET['action']) ? $_GET['action'] : null;
        
        if (!$current_id || !$action) {
            $this->json(['error' => 'Missing cur_id or action parameter']);
            return;
        }
        
        if (!in_array($action, ['next', 'prev'])) {
            $this->json(['error' => 'Invalid action. Use "next" or "prev"']);
            return;
        }
        
        // Parse screenshot ID
        if (!preg_match('/^screenshot_(\d+)_(.+)$/', $current_id, $matches)) {
            $this->json(['error' => 'Invalid screenshot ID']);
            return;
        }
        
        $monitor_id = intval($matches[1]);
        $file_hash = $matches[2];
        
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $monitor_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
            return;
        }
        
        // Use optimized method to get only the needed screenshots
        $result = $this->getScreenshotNavigation($monitor['ip_address'], $file_hash, $action);
        
        if (!$result) {
            $this->json(['error' => 'Screenshot navigation failed']);
            return;
        }
        
        $this->json([
            'success' => true,
            'screenshot' => $result['screenshot'],
            'current_position' => $result['current_position'],
            'total_screenshots' => $result['total_screenshots'],
            'has_next' => $result['has_next'],
            'has_prev' => $result['has_prev']
        ]);
    }
    
    /**
     * Database-based screenshot navigation (much faster than file system scanning)
     */
    private function getScreenshotNavigation($ip_address, $current_file_hash, $action) {
        // Get monitor info
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE ip_address = :ip",
            ['ip' => $ip_address]
        );
        
        if (!$monitor) {
            return false;
        }
        
        // Get total count of screenshots for this monitor
        $total_count = $this->db->fetch(
            "SELECT COUNT(*) as total FROM screenshot_metadata WHERE monitor_ip_id = :monitor_id",
            ['monitor_id' => $monitor['id']]
        )['total'];
        
        if ($total_count == 0) {
            return false;
        }
        
        // Find current screenshot by file hash
        $current_screenshot = $this->db->fetch(
            "SELECT * FROM screenshot_metadata WHERE monitor_ip_id = :monitor_id AND MD5(file_path) = :file_hash",
            ['monitor_id' => $monitor['id'], 'file_hash' => $current_file_hash]
        );
        
        if (!$current_screenshot) {
            return false;
        }
        
        // Get next/previous screenshot based on capture_datetime
        $target_screenshot = null;
        $current_position = 0;
        
        if ($action === 'next') {
            // Get next screenshot (older chronologically, but next in sequence)
            $target_screenshot = $this->db->fetch(
                "SELECT * FROM screenshot_metadata 
                 WHERE monitor_ip_id = :monitor_id 
                 AND capture_datetime < :current_datetime 
                 ORDER BY capture_datetime DESC 
                 LIMIT 1",
                ['monitor_id' => $monitor['id'], 'current_datetime' => $current_screenshot['capture_datetime']]
            );
        } else {
            // Get previous screenshot (newer chronologically, but previous in sequence)
            $target_screenshot = $this->db->fetch(
                "SELECT * FROM screenshot_metadata 
                 WHERE monitor_ip_id = :monitor_id 
                 AND capture_datetime > :current_datetime 
                 ORDER BY capture_datetime ASC 
                 LIMIT 1",
                ['monitor_id' => $monitor['id'], 'current_datetime' => $current_screenshot['capture_datetime']]
            );
        }
        
        if (!$target_screenshot) {
            return false;
        }
        
        // Get position of target screenshot (count of newer screenshots + 1)
        $position_result = $this->db->fetch(
            "SELECT COUNT(*) as position FROM screenshot_metadata 
             WHERE monitor_ip_id = :monitor_id 
             AND capture_datetime >= :target_datetime",
            ['monitor_id' => $monitor['id'], 'target_datetime' => $target_screenshot['capture_datetime']]
        );
        $current_position = $position_result['position'];
        
        // Check if there are more screenshots in each direction
        $has_next = $this->db->fetch(
            "SELECT COUNT(*) as count FROM screenshot_metadata 
             WHERE monitor_ip_id = :monitor_id 
             AND capture_datetime > :target_datetime",
            ['monitor_id' => $monitor['id'], 'target_datetime' => $target_screenshot['capture_datetime']]
        )['count'] > 0;
        
        $has_prev = $this->db->fetch(
            "SELECT COUNT(*) as count FROM screenshot_metadata 
             WHERE monitor_ip_id = :monitor_id 
             AND capture_datetime < :target_datetime",
            ['monitor_id' => $monitor['id'], 'target_datetime' => $target_screenshot['capture_datetime']]
        )['count'] > 0;
        
        // Build screenshot data
        $screenshot = [
            'id' => $target_screenshot['id'],
            'filename' => $target_screenshot['filename'],
            'file_path' => $target_screenshot['file_path'],
            'thumbnail_path' => $target_screenshot['thumbnail_path'] ?: $target_screenshot['file_path'],
            'capture_datetime' => $target_screenshot['capture_datetime'],
            'file_size' => $target_screenshot['file_size'],
            'image_width' => $target_screenshot['image_width'],
            'image_height' => $target_screenshot['image_height'],
            'monitor' => $monitor,
            'thumbnail' => $this->getRelativePath($target_screenshot['thumbnail_path'] ?: $target_screenshot['file_path']),
            'screenshot' => $this->getRelativePath($target_screenshot['file_path']),
            'global_id' => $this->generateScreenshotId($monitor['id'], $target_screenshot['file_path'])
        ];
        
        return [
            'screenshot' => $screenshot,
            'current_position' => $current_position,
            'total_screenshots' => $total_count,
            'has_next' => $has_next,
            'has_prev' => $has_prev
        ];
    }
    
    /**
     * Log Management Page - Similar to real-time monitoring but for logs only
     */
    public function logs() {
        // Get monitors from database
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Add basic status based on database data only
        foreach ($monitors as &$monitor) {
            // Determine status based on last_monitor_tic
            $last_tic_value = $monitor['last_monitor_tic'] ?? null;
            $last_tic = ($last_tic_value !== null && $last_tic_value !== '') ? strtotime($last_tic_value) : 0;
            $time_diff = time() - $last_tic;
            
            if ($time_diff <= 300) { // 5 minutes
                $monitor['live_status'] = 'online';
                $monitor['status_class'] = 'success';
                $monitor['status_text'] = 'Online';
            } elseif ($time_diff <= 3600) { // 1 hour
                $monitor['live_status'] = 'inactive';
                $monitor['status_class'] = 'warning';
                $monitor['status_text'] = 'Inactive';
            } else {
                $monitor['live_status'] = 'offline';
                $monitor['status_class'] = 'danger';
                $monitor['status_text'] = 'Offline';
            }
            
            $monitor['latest_activity'] = $last_tic;
        }
        
        $this->view('view/logs', ['monitors' => $monitors]);
    }
    
    /**
     * API endpoint to get logs for a specific monitor with pagination
     */
    public function api_logs_paginated($monitor_id) {
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $monitor_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
            return;
        }
        
        $ip_identifier = 'ip_' . $monitor['ip_address'];
        $logs = $this->dataSync->getLogs($ip_identifier);
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 20;
        $log_type = isset($_GET['log_type']) ? $_GET['log_type'] : 'browser_history';
        
        // Get date filter parameters
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        $result = [
            'monitor' => $monitor,
            'log_type' => $log_type,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_entries' => 0,
                'total_pages' => 0,
                'has_next' => false,
                'has_prev' => false
            ],
            'logs' => []
        ];
        
        // Process logs based on type
        switch ($log_type) {
            case 'browser_history':
                $result = $this->processBrowserLogs($logs, $result, $start_date, $end_date);
                break;
            case 'key_logs':
                $result = $this->processKeyLogs($logs, $result, $start_date, $end_date);
                break;
            case 'usb_logs':
                $result = $this->processUsbLogs($logs, $result, $start_date, $end_date);
                break;
            default:
                $this->json(['error' => 'Invalid log type']);
                return;
        }
        
        $this->json($result);
    }
    
    /**
     * Process browser logs with pagination and filtering
     */
    private function processBrowserLogs($logs, $result, $start_date, $end_date) {
        if (empty($logs['browser_history'])) {
            return $result;
        }
        
        $all_entries = [];
        
        // Process each browser history file
        foreach ($logs['browser_history'] as $log_file) {
            $file_path = BASEPATH . $log_file['path'];
            if (!file_exists($file_path)) {
                continue;
            }
            
            $content = file_get_contents($file_path);
            if ($content === false) {
                continue;
            }
            
            $parsed_data = $this->logParser->parseBrowserHistory($content);
            
            // Apply date filtering
            foreach ($parsed_data as $entry) {
                if ($this->shouldIncludeEntry($entry, $start_date, $end_date, 'date')) {
                    $entry['file_source'] = basename($log_file['path']);
                    $entry['file_modified'] = $log_file['modified_time'];
                    $all_entries[] = $entry;
                }
            }
        }
        
        // Sort by date (newest first)
        usort($all_entries, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $this->applyPagination($all_entries, $result);
    }
    
    /**
     * Process key logs with pagination and filtering
     */
    private function processKeyLogs($logs, $result, $start_date, $end_date) {
        if (empty($logs['key_logs'])) {
            return $result;
        }
        
        $all_entries = [];
        
        // Process each key log file
        foreach ($logs['key_logs'] as $log_file) {
            $file_path = BASEPATH . $log_file['path'];
            if (!file_exists($file_path)) {
                continue;
            }
            
            $content = file_get_contents($file_path);
            if ($content === false) {
                continue;
            }
            
            $parsed_data = $this->logParser->parseKeyLogs($content);
            
            // Apply date filtering
            foreach ($parsed_data as $entry) {
                if ($this->shouldIncludeEntry($entry, $start_date, $end_date, 'date')) {
                    $entry['file_source'] = basename($log_file['path']);
                    $entry['file_modified'] = $log_file['modified_time'];
                    $all_entries[] = $entry;
                }
            }
        }
        
        // Sort by date (newest first)
        usort($all_entries, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $this->applyPagination($all_entries, $result);
    }
    
    /**
     * Process USB logs with pagination and filtering
     */
    private function processUsbLogs($logs, $result, $start_date, $end_date) {
        if (empty($logs['usb_logs'])) {
            return $result;
        }
        
        $all_entries = [];
        
        // Process each USB log file
        foreach ($logs['usb_logs'] as $log_file) {
            $file_path = BASEPATH . $log_file['path'];
            if (!file_exists($file_path)) {
                continue;
            }
            
            $content = file_get_contents($file_path);
            if ($content === false) {
                continue;
            }
            
            $parsed_data = $this->logParser->parseUsbLogs($content);
            
            // Apply date filtering
            foreach ($parsed_data as $entry) {
                if ($this->shouldIncludeEntry($entry, $start_date, $end_date, 'date')) {
                    $entry['file_source'] = basename($log_file['path']);
                    $entry['file_modified'] = $log_file['modified_time'];
                    $all_entries[] = $entry;
                }
            }
        }
        
        // Sort by date (newest first)
        usort($all_entries, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $this->applyPagination($all_entries, $result);
    }
    
    /**
     * Check if entry should be included based on date filters
     */
    private function shouldIncludeEntry($entry, $start_date, $end_date, $date_field) {
        if (!$start_date && !$end_date) {
            return true;
        }
        
        $entry_date = strtotime($entry[$date_field]);
        if ($entry_date === false) {
            return true; // Include if date parsing fails
        }
        
        if ($start_date && $entry_date < strtotime($start_date)) {
            return false;
        }
        
        if ($end_date && $entry_date > strtotime($end_date . ' 23:59:59')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Apply pagination to entries
     */
    private function applyPagination($all_entries, $result) {
        $page = $result['pagination']['current_page'];
        $per_page = $result['pagination']['per_page'];
        
        $total_entries = count($all_entries);
        $total_pages = ceil($total_entries / $per_page);
        $offset = ($page - 1) * $per_page;
        
        $paginated_entries = array_slice($all_entries, $offset, $per_page);
        
        $result['logs'] = $paginated_entries;
        $result['pagination']['total_entries'] = $total_entries;
        $result['pagination']['total_pages'] = $total_pages;
        $result['pagination']['has_next'] = $page < $total_pages;
        $result['pagination']['has_prev'] = $page > 1;
        
        return $result;
    }
    
    /**
     * API endpoint to get log statistics for a monitor
     */
    public function api_log_stats($monitor_id) {
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $monitor_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
            return;
        }
        
        $ip_identifier = 'ip_' . $monitor['ip_address'];
        $logs = $this->dataSync->getLogs($ip_identifier);
        
        $stats = [
            'browser_history' => [
                'file_count' => count($logs['browser_history'] ?? []),
                'total_entries' => 0,
                'latest_entry' => null
            ],
            'key_logs' => [
                'file_count' => count($logs['key_logs'] ?? []),
                'total_entries' => 0,
                'latest_entry' => null
            ],
            'usb_logs' => [
                'file_count' => count($logs['usb_logs'] ?? []),
                'total_entries' => 0,
                'latest_entry' => null
            ]
        ];
        
        // Calculate stats for each log type
        foreach (['browser_history', 'key_logs', 'usb_logs'] as $log_type) {
            if (!empty($logs[$log_type])) {
                $total_entries = 0;
                $latest_time = 0;
                $latest_entry = null;
                
                foreach ($logs[$log_type] as $log_file) {
                    $file_path = BASEPATH . $log_file['path'];
                    if (!file_exists($file_path)) {
                        continue;
                    }
                    
                    $content = file_get_contents($file_path);
                    if ($content === false) {
                        continue;
                    }
                    
                    $parsed_data = $this->logParser->parseLogData($content, $log_type);
                    $total_entries += count($parsed_data['data']);
                    
                    // Find latest entry
                    foreach ($parsed_data['data'] as $entry) {
                        $entry_time = strtotime($entry['date']);
                        if ($entry_time > $latest_time) {
                            $latest_time = $entry_time;
                            $latest_entry = $entry;
                        }
                    }
                }
                
                $stats[$log_type]['total_entries'] = $total_entries;
                $stats[$log_type]['latest_entry'] = $latest_entry;
            }
        }
        
        $this->json([
            'monitor' => $monitor,
            'stats' => $stats
        ]);
    }
    

    public function test() {
        echo "View controller test method<br>";
        
        // Test path conversion
        $test_paths = [
            '/Volumes/Workspace/Projects/monitoring/Backend/html/data/thumbnails/ip_192.168.1.40/2023-08-27/192_168_1_40_2023-08-27_10.30.00.jpeg',
            'html/data/thumbnails/ip_192.168.1.40/2023-08-27/192_168_1_40_2023-08-27_10.30.00.jpeg',
            'data/thumbnails/ip_192.168.1.40/2023-08-27/192_168_1_40_2023-08-27_10.30.00.jpeg',
            'html/data/html/data/thumbnails/ip_192.168.1.233/2025-09-08/192_168_1_233_2025-09-08_05.44.04.jpg'
        ];
        
        echo "<h3>Path Conversion Test:</h3>";
        echo "<p><strong>BASEPATH:</strong> " . BASEPATH . "</p>";
        echo "<p><strong>BASE_URL:</strong> " . BASE_URL . "</p>";
        
        foreach ($test_paths as $path) {
            $converted = $this->getRelativePath($path);
            echo "<p><strong>Original:</strong> $path</p>";
            echo "<p><strong>After BASEPATH removal:</strong> " . str_replace(BASEPATH, '', str_replace('\\', '/', $path)) . "</p>";
            echo "<p><strong>Converted:</strong> $converted</p>";
            echo "<p><strong>Full URL:</strong> " . BASE_URL . $converted . "</p>";
            echo "<hr>";
        }
        
        // Test database paths
        echo "<h3>Database Paths Sample:</h3>";
        $screenshots = $this->db->fetchAll("SELECT file_path, thumbnail_path FROM screenshot_metadata LIMIT 3");
        foreach ($screenshots as $screenshot) {
            echo "<p><strong>File Path:</strong> " . $screenshot['file_path'] . "</p>";
            echo "<p><strong>Thumbnail Path:</strong> " . $screenshot['thumbnail_path'] . "</p>";
            echo "<p><strong>Converted File:</strong> " . $this->getRelativePath($screenshot['file_path']) . "</p>";
            echo "<p><strong>Converted Thumbnail:</strong> " . $this->getRelativePath($screenshot['thumbnail_path']) . "</p>";
            echo "<hr>";
        }
    }
}
?> 