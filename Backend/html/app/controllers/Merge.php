<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/DatabaseService.php';

class Merge extends Base {
    private $dbService;
    
    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
        
        // Initialize DatabaseService
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            error_log("Database connection failed: " . $mysqli->connect_error);
            $this->dbService = null;
        } else {
            $this->dbService = new DatabaseService($mysqli);
        }
    }
    
    public function index() {
        $this->view('merge/index');
    }
    
    /**
     * API endpoint to get list of monitors for selection
     */
    public function api_list() {
        $monitors = $this->db->fetchAll(
            "SELECT id, ip_address, username, mac_address, name, type, updated_at FROM tbl_monitor_ips ORDER BY ip_address"
        );
        
        // Add status information
        require_once BASEPATH . 'app/services/StatusService.php';
        $statusService = StatusService::getInstance();
        
        foreach ($monitors as &$monitor) {
            $statusInfo = $statusService->calculateClientStatus($monitor['updated_at'] ?? null);
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
        
        // Perform merge
        $result = $this->dbService->mergeMonitorLogs($source_id, $target_id);
        
        $this->json($result);
    }
}
?>

