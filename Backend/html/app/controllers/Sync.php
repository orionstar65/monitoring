<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Sync extends Base {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $this->requireAdmin();
        
        // Get monitor statistics from database
        $total_monitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips")['count'];
        $active_monitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips WHERE last_monitor_tic >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")['count'];
        
        $this->view('sync/index', [
            'total_monitors' => $total_monitors,
            'active_monitors' => $active_monitors,
            'synced_count' => 0 // No longer needed since we use database
        ]);
    }
    
    public function api_sync() {
        $this->requireAdmin();
        
        // Since we're using database-only approach, no sync is needed
        $total_monitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips")['count'];
        
        $this->json([
            'success' => true,
            'synced_count' => 0,
            'total_monitors' => $total_monitors,
            'message' => "Database-only approach - no file sync needed"
        ]);
    }
    
    public function api_status() {
        $this->requireAdmin();
        
        $total_monitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips")['count'];
        $active_monitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips WHERE last_monitor_tic >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)")['count'];
        
        $this->json([
            'total_monitors' => $total_monitors,
            'active_monitors' => $active_monitors,
            'data_directories' => [
                'screens' => SCREENS_DIR,
                'thumbnails' => THUMBNAILS_DIR,
                'logs' => LOGS_DIR
            ],
            'message' => "Database-only approach - all data managed in database tables"
        ]);
    }
}
?> 