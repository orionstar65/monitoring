<?php
require_once __DIR__.'/services/ClientIdentity.php';
requireSecureRequest();
header('Content-Type: application/json');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['error'=>'POST required']);exit;}
try {
    $db=clientIdentityDatabase();
    $data=json_decode(file_get_contents('php://input'),true)??[];
    $action=$data['action']??'';
    if($action==='login'){
        $identifier=trim((string)($data['identifier']??''));
        $password=(string)($data['password']??'');
        if(!$identifier||!$password||strlen($identifier)>254||strlen($password)>1024){http_response_code(400);echo json_encode(['error'=>'Invalid credentials']);exit;}
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.strtolower($identifier));
        $st=$db->prepare('SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=? AND attempted_at>DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $st->bind_param('s',$key);$st->execute();$count=(int)$st->get_result()->fetch_assoc()['hits'];$st->close();
        if($count>=8){http_response_code(429);echo json_encode(['error'=>'Too many attempts; try again later']);exit;}
        $st=$db->prepare('SELECT id,name,username,password FROM users WHERE username=? OR email=? LIMIT 1');
        $st->bind_param('ss',$identifier,$identifier);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
        if(!$user||!password_verify($password,$user['password'])){
            $st=$db->prepare('INSERT INTO login_attempts (attempt_key) VALUES (?)');
            $st->bind_param('s',$key);$st->execute();$st->close();
            http_response_code(401);echo json_encode(['error'=>'Invalid credentials']);exit;
        }
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $st=$db->prepare('INSERT INTO client_sessions (token_hash,user_id,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 7 DAY))');
        $st->bind_param('si',$hash,$user['id']);$st->execute();$st->close();
        $st=$db->prepare('SELECT id,name,ip_address,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY name,id');
        $st->bind_param('i',$user['id']);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        echo json_encode(['success'=>true,'token'=>$token,'expires_in'=>604800,'monitors'=>$monitors,'user'=>['id'=>(int)$user['id'],'name'=>$user['name'],'username'=>$user['username']]]);exit;
    }
    $session=clientAuthenticatedSession($db,false);
    if(!$session){http_response_code(401);echo json_encode(['error'=>'Sign in first']);exit;}
    $userId=(int)$session['user_id'];
    if($action==='monitors'){
        $st=$db->prepare('SELECT id,name,ip_address,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY name,id');
        $st->bind_param('i',$userId);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success'=>true,'monitors'=>$monitors]);exit;
    }
    if($action==='select'){
        $monitorId=filter_var($data['monitor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$monitorId){http_response_code(400);echo json_encode(['error'=>'Invalid monitor ID']);exit;}
        $st=$db->prepare('SELECT id FROM tbl_monitor_ips WHERE id=? AND owner_user_id=?');
        $st->bind_param('ii',$monitorId,$userId);$st->execute();$owned=$st->get_result()->fetch_assoc();$st->close();
        if(!$owned){http_response_code(403);echo json_encode(['error'=>'Monitor not assigned to your account']);exit;}
        $st=$db->prepare('UPDATE client_sessions SET monitor_id=? WHERE id=? AND user_id=?');
        $st->bind_param('iii',$monitorId,$session['id'],$userId);$st->execute();$st->close();
        echo json_encode(['success'=>true,'monitor_id'=>(int)$monitorId]);exit;
    }
    if($action==='request_monitor'){
        $name=trim((string)($data['name']??''));
        if(preg_match('/[<>\\x00-\\x1f]/',$name)){
            http_response_code(400);echo json_encode(['error'=>'Invalid monitor name']);exit;
        }
        if(strlen($name)<2||strlen($name)>100){http_response_code(400);echo json_encode(['error'=>'Name must be 2–100 characters']);exit;}
        $st=$db->prepare('SELECT COUNT(*) AS pending FROM tbl_monitor_ips WHERE requested_user_id=? AND owner_user_id IS NULL');
        $st->bind_param('i',$userId);$st->execute();
        $pending=(int)$st->get_result()->fetch_assoc()['pending'];$st->close();
        if($pending>=5){
            http_response_code(429);echo json_encode(['error'=>'You already have five pending monitor requests. Contact your administrator.']);exit;
        }
        $ip=$_SERVER['REMOTE_ADDR']??'';$type='desktop';
        $st=$db->prepare('INSERT INTO tbl_monitor_ips (name,ip_address,type,requested_user_id) VALUES (?,?,?,?)');
        $st->bind_param('sssi',$name,$ip,$type,$userId);$st->execute();
        echo json_encode(['success'=>true,'monitor_id'=>$db->insert_id,'pending_approval'=>true]);exit;
    }
    if($action==='logout'){
        $st=$db->prepare('UPDATE client_sessions SET revoked_at=NOW() WHERE id=? AND user_id=?');
        $st->bind_param('ii',$session['id'],$userId);$st->execute();
        echo json_encode(['success'=>true]);exit;
    }
    http_response_code(400);echo json_encode(['error'=>'Unknown action']);
}catch(Throwable $e){
    error_log('Client auth error: '.$e->getMessage());
    http_response_code(500);echo json_encode(['error'=>'Server error']);
}
