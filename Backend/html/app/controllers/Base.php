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
        if(($this->user['role']??'')!=='admin' && !in_array(get_class($this),['Auth','Monitors','Dashboard','Merge'],true)){
            http_response_code(403);exit('Access denied');
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
        return isset($this->user['role']) && $this->user['role'] === ROLE_ADMIN;
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
