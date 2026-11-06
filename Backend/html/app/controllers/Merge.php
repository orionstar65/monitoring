<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/DatabaseService.php';

class Merge extends Base {
    private $dbService;
    
    public function __construct() {
        parent::__construct();
        // Only administrators can merge records or rename monitoring files.
        $this->requireAdmin();
        // Merge operations are restricted to the authenticated user's monitors.
        
        // Initialize DatabaseService
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            error_log("Database connection failed: " . $mysqli->connect_error);
            $this->dbService = null;
        } else {
            $this->dbService = new DatabaseService($mysqli);
        }
    }
    
    private function allowedMonitor(int $id): bool {
        if($id<1)return false;
        return (bool)$this->db->fetch(
            $this->isAdmin() ? 'SELECT id FROM tbl_monitor_ips WHERE id=:id'
                : 'SELECT id FROM tbl_monitor_ips WHERE id=:id AND owner_user_id=:uid',
            $this->isAdmin()?['id'=>$id]:['id'=>$id,'uid'=>$this->user['id']]
        );
    }
    public function index() {
        $this->view('merge/index');
    }
    
    /**
     * API endpoint to get list of monitors for selection
     */
    public function api_list() {
        $monitors = $this->db->fetchAll(
            "SELECT m.id,m.owner_user_id,m.ip_address,m.last_seen_ip,u.name AS username,u.user_no AS user_no,m.mac_address,CONCAT('Device #',m.id) AS name,m.type,m.platform,m.last_monitor_tic,m.updated_at FROM tbl_monitor_ips m LEFT JOIN users u ON u.id=m.owner_user_id "
            .($this->isAdmin() ? "ORDER BY (u.id IS NULL), CAST(u.user_no AS UNSIGNED), u.name, COALESCE(m.type,''), COALESCE(m.platform,''), INET_ATON(COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address)), COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address), m.id" : "WHERE m.owner_user_id=:uid ORDER BY (u.id IS NULL), CAST(u.user_no AS UNSIGNED), u.name, COALESCE(m.type,''), COALESCE(m.platform,''), INET_ATON(COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address)), COALESCE(NULLIF(m.last_seen_ip,''),m.ip_address), m.id"),
            $this->isAdmin()?[]:['uid'=>$this->user['id']]
        );
        
        // Add status information
        require_once BASEPATH . 'app/services/StatusService.php';
        $statusService = StatusService::getInstance();
        
        foreach ($monitors as &$monitor) {
            $statusInfo = $statusService->calculateClientStatus($monitor['last_monitor_tic'] ?? null);
            $monitor['status'] = $statusInfo['status'];
            $monitor['status_text'] = $statusInfo['status_text'];
            $monitor['status_class'] = $statusInfo['status_class'];
        }
        
        $this->json([
            'success' => true,
            'monitors' => $monitors
        ]);
    }
    
    /**
     * API endpoint to fix mismatched filenames
     */
    public function api_fix_filenames() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Only POST method allowed'
            ]);
            return;
        }
        
        $monitor_id = intval($_POST['monitor_id'] ?? 0);
        $source_ips = $_POST['source_ips'] ?? [];
        
        if (!$monitor_id) {
            $this->json([
                'success' => false,
                'error' => 'Monitor ID is required'
            ]);
            return;
        }
        
        if (!$this->dbService) {
            $this->json([
                'success' => false,
                'error' => 'Database service not available'
            ]);
            return;
        }
        
        if(!$this->allowedMonitor($monitor_id)){
            http_response_code(403);$this->json(['success'=>false,'error'=>'Monitor not assigned to your account']);
        }
        // If source_ips is a string, convert to array
        if (is_string($source_ips)) {
            $source_ips = !empty($source_ips) ? explode(',', $source_ips) : [];
        }
        
        if(!is_array($source_ips)){http_response_code(400);$this->json(['success'=>false,'error'=>'Invalid source IPs']);}
        if(!$this->isAdmin()){
            $allowedIPs=array_column($this->db->fetchAll('SELECT ip_address FROM tbl_monitor_ips WHERE owner_user_id=:uid',
                ['uid'=>$this->user['id']]),'ip_address');
            // An empty array is a wildcard in DatabaseService. Non-admins may only
            // rewrite filenames for explicitly selected, owned source IPs.
            if(!$source_ips || array_diff($source_ips,$allowedIPs)){
                http_response_code(403);
                $this->json(['success'=>false,'error'=>'Select source IPs assigned to your account']);
            }
            $source_ips=array_values(array_unique($source_ips));
        }
        // Perform filename fix
        $result = $this->dbService->fixMismatchedFilenames($monitor_id, $source_ips);
        
        $this->json($result);
    }
    
    /**
     * API endpoint to perform the merge operation
     */
    public function api_merge() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'error' => 'Only POST method allowed'
            ]);
            return;
        }
        
        $source_id = intval($_POST['source_id'] ?? 0);
        $target_id = intval($_POST['target_id'] ?? 0);
        
        if (!$source_id || !$target_id) {
            $this->json([
                'success' => false,
                'error' => 'Source ID and Target ID are required'
            ]);
            return;
        }
        
        if (!$this->dbService) {
            $this->json([
                'success' => false,
                'error' => 'Database service not available'
            ]);
            return;
        }
        
        if(!$this->allowedMonitor($source_id)||!$this->allowedMonitor($target_id)){
            http_response_code(403);$this->json(['success'=>false,'error'=>'Both monitors must belong to your account']);
        }
        // Prevent cross-user merges, including those initiated by administrators.
        // The source and target must be two monitors owned by the same account.
        $pair=$this->db->fetch(
            'SELECT COUNT(*) AS total,COUNT(DISTINCT owner_user_id) AS owners,
                    SUM(owner_user_id IS NULL) AS unassigned
             FROM tbl_monitor_ips WHERE id IN (:source,:target)',
            ['source'=>$source_id,'target'=>$target_id]
        );
        if((int)($pair['total']??0)!==2||(int)($pair['owners']??0)!==1
            ||(int)($pair['unassigned']??0)!==0||$source_id===$target_id){
            http_response_code(403);
            $this->json(['success'=>false,'error'=>'Merge requires two different monitors assigned to the same user']);
        }
        // Perform merge
        $result = $this->dbService->mergeMonitorLogs($source_id, $target_id);
        
        $this->json($result);
    }
}
?>

