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
    if((int)($_SERVER['CONTENT_LENGTH']??0)>128*1024){
        http_response_code(413);echo json_encode(['error'=>'Request too large']);exit;
    }
    $raw=file_get_contents('php://input',false,null,0,128*1024+1);
    if(strlen($raw)>128*1024){
        http_response_code(413);echo json_encode(['error'=>'Request too large']);exit;
    }
    $data=json_decode($raw,true);
    if(!is_array($data) || !isset($data['action']) || !is_string($data['action'])){
        http_response_code(400);echo json_encode(['error'=>'Invalid JSON request']);exit;
    }
    $action=$data['action'];
    // Public directory reveals user numbers only. No names, email or monitor data.
    if($action==='directory'){
        $st=$db->prepare('SELECT user_no FROM users ORDER BY user_no LIMIT 10000');
        $st->execute();$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        echo json_encode(['success'=>true,'users'=>array_map(static fn($u)=>['user_no'=>(string)$u['user_no']],$rows)]);exit;
    }
    // One-time admin-issued pairing code is required on first use. This prevents
    // simply knowing an employee's user number from granting telemetry access.
    if($action==='enroll'){
        $userNo=(string)($data['user_no']??'');
        $code=strtoupper(trim((string)($data['pairing_code']??'')));
        if(!preg_match('/^[0-9]{6,15}$/',$userNo)||!preg_match('/^[A-F0-9]{16}$/',$code)){
            http_response_code(400);echo json_encode(['error'=>'Enter User No and the 16-character administrator pairing code']);exit;
        }
        $attemptKey=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|enroll');
        $st=$db->prepare('SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $st->bind_param('s',$attemptKey);$st->execute();$hits=(int)$st->get_result()->fetch_assoc()['hits'];$st->close();
        if($hits>=12){http_response_code(429);echo json_encode(['error'=>'Too many attempts, try later']);exit;}
        $st=$db->prepare('INSERT INTO login_attempts (attempt_key) VALUES (?)');
        $st->bind_param('s',$attemptKey);$st->execute();$st->close();
        $codeHash=hash('sha256',$code);
        $db->begin_transaction();
        try {
            $st=$db->prepare('SELECT m.id,m.owner_user_id,m.assignment_locked FROM tbl_monitor_ips m JOIN users u ON u.id=m.owner_user_id WHERE u.user_no=? AND m.pairing_code_hash=? AND m.pairing_expires_at>NOW() LIMIT 1 FOR UPDATE');
            $st->bind_param('ss',$userNo,$codeHash);$st->execute();$monitor=$st->get_result()->fetch_assoc();$st->close();
            if(!$monitor){$db->rollback();http_response_code(403);echo json_encode(['error'=>'Invalid or expired pairing code. Ask your administrator to generate a new code.']);exit;}
            $mid=(int)$monitor['id'];$uid=(int)$monitor['owner_user_id'];
            $st=$db->prepare('UPDATE tbl_monitor_ips SET pairing_code_hash=NULL,pairing_expires_at=NULL WHERE id=?');
            $st->bind_param('i',$mid);$st->execute();$st->close();
            // Re-enrollment invalidates the old installation's credentials.
            $st=$db->prepare("UPDATE client_sessions SET revoked_at=NOW() WHERE monitor_id=? AND session_type='device' AND revoked_at IS NULL");
            $st->bind_param('i',$mid);$st->execute();$st->close();
            $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
            $st=$db->prepare("INSERT INTO client_sessions (token_hash,user_id,monitor_id,session_type,expires_at) VALUES (?,?,?,'device',NULL)");
            $st->bind_param('sii',$hash,$uid,$mid);$st->execute();$st->close();
            $db->commit();
            echo json_encode(['success'=>true,'token'=>$token,'monitor_id'=>$mid,'user_no'=>$userNo,'assignment_locked'=>(bool)$monitor['assignment_locked']]);exit;
        } catch(Throwable $failure){$db->rollback();throw $failure;}
    }
    if($action==='signup'||$action==='login'){
        http_response_code(410);
        echo json_encode(['error'=>'Desktop passwords were retired. Select User No and enroll with an administrator pairing code.','code'=>'use_device_pairing']);exit;
    }
    $session=clientAuthenticatedSession($db,false);
    if(!$session || ($session['session_type']??'')!=='device'){
        http_response_code(401);echo json_encode(['error'=>'Pair this device using the administrator enrollment code']);exit;
    }
    $userId=(int)$session['user_id'];
    if($action==='switch_user'){
        $newNo=(string)($data['user_no']??'');
        if(!preg_match('/^[0-9]{6,15}$/',$newNo)){http_response_code(400);echo json_encode(['error'=>'Invalid User No']);exit;}
        $mid=(int)($session['monitor_id']??0);
        if($mid<1){http_response_code(403);echo json_encode(['error'=>'Device is not paired']);exit;}
        $db->begin_transaction();
        try {
            $st=$db->prepare('SELECT owner_user_id,assignment_locked FROM tbl_monitor_ips WHERE id=? FOR UPDATE');
            $st->bind_param('i',$mid);$st->execute();$monitor=$st->get_result()->fetch_assoc();$st->close();
            if(!$monitor||(int)$monitor['owner_user_id']!==$userId){$db->rollback();http_response_code(403);echo json_encode(['error'=>'Assignment changed. Ask your administrator to re-enroll this device.']);exit;}
            if((int)$monitor['assignment_locked']===1){$db->rollback();http_response_code(423);echo json_encode(['error'=>'User assignment is locked. Ask your administrator to edit the monitor.','code'=>'assignment_locked']);exit;}
            $st=$db->prepare('SELECT id FROM users WHERE user_no=? LIMIT 1');
            $st->bind_param('s',$newNo);$st->execute();$target=$st->get_result()->fetch_assoc();$st->close();
            if(!$target){$db->rollback();http_response_code(404);echo json_encode(['error'=>'User No not found']);exit;}
            $newId=(int)$target['id'];
            $st=$db->prepare('UPDATE tbl_monitor_ips SET owner_user_id=? WHERE id=?');
            $st->bind_param('ii',$newId,$mid);$st->execute();$st->close();
            $st=$db->prepare('UPDATE client_sessions SET revoked_at=NOW() WHERE monitor_id=? AND revoked_at IS NULL');
            $st->bind_param('i',$mid);$st->execute();$st->close();
            $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
            $st=$db->prepare("INSERT INTO client_sessions(token_hash,user_id,monitor_id,session_type,expires_at) VALUES(?,?,?,'device',NULL)");
            $st->bind_param('sii',$hash,$newId,$mid);$st->execute();$st->close();
            $db->commit();
            echo json_encode(['success'=>true,'token'=>$token,'user_no'=>$newNo,'monitor_id'=>$mid]);exit;
        }catch(Throwable $failure){$db->rollback();throw $failure;}
    }
    if($action==='restore'){
        $st=$db->prepare('SELECT id,name,username,user_no FROM users WHERE id=?');
        $st->bind_param('i',$userId);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
        $st=$db->prepare('SELECT id,name,ip_address,platform,type,remark,assignment_locked,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY name,id');
        $st->bind_param('i',$userId);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        $monitorId=(int)($session['monitor_id']??0);
        if($monitorId && !in_array($monitorId,array_map(static fn($m)=>(int)$m['id'],$monitors),true))$monitorId=0;
        echo json_encode(['success'=>true,'user'=>$user,'monitors'=>$monitors,'monitor_id'=>$monitorId,
            'assignment_locked'=>!empty(array_values(array_filter($monitors,static fn($m)=>(int)$m['id']===$monitorId))[0]['assignment_locked'])]);exit;
    }
    if($action==='monitors'){
        $st=$db->prepare('SELECT id,name,ip_address,platform,type,remark,assignment_locked,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY name,id');
        $st->bind_param('i',$userId);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success'=>true,'monitors'=>$monitors]);exit;
    }
    if($action==='select'){
        $monitorId=filter_var($data['monitor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$monitorId){http_response_code(400);echo json_encode(['error'=>'Invalid monitor ID']);exit;}
        if((int)$session['monitor_id']!==$monitorId){
            http_response_code(403);echo json_encode(['error'=>'This device is paired to a different monitor. Ask an administrator for its pairing code.']);exit;
        }
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
        if((int)$session['monitor_id']!==$monitorId){
            http_response_code(403);echo json_encode(['error'=>'Monitor is not paired to this device']);exit;
        }
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
        http_response_code(403);
        echo json_encode(['error'=>'New monitors must be added and assigned by an administrator in the web portal.']);exit;
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
