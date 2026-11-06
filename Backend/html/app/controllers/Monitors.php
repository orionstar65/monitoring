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
        [$scope,$params]=$this->monitorScope('m');
        if($this->isManager()){
            $monitors=$this->db->fetchAll(
                "SELECT m.*,u.name AS owner_name,u.user_no AS owner_user_no
                 FROM tbl_monitor_ips m LEFT JOIN users u ON u.id=m.owner_user_id
                 WHERE {$scope}
                 ORDER BY CAST(u.user_no AS UNSIGNED),u.name,m.type,m.platform,m.id",
                $params
            );
        }else{
            $monitors=$this->db->fetchAll(
                "SELECT m.*,u.name AS owner_name,u.user_no AS owner_user_no
                 FROM tbl_monitor_ips m LEFT JOIN users u ON u.id=m.owner_user_id
                 WHERE m.owner_user_id=:uid ORDER BY m.type,m.platform,m.id",
                ['uid'=>(int)$this->user['id']]
            );
        }

        // Add dynamic status calculation for each monitor
        foreach ($monitors as &$monitor) {
            // Use StatusService's calculateClientStatus() for consistent status calculation
            $statusInfo = $this->statusService->calculateClientStatus($monitor['last_monitor_tic'] ?? null);
            $monitor['dynamic_status'] = $statusInfo['status'];
            $monitor['status_text'] = $statusInfo['status_text'];
            $monitor['status_class'] = $statusInfo['status_class'];
        }
        
        // Calculate distinct usernames count
        $distinct_username_count = count(array_unique(array_filter(array_column($monitors, 'owner_user_id'))));
        
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
            $owner_user_id=filter_var($_POST['owner_user_id']??null,FILTER_VALIDATE_INT)?:null;
            $mac_address = $_POST['mac_address'] ?? '';
            $type = $_POST['type'] ?? 'desktop';
            $platform = trim($_POST['platform'] ?? '');
            
            // Verify the new owner and field shapes before creating any monitor.
            if($owner_user_id && !$this->db->fetch('SELECT id FROM users WHERE id=:id',['id'=>$owner_user_id])){
                $error='Unknown account';
            } elseif(!in_array($type,['desktop','laptop','server'],true)){
                $error='Invalid machine type';
            } elseif(!filter_var($ip_address,FILTER_VALIDATE_IP)){
                $error='Enter a valid IP address';
            } elseif(strlen($mac_address)>17){
                $error='Invalid MAC address';
            }
            // Validate platform if provided
            if (!isset($error) && !empty($platform) && !in_array($platform, ['Windows', 'Mac', 'Ubuntu'],true)) {
                $error = 'Invalid platform selected';
            } elseif (!isset($error) && empty($ip_address)) {
                $error = 'IP address is required';
            } elseif(!isset($error)) {
                $existing = false;
                $error = null;
                if (!$existing) {
                    // A user can own multiple monitors.
                    if (empty($error)) {
                        try {
                            $monitorId = $this->db->insert('tbl_monitor_ips', [
                                'ip_address' => $ip_address,
                                'owner_user_id' => $owner_user_id,
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
        
        $accounts=$this->db->fetchAll("SELECT u.id,u.name,u.user_no,
            (SELECT COUNT(*) FROM tbl_monitor_ips m WHERE m.owner_user_id=u.id) AS device_count
            FROM users u ORDER BY CAST(u.user_no AS UNSIGNED),u.name,u.id");
        $this->view('monitors/create', ['error' => $error ?? null,'accounts'=>$accounts]);
    }
    
    public function edit($id) {
        $this->requireManager();
        $id=(int)$id;
        $this->requireMonitorAccess($id);
        
        $monitor = $this->db->fetch(
            "SELECT m.*,u.name AS owner_name,u.user_no AS owner_user_no FROM tbl_monitor_ips m LEFT JOIN users u ON u.id=m.owner_user_id WHERE m.id=:id",
            ['id' => $id]
        );
        
        if (!$monitor) {
            $this->redirect('monitors');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $type = $_POST['type'] ?? 'desktop';
            $remark = trim((string)($_POST['remark']??''));
            $slack_id = trim($_POST['slack_id'] ?? '');
            $platform = trim($_POST['platform'] ?? '');
            $owner_user_id = filter_var($_POST['owner_user_id']??null,FILTER_VALIDATE_INT)?:null;
            // Delegated admins may edit monitor metadata but cannot transfer
            // ownership outside the assigned user (or to another assigned user).
            if($this->isScopedAdmin()&&(int)$owner_user_id!==(int)$monitor['owner_user_id']){
                http_response_code(403);exit('Monitor reassignment requires Super Admin');
            }
            $settings_locked = isset($_POST['settings_locked']) ? 1 : 0;
            if($owner_user_id && !$this->db->fetch('SELECT id FROM users WHERE id=:id',['id'=>$owner_user_id])) $error='Unknown account';
            // Validate type
            if(strlen($remark)>500){$error='Remark too long';}
            elseif (!in_array($type, ['desktop', 'laptop', 'server'])) {
                $error = 'Invalid device type selected';
            } elseif (!empty($platform) && !in_array($platform, ['Windows', 'Mac', 'Ubuntu'])) {
                $error = 'Invalid platform selected';
            } else {
                // A user can own multiple monitors.
                if (empty($error)) {
                    // Update ownership and monitor settings (leave last-event timestamps unchanged).
                    $update_data = [
                        'type' => $type,
                        'remark' => $remark,
                        'slack_id' => $slack_id,
                        'platform' => !empty($platform) ? $platform : null,
                        'owner_user_id' => $owner_user_id,
                        'assignment_locked' => $settings_locked,
                        'settings_locked' => $settings_locked
                    ];
                    
                    $this->db->update('tbl_monitor_ips', $update_data, 'id = :id', ['id' => $id]);
                    if((int)($monitor['owner_user_id']??0)!==(int)($owner_user_id??0)){
                        // Invalidate old device sessions after an administrator reassigns this monitor.
                        $this->db->query('UPDATE client_sessions SET revoked_at=NOW() WHERE monitor_id=:id AND revoked_at IS NULL',
                            ['id'=>$id]);
                    }
                    if(($_POST['return_to']??'')==='monitoring'){
                        $this->redirect('view?tab=settings&monitor_id='.(int)$id.'&saved=1');
                    }
                    $this->redirect('monitors');
                }
            }
        }
        
        $accounts=$this->isAdmin()
            ? $this->db->fetchAll("SELECT u.id,u.name,u.user_no,
                 (SELECT COUNT(*) FROM tbl_monitor_ips m WHERE m.owner_user_id=u.id) AS device_count
                 FROM users u ORDER BY CAST(u.user_no AS UNSIGNED),u.name,u.id")
            : $this->db->fetchAll(
                "SELECT u.id,u.name,u.user_no,
                   (SELECT COUNT(*) FROM tbl_monitor_ips m WHERE m.owner_user_id=u.id) AS device_count
                 FROM users u JOIN admin_user_assignments a ON a.managed_user_id=u.id
                 WHERE a.admin_user_id=:manager AND u.role=:role
                 ORDER BY CAST(u.user_no AS UNSIGNED),u.name,u.id",
                ['manager'=>(int)$this->user['id'],'role'=>ROLE_USER]);
        $monitor['status_info']=$this->statusService->calculateClientStatus($monitor['last_monitor_tic']??null);
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