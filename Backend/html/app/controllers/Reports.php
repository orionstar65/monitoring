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
            "SELECT m.*,owner.name AS username FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id ORDER BY (owner.id IS NULL), CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type,''), COALESCE(m.platform,''), INET_ATON(COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address)), COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address), m.id"
        );
        
        $this->view('view/reports', ['monitors' => $monitors]);
    }
    
    /**
     * API endpoint to get all devices for filtering
     */
    public function api_devices() {
        // OPTIMIZED: Remove SQL CASE statement - status calculation done in PHP for better performance
        $devices = $this->db->fetchAll(
            "SELECT m.id,m.ip_address,m.mac_address,owner.name AS username,owner.user_no AS user_no,CONCAT('Device #',m.id) AS name,m.type,m.platform,m.last_seen_ip,m.last_monitor_tic,m.updated_at
             FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id
             ORDER BY (owner.id IS NULL), CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type,''), COALESCE(m.platform,''), INET_ATON(COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address)), COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address), m.id"
        );
        
        // Calculate status in PHP using StatusService for consistency
        foreach ($devices as &$device) {
            $statusInfo = $this->statusService->calculateClientStatus($device['last_monitor_tic'] ?? null);
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
        // Check if platform column exists, otherwise use type as OS
        $columns = $this->db->query("SHOW COLUMNS FROM tbl_monitor_ips LIKE 'platform'")->fetchAll();
        $has_platform = !empty($columns);
        
        if ($has_platform) {
            $devices_sql = "SELECT m.id, m.ip_address, m.last_seen_ip, owner.name AS username, CONCAT('Device #',m.id) AS name, m.type, 
                                   COALESCE(NULLIF(m.platform, ''), m.type, 'Unknown') as os
                           FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id
                           WHERE 1=1 {$device_filter}
                           ORDER BY (owner.id IS NULL), CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type,''), COALESCE(m.platform,''), INET_ATON(COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address)), COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address), m.id";
        } else {
            // If platform column doesn't exist, use type as OS
            $devices_sql = "SELECT m.id, m.ip_address, m.last_seen_ip, owner.name AS username, CONCAT('Device #',m.id) AS name, m.type, 
                                   COALESCE(m.type, 'Unknown') as os
                       FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id
                       WHERE 1=1 {$device_filter}
                       ORDER BY (owner.id IS NULL), CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type,''), COALESCE(m.platform,''), INET_ATON(COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address)), COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address), m.id";
        }
        
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
                'ip_address' => $device['last_seen_ip'] ?: $device['ip_address'],
                'username' => $device['username'],
                'name' => $device['name'],
                'type' => $device['type'],
                'machine' => ucfirst(strtolower((string)($device['type'] ?: 'Unknown'))),
                'os' => $device['os'] ?? ($device['type'] ?? 'Unknown'),
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
        // Observed coverage only: never invent an hour from a single screenshot.
        // Sum adjacent capture intervals only when the gap is <= five minutes.
        $days=[];
        foreach($screenshot_times as $ts) {
            $epoch=strtotime($ts);
            if($epoch===false)continue;
            $days[date('Y-m-d',$epoch)][]=$epoch;
        }
        $seconds=0;
        foreach($days as $times){
            sort($times,SORT_NUMERIC);
            for($i=1;$i<count($times);$i++){
                $delta=$times[$i]-$times[$i-1];
                if($delta>0 && $delta<=300)$seconds+=$delta;
            }
        }
        return round($seconds/3600,2);
    }

    /**
     * Calculate activity gaps (online but not monitored)
     * Proper logic: Calculate time when device was online (has activity) but not monitored (no screenshots)
     * 
     * Approach:
     * 1. Identify online periods (continuous activity spans)
     * 2. For each online period, check screenshot coverage
     * 3. Calculate gaps where activity existed but screenshots were missing
     */
    private function calculateActivityGaps($screenshot_times, $key_times, $browser_times, $report_type) {
        // Convert all times to timestamps
        $screenshot_timestamps = array_map(function($t) { return strtotime($t); }, $screenshot_times);
        sort($screenshot_timestamps);
        
        // Combine all activity timestamps (key logs and browser activity indicate device was online)
        $activity_timestamps = array_merge(
            array_map(function($t) { return strtotime($t); }, $key_times),
            array_map(function($t) { return strtotime($t); }, $browser_times)
        );
        
        // If no activity, device was offline - not "not monitored"
        if (empty($activity_timestamps)) {
            return 0;
        }
        
        // Remove duplicates and sort
        $activity_timestamps = array_unique($activity_timestamps);
        sort($activity_timestamps);
        
        // If no screenshots but there's activity, estimate not monitored time
        if (empty($screenshot_timestamps)) {
            // Group activities into sessions (activities within 30 minutes are considered same session)
            $sessions = [];
            $current_session = [];
            $session_gap_minutes = 30;
            
            foreach ($activity_timestamps as $activity_time) {
                if (empty($current_session)) {
                    $current_session[] = $activity_time;
                } else {
                    $last_activity = end($current_session);
                    $gap_minutes = ($activity_time - $last_activity) / 60;
                    
                    if ($gap_minutes <= $session_gap_minutes) {
                        $current_session[] = $activity_time;
                    } else {
                        $sessions[] = $current_session;
                        $current_session = [$activity_time];
                    }
                }
            }
            if (!empty($current_session)) {
                $sessions[] = $current_session;
            }
            
            // Estimate not monitored time based on session duration
            $total_not_monitored = 0;
            foreach ($sessions as $session) {
                if (count($session) > 1) {
                    $session_duration = (end($session) - reset($session)) / 3600; // hours
                    $total_not_monitored += min($session_duration, 0.5); // Cap at 30 min per session
                } else {
                    $total_not_monitored += 0.05; // 3 minutes for single activity
                }
            }
            
            return round(min($total_not_monitored, 24), 2);
        }
        
        // Calculate not monitored time using time-based approach
        $monitoring_interval_seconds = 5 * 60; // 5 minutes in seconds
        $not_monitored_seconds = 0;
        
        // Group activities into continuous online periods
        $online_periods = [];
        $current_period_start = null;
        $current_period_end = null;
        $activity_gap_threshold = 30 * 60; // 30 minutes - gap larger than this means new period
        
        foreach ($activity_timestamps as $activity_time) {
            if ($current_period_start === null) {
                $current_period_start = $activity_time;
                $current_period_end = $activity_time;
            } else {
                $gap = $activity_time - $current_period_end;
                if ($gap <= $activity_gap_threshold) {
                    // Continue current period
                    $current_period_end = $activity_time;
                } else {
                    // Start new period
                    $online_periods[] = ['start' => $current_period_start, 'end' => $current_period_end];
                    $current_period_start = $activity_time;
                    $current_period_end = $activity_time;
                }
            }
        }
        // Add last period
        if ($current_period_start !== null) {
            $online_periods[] = ['start' => $current_period_start, 'end' => $current_period_end];
        }
        
        // For each online period, check screenshot coverage
        foreach ($online_periods as $period) {
            $period_start = $period['start'];
            $period_end = $period['end'];
            $period_duration = $period_end - $period_start;
            
            // Find screenshots within this period (with buffer)
            $period_screenshots = array_filter($screenshot_timestamps, function($screenshot_time) use ($period_start, $period_end, $monitoring_interval_seconds) {
                return $screenshot_time >= ($period_start - $monitoring_interval_seconds) && 
                       $screenshot_time <= ($period_end + $monitoring_interval_seconds);
            });
            
            if (empty($period_screenshots)) {
                // No screenshots for this entire online period - count as not monitored
                $not_monitored_seconds += min($period_duration, 30 * 60); // Cap at 30 minutes per period
            } else {
                // Check gaps within the period
                sort($period_screenshots);
                
                // Check gap before first screenshot
                $first_screenshot = min($period_screenshots);
                if ($first_screenshot > $period_start + $monitoring_interval_seconds) {
                    $gap = $first_screenshot - $period_start - $monitoring_interval_seconds;
                    $not_monitored_seconds += min($gap, 30 * 60);
                }
                
                // Check gaps between screenshots
                for ($i = 0; $i < count($period_screenshots) - 1; $i++) {
                    $gap = $period_screenshots[$i + 1] - $period_screenshots[$i];
                    if ($gap > $monitoring_interval_seconds) {
                        // Check if there was activity in this gap
                        $gap_start = $period_screenshots[$i];
                        $gap_end = $period_screenshots[$i + 1];
                        $activity_in_gap = array_filter($activity_timestamps, function($t) use ($gap_start, $gap_end) {
                            return $t > $gap_start && $t < $gap_end;
                        });
                        
                        if (count($activity_in_gap) > 0) {
                            $excess_gap = $gap - $monitoring_interval_seconds;
                            $not_monitored_seconds += min($excess_gap, 30 * 60); // Cap at 30 minutes per gap
                        }
                    }
                }
                
                // Check gap after last screenshot
                $last_screenshot = max($period_screenshots);
                if ($last_screenshot < $period_end - $monitoring_interval_seconds) {
                    $gap = $period_end - $last_screenshot - $monitoring_interval_seconds;
                    $not_monitored_seconds += min($gap, 30 * 60);
                }
            }
        }
        
        // Convert to hours
        $not_monitored_hours = $not_monitored_seconds / 3600;
        
        // Ensure reasonable value (not more than total period)
        return round(min($not_monitored_hours, 24), 2);
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
