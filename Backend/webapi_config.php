<?php
/**
 * Configuration API Endpoint
 * 
 * Provides configuration settings to monitoring clients
 * Endpoint: /webapi_config.php
 */

require_once 'config/ClientConfig.php';
require_once 'config.inc.php';
require_once 'html/app/services/DatabaseService.php';

// Start timing for API request logging
$start_time = microtime(true);

// Set JSON response header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Initialize database connection for logging
$config = new JConfig();
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);
$dbService = null;

if (!$mysqli->connect_error) {
    $dbService = new DatabaseService($mysqli);
}

$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    // Handle GET request (fetch configuration)
    if ($method === 'GET') {
        handleGetConfig();
    }
    // Handle POST request (update configuration - admin only)
    elseif ($method === 'POST') {
        handlePostConfig();
    }
    else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    // Calculate response time
    $response_time_ms = (microtime(true) - $start_time) * 1000;
    
    // Get HTTP status code
    $status_code = http_response_code();
    if ($status_code === false) {
        $status_code = 200; // Default to 200 if not set
    }
    
    // Log API request if database service is available
    // Disabled: daily API log table was for debugging only
    /*
    if ($dbService) {
        $log_request_type = ($method === 'GET') ? 'config' : 'config_update';
        $dbService->logApiRequest('/webapi_config.php', $method, $clientIP, $status_code, $response_time_ms, $log_request_type);
    }
    */
    
    // Close database connection if opened
    if ($mysqli && !$mysqli->connect_error) {
        $mysqli->close();
    }
}

/**
 * Handle GET request - fetch configuration
 */
function handleGetConfig() {
    // Get platform from query parameter or user agent
    $platform = $_GET['platform'] ?? detectPlatform();
    
    // Validate platform
    $validPlatforms = ['macos', 'windows', 'ubuntu', 'default'];
    if (!in_array($platform, $validPlatforms)) {
        $platform = 'default';
    }
    
    // Get configuration
    $config = ClientConfig::getConfig($platform);
    
    // Add client-specific information
    $config['client_info'] = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'timestamp' => time(),
        'request_id' => uniqid()
    ];
    
    // Log configuration request
    error_log("Configuration requested for platform: $platform, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    echo json_encode([
        'success' => true,
        'data' => $config
    ]);
}

/**
 * Handle POST request - update configuration (admin only)
 */
function handlePostConfig() {
    // Check for admin authentication (implement your auth logic here)
    if (!isAdminAuthenticated()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Admin authentication required'
        ]);
        return;
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate configuration
    $validation = ClientConfig::validateConfig($input);
    if (!$validation['valid']) {
        throw new Exception('Invalid configuration: ' . implode(', ', $validation['errors']));
    }
    
    // Here you would typically save to database or config file
    // For now, we'll just return success
    error_log("Configuration updated by admin: " . json_encode($input));
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuration updated successfully',
        'data' => $input
    ]);
}

/**
 * Detect platform from user agent
 */
function detectPlatform() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (stripos($userAgent, 'mac') !== false) {
        return 'macos';
    } elseif (stripos($userAgent, 'windows') !== false) {
        return 'windows';
    } elseif (stripos($userAgent, 'linux') !== false) {
        return 'ubuntu';
    }
    
    return 'default';
}

/**
 * Check if user is authenticated as admin
 * Implement your authentication logic here
 */
function isAdminAuthenticated() {
    // For now, return false - implement proper admin authentication
    // You might check for admin session, API key, or IP whitelist
    return false;
}
?>
