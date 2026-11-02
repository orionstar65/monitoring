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
        $monitors = $this->isAdmin()
          ? $this->db->fetchAll("SELECT * FROM tbl_monitor_ips ORDER BY id")
          : $this->db->fetchAll("SELECT * FROM tbl_monitor_ips WHERE owner_user_id=:uid ORDER BY id", ['uid'=>$this->user['id']]);
        
        // Add dynamic status calculation for each monitor
        foreach ($monitors as &$monitor) {
            // Use StatusService's calculateClientStatus() for consistent status calculation
            $statusInfo = $this->statusService->calculateClientStatus($monitor['last_monitor_tic'] ?? null);
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
            $platform = trim($_POST['platform'] ?? '');
            
            // Validate platform if provided
            if (!empty($platform) && !in_array($platform, ['Windows', 'Mac', 'Ubuntu'])) {
                $error = 'Invalid platform selected';
            } elseif (empty($ip_address) || empty($name)) {
                $error = 'IP address and name are required';
            } else {
                $existing = false;
                $error = null;
                if (!$existing) {
                    // No username uniqueness check needed - multiple monitors can have same username
                    if (empty($error)) {
                        try {
                            $monitorId = $this->db->insert('tbl_monitor_ips', [
                                'ip_address' => $ip_address,
                                'name' => $name,
                                'username' => $username,
                                'mac_address' => ($mac_address !== '' ? $mac_address : null),
                                'type' => $type,
                                'platform' => ($platform ?: null),
                                'os_status' => 0,
                                'monitor_status' => 0,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                            
                            if ($monitorId) {
                                $this->redirect('monitors');
                            } else {
                                $error = 'Failed to create monitor';
                            }
                        } catch (PDOException $e) {
                            // Handle race condition: another request inserted same IP/MAC between check and insert.
                            // MySQL duplicate key uses SQLSTATE 23000
                            if ($e->getCode() === '23000') {
                                $error = 'IP address or MAC address already exists';
                            } else {
                                $error = 'Failed to create monitor';
                                error_log("Monitor create failed: " . $e->getMessage());
                            }
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
            $platform = trim($_POST['platform'] ?? '');
            $owner_user_id = filter_var($_POST['owner_user_id']??null,FILTER_VALIDATE_INT)?:null;
            if($owner_user_id && !$this->db->fetch('SELECT id FROM users WHERE id=:id',['id'=>$owner_user_id])) $error='Unknown account';
            // Validate type
            if (!in_array($type, ['desktop', 'laptop', 'server'])) {
                $error = 'Invalid device type selected';
            } elseif (!empty($platform) && !in_array($platform, ['Windows', 'Mac', 'Ubuntu'])) {
                $error = 'Invalid platform selected';
            } else {
                // No username uniqueness check needed - multiple monitors can have same username
                if (empty($error)) {
                    // Update username, slack_id, platform and type fields (but NOT updated_at - that's only for monitoring events)
                    $update_data = [
                        'type' => $type,
                        'username' => $username,
                        'slack_id' => $slack_id,
                        'platform' => !empty($platform) ? $platform : null,
                        'owner_user_id' => $owner_user_id
                    ];
                    
                    $this->db->update('tbl_monitor_ips', $update_data, 'id = :id', ['id' => $id]);
                    
                    $this->redirect('monitors');
                }
            }
        }
        
        $accounts=$this->db->fetchAll("SELECT id,name,username,email FROM users ORDER BY name,username");
        $this->view('monitors/edit', ['monitor'=>$monitor,'accounts'=>$accounts,'error'=>$error??null]);
    }
    
    public function delete($id) {
        $this->requireAdmin();
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);exit('POST required');}
        $this->db->delete('tbl_monitor_ips', 'id = :id', ['id' => $id]);
        $this->redirect('monitors');
    }
}
?> 