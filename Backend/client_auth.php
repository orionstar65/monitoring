<?php
require_once __DIR__.'/services/ClientIdentity.php';
requireSecureRequest();
function clientMonitorFields(array $data): array {
    $name=trim((string)($data['label']??$data['name']??''));
    $remark=trim((string)($data['remark']??''));
    $os=trim((string)($data['os']??$data['platform']??''));
    $type=strtolower(trim((string)($data['machine']??$data['type']??'desktop')));
    $map=['win'=>'Windows','windows'=>'Windows','mac'=>'Mac','macos'=>'Mac','ubuntu'=>'Ubuntu'];
    $platform=$map[strtolower($os)]??'';
    if($name==='')$name='Monitor';
    if(strlen($name)>100||preg_match('/[<>\\x00-\\x1f]/',$name))return ['error'=>'Invalid monitor label'];
    if(strlen($remark)>500||preg_match('/[<>\\x00-\\x1f]/',$remark))return ['error'=>'Invalid remark'];
    if(!$platform)return ['error'=>'Select Windows, Mac or Ubuntu'];
    if(!in_array($type,['laptop','desktop'],true))return ['error'=>'Select Laptop or Desktop'];
    return ['name'=>$name,'platform'=>$platform,'type'=>$type,'remark'=>$remark];
}
header('Content-Type: application/json');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['error'=>'POST required']);exit;}
try {
    $db=clientIdentityDatabase();
    $data=json_decode(file_get_contents('php://input'),true)??[];
    $action=$data['action']??'';
    if($action==='signup'){
        $username=trim((string)($data['username']??''));
        $fullName=trim((string)($data['full_name']??$data['name']??$username));
        $password=(string)($data['password']??'');
        $confirm=(string)($data['confirm_password']??'');
        if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)
            ||strlen($password)<12||strlen($password)>1024){
            http_response_code(400);echo json_encode(['error'=>'Username must be 3–50 letters, numbers, underscores, periods or dashes; password at least 12 characters.']);exit;
        }
        if(strlen($fullName)<2||strlen($fullName)>150
            ||preg_match('/[<>\\x00-\\x1f\\x7f]/',$fullName)){
            http_response_code(400);
            echo json_encode(['error'=>'Enter a valid full name (2–150 characters).']);exit;
        }
        if(!hash_equals($password,$confirm)){
            http_response_code(400);echo json_encode(['error'=>'Passwords do not match']);exit;
        }
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|signup');
        $st=$db->prepare('SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $st->bind_param('s',$key);$st->execute();$hits=(int)$st->get_result()->fetch_assoc()['hits'];$st->close();
        if($hits>=10){http_response_code(429);echo json_encode(['error'=>'Too many signup attempts; try again later']);exit;}
        $st=$db->prepare('INSERT INTO login_attempts (attempt_key) VALUES (?)');
        $st->bind_param('s',$key);$st->execute();$st->close();
        $st=$db->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $st->bind_param('ss',$username,$username);$st->execute();$exists=$st->get_result()->fetch_assoc();$st->close();
        if($exists){http_response_code(409);echo json_encode(['error'=>'This username already exists. Please sign in instead.','code'=>'username_exists']);exit;}
        $hash=password_hash($password,PASSWORD_DEFAULT);
        try {
            $st=$db->prepare("INSERT INTO users (name,username,password,role) VALUES (?,?,?,'viewer')");
            $st->bind_param('sss',$fullName,$username,$hash);$st->execute();$st->close();
        } catch(mysqli_sql_exception $e) {
            if($e->getCode()===1062){http_response_code(409);echo json_encode(['error'=>'This username already exists. Please sign in instead.','code'=>'username_exists']);exit;}
            throw $e;
        }
        http_response_code(201);
        echo json_encode(['success'=>true,'message'=>'Account created. Please sign in.']);exit;
    }
    if($action==='login'){
        $identifier=trim((string)($data['identifier']??''));
        $password=(string)($data['password']??'');
        if(!$identifier||!$password||strlen($identifier)>254||strlen($password)>1024){http_response_code(400);echo json_encode(['error'=>'Invalid credentials']);exit;}
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.strtolower($identifier));
        $st=$db->prepare('SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=? AND attempted_at>DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $st->bind_param('s',$key);$st->execute();$count=(int)$st->get_result()->fetch_assoc()['hits'];$st->close();
        if($count>=8){http_response_code(429);echo json_encode(['error'=>'Too many attempts; try again later']);exit;}
        $st=$db->prepare('SELECT id,name,username,password,must_change_password FROM users WHERE username=? OR email=? LIMIT 1');
        $st->bind_param('ss',$identifier,$identifier);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
        if(!$user||!password_verify($password,$user['password'])){
            $st=$db->prepare('INSERT INTO login_attempts (attempt_key) VALUES (?)');
            $st->bind_param('s',$key);$st->execute();$st->close();
            http_response_code(401);echo json_encode(['error'=>'Incorrect credentials. If you forgot your password, ask an administrator to reset it.','code'=>'invalid_credentials']);exit;
        }
        if(!empty($user['must_change_password'])){
            http_response_code(403);
            echo json_encode(['error'=>'Change your temporary password in the web portal before signing into a desktop client.','code'=>'password_change_required']);exit;
        }
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $st=$db->prepare('INSERT INTO client_sessions (token_hash,user_id,expires_at) VALUES (?,?,NULL)');
        $st->bind_param('si',$hash,$user['id']);$st->execute();$st->close();
        $st=$db->prepare('SELECT id,name,ip_address,platform,type,remark,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY name,id');
        $st->bind_param('i',$user['id']);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        echo json_encode(['success'=>true,'token'=>$token,'expires_in'=>null,'monitors'=>$monitors,'user'=>['id'=>(int)$user['id'],'name'=>$user['name'],'username'=>$user['username']]]);exit;
    }
    $session=clientAuthenticatedSession($db,false);
    if(!$session){http_response_code(401);echo json_encode(['error'=>'Sign in first']);exit;}
    $userId=(int)$session['user_id'];
    if($action==='restore'){
        $st=$db->prepare('SELECT id,name,username FROM users WHERE id=?');
        $st->bind_param('i',$userId);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
        $st=$db->prepare('SELECT id,name,ip_address,platform,type,remark,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY name,id');
        $st->bind_param('i',$userId);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        $monitorId=(int)($session['monitor_id']??0);
        if($monitorId && !in_array($monitorId,array_map(static fn($m)=>(int)$m['id'],$monitors),true))$monitorId=0;
        echo json_encode(['success'=>true,'user'=>$user,'monitors'=>$monitors,'monitor_id'=>$monitorId]);exit;
    }
    if($action==='monitors'){
        $st=$db->prepare('SELECT id,name,ip_address,platform,type,remark,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY name,id');
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
    if($action==='save_monitor'){
        $monitorId=filter_var($data['monitor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$monitorId){http_response_code(400);echo json_encode(['error'=>'Select an assigned monitor']);exit;}
        $st=$db->prepare('SELECT id FROM tbl_monitor_ips WHERE id=? AND owner_user_id=? LIMIT 1');
        $st->bind_param('ii',$monitorId,$userId);$st->execute();$owned=$st->get_result()->fetch_assoc();$st->close();
        if(!$owned){http_response_code(403);echo json_encode(['error'=>'Monitor not assigned to your account']);exit;}
        $fields=clientMonitorFields($data);
        if(isset($fields['error'])){http_response_code(400);echo json_encode(['error'=>$fields['error']]);exit;}
        $st=$db->prepare('UPDATE tbl_monitor_ips SET name=?,platform=?,type=?,remark=? WHERE id=? AND owner_user_id=?');
        $st->bind_param('ssssii',$fields['name'],$fields['platform'],$fields['type'],$fields['remark'],$monitorId,$userId);
        $st->execute();$st->close();
        echo json_encode(['success'=>true,'monitor_id'=>$monitorId]);exit;
    }
    if($action==='request_monitor'){
        $fields=clientMonitorFields($data);
        if(isset($fields['error'])){http_response_code(400);echo json_encode(['error'=>$fields['error']]);exit;}
        $name=$fields['name'];
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
        $ip=$_SERVER['REMOTE_ADDR']??'';
        $st=$db->prepare('INSERT INTO tbl_monitor_ips (name,ip_address,type,platform,remark,requested_user_id) VALUES (?,?,?,?,?,?)');
        $st->bind_param('sssssi',$name,$ip,$fields['type'],$fields['platform'],$fields['remark'],$userId);$st->execute();
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
