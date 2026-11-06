<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Apilogs extends Base {
    
    public function __construct() {
        parent::__construct();
    }
    
    public function index() {
        $this->view('api_logs/index', []);
    }
    
    public function getLogs() {
        // Turn off error display for JSON responses
        ini_set('display_errors', 0);
        error_reporting(0);
        
        // Set JSON header first
        header('Content-Type: application/json; charset=utf-8');
        
        // Suppress any output that might interfere with JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        try {
            $date = $_GET['date'] ?? gmdate('Y-m-d');
            $endpoint = $_GET['endpoint'] ?? '';
            $request_type = $_GET['request_type'] ?? '';
            $ip_address = $_GET['ip_address'] ?? '';
            $status_code = $_GET['status_code'] ?? '';
            $min_response_time = $_GET['min_response_time'] ?? '';
            $max_response_time = $_GET['max_response_time'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
            $sort_by = $_GET['sort_by'] ?? 'created_at';
            $sort_order = $_GET['sort_order'] ?? 'DESC';
        
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->json([
                    'success' => false,
                    'message' => 'Invalid date format',
                    'data' => [],
                    'total' => 0
                ]);
                return;
            }
            
            // Generate table name
            $table_name = 'api_logs_' . str_replace('-', '_', $date);
            
            // Check if table exists
            $table_exists = $this->checkTableExists($table_name);
            
            if (!$table_exists) {
                $this->json([
                    'success' => true,
                    'message' => 'No data available for selected date',
                    'data' => [],
                    'total' => 0,
                    'table_exists' => false
                ]);
                return;
            }
            
            // Build WHERE clause
            $where_conditions = [];
            $params = [];
            
            if (!empty($endpoint) && $endpoint !== 'All') {
                $where_conditions[] = "endpoint = :endpoint";
                $params['endpoint'] = $endpoint;
            }
            
            // Check if request_type column exists before filtering by it
            $has_request_type_column = $this->checkColumnExists($table_name, 'request_type');
            
            if (!empty($request_type) && $request_type !== 'All' && $has_request_type_column) {
                $where_conditions[] = "request_type = :request_type";
                $params['request_type'] = $request_type;
            }
            
            if (!empty($ip_address)) {
                $where_conditions[] = "ip_address LIKE :ip_address";
                $params['ip_address'] = '%' . $ip_address . '%';
            }
            
            if (!empty($status_code) && $status_code !== 'All') {
                $where_conditions[] = "status_code = :status_code";
                $params['status_code'] = (int)$status_code;
            }
            
            if (!empty($min_response_time)) {
                $where_conditions[] = "response_time_ms >= :min_response_time";
                $params['min_response_time'] = (float)$min_response_time;
            }
            
            if (!empty($max_response_time)) {
                $where_conditions[] = "response_time_ms <= :max_response_time";
                $params['max_response_time'] = (float)$max_response_time;
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            // Validate sort_by and sort_order
            $allowed_sort_columns = ['id', 'endpoint', 'method', 'ip_address', 'status_code', 'response_time_ms', 'created_at'];
            // Only allow request_type sorting if column exists
            if ($has_request_type_column) {
                $allowed_sort_columns[] = 'request_type';
            }
            $sort_by = in_array($sort_by, $allowed_sort_columns) ? $sort_by : 'created_at';
            $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
            
            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM `$table_name` $where_clause";
            $total_result = $this->db->fetch($count_sql, $params);
            $total = $total_result['total'] ?? 0;
            
            if ($total == 0) {
                $this->json([
                    'success' => true,
                    'message' => 'No data available matching the selected filters',
                    'data' => [],
                    'total' => 0,
                    'table_exists' => true
                ]);
                return;
            }
            
            // Calculate pagination
            $offset = ($page - 1) * $per_page;
            $total_pages = ceil($total / $per_page);
            
            // Get logs
            // PDO doesn't support named parameters for LIMIT/OFFSET directly, so we use placeholders
            $sql = "SELECT * FROM `$table_name` $where_clause ORDER BY $sort_by $sort_order LIMIT $per_page OFFSET $offset";
            
            $logs = $this->db->fetchAll($sql, $params);
            
            // Check if export is requested
            if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                // For export, get all records matching filters (no pagination)
                $export_sql = "SELECT * FROM `$table_name` $where_clause ORDER BY $sort_by $sort_order";
                $all_logs = $this->db->fetchAll($export_sql, $params);
                $this->exportToCSV($all_logs, $date);
                return;
            }
            
            // Format response
            $this->json([
                'success' => true,
                'data' => $logs,
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
                'table_exists' => true
            ]);
        
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Error loading logs: ' . $e->getMessage(),
                'data' => [],
                'total' => 0
            ]);
        } catch (Error $e) {
            $this->json([
                'success' => false,
                'message' => 'Error loading logs: ' . $e->getMessage(),
                'data' => [],
                'total' => 0
            ]);
        } catch (Throwable $e) {
            $this->json([
                'success' => false,
                'message' => 'Error loading logs: ' . $e->getMessage(),
                'data' => [],
                'total' => 0
            ]);
        }
    }
    
    private function exportToCSV($logs, $date) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="api_logs_' . $date . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, ['Timestamp', 'Endpoint', 'Method', 'Request Type', 'IP Address', 'Status Code', 'Response Time (ms)']);
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['created_at'],
                $log['endpoint'] ?? '',
                $log['method'] ?? '',
                $log['request_type'] ?? '',
                $log['ip_address'] ?? '',
                $log['status_code'] ?? '',
                $log['response_time_ms'] ?? ''
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    public function getAvailableDates() {
        // Turn off error display for JSON responses
        ini_set('display_errors', 0);
        error_reporting(0);
        
        // Set JSON header first
        header('Content-Type: application/json; charset=utf-8');
        
        // Suppress any output that might interfere with JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        try {
            // Get all available log tables using information_schema (more reliable)
            $db_name = DB_NAME;
            $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME LIKE 'api_logs_%' ORDER BY TABLE_NAME DESC";
            $tables = $this->db->fetchAll($sql, ['db_name' => $db_name]);
            
            $dates = [];
            foreach ($tables as $table) {
                $table_name = $table['TABLE_NAME'];
                // Extract date from table name: api_logs_2025_01_15 -> 2025-01-15
                if (preg_match('/api_logs_(\d{4})_(\d{2})_(\d{2})/', $table_name, $matches)) {
                    $dates[] = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                }
            }
            
            // Sort dates descending (newest first)
            rsort($dates);
            
            $this->json([
                'success' => true,
                'dates' => $dates
            ]);
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Error loading dates: ' . $e->getMessage(),
                'dates' => []
            ]);
        } catch (Error $e) {
            $this->json([
                'success' => false,
                'message' => 'Error loading dates: ' . $e->getMessage(),
                'dates' => []
            ]);
        }
    }
    
    private function checkTableExists($table_name) {
        try {
            // Validate table name (only alphanumeric, underscore, and dash)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
                return false;
            }
            
            // Use information_schema for more reliable table checking
            $db_name = DB_NAME;
            $sql = "SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name";
            $result = $this->db->fetch($sql, [
                'db_name' => $db_name,
                'table_name' => $table_name
            ]);
            return isset($result['count']) && $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }
    
    private function checkColumnExists($table_name, $column_name) {
        try {
            // Validate table and column names (only alphanumeric, underscore, and dash)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name) || !preg_match('/^[a-zA-Z0-9_]+$/', $column_name)) {
                return false;
            }
            
            // Use information_schema for more reliable column checking
            $db_name = DB_NAME;
            $sql = "SELECT COUNT(*) as count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name";
            $result = $this->db->fetch($sql, [
                'db_name' => $db_name,
                'table_name' => $table_name,
                'column_name' => $column_name
            ]);
            return isset($result['count']) && $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        } catch (Error $e) {
            return false;
        }
    }
    
    public function deleteOldTables() {
        // Deleting archived tables must never be triggered by a GET or link preview.
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
            http_response_code(405);
            $this->json(['success'=>false,'error'=>'POST required']);
        }
        // Turn off error display for JSON responses
        ini_set('display_errors', 0);
        error_reporting(0);
        
        // Set JSON header first
        header('Content-Type: application/json; charset=utf-8');
        
        // Suppress any output that might interfere with JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        try {
            // Calculate cutoff date (2 months ago)
            $cutoffDate = date('Y-m-d', strtotime('-2 months'));
            $cutoffTimestamp = strtotime($cutoffDate);
            
            // Get all API log tables
            $db_name = DB_NAME;
            $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME LIKE 'api_logs_%' ORDER BY TABLE_NAME";
            $tables = $this->db->fetchAll($sql, ['db_name' => $db_name]);
            
            $deleted_count = 0;
            $errors = [];
            
            foreach ($tables as $table) {
                $table_name = $table['TABLE_NAME'];
                
                // Extract date from table name: api_logs_2025_01_15 -> 2025-01-15
                if (preg_match('/api_logs_(\d{4})_(\d{2})_(\d{2})/', $table_name, $matches)) {
                    $table_date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
                    $table_timestamp = strtotime($table_date);
                    
                    // Only delete tables older than 2 months
                    if ($table_timestamp < $cutoffTimestamp) {
                        // Validate table name before deletion (security check)
                        if (preg_match('/^api_logs_\d{4}_\d{2}_\d{2}$/', $table_name)) {
                            try {
                                // Use PDO connection directly for DROP TABLE (no parameters needed)
                                $drop_sql = "DROP TABLE IF EXISTS `$table_name`";
                                $this->db->getConnection()->exec($drop_sql);
                                $deleted_count++;
                            } catch (Exception $e) {
                                $errors[] = "Failed to delete $table_name: " . $e->getMessage();
                            } catch (Error $e) {
                                $errors[] = "Failed to delete $table_name: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
            
            $this->json([
                'success' => true,
                'deleted_count' => $deleted_count,
                'message' => $deleted_count > 0 
                    ? "Successfully deleted $deleted_count log table(s) older than 2 months."
                    : "No log tables older than 2 months found.",
                'errors' => $errors
            ]);
            
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Error deleting old tables: ' . $e->getMessage(),
                'deleted_count' => 0
            ]);
        } catch (Error $e) {
            $this->json([
                'success' => false,
                'message' => 'Error deleting old tables: ' . $e->getMessage(),
                'deleted_count' => 0
            ]);
        }
    }
}
?>

