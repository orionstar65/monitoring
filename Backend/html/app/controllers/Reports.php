<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/StatusService.php';

class Reports extends Base {
    private $statusService;
    
    public function __construct() {
        parent::__construct();
        $this->statusService = StatusService::getInstance();
    }
    
    /**
     * Main reports page
     */
    public function index() {
        // Get all monitors for device filtering
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY username ASC, ip_address ASC"
        );
        
        $this->view('view/reports', ['monitors' => $monitors]);
    }
    
    /**
     * API endpoint to get all devices for filtering
     */
    public function api_devices() {
        $devices = $this->db->fetchAll(
            "SELECT id, ip_address, username, name, type, updated_at
             FROM tbl_monitor_ips 
             ORDER BY username ASC, ip_address ASC"
        );
        
        // Calculate status in PHP using StatusService for consistency
        foreach ($devices as &$device) {
            $statusInfo = $this->statusService->calculateClientStatus($device['updated_at'] ?? null);
            $device['status'] = $statusInfo['status'];
        }
        
        $this->json($devices);
    }
    
    /**
     * API endpoint to get monitoring usage reports
     */
    public function api_usage_report() {
        $device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : null;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily'; // daily, weekly, monthly
        
        // Validate date range
        $start_dt = new DateTime($start_date);
        $end_dt = new DateTime($end_date);
        
        // Validate custom date range
        if ($report_type === 'custom') {
            if ($start_dt > $end_dt) {
                $this->json([
                    'success' => false,
                    'message' => 'Start date must be before or equal to end date.'
                ]);
                return;
            }
            
            // Optional: Validate maximum range (e.g., 365 days)
            $diff = $start_dt->diff($end_dt);
            if ($diff->days > 365) {
                $this->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 365 days.'
                ]);
                return;
            }
        }
        
        if ($report_type === 'weekly') {
            // For weekly reports, extend end date to end of week
            $end_dt->modify('+6 days');
        } elseif ($report_type === 'monthly') {
            // For monthly reports, extend to end of month
            $end_dt->modify('last day of this month');
        }
        
        $end_date = $end_dt->format('Y-m-d');
        
        // Build device filter
        $device_filter = $device_id ? "AND m.id = :device_id" : "";
        $params = ['start_date' => $start_date . ' 00:00:00', 'end_date' => $end_date . ' 23:59:59'];
        if ($device_id) {
            $params['device_id'] = $device_id;
        }
        
        // Get monitoring data for the period
        $monitoring_data = $this->getMonitoringData($start_date, $end_date, $device_filter, $params);
        
        // Calculate usage statistics
        $usage_stats = $this->calculateUsageStats($monitoring_data, $report_type, $start_date, $end_date);
        
        $this->json([
            'success' => true,
            'report_type' => $report_type,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'data' => $usage_stats
        ]);
    }
    
    /**
     * Get monitoring data for analysis
     */
    private function getMonitoringData($start_date, $end_date, $device_filter, $params) {
        // Get device information
        $devices_sql = "SELECT m.id, m.ip_address, m.username, m.name, m.type
                       FROM tbl_monitor_ips m 
                       WHERE 1=1 {$device_filter}
                       ORDER BY m.username ASC, m.ip_address ASC";
        
        $devices = $this->db->query($devices_sql, $device_filter ? $params : [])->fetchAll();
        
        $monitoring_data = [];
        
        foreach ($devices as $device) {
            $device_id = $device['id'];
            
            // Get screenshot activity (primary monitoring indicator)
            $screenshots_sql = "SELECT capture_datetime 
                               FROM screenshot_metadata 
                               WHERE monitor_ip_id = :device_id 
                               AND capture_datetime >= :start_date 
                               AND capture_datetime <= :end_date 
                               ORDER BY capture_datetime ASC";
            
            $screenshot_times = $this->db->query($screenshots_sql, [
                'device_id' => $device_id,
                'start_date' => $start_date . ' 00:00:00',
                'end_date' => $end_date . ' 23:59:59'
            ])->fetchAll(PDO::FETCH_COLUMN);
            
            // Get other activity indicators
            $key_activity_sql = "SELECT key_date 
                                FROM key_logs 
                                WHERE monitor_ip_id = :device_id 
                                AND key_date >= :start_date 
                                AND key_date <= :end_date 
                                ORDER BY key_date ASC";
            
            $key_times = $this->db->query($key_activity_sql, [
                'device_id' => $device_id,
                'start_date' => $start_date . ' 00:00:00',
                'end_date' => $end_date . ' 23:59:59'
            ])->fetchAll(PDO::FETCH_COLUMN);
            
            $browser_activity_sql = "SELECT visit_date 
                                    FROM browser_history_logs 
                                    WHERE monitor_ip_id = :device_id 
                                    AND visit_date >= :start_date 
                                    AND visit_date <= :end_date 
                                    ORDER BY visit_date ASC";
            
            $browser_times = $this->db->query($browser_activity_sql, [
                'device_id' => $device_id,
                'start_date' => $start_date . ' 00:00:00',
                'end_date' => $end_date . ' 23:59:59'
            ])->fetchAll(PDO::FETCH_COLUMN);
            
            $monitoring_data[$device_id] = [
                'device' => $device,
                'screenshot_times' => $screenshot_times,
                'key_times' => $key_times,
                'browser_times' => $browser_times
            ];
        }
        
        return $monitoring_data;
    }
    
    /**
     * Calculate usage statistics for monitoring data
     */
    private function calculateUsageStats($monitoring_data, $report_type, $start_date = null, $end_date = null) {
        $stats = [];
        
        foreach ($monitoring_data as $device_id => $data) {
            $device = $data['device'];
            $screenshot_times = $data['screenshot_times'];
            $key_times = $data['key_times'];
            $browser_times = $data['browser_times'];
            
            // Calculate period duration in hours
            $period_hours = $this->getPeriodHours($report_type, $start_date, $end_date);
            
            // Calculate captured hours (time when screenshots were taken)
            $captured_hours = $this->calculateCapturedHours($screenshot_times);
            
            // Calculate activity gaps (online but not monitored)
            $not_captured_while_online = $this->calculateActivityGaps($screenshot_times, $key_times, $browser_times, $report_type);
            
            // Calculate offline hours
            $offline_hours = $this->calculateOfflineHours($device, $report_type, $period_hours, $captured_hours, $not_captured_while_online);
            
            $stats[] = [
                'device_id' => $device_id,
                'ip_address' => $device['ip_address'],
                'username' => $device['username'],
                'name' => $device['name'],
                'type' => $device['type'],
                'period_hours' => $period_hours,
                'captured_hours' => round($captured_hours, 2),
                'not_captured_while_online' => round($not_captured_while_online, 2),
                'offline_hours' => round($offline_hours, 2),
                'screenshot_count' => count($screenshot_times),
                'key_activity_count' => count($key_times),
                'browser_activity_count' => count($browser_times)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get period duration in hours based on report type
     */
    private function getPeriodHours($report_type, $start_date = null, $end_date = null) {
        switch ($report_type) {
            case 'daily':
                return 24;
            case 'weekly':
                return 168; // 7 * 24
            case 'monthly':
                return 730; // ~30 * 24
            case 'custom':
                // Calculate actual hours between start and end dates
                if ($start_date && $end_date) {
                    $start_dt = new DateTime($start_date);
                    $end_dt = new DateTime($end_date);
                    // Add 1 day to include both start and end dates
                    $end_dt->modify('+1 day');
                    $diff = $start_dt->diff($end_dt);
                    // Calculate total hours: days * 24 + hours
                    return ($diff->days * 24) + $diff->h;
                }
                return 24; // Default fallback
            default:
                return 24;
        }
    }
    
    /**
     * Calculate captured hours based on screenshot activity
     */
    private function calculateCapturedHours($screenshot_times) {
        if (empty($screenshot_times)) {
            return 0;
        }
        
        // Group screenshots by day and estimate monitoring hours
        $daily_activity = [];
        foreach ($screenshot_times as $time) {
            $day = date('Y-m-d', strtotime($time));
            if (!isset($daily_activity[$day])) {
                $daily_activity[$day] = [];
            }
            $daily_activity[$day][] = $time;
        }
        
        $total_captured_hours = 0;
        
        foreach ($daily_activity as $day => $times) {
            if (empty($times)) continue;
            
            // Sort times for the day
            sort($times);
            
            // Calculate monitoring duration for this day
            $first_capture = strtotime($times[0]);
            $last_capture = strtotime($times[count($times) - 1]);
            
            // Estimate monitoring hours based on screenshot frequency
            // If screenshots are frequent (every few minutes), assume continuous monitoring
            // If sparse, estimate based on gaps
            $screenshot_count = count($times);
            $time_span_hours = ($last_capture - $first_capture) / 3600;
            
            if ($screenshot_count > 1) {
                // Estimate based on screenshot frequency
                $avg_interval_minutes = $time_span_hours * 60 / ($screenshot_count - 1);
                
                if ($avg_interval_minutes <= 5) {
                    // Very frequent screenshots - likely continuous monitoring
                    $captured_hours = min($time_span_hours + 1, 24); // Add buffer for first/last screenshots
                } elseif ($avg_interval_minutes <= 30) {
                    // Regular monitoring with some gaps
                    $captured_hours = $time_span_hours * 0.8; // 80% of time span
                } else {
                    // Sparse monitoring
                    $captured_hours = $time_span_hours * 0.5; // 50% of time span
                }
            } else {
                // Single screenshot - estimate 1 hour of monitoring
                $captured_hours = 1;
            }
            
            $total_captured_hours += min($captured_hours, 24); // Cap at 24 hours per day
        }
        
        return $total_captured_hours;
    }
    
    /**
     * Calculate activity gaps (online but not monitored)
     */
    private function calculateActivityGaps($screenshot_times, $key_times, $browser_times, $report_type) {
        // Combine all activity timestamps
        $all_activity = array_merge(
            array_map(function($t) { return strtotime($t); }, $screenshot_times),
            array_map(function($t) { return strtotime($t); }, $key_times),
            array_map(function($t) { return strtotime($t); }, $browser_times)
        );
        
        if (empty($all_activity)) {
            return 0;
        }
        
        // Remove duplicates and sort
        $all_activity = array_unique($all_activity);
        sort($all_activity);
        
        // Calculate gaps between activities
        $total_gap_minutes = 0;
        
        for ($i = 0; $i < count($all_activity) - 1; $i++) {
            $gap_minutes = ($all_activity[$i + 1] - $all_activity[$i]) / 60;
            
            // Consider gaps > 10 minutes as "not captured while online"
            if ($gap_minutes > 10) {
                $total_gap_minutes += min($gap_minutes - 10, 60); // Cap gap at 1 hour max
            }
        }
        
        return $total_gap_minutes / 60; // Convert to hours
    }
    
    /**
     * Calculate offline hours
     */
    private function calculateOfflineHours($device, $report_type, $period_hours, $captured_hours, $not_captured_while_online) {
        // Basic calculation: Total hours - captured hours - not captured while online
        $calculated_offline = $period_hours - $captured_hours - $not_captured_while_online;
        
        // Ensure non-negative
        return max(0, $calculated_offline);
    }
}
?>
