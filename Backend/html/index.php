<?php
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');
session_start();
if(!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
if(isset($_SESSION['last_activity']) && time()-$_SESSION['last_activity']>3600){
    session_unset();session_regenerate_id(true);$_SESSION['csrf_token']=bin2hex(random_bytes(32));
}
$_SESSION['last_activity']=time();
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $csrf=$_POST['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if(!is_string($csrf)||!hash_equals($_SESSION['csrf_token'],$csrf)){
        http_response_code(403);exit('Invalid CSRF token');
    }
}

// Define base path
define('BASEPATH', __DIR__ . '/');

// Include configuration
require_once 'app/config/config.php';
require_once 'app/config/database.php';

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = dirname($_SERVER['SCRIPT_NAME']);

// Handle subdirectory installations
if ($base_path !== '/') {
    $path = str_replace($base_path, '', $request_uri);
} else {
    $path = $request_uri;
}

$path = trim($path, '/');

// Remove query string
if (strpos($path, '?') !== false) {
    $path = substr($path, 0, strpos($path, '?'));
}

// Debug routing
error_log("Request URI: " . $request_uri);
error_log("Base Path: " . $base_path);
error_log("Path: " . $path);

// Default route
if (empty($path)) {
    $path = 'auth/login';
}

// Parse route
$segments = explode('/', $path);
$controller = isset($segments[0]) ? $segments[0] : 'auth';
$method = isset($segments[1]) ? $segments[1] : ($controller === 'auth' ? 'login' : 'index');
$params = array_slice($segments, 2);

// Check if user is logged in (except for auth controller)
if ($controller !== 'auth' && !isset($_SESSION['user_id'])) {
    $redirect_url = $base_path . '/auth/login';
    header('Location: ' . $redirect_url);
    exit;
}

// Load controller
$controller_file = 'app/controllers/' . ucfirst($controller) . '.php';
if (file_exists($controller_file)) {
    require_once $controller_file;
    $controller_class = ucfirst($controller);
    $controller_instance = new $controller_class();
    
    if (preg_match('/^[a-z_][a-z0-9_]*$/i', $method) && !str_starts_with($method,'_') && method_exists($controller_instance, $method)) {
        // Check if method is public
        $reflection = new ReflectionMethod($controller_instance, $method);
        if ($reflection->isPublic() && $reflection->getDeclaringClass()->getName() === $controller_class && $method !== '__construct') {
            call_user_func_array([$controller_instance, $method], $params);
        } else {
            http_response_code(404);
            echo 'Route not found';
        }
    } else {
        http_response_code(404);
        echo 'Route not found';
    }
} else {
    http_response_code(404);
    echo "Controller not found: $controller";
}
?> 