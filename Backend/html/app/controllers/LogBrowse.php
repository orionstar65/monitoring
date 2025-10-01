<?php
require_once BASEPATH . 'app/controllers/Base.php';

class LogBrowse extends Base {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Main log browsing page
     */
    public function index() {
        // Get all monitors for device filtering
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY username ASC, ip_address ASC"
        );
        
        $this->view('view/logbrowse', ['monitors' => $monitors]);
    }
    
    /**
     * API endpoint to get all devices for filtering
     */
    public function api_devices() {
        $devices = $this->db->fetchAll(
            "SELECT id, ip_address, username, name, type, last_monitor_tic, 
                    CASE 
                        WHEN last_monitor_tic IS NULL THEN 'offline'
                        WHEN TIMESTAMPDIFF(MINUTE, last_monitor_tic, NOW()) <= 5 THEN 'online'
                        WHEN TIMESTAMPDIFF(MINUTE, last_monitor_tic, NOW()) <= 60 THEN 'inactive'
                        ELSE 'offline'
                    END as status
             FROM tbl_monitor_ips 
             ORDER BY username ASC, ip_address ASC"
        );
        
        $this->json($devices);
    }
    
    /**
     * API endpoint to get browser history logs with pagination and filtering
     */
    public function api_browser_logs() {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 20;
        $device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : null;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE conditions
        $where_conditions = [];
        $params = [];
        
        if ($device_id) {
            $where_conditions[] = "b.monitor_ip_id = :device_id";
            $params['device_id'] = $device_id;
        }
        
        if ($start_date) {
            $where_conditions[] = "b.visit_date >= :start_date";
            $params['start_date'] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where_conditions[] = "b.visit_date <= :end_date";
            $params['end_date'] = $end_date . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total 
                      FROM browser_history_logs b 
                      JOIN tbl_monitor_ips m ON b.monitor_ip_id = m.id 
                      {$where_clause}";
        
        $total_result = $this->db->query($count_sql, $params)->fetch();
        $total_entries = $total_result['total'];
        $total_pages = ceil($total_entries / $per_page);
        
        // Get paginated data
        $data_sql = "SELECT b.*, m.ip_address, m.username, m.name, m.type,
                            b.visit_date as date,
                            b.browser,
                            b.url,
                            b.title
                     FROM browser_history_logs b 
                     JOIN tbl_monitor_ips m ON b.monitor_ip_id = m.id 
                     {$where_clause}
                     ORDER BY b.visit_date DESC 
                     LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $per_page;
        $params['offset'] = $offset;
        
        $logs = $this->db->query($data_sql, $params)->fetchAll();
        
        $this->json([
            'success' => true,
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_entries' => $total_entries,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ]);
    }
    
    /**
     * API endpoint to get key logs with pagination and filtering
     */
    public function api_key_logs() {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 20;
        $device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : null;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE conditions
        $where_conditions = [];
        $params = [];
        
        if ($device_id) {
            $where_conditions[] = "k.monitor_ip_id = :device_id";
            $params['device_id'] = $device_id;
        }
        
        if ($start_date) {
            $where_conditions[] = "k.key_date >= :start_date";
            $params['start_date'] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where_conditions[] = "k.key_date <= :end_date";
            $params['end_date'] = $end_date . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total 
                      FROM key_logs k 
                      JOIN tbl_monitor_ips m ON k.monitor_ip_id = m.id 
                      {$where_clause}";
        
        $total_result = $this->db->query($count_sql, $params)->fetch();
        $total_entries = $total_result['total'];
        $total_pages = ceil($total_entries / $per_page);
        
        // Get paginated data
        $data_sql = "SELECT k.*, m.ip_address, m.username, m.name, m.type,
                            k.key_date as date,
                            k.application,
                            k.key_pressed as `key`,
                            'keydown' as action
                     FROM key_logs k 
                     JOIN tbl_monitor_ips m ON k.monitor_ip_id = m.id 
                     {$where_clause}
                     ORDER BY k.key_date DESC 
                     LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $per_page;
        $params['offset'] = $offset;
        
        $logs = $this->db->query($data_sql, $params)->fetchAll();
        
        $this->json([
            'success' => true,
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_entries' => $total_entries,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ]);
    }
    
    /**
     * API endpoint to get USB logs with pagination and filtering
     */
    public function api_usb_logs() {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 20;
        $device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : null;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE conditions
        $where_conditions = [];
        $params = [];
        
        if ($device_id) {
            $where_conditions[] = "u.monitor_ip_id = :device_id";
            $params['device_id'] = $device_id;
        }
        
        if ($start_date) {
            $where_conditions[] = "u.device_date >= :start_date";
            $params['start_date'] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where_conditions[] = "u.device_date <= :end_date";
            $params['end_date'] = $end_date . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) as total 
                      FROM usb_device_logs u 
                      JOIN tbl_monitor_ips m ON u.monitor_ip_id = m.id 
                      {$where_clause}";
        
        $total_result = $this->db->query($count_sql, $params)->fetch();
        $total_entries = $total_result['total'];
        $total_pages = ceil($total_entries / $per_page);
        
        // Get paginated data
        $data_sql = "SELECT u.*, m.ip_address, m.username, m.name, m.type,
                            u.device_date as date,
                            u.device_name as device,
                            u.action,
                            CONCAT(COALESCE(u.device_type, 'Unknown Type'), ' - ', COALESCE(u.device_path, 'No Path')) as details
                     FROM usb_device_logs u 
                     JOIN tbl_monitor_ips m ON u.monitor_ip_id = m.id 
                     {$where_clause}
                     ORDER BY u.device_date DESC 
                     LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $per_page;
        $params['offset'] = $offset;
        
        $logs = $this->db->query($data_sql, $params)->fetchAll();
        
        $this->json([
            'success' => true,
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_entries' => $total_entries,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ]);
    }
    
    /**
     * API endpoint to get log statistics for dashboard
     */
    public function api_stats() {
        $device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : null;
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        
        $where_conditions = [];
        $params = [];
        
        if ($device_id) {
            $where_conditions[] = "monitor_ip_id = :device_id";
            $params['device_id'] = $device_id;
        }
        
        if ($start_date) {
            $where_conditions[] = "visit_date >= :start_date";
            $params['start_date'] = $start_date . ' 00:00:00';
        }
        
        if ($end_date) {
            $where_conditions[] = "visit_date <= :end_date";
            $params['end_date'] = $end_date . ' 23:59:59';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get browser history count
        $browser_count = $this->db->query("SELECT COUNT(*) as total FROM browser_history_logs {$where_clause}", $params)->fetch()['total'];
        
        // Get key logs count (adjust date field)
        $key_where_conditions = [];
        $key_params = [];
        if ($device_id) {
            $key_where_conditions[] = "monitor_ip_id = :device_id";
            $key_params['device_id'] = $device_id;
        }
        if ($start_date) {
            $key_where_conditions[] = "key_date >= :start_date";
            $key_params['start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $key_where_conditions[] = "key_date <= :end_date";
            $key_params['end_date'] = $end_date . ' 23:59:59';
        }
        $key_where_clause = !empty($key_where_conditions) ? 'WHERE ' . implode(' AND ', $key_where_conditions) : '';
        $key_count = $this->db->query("SELECT COUNT(*) as total FROM key_logs {$key_where_clause}", $key_params)->fetch()['total'];
        
        // Get USB logs count (adjust date field)
        $usb_where_conditions = [];
        $usb_params = [];
        if ($device_id) {
            $usb_where_conditions[] = "monitor_ip_id = :device_id";
            $usb_params['device_id'] = $device_id;
        }
        if ($start_date) {
            $usb_where_conditions[] = "device_date >= :start_date";
            $usb_params['start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $usb_where_conditions[] = "device_date <= :end_date";
            $usb_params['end_date'] = $end_date . ' 23:59:59';
        }
        $usb_where_clause = !empty($usb_where_conditions) ? 'WHERE ' . implode(' AND ', $usb_where_conditions) : '';
        $usb_count = $this->db->query("SELECT COUNT(*) as total FROM usb_device_logs {$usb_where_clause}", $usb_params)->fetch()['total'];
        
        $this->json([
            'success' => true,
            'stats' => [
                'browser_history' => $browser_count,
                'key_logs' => $key_count,
                'usb_logs' => $usb_count,
                'total' => $browser_count + $key_count + $usb_count
            ]
        ]);
    }
}
?>
