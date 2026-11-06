<?php
/**
 * Event Handler v2.0 - Database-Based Storage
 * Handles incoming client events and stores them in database instead of files
 * Uses DatabaseService class for all database operations
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Include configuration and database service
require_once 'config.inc.php';
require_once __DIR__.'/services/ClientIdentity.php';
requireSecureRequest();
require_once 'html/app/services/DatabaseService.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start timing for API request logging
$start_time = microtime(true);

$config = new JConfig();

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
// Record every request, including validation/auth failures that exit early.
// Only route, request type, IP, status and elapsed time are retained.
$loggedEvent='Unknown';
register_shutdown_function(static function() use ($dbService,$start_time,&$loggedEvent):void {
    try{
        $code=http_response_code();
        $dbService->logApiRequest('/eventhandler.php',
            (string)($_SERVER['REQUEST_METHOD']??'GET'),
            (string)($_SERVER['REMOTE_ADDR']??''),
            is_int($code)&&$code>=100?$code:200,
            (microtime(true)-$start_time)*1000,
            $loggedEvent);
    }catch(Throwable $error){
        error_log('API request logging shutdown failed: '.$error->getMessage());
    }
});
// Validate request shape and bound ingest payload sizes before database writes.
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){
    http_response_code(405);echo json_encode(['error'=>'POST required']);exit;
}
if((int)($_SERVER['CONTENT_LENGTH']??0)>12*1024*1024){
    http_response_code(413);echo json_encode(['error'=>'Payload too large']);exit;
}
$input=file_get_contents('php://input',false,null,0,12*1024*1024+1);
if(strlen($input)>12*1024*1024){
    http_response_code(413);echo json_encode(['error'=>'Payload too large']);exit;
}
$data = json_decode($input,true);
if (!is_array($data) || !isset($data['Event']) || !is_string($data['Event'])) {
    error_log("Invalid JSON input received");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Resolve the authenticated v1.5 session, or an explicitly allowlisted
// already-registered v1.3 device. No new monitor is created by telemetry.
$monitorId=requireClientMonitorOrLegacy($mysqli,(string)($data['MacAddress']??''));
$dbService->setAuthenticatedMonitorId($monitorId);

// Explicit monitor ID must agree with the server-side authenticated selection.
$claimedId=filter_var($data['MonitorId']??null,FILTER_VALIDATE_INT);
if($claimedId && (int)$claimedId!==$monitorId){http_response_code(403);echo json_encode(['error'=>'Monitor selection mismatch']);exit;}
// Extract common data
$appEvent = $data['Event'] ?? '';
$loggedEvent=$appEvent;
$version = $data['Version'] ?? '';
$macAddress = $data['MacAddress'] ?? '';
// Client-supplied Username is not authoritative; account ownership is server-side.

// Get client IP address
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

// Monitor identity comes from the authenticated session, never MAC/IP.
// A malformed or unsupported event must not update last activity.
// Handle different event types
$response = ['status' => 'success', 'message' => 'Event processed successfully'];

try {
    
    switch ($appEvent) {
        case 'Tic':
            $dbService->updateMonitorActivity($clientIP, 'monitor');
            $response['data'] = [];
            break;
            
        case 'BrowserHistory':
            $browserHistories = $data['BrowserHistories'] ?? [];
            if (!is_array($browserHistories) || count($browserHistories)>500) {
                http_response_code(400);echo json_encode(['error'=>'Invalid browser history batch']);exit;
            }
            if (!empty($browserHistories)) {
                foreach($browserHistories as $record){
                    if(!is_array($record)){http_response_code(400);echo json_encode(['error'=>'Malformed browser record']);exit;}
                }
                $inserted_count = $dbService->addBrowserHistoryLogs($browserHistories, $clientIP, $macAddress);
                // Update monitor activity for browser history logging (pass MAC address)
                $dbService->updateMonitorActivity($clientIP, 'browser', $macAddress);
                $response['data'] = [
                    'inserted_count' => $inserted_count
                ];
            }
            break;
            
        case 'KeyLog':
            $keyLogs = $data['KeyLogs'] ?? [];
            if (!is_array($keyLogs) || count($keyLogs)>2000) {
                http_response_code(400);echo json_encode(['error'=>'Invalid key log batch']);exit;
            }
            if (!empty($keyLogs)) {
                foreach($keyLogs as $record){
                    if(!is_array($record) ||
                       !is_scalar($record['key']??$record['key_pressed']??null) ||
                       (string)($record['key']??$record['key_pressed']??'')===''){
                        http_response_code(400);
                        echo json_encode(['error'=>'Malformed keyboard record (missing key)']);
                        exit;
                    }
                }
                $inserted_count = $dbService->addKeyLogs($keyLogs, $clientIP, $macAddress);
                if($inserted_count===false){
                    http_response_code(500);
                    echo json_encode(['error'=>'Keyboard log storage unavailable']);
                    exit;
                }
                if($inserted_count===0){
                    http_response_code(422);
                    echo json_encode(['error'=>'No valid keyboard records in batch']);
                    exit;
                }
                // Update monitor activity for key logging (pass MAC address)
                $dbService->updateMonitorActivity($clientIP, 'monitor', $macAddress);
                $response['data'] = [
                    'inserted_count' => $inserted_count
                ];
            }
            break;
            
        case 'USBLog':
            $usbLogs = $data['USBLogs'] ?? [];
            if (!is_array($usbLogs) || count($usbLogs)>500) {
                http_response_code(400);echo json_encode(['error'=>'Invalid USB log batch']);exit;
            }
            if (!empty($usbLogs)) {
                foreach($usbLogs as $record){
                    if(!is_array($record)){http_response_code(400);echo json_encode(['error'=>'Malformed USB record']);exit;}
                }
                $inserted_count = $dbService->addUSBDeviceLogs($usbLogs, $clientIP, $macAddress);
                // Update monitor activity for USB logging (pass MAC address)
                $dbService->updateMonitorActivity($clientIP, 'monitor', $macAddress);
                $response['data'] = [
                    'inserted_count' => $inserted_count
                ];
            }
            break;
            
        default:
            error_log("Unknown event type: $appEvent");
            http_response_code(400);
            echo json_encode(['error' => 'Unknown event type']);
            exit;
    }
    
} catch (Throwable $e) {
    error_log("Error processing event $appEvent: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}

// Return success response
echo json_encode($response);
?> 