<?php
/** Authentication for telemetry, bound to an assigned monitor ID. */
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
function monitoringTrustedProxyHttps(): bool {
    if(getenv('MONITOR_TRUST_PROXY_HTTPS')!=='1')return false;
    // Never trust a caller-supplied X-Forwarded-Proto from an arbitrary peer.
    // Only localhost is trusted by default; configure explicit proxy IPs if needed.
    $peer=$_SERVER['REMOTE_ADDR']??'';
    $trusted=explode(',',getenv('MONITOR_TRUSTED_PROXY_IPS')?:'127.0.0.1,::1');
    $trusted=array_map('trim',$trusted);
    return in_array($peer,$trusted,true)
        && strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https';
}
function requireSecureRequest(): void {
    // HTTPS must be end-to-end or terminated by an explicitly trusted proxy.
    $direct = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $proxy = monitoringTrustedProxyHttps();
    if (!$direct && !$proxy && !monitoringLanHttpAllowed()) {
        http_response_code(426);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'HTTPS is required']);
        exit;
    }
}
/**
 * Minimal trusted-LAN check for opt-in no-code desktop enrollment.
 * Forwarded headers are never trusted from arbitrary clients.
 */
function monitoringIsPrivateLanAddress(string $address): bool {
    if(filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){
        $parts=array_map('intval',explode('.',$address));
        return $parts[0]===10
            || ($parts[0]===172 && $parts[1]>=16 && $parts[1]<=31)
            || ($parts[0]===192 && $parts[1]===168)
            || ($parts[0]===169 && $parts[1]===254);
    }
    if(!filter_var($address,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6))return false;
    $packed=inet_pton($address);
    if(!$packed)return false;
    $first=ord($packed[0]);$second=ord($packed[1]);
    return ($first & 0xfe)===0xfc
        || ($first===0xfe && ($second & 0xc0)===0x80);
}
function monitoringLanEnrollmentClient(): bool {
    $peer=(string)($_SERVER['REMOTE_ADDR']??'');
    // Automatic LAN access does not treat loopback as a LAN peer. This
    // avoids treating every public request tunneled through localhost as local.
    if(!monitoringIsPrivateLanAddress($peer))return false;
    if(isset($_SERVER['HTTP_FORWARDED'])||isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
        // A proxy can relay public traffic. Only an explicitly trusted HTTPS
        // proxy may supply a client IP for this decision.
        if(!monitoringTrustedProxyHttps())return false;
        $client=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??''))[0]);
        return monitoringIsPrivateLanAddress($client);
    }
    return true;
}
function monitoringNoCodeEnrollmentAllowed(mysqli $db): bool {
    // Explicit server-side deny always wins.
    if(getenv('MONITOR_DISABLE_USER_NO_ENROLL')==='1')return false;
    // Existing opt-in for tightly firewalled remote/VPN deployments.
    if(getenv('MONITOR_ENABLE_USER_NO_ENROLL')==='1')return true;
    // Direct trusted-LAN clients can list/select users without provisioning
    // a pairing code. Public clients and untrusted proxy headers are denied.
    return monitoringLanEnrollmentClient();
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
    $stmt=$db->prepare('SELECT s.id,s.user_id,s.monitor_id,s.session_type FROM client_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND (s.expires_at IS NULL OR s.expires_at>NOW()) AND s.revoked_at IS NULL AND s.session_type=\'device\' LIMIT 1');
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
/**
 * Explicit transitional bridge for already-installed v1.3 devices that cannot
 * send a bearer token. Disabled by default; never creates monitors or sessions.
 *
 * It requires (1) a direct private-LAN peer, (2) an exact source-IP entry in
 * MONITOR_LEGACY_V13_IPS, (3) precisely one existing assigned monitor whose
 * registered IP matches the peer, and (4) optional MAC equality when the
 * client supplied a MAC. Do not put a reverse proxy in front of this bridge.
 */
function monitoringLegacyV13Monitor(mysqli $db, ?string $claimedMac = null): ?int {
    // Legacy clients lack tokens. Only uniquely registered direct-LAN peers may use this route.
    // An explicit opt-out remains available for sites that retired every old client.
    if(getenv('MONITOR_DISABLE_LEGACY_V13')==='1')return null;
    // Any presented Authorization header must use the authenticated v1.5 path.
    if(!empty($_SERVER['HTTP_AUTHORIZATION'])||!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))return null;
    if(function_exists('getallheaders'))foreach(getallheaders() as $key=>$value){
        if(strcasecmp($key,'Authorization')===0)return null;
    }
    $peer=(string)($_SERVER['REMOTE_ADDR']??'');
    if(!monitoringIsPrivateLanAddress($peer))return null;
    if(isset($_SERVER['HTTP_FORWARDED'])||isset($_SERVER['HTTP_X_FORWARDED_FOR']))return null;
    $mac=trim((string)$claimedMac);
    if($mac!==''&&!preg_match('/^[0-9a-f]{2}(?:[:-][0-9a-f]{2}){5}$/iD',$mac))return null;
    $stmt=$db->prepare(
        'SELECT m.id,m.mac_address FROM tbl_monitor_ips m
         JOIN users u ON u.id=m.owner_user_id
         WHERE m.ip_address=? LIMIT 3'
    );
    $stmt->bind_param('s',$peer);$stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    // IP-only screenshot uploads are permitted solely for unique IP mappings.
    if(count($rows)!==1)return null;
    $registeredMac=trim((string)($rows[0]['mac_address']??''));
    if($mac!=='' && ($registeredMac==='' ||
        str_replace('-',':',strtolower($registeredMac))!==str_replace('-',':',strtolower($mac))))return null;
    return (int)$rows[0]['id'];
}
function requireClientMonitorOrLegacy(mysqli $db, ?string $claimedMac = null): int {
    $session=clientAuthenticatedSession($db,true);
    if($session)return (int)$session['monitor_id'];
    $legacy=monitoringLegacyV13Monitor($db,$claimedMac);
    if($legacy!==null)return $legacy;
    header('Content-Type: application/json');http_response_code(401);
    echo json_encode(['error'=>'Device authentication required or legacy device not registered']);exit;
}
function requireClientMonitor(mysqli $db): int {
    $session=clientAuthenticatedSession($db,true);
    if(!$session){
        header('Content-Type: application/json');http_response_code(401);
        echo json_encode(['error'=>'Select User No and an assigned monitor']);exit;
    }
    return (int)$session['monitor_id'];
}
