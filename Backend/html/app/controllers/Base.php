<?php
class Base {
    protected $db;
    protected $user;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadUser();
        if(!empty($this->user['must_change_password']) && get_class($this)!=='Auth'){
            $this->redirect('auth/change_password');
        }
        $role=(string)($this->user['role']??'');
        $class=get_class($this);
        $parts=explode('/',trim((string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/'));
        $controllerPosition=array_search(strtolower($class),array_map('strtolower',$parts),true);
        $method=$controllerPosition===false?'index':($parts[$controllerPosition+1]??'index');
        if($role===ROLE_SCOPED_ADMIN){
            // Delegated admins can inspect only assigned-user monitoring data
            // and edit only those users/monitors. Other controllers fail closed.
            $allowed=[
                'Auth'=>['*'],
                'View'=>['view','index','api_live_status','api_screenshots',
                    'api_screenshot_by_id','api_screenshot_navigation','api_screenshot_timeline',
                    'view_file','api_monitor_settings','logs'],
                'Logbrowse'=>['index','logbrowse','api_devices','api_browser_logs',
                    'api_key_logs','api_key_timeline','api_key_context','api_usb_logs','api_stats'],
                'Monitors'=>['index','edit'],
                'Users'=>['index','edit']
            ];
            if(!isset($allowed[$class])&&!in_array($class,['Base'],true)
                || (isset($allowed[$class])&&!in_array('*',$allowed[$class],true)
                    && !in_array($method,$allowed[$class],true))){
                http_response_code(403);exit('Access denied');
            }
        } elseif($role!==ROLE_SUPER_ADMIN && !in_array($class,['Auth','Monitors','Dashboard','Merge'],true)){
            // Aggregate-only summary pages for ordinary users.
            $summary=$role===ROLE_USER
                && (($class==='Logbrowse' && in_array($method,['logbrowse','index','api_stats'],true))
                    || ($class==='View' && in_array($method,['view','index'],true)));
            if(!$summary){http_response_code(403);exit('Access denied');}
        }
    }
    
    protected function loadUser() {
        if (!isset($_SESSION['user_id'])) return;
        $this->user = $this->db->fetch(
            "SELECT * FROM users WHERE id = :id",
            ['id' => $_SESSION['user_id']]
        );
        if (!$this->user) {
            $_SESSION = [];
            session_regenerate_id(true);
            $this->redirect('auth/login');
        }
        // Never trust stale roles cached before an administrator's edit.
        $_SESSION['role'] = $this->user['role'];
        $_SESSION['user_no'] = (string)$this->user['user_no'];
        $_SESSION['full_name'] = $this->user['name'];
    }
    
    protected function view($view, $data = []) {
        // Extract data to variables
        extract($data);
        
        // Include the view file
        $view_file = BASEPATH . 'app/views/' . $view . '.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            die("View not found: $view");
        }
    }
    
    protected function redirect($url) {
        // If URL doesn't start with http, assume it's relative and add BASE_URL
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = BASE_URL . $url;
        }
        header('Location: ' . $url);
        exit;
    }
    
    protected function isAdmin() {
        // Legacy controller checks are intentionally super-admin-only.
        return ($this->user['role']??'')===ROLE_SUPER_ADMIN;
    }
    protected function isScopedAdmin(): bool {
        return ($this->user['role']??'')===ROLE_SCOPED_ADMIN;
    }
    protected function isManager(): bool {
        return $this->isAdmin()||$this->isScopedAdmin();
    }
    protected function requireManager(): void {
        if(!$this->isManager()){http_response_code(403);exit('Access denied');}
    }
    protected function requireSuperAdmin(): void {
        if(!$this->isAdmin()){http_response_code(403);exit('Super Admin required');}
    }
    protected function canAccessUser(int $id): bool {
        if($this->isAdmin())return true;
        if(!$this->isScopedAdmin()||$id<1)return false;
        // Query on every authorization decision so revoked assignments apply
        // without requiring delegated admins to log out.
        return (bool)$this->db->fetch(
            'SELECT 1 FROM admin_user_assignments a JOIN users u ON u.id=a.managed_user_id
             WHERE a.admin_user_id=:admin AND a.managed_user_id=:target
               AND u.role=:role LIMIT 1',
            ['admin'=>(int)$this->user['id'],'target'=>$id,'role'=>ROLE_USER]);
    }
    protected function requireUserAccess(int $id): void {
        if(!$this->canAccessUser($id)){http_response_code(403);exit('User not assigned to this administrator');}
    }
    protected function canAccessMonitor(int $id): bool {
        if($this->isAdmin())return true;
        if(!$this->isScopedAdmin()||$id<1)return false;
        return (bool)$this->db->fetch(
            'SELECT 1 FROM tbl_monitor_ips m JOIN admin_user_assignments a
             ON a.managed_user_id=m.owner_user_id JOIN users u ON u.id=a.managed_user_id
             WHERE m.id=:monitor AND a.admin_user_id=:admin
             AND u.role=:role LIMIT 1',
            ['monitor'=>$id,'admin'=>(int)$this->user['id'],'role'=>ROLE_USER]);
    }
    protected function requireMonitorAccess(int $id): void {
        if(!$this->canAccessMonitor($id)){http_response_code(403);exit('Monitor not assigned to this administrator');}
    }
    /** Predicate and parameters for SELECTs that involve a tbl_monitor_ips alias. */
    protected function monitorScope(string $alias='m'): array {
        if($this->isAdmin())return ['1=1',[]];
        if(!$this->isScopedAdmin())return ['1=0',[]];
        if(!preg_match('/^[a-z][a-z0-9_]*$/i',$alias))throw new InvalidArgumentException('Invalid SQL alias');
        return [
            "{$alias}.owner_user_id IN (
                SELECT a.managed_user_id FROM admin_user_assignments a
                JOIN users scope_user ON scope_user.id=a.managed_user_id
                WHERE a.admin_user_id=:scope_admin_id AND scope_user.role=:scope_role
            )",
            ['scope_admin_id'=>(int)$this->user['id'],'scope_role'=>ROLE_USER]
        ];
    }
    
    protected function requireAdmin() {
        if (!$this->isAdmin()) {
            $this->redirect('dashboard');
        }
    }
    
    protected function json($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
