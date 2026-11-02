<?php
/** Authentication for telemetry, bound to an admin-assigned monitor ID. */
/**
 * HTTP is available only as explicit LAN opt-in on a dedicated restricted vhost.
 * This is not safe for credentials and sensitive monitoring telemetry on
 * networks where other parties can intercept or modify traffic.
 */
function monitoringLanHttpAllowed(): bool {
    if (getenv('MONITOR_ALLOW_HTTP') !== '1') return false;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '::1') return true;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
    if (str_starts_with($ip, '127.')) return true;
    return filter_var($ip, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}
function requireSecureRequest(): void {
    // HTTPS must be end-to-end or terminated by a trusted, isolated reverse proxy.
    $direct = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $proxy = getenv('MONITOR_TRUST_PROXY_HTTPS') === '1'
        && strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    if (!$direct && !$proxy && !monitoringLanHttpAllowed()) {
        http_response_code(426);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'HTTPS is required']);
        exit;
    }
}
function clientIdentityDatabase(): mysqli {
    require_once __DIR__ . '/../config.inc.php';
    $config=new JConfig();
    $db=new mysqli($config->dbhost,$config->dbuser,$config->dbpassword,$config->dbname);
    if ($db->connect_error) throw new RuntimeException('Database unavailable');
    $db->set_charset('utf8mb4');
    return $db;
}
function clientBearer(): string {
    $header=$_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$header && function_exists('getallheaders')) foreach(getallheaders() as $key=>$value) {
        if(strcasecmp($key,'Authorization')===0){$header=$value;break;}
    }
    return preg_match('/^Bearer ([a-f0-9]{64})$/i',trim($header),$m) ? strtolower($m[1]) : '';
}
function clientAuthenticatedSession(mysqli $db,bool $bound=true): ?array {
    $token=clientBearer();
    if(!$token)return null;
    $hash=hash('sha256',$token);
    $stmt=$db->prepare('SELECT s.id,s.user_id,s.monitor_id FROM client_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND (s.expires_at IS NULL OR s.expires_at>NOW()) AND s.revoked_at IS NULL LIMIT 1');
    $stmt->bind_param('s',$hash);$stmt->execute();
    $session=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$session)return null;
    if($bound){
        if(empty($session['monitor_id']))return null;
        $stmt=$db->prepare('SELECT id FROM tbl_monitor_ips WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('ii',$session['monitor_id'],$session['user_id']);$stmt->execute();
        $valid=$stmt->get_result()->fetch_assoc();$stmt->close();
        if(!$valid)return null;
    }
    return $session;
}
function requireClientMonitor(mysqli $db): int {
    $session=clientAuthenticatedSession($db,true);
    if(!$session){
        header('Content-Type: application/json');http_response_code(401);
        echo json_encode(['error'=>'Sign in and select an assigned monitor']);exit;
    }
    return (int)$session['monitor_id'];
}
