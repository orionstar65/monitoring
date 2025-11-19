<?php
/**
 * Status Service
 * Provides easy access to client status calculations using DatabaseService
 */

require_once __DIR__ . '/DatabaseService.php';

class StatusService {
    private static $instance = null;
    private $dbService;
    
    private function __construct() {
        // Create DatabaseService instance for status calculations
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            error_log("Database connection failed: " . $mysqli->connect_error);
            $this->dbService = null;
        } else {
            $this->dbService = new DatabaseService($mysqli);
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Calculate client status based on updated_at timestamp
     * This is the single source of truth for status calculation across the entire application
     * 
     * @param string|null $updated_at The updated_at timestamp from database
     * @return array Status information: ['status' => 'online'|'inactive'|'offline', 'status_class' => 'success'|'warning'|'danger', 'status_text' => 'Online'|'Inactive'|'Offline', 'time_diff' => seconds, 'updated_at_timestamp' => unix timestamp]
     */
    public function calculateClientStatus($updated_at) {
        if ($this->dbService === null) {
            // Fallback if DatabaseService is not available
            return [
                'status' => 'offline',
                'status_class' => 'danger',
                'status_text' => 'Offline',
                'time_diff' => PHP_INT_MAX,
                'updated_at_timestamp' => 0,
                'live_status' => 'offline',
            ];
        }
        
        return $this->dbService->calculateClientStatus($updated_at);
    }
    
    /**
     * Get DatabaseService instance (for advanced usage)
     */
    public function getDatabaseService() {
        return $this->dbService;
    }
}
?>

