<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/StatusService.php';

class Monitors extends Base {
    private $statusService;
    
    public function __construct() {
        parent::__construct();
        $this->statusService = StatusService::getInstance();
    }
    
    public function index() {
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY id"
        );
        
        // Add dynamic status calculation for each monitor
        foreach ($monitors as &$monitor) {
            // Use StatusService's calculateClientStatus() for consistent status calculation
            $statusInfo = $this->statusService->calculateClientStatus($monitor['updated_at'] ?? null);
            $monitor['dynamic_status'] = $statusInfo['status'];
            $monitor['status_text'] = $statusInfo['status_text'];
            $monitor['status_class'] = $statusInfo['status_class'];
        }
        
        // Calculate distinct usernames count
        $distinct_usernames = array_unique(array_filter(array_column($monitors, 'username')));
        $distinct_username_count = count($distinct_usernames);
        
        $this->view('monitors/index', [
            'monitors' => $monitors,
            'distinct_username_count' => $distinct_username_count
        ]);
    }
    
    /**
     * Calculate dynamic status based on updated_at timestamp
     * @deprecated Use StatusService::calculateClientStatus() instead
     */
    private function calculateDynamicStatus($updated_at) {
        // Use StatusService for consistent status calculation
        $statusInfo = $this->statusService->calculateClientStatus($updated_at);
        return [
            'status' => $statusInfo['status'],
            'text' => $statusInfo['status_text'],
            'class' => $statusInfo['status_class']
        ];
    }
    
    public function create() {
        $this->requireAdmin();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ip_address = $_POST['ip_address'] ?? '';
            $name = $_POST['name'] ?? '';
            $username = $_POST['username'] ?? '';
            $mac_address = $_POST['mac_address'] ?? '';
            $type = $_POST['type'] ?? 'user';
            
            if (empty($ip_address) || empty($name)) {
                $error = 'IP address and name are required';
            } else {
                // Check if IP already exists
                $existing = $this->db->fetch(
                    "SELECT id FROM tbl_monitor_ips WHERE ip_address = :ip_address",
                    ['ip_address' => $ip_address]
                );
                
                if ($existing) {
                    $error = 'IP address already exists';
                } else {
                    // No username uniqueness check needed - multiple monitors can have same username
                    if (empty($error)) {
                        $monitorId = $this->db->insert('tbl_monitor_ips', [
                            'ip_address' => $ip_address,
                            'name' => $name,
                            'username' => $username,
                            'mac_address' => $mac_address,
                            'type' => $type,
                            'os_status' => 0,
                            'monitor_status' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        if ($monitorId) {
                            $this->redirect('monitors');
                        } else {
                            $error = 'Failed to create monitor';
                        }
                    }
                }
            }
        }
        
        $this->view('monitors/create', ['error' => $error ?? null]);
    }
    
    public function edit($id) {
        $this->requireAdmin();
        
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $id]
        );
        
        if (!$monitor) {
            $this->redirect('monitors');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $type = $_POST['type'] ?? 'desktop';
            $username = trim($_POST['username'] ?? '');
            $slack_id = trim($_POST['slack_id'] ?? '');
            
            // Validate type
            if (!in_array($type, ['desktop', 'laptop', 'server'])) {
                $error = 'Invalid device type selected';
            } else {
                // No username uniqueness check needed - multiple monitors can have same username
                if (empty($error)) {
                    // Update username, slack_id and type fields (but NOT updated_at - that's only for monitoring events)
                    $update_data = [
                        'type' => $type,
                        'username' => $username,
                        'slack_id' => $slack_id
                    ];
                    
                    $this->db->update('tbl_monitor_ips', $update_data, 'id = :id', ['id' => $id]);
                    
                    $this->redirect('monitors');
                }
            }
        }
        
        $this->view('monitors/edit', ['monitor' => $monitor, 'error' => $error ?? null]);
    }
    
    public function delete($id) {
        $this->requireAdmin();
        $this->db->delete('tbl_monitor_ips', 'id = :id', ['id' => $id]);
        $this->redirect('monitors');
    }
}
?> 