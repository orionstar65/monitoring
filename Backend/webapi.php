<?php
/**
 * Web API v2.0 - Database-Based Storage
 * Handles screenshot uploads and username updates
 * Uses DatabaseService class for metadata storage
 */

// Include configuration and database service
require_once 'config.inc.php';
require_once 'html/app/services/DatabaseService.php';

// Start timing for API request logging
$start_time = microtime(true);

// Initialize configuration
$config = new JConfig();

// Check if GD extension is available
if (!extension_loaded('gd')) {
    error_log("GD extension is not loaded. Please enable GD extension in php.ini");
    http_response_code(500);
    echo json_encode(['error' => 'GD extension is required for image processing. Please contact administrator.']);
    exit;
}

// Initialize database connection
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);

if ($mysqli->connect_error) {
    error_log("Database connection failed: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Initialize database service
$dbService = new DatabaseService($mysqli);

// Get client IP address
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

// Get monitor IP ID
$ip_id = $dbService->getMonitorIpId($clientIP);
if (!$ip_id) {
    error_log("Could not find monitor IP ID for IP: $clientIP");
    http_response_code(404);
    echo json_encode(['error' => 'Monitor not found']);
    exit;
}

// Get monitor IP information
$monitor = $dbService->getMonitorIP($clientIP);
if (!$monitor) {
    error_log("Could not find monitor IP information for IP: $clientIP");
    http_response_code(404);
    echo json_encode(['error' => 'Monitor not found']);
    exit;
}

// Get monitor IP username
$username = $monitor['username'] ?? '';

// Create or update monitor IP record
$dbService->createOrUpdateMonitorIP($clientIP, null, $username, null);

/**
 * Upload screenshot with metadata storage
 */
function uploadCacheScreenWithMetadata($com_name, $ctime, $screenfile) {
    global $config, $dbService, $mysqli;
    
    // Get client IP address
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Check for MAC address in POST parameters (optional, some platforms send it)
    $macAddress = $_POST['mac_address'] ?? null;
    if ($macAddress === '') {
        $macAddress = null;
    }
    
    // Get monitor IP ID (will use MAC address if provided, or ARP lookup as fallback)
    $ip_id = $dbService->getMonitorIpId($clientIP, $macAddress);
    if (!$ip_id) {
        error_log("Could not find monitor IP ID for IP: $clientIP, MAC: " . ($macAddress ?? 'null'));
        return false;
    }
    
    // Get device-based paths for file storage (prefer MAC/monitor id; fallback to IP)
    $ip_paths = getIPBasedPaths($ip_id, $clientIP, $macAddress);
    
    // Create directories if they don't exist
    foreach ($ip_paths as $path) {
        if (!file_exists($path)) {
            @mkdir($path, 0755, true);
        }
    }
    
    // Create date-based subdirectory (no time-based subdirectories)
    $date_dir = date('Y-m-d', strtotime($ctime));
    $screens_date_path = $ip_paths['screens'] . $date_dir . '/';
    $thumbnails_date_path = $ip_paths['thumbnails'] . $date_dir . '/';
    
    if (!file_exists($screens_date_path)) {
        @mkdir($screens_date_path, 0755, true);
    }
    if (!file_exists($thumbnails_date_path)) {
        @mkdir($thumbnails_date_path, 0755, true);
    }
    
    // Generate filename with IP address and datetime
    $datetime = date('Y-m-d_H.i.s', strtotime($ctime));
    $ip_clean = str_replace('.', '_', $clientIP); // Replace dots with underscores for filename safety
    $img_name = $ip_clean . '_' . $datetime . '.jpg';
    $file_location = $screens_date_path . $img_name;
    
    // Handle file upload
    $imagefile_tmp = $_FILES['imagefile']['tmp_name'] ?? null;
    if (!$imagefile_tmp || !is_uploaded_file($imagefile_tmp)) {
        error_log("No valid uploaded file found");
        return false;
    }
    
    // Move uploaded file
    if (move_uploaded_file($imagefile_tmp, $file_location)) {
        // Get file information
        $file_size = filesize($file_location);
        $image_info = getimagesize($file_location);
        $imagewidth = $image_info[0] ?? 0;
        $imageheight = $image_info[1] ?? 0;
        
        // Determine file extension from uploaded file
        $original_name = $_FILES['imagefile']['name'] ?? '';
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (empty($file_ext)) {
            $file_ext = 'jpg'; // Default fallback
        }
        
        // Update filename with correct extension
        $img_name = $ip_clean . '_' . $datetime . '.' . $file_ext;
        $file_location = $screens_date_path . $img_name;
        
        // Rename the file if extension changed
        if ($file_ext !== 'jpg') {
            $old_location = $screens_date_path . $ip_clean . '_' . $datetime . '.jpg';
            if (file_exists($old_location)) {
                rename($old_location, $file_location);
            }
        }
        
        // Create thumbnail
        $thumb_file_location = $thumbnails_date_path . $img_name;
        createThumbnail($file_location, $thumb_file_location, 200, 150);
        
        // Store metadata in database
        $capture_datetime = date('Y-m-d H:i:s', strtotime($ctime));
        $result = $dbService->addScreenshotMetadata(
            $ip_id, 
            $img_name, 
            $file_location, 
            $thumb_file_location, 
            $capture_datetime, 
            $file_size, 
            $imagewidth, 
            $imageheight, 
            $file_ext
        );
        
        if ($result) {
            // Update monitor activity (pass MAC address if available)
            $dbService->updateMonitorActivity($clientIP, 'monitor', $macAddress);
            
            // Return success response
            echo json_encode([
                'status' => 'success',
                'message' => 'Screenshot uploaded successfully',
                'data' => [
                    'filename' => $img_name,
                    'file_size' => $file_size,
                    'image_width' => $imagewidth,
                    'image_height' => $imageheight,
                    'interval' => 60 // Return interval for next screenshot
                ]
            ]);
            return true;
        } else {
            error_log("Failed to store screenshot metadata in database");
            return false;
        }
    } else {
        error_log("Failed to move uploaded file to destination");
        return false;
    }
}

/**
 * Update username for a monitor IP
 */
function updateUsername($username) {
    global $dbService;
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Update username in database
    $result = $dbService->createOrUpdateMonitorIP($clientIP, null, $username, null);
    
    if ($result) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Username updated successfully'
        ]);
        return true;
    } else {
        error_log("Failed to update username for IP: $clientIP");
        return false;
    }
}

/**
 * Create thumbnail from image
 */
function createThumbnail($source_path, $thumb_path, $max_width, $max_height) {
    $source_info = getimagesize($source_path);
    if (!$source_info) {
        return false;
    }
    
    $source_width = $source_info[0];
    $source_height = $source_info[1];
    $source_type = $source_info[2];
    
    // Calculate thumbnail dimensions
    $ratio = min($max_width / $source_width, $max_height / $source_height);
    $thumb_width = round($source_width * $ratio);
    $thumb_height = round($source_height * $ratio);
    
    // Create source image
    switch ($source_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        default:
            error_log("Unsupported image type: $source_type");
            return false;
    }
    
    if (!$source_image) {
        error_log("Failed to create image resource from: $source_path (type: $source_type)");
        return false;
    }
    
    // Create thumbnail image
    $thumb_image = imagecreatetruecolor($thumb_width, $thumb_height);
    
    // Preserve transparency for PNG and GIF
    if ($source_type == IMAGETYPE_PNG || $source_type == IMAGETYPE_GIF) {
        imagealphablending($thumb_image, false);
        imagesavealpha($thumb_image, true);
        $transparent = imagecolorallocatealpha($thumb_image, 255, 255, 255, 127);
        imagefilledrectangle($thumb_image, 0, 0, $thumb_width, $thumb_height, $transparent);
    }
    
    // Resize image
    imagecopyresampled($thumb_image, $source_image, 0, 0, 0, 0, $thumb_width, $thumb_height, $source_width, $source_height);
    
    // Save thumbnail
    $result = imagejpeg($thumb_image, $thumb_path, 85);
    
    // Clean up
    imagedestroy($source_image);
    imagedestroy($thumb_image);
    
    return $result;
}

// Handle different request types
$request_type_param = $_GET['type'] ?? '';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';
$log_request_type = null; // Will be set based on the request type

switch ($request_type_param) {
    case 'upload':
        $log_request_type = 'screenshot';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagefile'])) {
            $com_name = $_POST['com_name'] ?? '';
            $ctime = $_POST['ctime'] ?? date('Y-m-d H:i:s');
            
            $result = uploadCacheScreenWithMetadata($com_name, $ctime, $_FILES['imagefile']);
            if (!$result) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload screenshot']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid upload request']);
        }
        break;
        
    case 'username':
        $log_request_type = 'username';
        // Username updates are disabled - only allowed through admin edit page
        http_response_code(403);
        echo json_encode(['error' => 'Username updates are disabled. Please contact administrator.']);
        break;
        
    default:
        $log_request_type = $request_type_param ?: 'unknown';
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request type']);
        break;
}

// Calculate response time
$response_time_ms = (microtime(true) - $start_time) * 1000;

// Get HTTP status code
$status_code = http_response_code();
if ($status_code === false) {
    $status_code = 200; // Default to 200 if not set
}

// Log API request with request type
$dbService->logApiRequest('/webapi.php', $method, $clientIP, $status_code, $response_time_ms, $log_request_type);

// Close database connection
$mysqli->close();
?> 