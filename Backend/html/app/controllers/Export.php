<?php
require_once BASEPATH . 'app/controllers/Base.php';

class Export extends Base {
    private $exportDir;
    private $progressFile;
    private $currentProgress = 0;
    private $totalSteps = 0;
    private $ffmpegPath;

    public function __construct() {
        parent::__construct();
        $this->requireAdmin();
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
            
            // Test FFmpeg availability
            try {
                $ffmpegTest = $this->testFFmpegEnvironment();
            } catch (Exception $e) {
                $ffmpegTest = ['error' => $e->getMessage()];
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Export controller is working',
                'database_connected' => true,
                'device_count' => $test[0]['count'] ?? 0,
                'basepath' => BASEPATH,
                'ffmpeg_test' => $ffmpegTest
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function testFFmpegEnvironment() {
        $result = [
            'php_version' => PHP_VERSION,
            'php_sapi' => PHP_SAPI,
            'php_os_family' => PHP_OS_FAMILY,
            'current_user' => get_current_user(),
            'path' => getenv('PATH'),
            'ffmpeg_paths_tested' => [],
            'working_path' => null,
            'recommendations' => []
        ];
        
        $ffmpegPaths = [
            // Windows paths (check first for Windows systems)
            'ffmpeg',  // System PATH (Windows)
            'ffmpeg.exe',  // Windows executable
            'C:\\ffmpeg\\bin\\ffmpeg.exe',  // Common Windows installation
            
            // Unix/Linux/macOS paths
            '/usr/local/bin/ffmpeg_xampp',  // XAMPP-compatible wrapper
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
            '/Applications/XAMPP/bin/ffmpeg',
            '/usr/bin/ffmpeg',
            '/bin/ffmpeg'
        ];
        
        foreach ($ffmpegPaths as $path) {
            exec("{$path} -version 2>&1", $output, $returnCode);
            $testResult = [
                'path' => $path,
                'return_code' => $returnCode,
                'available' => $returnCode === 0,
                'output' => array_slice($output, 0, 3)
            ];
            
            $result['ffmpeg_paths_tested'][] = $testResult;
            
            if ($returnCode === 0 && !$result['working_path']) {
                $result['working_path'] = $path;
            }
        }
        
        // Add recommendations based on OS
        if (PHP_OS_FAMILY === 'Windows') {
            $result['recommendations'] = [
                '1. Download FFmpeg from https://ffmpeg.org/download.html',
                '2. Extract to C:\\ffmpeg\\',
                '3. Add C:\\ffmpeg\\bin to your system PATH',
                '4. Or copy ffmpeg.exe to your XAMPP bin directory (D:\\xampp\\bin\\)',
                '5. Restart Apache after installation'
            ];
        } else {
            $result['recommendations'] = [
                '1. Install FFmpeg via package manager (brew, apt, yum, etc.)',
                '2. Or download from https://ffmpeg.org/download.html',
                '3. Ensure FFmpeg is in your system PATH'
            ];
        }
        
        return $result;
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
            if(!is_string($export_dir)||!preg_match('/^export_\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2}$/',$export_dir)) throw new Exception('Invalid export directory');
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
            $device_ids = $_POST['device_ids'] ?? null;
            
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
            
            // Get list of devices to export based on selection
            if ($device_ids) {
                $device_ids_array = explode(',', $device_ids);
                $placeholders = str_repeat('?,', count($device_ids_array) - 1) . '?';
                $sql = "SELECT * FROM tbl_monitor_ips WHERE id IN ({$placeholders}) ORDER BY username ASC, ip_address ASC";
                $devices = $this->db->fetchAll($sql, $device_ids_array);
                
                // Log selected devices
                file_put_contents($this->progressFile, "Selected devices: " . implode(', ', $device_ids_array) . "\n", FILE_APPEND);
            } else {
                $devices = $this->db->fetchAll("SELECT * FROM tbl_monitor_ips ORDER BY username ASC, ip_address ASC");
                file_put_contents($this->progressFile, "All devices selected\n", FILE_APPEND);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Export initialized successfully',
                'export_dir' => "export_{$timestamp}",
                'progress_file' => "progress.txt",
                'devices' => $devices,
                'total_devices' => count($devices),
                'selected_devices' => $device_ids ? explode(',', $device_ids) : []
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /** Download a completed export only through authenticated administration. */
    public function api_download() {
        $name=(string)($_GET['export_dir']??'');
        if(!preg_match('/^export_\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2}$/',$name)){
            http_response_code(400);exit('Invalid export');
        }
        $base=realpath(BASEPATH.'exports');
        $root=realpath(BASEPATH.'exports/'.$name);
        if(!$base||!$root||!str_starts_with($root,$base.DIRECTORY_SEPARATOR)||!is_dir($root)){
            http_response_code(404);exit('Export not found');
        }
        if(!class_exists('ZipArchive')){http_response_code(503);exit('PHP zip extension required');}
        $zipPath=$base.'/'.$name.'.zip';
        $zip=new ZipArchive();
        if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){
            http_response_code(500);exit('Unable to build ZIP');
        }
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file){
            if(!$file->isFile()||$file->isLink())continue;
            $resolved=realpath($file->getPathname());
            if(!$resolved||!str_starts_with($resolved,$root.DIRECTORY_SEPARATOR))continue;
            $zip->addFile($resolved,substr($resolved,strlen($root)+1));
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.$name.'.zip"');
        header('Content-Length: '.filesize($zipPath));
        header('Cache-Control: no-store');
        readfile($zipPath);
    }

    public function api_progress() {
        // Disable error reporting to prevent HTML error output
        error_reporting(0);
        ini_set('display_errors', 0);
        
        header('Content-Type: application/json');
        
        $export_dir = $_GET['export_dir'] ?? null;
        $progress_file = 'progress.txt';
        if(!is_string($export_dir)||!preg_match('/^export_\\d{4}-\\d{2}-\\d{2}_\\d{2}-\\d{2}-\\d{2}$/',$export_dir)){
            http_response_code(400);echo json_encode(['error'=>'Invalid export directory']);return;
        }
        
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
                // No artificial delay.
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
        
        // Sanitize username for folder names - remove spaces and special characters
        $sanitizedUsername = $this->sanitizeFolderName($username);
        
        // Create device folder structure
        $deviceFolder = $this->exportDir . "/{$sanitizedUsername}_{$ip}";
        $this->logProgress("Creating folder structure for {$username}...");
        
        if (!file_exists($deviceFolder)) {
            mkdir($deviceFolder, 0755, true);
            $this->logProgress("Created main device folder: {$sanitizedUsername}_{$ip}");
        }
        
        // Create subfolders (no spaces in folder names)
        $screenshotsFolder = $deviceFolder . "/screenshots";
        $keylogFolder = $deviceFolder . "/keylog";
        $usbFolder = $deviceFolder . "/usb_log";
        $browserFolder = $deviceFolder . "/browser_history";
        
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
            $this->exportBrowserLogs($deviceId, $sanitizedUsername, $ip, $start_date, $end_date, $browserFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ Browser history export failed: " . $e->getMessage());
        }
        // No artificial delay.
        
        // Export key logs
        $this->logProgress("Exporting key logs for {$username}...");
        try {
            $this->exportKeyLogs($deviceId, $sanitizedUsername, $ip, $start_date, $end_date, $keylogFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ Key logs export failed: " . $e->getMessage());
        }
        // No artificial delay.
        
        // Export USB logs
        $this->logProgress("Exporting USB logs for {$username}...");
        try {
            $this->exportUsbLogs($deviceId, $sanitizedUsername, $ip, $start_date, $end_date, $usbFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ USB logs export failed: " . $e->getMessage());
        }
        // No artificial delay.
        
        // Export screenshots
        $this->logProgress("Exporting screenshots for {$username}...");
        try {
            $this->exportScreenshots($deviceId, $sanitizedUsername, $ip, $start_date, $end_date, $screenshotsFolder);
        } catch (Exception $e) {
            $this->logProgress("⚠ Screenshots export failed: " . $e->getMessage());
        }
        // No artificial delay.
        
        $this->logProgress("✓ Device export completed for {$username} ({$ip})");
    }
    
    private function sanitizeFolderName($name) {
        // Remove or replace problematic characters for folder names
        $sanitized = $name;
        
        // Replace spaces with underscores
        $sanitized = str_replace(' ', '_', $sanitized);
        
        // Remove other problematic characters
        $sanitized = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sanitized);
        
        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        
        // Remove leading/trailing underscores
        $sanitized = trim($sanitized, '_');
        
        // Ensure it's not empty
        if (empty($sanitized)) {
            $sanitized = 'Unknown';
        }
        
        return $sanitized;
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
        
        // Group screenshots by date
        $screenshotsByDate = [];
        foreach ($screenshots as $screenshot) {
            $captureDate = date('Y-m-d', strtotime($screenshot['capture_datetime']));
            $screenshotsByDate[$captureDate][] = $screenshot;
        }
        
        $this->logProgress("Creating video slideshows for " . count($screenshotsByDate) . " dates");
        
        $totalVideosCreated = 0;
        foreach ($screenshotsByDate as $date => $dateScreenshots) {
            $count = count($dateScreenshots);
            $this->logProgress("Processing date: {$date} ({$count} screenshots)");
            
            // Create date folder
            $dateFolder = $folder . "/" . $date;
            if (!file_exists($dateFolder)) {
                mkdir($dateFolder, 0755, true);
            }
            
            // Create video slideshow for this date
            $videoPath = $this->createVideoSlideshow($dateScreenshots, $dateFolder, $username, $ip, $date);
            
            if ($videoPath) {
                $totalVideosCreated++;
                $this->logProgress("✓ Created video: " . basename($videoPath));
            } else {
                $this->logProgress("⚠ Failed to create video for date: {$date}");
            }
        }
        
        $this->logProgress("✓ Completed screenshot export: {$totalVideosCreated} video slideshows created for {$username}");
    }
    
    private function createVideoSlideshow($screenshots, $outputFolder, $username, $ip, $date) {
        try {
            // Check if FFmpeg is available
            if (!$this->isFFmpegAvailable()) {
                $this->logProgress("⚠ FFmpeg not available, skipping video creation");
                return $this->fallbackToImageCopy($screenshots, $outputFolder, $username, $ip, $date);
            }
            
            $this->logProgress("Creating video slideshow for {$date}...");
            
            $processedCount = 0;
            $totalScreenshots = count($screenshots);
            
            // Copy screenshots directly to the output folder with sequential names
            foreach ($screenshots as $index => $screenshot) {
                $sourcePath = $this->resolveScreenshotPath($screenshot);
                if (!$sourcePath) {
                    $this->logProgress("⚠ Skipping screenshot: " . $screenshot['filename']);
                    continue;
                }
                
                // Create sequential filename for FFmpeg
                $tempFilename = sprintf("img_%04d.jpg", $index);
                $tempPath = $outputFolder . "/" . $tempFilename;
                
                // Copy to output folder
                if (copy($sourcePath, $tempPath)) {
                    $processedCount++;
                    
                    // Update progress every 50 images
                    if ($processedCount % 50 == 0) {
                        $this->logProgress("Copied {$processedCount}/{$totalScreenshots} images to output folder...");
                    }
                }
            }
            
            if ($processedCount == 0) {
                $this->logProgress("⚠ No valid screenshots found for video creation");
                return false;
            }
            
            $this->logProgress("Found {$processedCount} images ready for video creation");
            $this->logProgress("Creating video from {$processedCount} images...");
            
            // Create video filename
            $videoFilename = "{$username}_{$ip}_screenshots_{$date}.mp4";
            $videoPath = $outputFolder . "/" . $videoFilename;
            
            // Simple FFmpeg command - no complex escaping
            $ffmpegCmd = escapeshellarg($this->ffmpegPath) . " -y -framerate 1 -i " . escapeshellarg($outputFolder . '/img_%04d.jpg') . " -c:v libx264 -pix_fmt yuv420p -crf 28 -vf scale=1280:720 " . escapeshellarg($videoPath) . " 2>&1";
            
            $this->logProgress("Running FFmpeg command...");
            $this->logProgress("Command: " . $ffmpegCmd);
            
            exec($ffmpegCmd, $output, $returnCode);
            
            $this->logProgress("FFmpeg return code: {$returnCode}");
            if (!empty($output)) {
                $this->logProgress("FFmpeg output: " . implode("\n", $output));
            }
            
            // Clean up individual image files after video creation
            $this->cleanupImageFiles($outputFolder);
            
            if ($returnCode === 0 && file_exists($videoPath)) {
                $fileSize = $this->formatFileSize(filesize($videoPath));
                $this->logProgress("✓ Video created successfully: {$videoFilename} ({$fileSize})");
                return $videoPath;
            } else {
                $this->logProgress("⚠ FFmpeg failed with return code: {$returnCode}");
                return false;
            }
            
        } catch (Exception $e) {
            $this->logProgress("⚠ Video creation failed: " . $e->getMessage());
            // Clean up any remaining image files
            $this->cleanupImageFiles($outputFolder);
            return false;
        }
    }
    
    private function isFFmpegAvailable() {
        // Try multiple possible FFmpeg paths
        $ffmpegPaths = [
            // Windows paths (check first for Windows systems)
            'ffmpeg',  // System PATH (Windows)
            'ffmpeg.exe',  // Windows executable
            'C:\\ffmpeg\\bin\\ffmpeg.exe',  // Common Windows installation
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',  // Program Files
            'C:\\Program Files (x86)\\ffmpeg\\bin\\ffmpeg.exe',  // Program Files x86
            'D:\\ffmpeg\\bin\\ffmpeg.exe',  // D drive installation
            'D:\\xampp\\bin\\ffmpeg.exe',  // XAMPP bin directory
            'D:\\xampp\\bin\\ffmpeg',  // XAMPP bin directory (no extension)
            
            // Unix/Linux/macOS paths
            '/usr/local/bin/ffmpeg_xampp',  // XAMPP-compatible wrapper (preferred)
            '/usr/local/bin/ffmpeg',  // Homebrew
            '/opt/homebrew/bin/ffmpeg',  // Apple Silicon Homebrew
            '/Applications/XAMPP/bin/ffmpeg',  // XAMPP
            '/usr/bin/ffmpeg',  // System default
            '/bin/ffmpeg'  // Alternative system location
        ];
        
        foreach ($ffmpegPaths as $path) {
            // On Windows, try both with and without quotes for paths with spaces
            if (PHP_OS_FAMILY === 'Windows') {
                $testPaths = [$path];
                if (strpos($path, ' ') !== false) {
                    $testPaths[] = '"' . $path . '"';
                }
            } else {
                $testPaths = [$path];
            }
            
            foreach ($testPaths as $testPath) {
                exec("{$testPath} -version 2>&1", $output, $returnCode);
                
                if ($returnCode === 0) {
                    $this->logProgress("✓ FFmpeg is available at: {$testPath}");
                    $this->ffmpegPath = $testPath;  // Store the working path
                    return true;
                }
            }
        }
        
        $this->logProgress("⚠ FFmpeg is not available");
        
        // Provide helpful debugging information
        if (PHP_OS_FAMILY === 'Windows') {
            $this->logProgress("Windows detected. Common FFmpeg installation paths:");
            $this->logProgress("- C:\\ffmpeg\\bin\\ffmpeg.exe");
            $this->logProgress("- C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe");
            $this->logProgress("- Add FFmpeg bin directory to your system PATH");
            $this->logProgress("- Or place ffmpeg.exe in your XAMPP bin directory");
        }
        
        return false;
    }
    
    private function resolveScreenshotPath($screenshot) {
        $filePath = $screenshot['file_path'];
        
        // Remove common prefixes that might cause double paths
        $cleanPath = $filePath;
        $cleanPath = ltrim($cleanPath, './');
        $cleanPath = ltrim($cleanPath, 'html/');
        
        $possiblePaths = [
            BASEPATH . $cleanPath,
            BASEPATH . $filePath,
            BASEPATH . ltrim($filePath, './'),
            $filePath,
            ltrim($filePath, './'),
            str_replace('./', '', $filePath)
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }
    
    private function cleanupImageFiles($outputFolder) {
        $this->logProgress("Cleaning up individual image files...");
        $imageFiles = glob($outputFolder . "/img_*.jpg");
        $removedCount = 0;
        
        foreach ($imageFiles as $file) {
            if (is_file($file) && unlink($file)) {
                $removedCount++;
            }
        }
        
        $this->logProgress("✓ Removed {$removedCount} individual image files");
    }
    
    private function fallbackToImageCopy($screenshots, $outputFolder, $username, $ip, $date) {
        $this->logProgress("⚠ FFmpeg not available, skipping screenshot export for {$date}");
        $this->logProgress("⚠ Please install FFmpeg to export screenshots as videos");
        
        // Create a note file instead of copying images
        $noteFile = $outputFolder . "/" . "ffmpeg_required.txt";
        $noteContent = "Screenshot export skipped for {$date}\n";
        $noteContent .= "FFmpeg is required to create video slideshows from screenshots.\n";
        $noteContent .= "Please install FFmpeg and try again.\n";
        $noteContent .= "Found " . count($screenshots) . " screenshots for this date.\n";
        
        file_put_contents($noteFile, $noteContent);
        
        return $noteFile;
    }
    
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
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
