<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/StatusService.php';

class Logbrowse extends Base {
    private $statusService;
    
    public function __construct() {
        parent::__construct();
        $this->statusService = StatusService::getInstance();
    }
    
    /**
     * Main log browsing page
     */
    public function index() {
        if(($this->user['role']??'')==='viewer'){
            $this->view('view/aggregate');return;
        }
        // Get all monitors for device filtering
        $monitors = $this->db->fetchAll(
            "SELECT m.*,owner.name AS username,owner.user_no AS user_no FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id ORDER BY owner.name ASC,m.ip_address ASC"
        );
        
        $this->view('view/logbrowse', ['monitors' => $monitors]);
    }
    
    /**
     * API endpoint to get all devices for filtering
     */
    public function api_devices() {
        // OPTIMIZED: Remove SQL CASE statement - status calculation done in PHP for better performance
        $devices = $this->db->fetchAll(
            "SELECT m.id,m.ip_address,m.mac_address,owner.name AS username,owner.user_no AS user_no,CONCAT('Device #',m.id) AS name,m.type,m.platform,m.last_seen_ip,m.last_monitor_tic,m.updated_at
             FROM tbl_monitor_ips m LEFT JOIN users owner ON owner.id=m.owner_user_id
             ORDER BY owner.name ASC,m.ip_address ASC"
        );
        
        // Calculate status in PHP using StatusService for consistency
        foreach ($devices as &$device) {
            $statusInfo = $this->statusService->calculateClientStatus($device['last_monitor_tic'] ?? null);
            $device['status'] = $statusInfo['status'];
        }
        
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
                      JOIN tbl_monitor_ips m ON b.monitor_ip_id = m.id LEFT JOIN users owner ON owner.id=m.owner_user_id 
                      {$where_clause}";
        
        $total_result = $this->db->query($count_sql, $params)->fetch();
        $total_entries = $total_result['total'];
        $total_pages = ceil($total_entries / $per_page);
        
        // Get paginated data
        $data_sql = "SELECT b.*, m.ip_address, owner.name AS username,owner.user_no AS user_no, CONCAT('Device #',m.id) AS name, m.type,m.platform,m.last_monitor_tic, TIMESTAMPDIFF(SECOND,m.last_monitor_tic,NOW()) AS heartbeat_age_seconds,
                            b.visit_date as date,
                            b.browser,
                            b.url,
                            b.title
                     FROM browser_history_logs b 
                     JOIN tbl_monitor_ips m ON b.monitor_ip_id = m.id LEFT JOIN users owner ON owner.id=m.owner_user_id 
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
                      JOIN tbl_monitor_ips m ON k.monitor_ip_id = m.id LEFT JOIN users owner ON owner.id=m.owner_user_id 
                      {$where_clause}";
        
        $total_result = $this->db->query($count_sql, $params)->fetch();
        $total_entries = $total_result['total'];
        $total_pages = ceil($total_entries / $per_page);
        
        // Get paginated data
        $data_sql = "SELECT k.*, m.ip_address, owner.name AS username,owner.user_no AS user_no, CONCAT('Device #',m.id) AS name, m.type,m.platform,m.last_monitor_tic, TIMESTAMPDIFF(SECOND,m.last_monitor_tic,NOW()) AS heartbeat_age_seconds,
                            k.key_date as date,
                            k.application,
                            k.key_pressed as `key`,
                            'keydown' as action
                     FROM key_logs k 
                     JOIN tbl_monitor_ips m ON k.monitor_ip_id = m.id LEFT JOIN users owner ON owner.id=m.owner_user_id 
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
    
    /** Fetch the ordered key sequence around a selected event. */
    public function api_key_context() {
        $monitorId=filter_var($_GET['device_id']??null,FILTER_VALIDATE_INT);
        $center=$_GET['at']??'';
        if(!$monitorId || !is_string($center) || !preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/D',$center)){
            http_response_code(400);$this->json(['success'=>false,'error'=>'Invalid monitor or timestamp']);
        }
        $rows=$this->db->fetchAll(
            "SELECT k.key_date,k.key_pressed,k.application FROM key_logs k WHERE k.monitor_ip_id=:id AND k.key_date BETWEEN DATE_SUB(:start, INTERVAL 2 MINUTE) AND DATE_ADD(:end, INTERVAL 2 MINUTE) ORDER BY k.key_date ASC,k.id ASC LIMIT 2000",
            ['id'=>$monitorId,'start'=>$center,'end'=>$center]
        );
        $this->json(['success'=>true,'logs'=>$rows,'center'=>$center,'limited'=>count($rows)>=2000]);
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
                      JOIN tbl_monitor_ips m ON u.monitor_ip_id = m.id LEFT JOIN users owner ON owner.id=m.owner_user_id 
                      {$where_clause}";
        
        $total_result = $this->db->query($count_sql, $params)->fetch();
        $total_entries = $total_result['total'];
        $total_pages = ceil($total_entries / $per_page);
        
        // Get paginated data
        $data_sql = "SELECT u.*, m.ip_address, owner.name AS username,owner.user_no AS user_no, CONCAT('Device #',m.id) AS name, m.type,m.platform,m.last_monitor_tic, TIMESTAMPDIFF(SECOND,m.last_monitor_tic,NOW()) AS heartbeat_age_seconds,
                            u.device_date as date,
                            u.device_name as device,
                            u.action,
                            CONCAT(COALESCE(u.device_type, 'Unknown Type'), ' - ', COALESCE(u.device_path, 'No Path')) as details
                     FROM usb_device_logs u 
                     JOIN tbl_monitor_ips m ON u.monitor_ip_id = m.id LEFT JOIN users owner ON owner.id=m.owner_user_id 
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
        if(($this->user['role']??'')==='viewer'){
            $where_conditions[] = 'monitor_ip_id IN (SELECT id FROM tbl_monitor_ips WHERE owner_user_id=:viewer_id)';
            $params['viewer_id']=(int)$this->user['id'];
        }
        
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
        if(($this->user['role']??'')==='viewer'){
            $key_where_conditions[] = 'monitor_ip_id IN (SELECT id FROM tbl_monitor_ips WHERE owner_user_id=:viewer_id)';
            $key_params['viewer_id']=(int)$this->user['id'];
        }
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
        if(($this->user['role']??'')==='viewer'){
            $usb_where_conditions[] = 'monitor_ip_id IN (SELECT id FROM tbl_monitor_ips WHERE owner_user_id=:viewer_id)';
            $usb_params['viewer_id']=(int)$this->user['id'];
        }
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
        
        // Include screenshots in the aggregate-only response. Use the
        // same ownership and date bounds; never return media paths to viewers.
        $shotWhere = [];
        $shotParams = [];
        if(($this->user['role']??'')==='viewer'){
            $shotWhere[]='monitor_ip_id IN (SELECT id FROM tbl_monitor_ips WHERE owner_user_id=:viewer_id)';
            $shotParams['viewer_id']=(int)$this->user['id'];
        }
        if($device_id){$shotWhere[]='monitor_ip_id=:device_id';$shotParams['device_id']=$device_id;}
        if($start_date){$shotWhere[]='capture_datetime>=:start_date';$shotParams['start_date']=$start_date.' 00:00:00';}
        if($end_date){$shotWhere[]='capture_datetime<=:end_date';$shotParams['end_date']=$end_date.' 23:59:59';}
        $shotClause=$shotWhere?' WHERE '.implode(' AND ',$shotWhere):'';
        $screenshotCount=(int)$this->db->query('SELECT COUNT(*) AS total FROM screenshot_metadata'.$shotClause,$shotParams)->fetch()['total'];

        $this->json([
            'success' => true,
            'stats' => [
                'screenshots' => $screenshotCount,
                'browser_history' => $browser_count,
                'key_logs' => $key_count,
                'usb_logs' => $usb_count,
                'total' => $browser_count + $key_count + $usb_count + $screenshotCount
            ]
        ]);
    }
}
?>
