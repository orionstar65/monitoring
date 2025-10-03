<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Export extends Base {
    private $exportDir;
    private $progressFile;
    private $currentProgress = 0;
    private $totalSteps = 0;

    public function __construct() {
        parent::__construct();
    }

    public function index() {
        $this->view('view/export');
    }
    
    public function api_test() {
        header('Content-Type: application/json');
        
        try {
            // Test database connection
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }
            
            // Test basic query
            $test = $this->db->fetchAll("SELECT COUNT(*) as count FROM tbl_monitor_ips");
            
            echo json_encode([
                'success' => true,
                'message' => 'Export controller is working',
                'database_connected' => true,
                'device_count' => $test[0]['count'] ?? 0,
                'basepath' => BASEPATH
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function api_get_devices() {
        header('Content-Type: application/json');
        
        try {
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }
            
            $devices = $this->db->fetchAll("SELECT * FROM tbl_monitor_ips ORDER BY username ASC, ip_address ASC");
            
            echo json_encode([
                'success' => true,
                'devices' => $devices,
                'total_devices' => count($devices)
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function api_export_device() {
        // Disable error reporting to prevent HTML error output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        header('Content-Type: application/json');
        
        try {
            $device_id = $_POST['device_id'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $export_dir = $_POST['export_dir'] ?? null;
            
            if (!$device_id || !$start_date || !$end_date || !$export_dir) {
                throw new Exception('Missing required parameters');
            }
            
            // Get device info
            $device = $this->db->fetchAll("SELECT * FROM tbl_monitor_ips WHERE id = :device_id", [':device_id' => $device_id]);
            if (empty($device)) {
                throw new Exception('Device not found');
            }
            $device = $device[0];
            
            // Set export directory
            $this->exportDir = BASEPATH . "exports/{$export_dir}";
            $this->progressFile = $this->exportDir . "/progress.txt";
            
            // Export this device
            $this->exportDeviceData($device, $start_date, $end_date);
            
            echo json_encode([
                'success' => true,
                'message' => "Device {$device['username']} exported successfully",
                'device_id' => $device_id,
                'username' => $device['username'],
                'ip_address' => $device['ip_address']
            ]);
            
        } catch (Exception $e) {
            http_response_code(200); // Always return 200 for JSON responses
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'device_id' => $device_id ?? null,
                'username' => $device['username'] ?? 'Unknown',
                'ip_address' => $device['ip_address'] ?? 'Unknown'
            ]);
        } catch (Error $e) {
            // Catch PHP fatal errors
            http_response_code(200);
            echo json_encode([
                'success' => false,
                'message' => 'PHP Error: ' . $e->getMessage(),
                'device_id' => $device_id ?? null,
                'username' => $device['username'] ?? 'Unknown',
                'ip_address' => $device['ip_address'] ?? 'Unknown'
            ]);
        }
    }

    public function api_start_export() {
        header('Content-Type: application/json');
        
        try {
            $start_date = $_POST['start_date'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            
            if (!$start_date || !$end_date) {
                throw new Exception('Start date and end date are required');
            }
            
            // Validate date range (max 1 month)
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            
            if ($interval->days > 31) {
                throw new Exception('Date range cannot exceed 1 month (31 days)');
            }
            
            // Create export directory with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $this->exportDir = BASEPATH . "exports/export_{$timestamp}";
            
            // Check if BASEPATH is valid
            if (!defined('BASEPATH') || !BASEPATH) {
                throw new Exception("BASEPATH constant not defined");
            }
            
            $exportsDir = BASEPATH . "exports";
            if (!file_exists($exportsDir)) {
                if (!mkdir($exportsDir, 0755, true)) {
                    throw new Exception("Failed to create exports directory: {$exportsDir}");
                }
            }
            
            if (!mkdir($this->exportDir, 0755, true)) {
                throw new Exception("Failed to create export directory: {$this->exportDir}");
            }
            
            // Create progress file
            $this->progressFile = $this->exportDir . "/progress.txt";
            if (!file_put_contents($this->progressFile, "Export started at " . date('Y-m-d H:i:s') . "\n")) {
                throw new Exception("Failed to create progress file: {$this->progressFile}");
            }
            
            // Get list of devices to export
            $devices = $this->db->fetchAll("SELECT * FROM tbl_monitor_ips ORDER BY username ASC, ip_address ASC");
            
            echo json_encode([
                'success' => true,
                'message' => 'Export initialized successfully',
                'export_dir' => "export_{$timestamp}",
                'progress_file' => "progress.txt",
                'devices' => $devices,
                'total_devices' => count($devices)
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function api_progress() {
        // Disable error reporting to prevent HTML error output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        header('Content-Type: application/json');
        
        $export_dir = $_GET['export_dir'] ?? null;
        $progress_file = $_GET['progress_file'] ?? 'progress.txt';
        
        if (!$export_dir) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Export directory required']);
            return;
        }
        
        $progressPath = BASEPATH . "exports/{$export_dir}/{$progress_file}";
        
        if (!file_exists($progressPath)) {
            echo json_encode(['success' => false, 'message' => 'Progress file not found']);
            return;
        }
        
        $progress = file_get_contents($progressPath);
        $lines = explode("\n", trim($progress));
        $latestLine = end($lines);
        $isComplete = strpos($latestLine, 'Export completed successfully!') !== false;
        
        // Calculate progress percentage based on log content
        $progressPercentage = 0;
        if (!$isComplete && count($lines) > 1) {
            // Estimate progress based on log entries
            $deviceCount = 0;
            $completedDevices = 0;
            
            foreach ($lines as $line) {
                if (strpos($line, 'Found') !== false && strpos($line, 'devices to export') !== false) {
                    preg_match('/Found (\d+) devices/', $line, $matches);
                    if (isset($matches[1])) {
                        $deviceCount = (int)$matches[1];
                    }
                }
                if (strpos($line, 'Device export completed for') !== false) {
                    $completedDevices++;
                }
            }
            
            if ($deviceCount > 0) {
                $progressPercentage = min(($completedDevices / $deviceCount) * 100, 95); // Max 95% until truly complete
            }
        } elseif ($isComplete) {
            $progressPercentage = 100;
        }
        
        echo json_encode([
            'success' => true,
            'progress' => $progress,
            'latest_line' => $latestLine,
            'is_complete' => $isComplete,
            'total_lines' => count($lines),
            'progress_percentage' => $progressPercentage
        ]);
    }

    private function startBackgroundExport($start_date, $end_date) {
        // Run export synchronously with real-time progress logging
        $this->performExport($start_date, $end_date);
    }

    private function performExport($start_date, $end_date) {
        // Extend timeout for long-running export
        set_time_limit(0);
        ini_set('max_execution_time', 0);
        
        try {
            $this->logProgress("Initializing export...");
            
            // Test database connection first
            if (!$this->db) {
                throw new Exception("Database connection not available");
            }
            
            // Get all devices
            $devices = $this->db->fetchAll("SELECT * FROM tbl_monitor_ips ORDER BY username ASC, ip_address ASC");
            $this->totalSteps = count($devices) * 4; // 4 steps per device (3 logs + screenshots)
            
            $this->logProgress("Found " . count($devices) . " devices to export");
            
            foreach ($devices as $device) {
                $this->exportDeviceData($device, $start_date, $end_date);
                $this->currentProgress++;
                $this->logProgress("Completed device: {$device['username']} ({$device['ip_address']})");
                
                // Small delay to allow progress polling to catch up
                usleep(2000000); // 2 second delay
            }
            
            $this->logProgress("Export completed successfully!");
            
        } catch (Exception $e) {
            $this->logProgress("ERROR: " . $e->getMessage());
        }
    }

    private function exportDeviceData($device, $start_date, $end_date) {
        $username = $device['username'] ?: 'Unknown';
        $ip = str_replace('.', '_', $device['ip_address']);
        $deviceId = $device['id'];
        
        // Create device folder structure
        $deviceFolder = $this->exportDir . "/{$username}_{$ip}";
        $this->logProgress("Creating folder structure for {$username}...");
        
        if (!file_exists($deviceFolder)) {
            mkdir($deviceFolder, 0755, true);
            $this->logProgress("Created main device folder: {$username}_{$ip}");
        }
        
        // Create subfolders
        $screenshotsFolder = $deviceFolder . "/screenshots";
        $keylogFolder = $deviceFolder . "/keylog";
        $usbFolder = $deviceFolder . "/usb log";
        $browserFolder = $deviceFolder . "/browser history";
        
        if (!file_exists($screenshotsFolder)) {
            mkdir($screenshotsFolder, 0755, true);
            $this->logProgress("Created screenshots folder");
        }
        if (!file_exists($keylogFolder)) {
            mkdir($keylogFolder, 0755, true);
            $this->logProgress("Created keylog folder");
        }
        if (!file_exists($usbFolder)) {
            mkdir($usbFolder, 0755, true);
            $this->logProgress("Created USB log folder");
        }
        if (!file_exists($browserFolder)) {
            mkdir($browserFolder, 0755, true);
            $this->logProgress("Created browser history folder");
        }
        
        // Export browser history logs
        $this->logProgress("Exporting browser history for {$username}...");
        try {
            $this->exportBrowserLogs($deviceId, $username, $ip, $start_date, $end_date, $browserFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ Browser history export failed: " . $e->getMessage());
        }
        usleep(1000000); // 1 second delay
        
        // Export key logs
        $this->logProgress("Exporting key logs for {$username}...");
        try {
            $this->exportKeyLogs($deviceId, $username, $ip, $start_date, $end_date, $keylogFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ Key logs export failed: " . $e->getMessage());
        }
        usleep(1000000); // 1 second delay
        
        // Export USB logs
        $this->logProgress("Exporting USB logs for {$username}...");
        try {
            $this->exportUsbLogs($deviceId, $username, $ip, $start_date, $end_date, $usbFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ USB logs export failed: " . $e->getMessage());
        }
        usleep(1000000); // 1 second delay
        
        // Export screenshots
        $this->logProgress("Exporting screenshots for {$username}...");
        try {
            $this->exportScreenshots($deviceId, $username, $ip, $start_date, $end_date, $screenshotsFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ Screenshots export failed: " . $e->getMessage());
        }
        usleep(1000000); // 1 second delay
        
        $this->logProgress("✓ Device export completed for {$username} ({$ip})");
    }

    private function exportBrowserLogs($deviceId, $username, $ip, $start_date, $end_date, $folder) {
        $sql = "SELECT * FROM browser_history_logs 
                WHERE monitor_ip_id = :device_id 
                AND created_at >= :start_date 
                AND created_at <= :end_date 
                ORDER BY created_at ASC";
        
        $params = [
            'device_id' => $deviceId,
            'start_date' => $start_date,
            'end_date' => $end_date . ' 23:59:59'
        ];
        
        $logs = $this->db->query($sql, $params)->fetchAll();
        
        if (empty($logs)) {
            $this->logProgress("No browser history logs found for {$username}");
            return;
        }
        
        $filename = "{$username}_{$ip}_browser_{$start_date}_to_{$end_date}.csv";
        $filepath = $folder . "/" . $filename;
        
        $this->logProgress("Creating browser history CSV file: {$filename}");
        
        $file = fopen($filepath, 'w');
        if (!$file) {
            throw new Exception("Failed to create CSV file: {$filename}");
        }
        
        fputcsv($file, ['ID', 'URL', 'Title', 'Visit Date', 'Created At', 'IP Address']);
        
        $exported_count = 0;
        foreach ($logs as $log) {
            fputcsv($file, [
                $log['id'] ?? '',
                $log['url'] ?? '',
                $log['title'] ?? '',
                $log['visit_date'] ?? '',
                $log['created_at'] ?? '',
                $log['ip_address'] ?? ''
            ]);
            $exported_count++;
            
            // Update progress every 50 records
            if ($exported_count % 50 == 0) {
                $this->logProgress("Exported {$exported_count}/" . count($logs) . " browser history records...");
            }
        }
        
        fclose($file);
        $this->logProgress("✓ Completed browser history export: {$exported_count} records saved to {$filename}");
    }

    private function exportKeyLogs($deviceId, $username, $ip, $start_date, $end_date, $folder) {
        $sql = "SELECT * FROM key_logs 
                WHERE monitor_ip_id = :device_id 
                AND key_date >= :start_date 
                AND key_date <= :end_date 
                ORDER BY key_date ASC";
        
        $params = [
            'device_id' => $deviceId,
            'start_date' => $start_date,
            'end_date' => $end_date . ' 23:59:59'
        ];
        
        $logs = $this->db->query($sql, $params)->fetchAll();
        
        if (empty($logs)) {
            $this->logProgress("No key logs found for {$username}");
            return;
        }
        
        $filename = "{$username}_{$ip}_keylog_{$start_date}_to_{$end_date}.csv";
        $filepath = $folder . "/" . $filename;
        
        $this->logProgress("Creating key logs CSV file: {$filename}");
        
        $file = fopen($filepath, 'w');
        if (!$file) {
            throw new Exception("Failed to create CSV file: {$filename}");
        }
        
        fputcsv($file, ['ID', 'Key Pressed', 'Application', 'Key Date', 'IP Address']);
        
        $exported_count = 0;
        foreach ($logs as $log) {
            fputcsv($file, [
                $log['id'] ?? '',
                $log['key_pressed'] ?? '',
                $log['application'] ?? '',
                $log['key_date'] ?? '',
                $log['ip_address'] ?? ''
            ]);
            $exported_count++;
            
            // Update progress every 50 records
            if ($exported_count % 50 == 0) {
                $this->logProgress("Exported {$exported_count}/" . count($logs) . " key log records...");
            }
        }
        
        fclose($file);
        $this->logProgress("✓ Completed key logs export: {$exported_count} records saved to {$filename}");
    }

    private function exportUsbLogs($deviceId, $username, $ip, $start_date, $end_date, $folder) {
        $sql = "SELECT * FROM usb_device_logs 
                WHERE monitor_ip_id = :device_id 
                AND created_at >= :start_date 
                AND created_at <= :end_date 
                ORDER BY created_at ASC";
        
        $params = [
            'device_id' => $deviceId,
            'start_date' => $start_date,
            'end_date' => $end_date . ' 23:59:59'
        ];
        
        $logs = $this->db->query($sql, $params)->fetchAll();
        
        if (empty($logs)) {
            $this->logProgress("No USB logs found for {$username}");
            return;
        }
        
        $filename = "{$username}_{$ip}_usblog_{$start_date}_to_{$end_date}.csv";
        $filepath = $folder . "/" . $filename;
        
        $this->logProgress("Creating USB logs CSV file: {$filename}");
        
        $file = fopen($filepath, 'w');
        if (!$file) {
            throw new Exception("Failed to create CSV file: {$filename}");
        }
        
        fputcsv($file, ['ID', 'Device Name', 'Device Type', 'Action', 'Created At', 'IP Address']);
        
        $exported_count = 0;
        foreach ($logs as $log) {
            fputcsv($file, [
                $log['id'] ?? '',
                $log['device_name'] ?? '',
                $log['device_type'] ?? '',
                $log['action'] ?? '',
                $log['created_at'] ?? '',
                $log['ip_address'] ?? ''
            ]);
            $exported_count++;
            
            // Update progress every 50 records
            if ($exported_count % 50 == 0) {
                $this->logProgress("Exported {$exported_count}/" . count($logs) . " USB log records...");
            }
        }
        
        fclose($file);
        $this->logProgress("✓ Completed USB logs export: {$exported_count} records saved to {$filename}");
    }

    private function exportScreenshots($deviceId, $username, $ip, $start_date, $end_date, $folder) {
        $sql = "SELECT * FROM screenshot_metadata 
                WHERE monitor_ip_id = :device_id 
                AND capture_datetime >= :start_date 
                AND capture_datetime <= :end_date 
                ORDER BY capture_datetime ASC";
        
        $params = [
            'device_id' => $deviceId,
            'start_date' => $start_date,
            'end_date' => $end_date . ' 23:59:59'
        ];
        
        $screenshots = $this->db->query($sql, $params)->fetchAll();
        
        if (empty($screenshots)) {
            $this->logProgress("No screenshots found for {$username}");
            return;
        }
        
        $this->logProgress("Starting screenshot export for {$username}...");
        $this->logProgress("Found " . count($screenshots) . " screenshots to export");
        
        // Debug: Show first screenshot's file path
        if (!empty($screenshots)) {
            $firstScreenshot = $screenshots[0];
            $this->logProgress("Debug - First screenshot file_path: " . $firstScreenshot['file_path']);
            $this->logProgress("Debug - First screenshot filename: " . $firstScreenshot['filename']);
        }
        
        $dateFolders = [];
        $exported_count = 0;
        $total_screenshots = count($screenshots);
        
        foreach ($screenshots as $screenshot) {
            $captureDate = date('Y-m-d', strtotime($screenshot['capture_datetime']));
            
            // Create date folder if not exists
            if (!isset($dateFolders[$captureDate])) {
                $dateFolder = $folder . "/" . $captureDate;
                if (!file_exists($dateFolder)) {
                    mkdir($dateFolder, 0755, true);
                    $this->logProgress("Created date folder: {$captureDate}");
                }
                $dateFolders[$captureDate] = $dateFolder; // Store the actual path
            } else {
                $dateFolder = $dateFolders[$captureDate]; // Get the stored path
            }
            
            // Copy screenshot file
            $filename = basename($screenshot['filename']);
            
            // Try multiple possible source paths
            $filePath = $screenshot['file_path'];
            
            // Remove common prefixes that might cause double paths
            $cleanPath = $filePath;
            $cleanPath = ltrim($cleanPath, './');  // Remove leading ./
            $cleanPath = ltrim($cleanPath, 'html/');  // Remove leading html/ since BASEPATH already includes it
            
            // Debug: Show path transformation
            if ($exported_count < 3) { // Only show first few for debugging
                $this->logProgress("Debug - Original path: {$filePath}");
                $this->logProgress("Debug - Clean path: {$cleanPath}");
                $this->logProgress("Debug - BASEPATH: " . BASEPATH);
            }
            
            $possiblePaths = [
                BASEPATH . $cleanPath,  // Clean path with BASEPATH
                BASEPATH . $filePath,  // Original path with BASEPATH
                BASEPATH . ltrim($filePath, './'),  // Remove only ./
                BASEPATH . str_replace('./', '', $filePath),  // Remove ./
                BASEPATH . str_replace('.\/', '', $filePath),  // Remove .\/
                // Absolute paths
                $filePath,
                ltrim($filePath, './'),
                str_replace('./', '', $filePath)
            ];
            
            $sourcePath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $sourcePath = $path;
                    break;
                }
            }
            
            if ($sourcePath) {
                $destinationPath = $dateFolder . "/" . $filename;
                if (copy($sourcePath, $destinationPath)) {
                    $exported_count++;
                    $this->logProgress("✓ Copied screenshot: {$filename}");
                } else {
                    $this->logProgress("⚠ Failed to copy screenshot: {$filename} (copy failed)");
                }
            } else {
                $this->logProgress("⚠ Source file not found: {$filename}");
                $this->logProgress("   Tried paths: " . implode(', ', $possiblePaths));
            }
            
            // Update progress every 10 screenshots
            if ($exported_count % 10 == 0 && $exported_count > 0) {
                $this->logProgress("Copied {$exported_count}/{$total_screenshots} screenshots...");
            }
        }
        
        $this->logProgress("✓ Completed screenshot export: {$exported_count}/{$total_screenshots} screenshots copied for {$username}");
    }

    private function logProgress($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";
        file_put_contents($this->progressFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Force output to browser if possible (for real-time feedback)
        if (ob_get_level()) {
            ob_flush();
            flush();
        }
    }
}
?>
