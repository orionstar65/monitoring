<?php
require_once __DIR__.'/services/ClientIdentity.php';
requireSecureRequest();
function clientMonitorFields(array $data): array {
    $remark=trim((string)($data['remark']??''));
    $os=trim((string)($data['os']??$data['platform']??''));
    $type=strtolower(trim((string)($data['machine']??$data['type']??'desktop')));
    $map=['win'=>'Windows','windows'=>'Windows','mac'=>'Mac','macos'=>'Mac','ubuntu'=>'Ubuntu'];
    $platform=$map[strtolower($os)]??'';
     if(strlen($remark)>500||preg_match('/[<>\\x00-\\x1f]/',$remark))return ['error'=>'Invalid remark'];
    if(!$platform)return ['error'=>'Select Windows, Mac or Ubuntu'];
    if(!in_array($type,['laptop','desktop'],true))return ['error'=>'Select Laptop or Desktop'];
    return ['platform'=>$platform,'type'=>$type,'remark'=>$remark];
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
    // Direct private-LAN clients may list/enroll without pairing codes.
    // Public clients and untrusted proxy traffic remain denied unless an
    // administrator explicitly permits remote/VPN use via the environment.
    if(in_array($action,['directory','enroll','bootstrap_monitor'],true)
       && !monitoringNoCodeEnrollmentAllowed($db)){
        http_response_code(503);
        echo json_encode([
            'success'=>false,
            'code'=>'directory_disabled',
            'error'=>'User list is restricted to the trusted LAN. Connect with a private LAN address or ask the server admin to configure restricted remote enrollment.'
        ]);exit;
    }
    // Restricted to explicitly enabled, trusted deployments: return each eligible
    // user's number, display name and the IP of their first assigned monitor.
    if($action==='directory'){
        $st=$db->prepare("SELECT u.user_no,u.name,
               (SELECT m.ip_address FROM tbl_monitor_ips m WHERE m.owner_user_id=u.id ORDER BY m.id LIMIT 1) AS ip_address,
               (SELECT COUNT(*) FROM tbl_monitor_ips m WHERE m.owner_user_id=u.id) AS device_count
               FROM users u WHERE u.user_no IS NOT NULL
               ORDER BY u.user_no LIMIT 10000");
        $st->execute();$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        echo json_encode(['success'=>true,'users'=>array_map(static fn($u)=>[
            'user_no'=>(string)$u['user_no'],
            'full_name'=>(string)$u['name'],
            'ip_address'=>(string)($u['ip_address']??'—'),
            'device_count'=>(int)$u['device_count']
        ],$rows)]);exit;
    }
    // First-device enrollment is available only to clients satisfying the
    // same restricted-LAN access gate as the User No directory. It can only
    // create a monitor for a user who currently owns none.
    if($action==='bootstrap_monitor'){
        $userNo=trim((string)($data['user_no']??''));
        if(!preg_match('/^[1-9][0-9]{0,14}$/D',$userNo)){
            http_response_code(400);echo json_encode(['error'=>'Select a valid User No']);exit;
        }
        $fields=clientMonitorFields($data);
        if(isset($fields['error'])){
            http_response_code(400);echo json_encode(['error'=>$fields['error']]);exit;
        }
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|enroll');
        $st=$db->prepare('SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $st->bind_param('s',$key);$st->execute();$hits=(int)$st->get_result()->fetch_assoc()['hits'];$st->close();
        if($hits>=30){http_response_code(429);echo json_encode(['error'=>'Too many enrollment attempts']);exit;}
        $st=$db->prepare('INSERT INTO login_attempts(attempt_key) VALUES (?)');
        $st->bind_param('s',$key);$st->execute();$st->close();
        $db->begin_transaction();
        try{
            $st=$db->prepare('SELECT id FROM users WHERE user_no=? FOR UPDATE');
            $st->bind_param('s',$userNo);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
            if(!$user){
                $db->rollback();http_response_code(404);
                echo json_encode(['error'=>'Selected user was not found']);exit;
            }
            $uid=(int)$user['id'];
            // A User No may own many independent monitors. A lock belongs
            // only to its monitor, never to the user or a new installation.
            $ip=(string)($_SERVER['REMOTE_ADDR']??'');
            if(!filter_var($ip,FILTER_VALIDATE_IP))throw new RuntimeException('Invalid client address');
            $mac=null;$unlocked=0;
            $platform=$fields['platform'];$type=$fields['type'];$remark=$fields['remark'];
            $st=$db->prepare('INSERT INTO tbl_monitor_ips(ip_address,mac_address,owner_user_id,platform,type,remark,assignment_locked,settings_locked) VALUES (?,?,?,?,?,?,?,?)');
            $st->bind_param('ssisssii',$ip,$mac,$uid,$platform,$type,$remark,$unlocked,$unlocked);
            $st->execute();$mid=(int)$db->insert_id;$st->close();
            $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
            $st=$db->prepare("INSERT INTO client_sessions(token_hash,user_id,monitor_id,session_type,expires_at) VALUES (?,?,?,'device',NULL)");
            $st->bind_param('sii',$hash,$uid,$mid);$st->execute();$st->close();
            $db->commit();
            echo json_encode(['success'=>true,'token'=>$token,'monitor_id'=>$mid,'user_no'=>$userNo]);exit;
        }catch(Throwable $failure){$db->rollback();throw $failure;}
    }
    // A fresh machine must register its OWN monitor. Never silently bind its
    // session to the user's first device (which may be locked on another
    // physical machine), or inherit somebody else's screenshots and logs.
    // The existing clients understand no_monitors as Add new... mode.
    if($action==='enroll'){
        $userNo=trim((string)($data['user_no']??''));
        if(!preg_match('/^[1-9][0-9]{0,14}$/D',$userNo)){
            http_response_code(400);echo json_encode(['error'=>'Invalid User No']);exit;
        }
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|enroll');
        $st=$db->prepare('SELECT COUNT(*) AS hits FROM login_attempts WHERE attempt_key=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $st->bind_param('s',$key);$st->execute();$hits=(int)$st->get_result()->fetch_assoc()['hits'];$st->close();
        if($hits>=30){http_response_code(429);echo json_encode(['error'=>'Too many enrollment attempts']);exit;}
        $st=$db->prepare('INSERT INTO login_attempts (attempt_key) VALUES (?)');
        $st->bind_param('s',$key);$st->execute();$st->close();
        $st=$db->prepare('SELECT id FROM users WHERE user_no=? LIMIT 1');
        $st->bind_param('s',$userNo);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
        if(!$user){http_response_code(404);echo json_encode(['error'=>'User No not found']);exit;}
        http_response_code(409);
        echo json_encode(['success'=>false,'code'=>'no_monitors',
            'error'=>'Create a monitor for this installation.','user_no'=>$userNo]);exit;
    }
    if($action==='signup'||$action==='login'){
        http_response_code(410);
        echo json_encode(['error'=>'Desktop passwords were retired. Select User No in Monitor Settings.','code'=>'use_user_no']);exit;
    }
    $session=clientAuthenticatedSession($db,false);
    if(!$session || ($session['session_type']??'')!=='device'){
        http_response_code(401);echo json_encode(['error'=>'Select a User No to start monitoring']);exit;
    }
    $userId=(int)$session['user_id'];
    if($action==='switch_user'){
        $newNo=(string)($data['user_no']??'');
        if(!preg_match('/^[1-9][0-9]{0,14}$/D',$newNo)){
            http_response_code(400);echo json_encode(['error'=>'Invalid User No']);exit;
        }
        // Changing a user must never silently transfer a monitor to another
        // account. Select the first monitor already owned by the target user.
        $mid=(int)($session['monitor_id']??0);
        $db->begin_transaction();
        try {
            $st=$db->prepare('SELECT owner_user_id,assignment_locked,settings_locked FROM tbl_monitor_ips WHERE id=? FOR UPDATE');
            $st->bind_param('i',$mid);$st->execute();$current=$st->get_result()->fetch_assoc();$st->close();
            if(!$current || (int)$current['owner_user_id']!==$userId){
                $db->rollback();http_response_code(403);
                echo json_encode(['error'=>'The selected device is no longer assigned to this account']);exit;
            }
            if((int)$current['assignment_locked']===1||(int)$current['settings_locked']===1){
                $db->rollback();http_response_code(423);
                echo json_encode(['error'=>'Settings are locked. Ask your administrator.','code'=>'settings_locked']);exit;
            }
            $st=$db->prepare('SELECT id FROM users WHERE user_no=? LIMIT 1');
            $st->bind_param('s',$newNo);$st->execute();$target=$st->get_result()->fetch_assoc();$st->close();
            $db->rollback();
            if(!$target){http_response_code(404);echo json_encode(['error'=>'User No not found']);exit;}
            // Do not switch to another machine's existing monitor.
            http_response_code(409);
            echo json_encode(['success'=>false,'code'=>'no_monitors',
                'error'=>'Create a monitor for this installation.','user_no'=>$newNo]);exit;
        }catch(Throwable $failure){$db->rollback();throw $failure;}
    }
    if($action==='restore'){
        $st=$db->prepare('SELECT id,name,user_no FROM users WHERE id=?');
        $st->bind_param('i',$userId);$st->execute();$user=$st->get_result()->fetch_assoc();$st->close();
        $st=$db->prepare("SELECT id,CONCAT('Device #',id) AS name,ip_address,platform,type,remark,assignment_locked,settings_locked,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY id");
        $st->bind_param('i',$userId);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        $monitors=array_map(static function(array $m):array {
            $m['id']=(int)$m['id'];
            $m['settings_locked']=!empty($m['settings_locked'])||!empty($m['assignment_locked']);
            $m['assignment_locked']=$m['settings_locked'];
            return $m;
        },$monitors);
        $monitorId=(int)($session['monitor_id']??0);
        if($monitorId && !in_array($monitorId,array_map(static fn($m)=>(int)$m['id'],$monitors),true))$monitorId=0;
        $locked=false;
        foreach($monitors as $monitor){
            if((int)$monitor['id']===$monitorId){$locked=!empty($monitor['settings_locked']);break;}
        }
        echo json_encode(['success'=>true,'user'=>$user,'monitors'=>$monitors,
            'monitor_id'=>$monitorId,'settings_locked'=>$locked,'assignment_locked'=>$locked]);exit;
    }
    if($action==='monitors'){
        $st=$db->prepare("SELECT id,CONCAT('Device #',id) AS name,ip_address,platform,type,remark,assignment_locked,settings_locked,updated_at FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY id");
        $st->bind_param('i',$userId);$st->execute();$monitors=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
        $monitors=array_map(static function(array $m):array {
            $m['id']=(int)$m['id'];
            $m['settings_locked']=!empty($m['settings_locked'])||!empty($m['assignment_locked']);
            $m['assignment_locked']=$m['settings_locked'];
            return $m;
        },$monitors);
        echo json_encode(['success'=>true,'monitors'=>$monitors]);exit;
    }
    if($action==='select'){
        $monitorId=filter_var($data['monitor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$monitorId){http_response_code(400);echo json_encode(['error'=>'Invalid monitor ID']);exit;}
        // A unified lock also freezes monitor switching for this installation.
        $currentId=(int)($session['monitor_id']??0);
        if($currentId!==$monitorId && $currentId>0){
            $st=$db->prepare('SELECT assignment_locked,settings_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=?');
            $st->bind_param('ii',$currentId,$userId);$st->execute();$origin=$st->get_result()->fetch_assoc();$st->close();
            if(!$origin || $origin['settings_locked'] || $origin['assignment_locked']){
                http_response_code(423);echo json_encode(['error'=>'Settings are locked. Ask your administrator.','code'=>'settings_locked']);exit;
            }
        }
        // Only a monitor owned by this session's user may be activated.
        $st=$db->prepare('SELECT id,settings_locked,assignment_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=?');
        $st->bind_param('ii',$monitorId,$userId);$st->execute();$owned=$st->get_result()->fetch_assoc();$st->close();
        if(!$owned){http_response_code(403);echo json_encode(['error'=>'Monitor not assigned to your account']);exit;}
        if($currentId!==$monitorId && ($owned['settings_locked']||$owned['assignment_locked'])){
            http_response_code(423);
            echo json_encode(['error'=>'The selected monitor is locked on another installation.','code'=>'settings_locked']);exit;
        }
        $st=$db->prepare('UPDATE client_sessions SET monitor_id=? WHERE id=? AND user_id=?');
        $st->bind_param('iii',$monitorId,$session['id'],$userId);$st->execute();$st->close();
        echo json_encode(['success'=>true,'monitor_id'=>(int)$monitorId]);exit;
    }
    if($action==='lock_settings'){
        $mid=(int)($session['monitor_id']??0);
        if($mid<1){http_response_code(400);echo json_encode(['error'=>'Select a monitor']);exit;}
        $hasFields=isset($data['os'],$data['machine']);
        $fields=$hasFields?clientMonitorFields($data):[];
        if(isset($fields['error'])){
            http_response_code(400);echo json_encode(['error'=>$fields['error']]);exit;
        }
        // Save the displayed fields and lock in the same transaction, so
        // pressing Lock Settings cannot silently discard unsaved changes.
        $db->begin_transaction();
        try{
            $st=$db->prepare('SELECT settings_locked,assignment_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=? FOR UPDATE');
            $st->bind_param('ii',$mid,$userId);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();
            if(!$row){
                $db->rollback();http_response_code(403);
                echo json_encode(['error'=>'Monitor not assigned to this user']);exit;
            }
            if(!(int)$row['settings_locked']&&!(int)$row['assignment_locked']){
                if($hasFields){
                    $platform=$fields['platform'];$type=$fields['type'];$remark=$fields['remark'];
                    $st=$db->prepare('UPDATE tbl_monitor_ips SET platform=?,type=?,remark=?,settings_locked=1,assignment_locked=1 WHERE id=? AND owner_user_id=?');
                    $st->bind_param('sssii',$platform,$type,$remark,$mid,$userId);
                }else{
                    $st=$db->prepare('UPDATE tbl_monitor_ips SET settings_locked=1,assignment_locked=1 WHERE id=? AND owner_user_id=?');
                    $st->bind_param('ii',$mid,$userId);
                }
                $st->execute();$st->close();
            }
            $db->commit();
            echo json_encode(['success'=>true,'settings_locked'=>true,'assignment_locked'=>true]);exit;
        }catch(Throwable $failure){$db->rollback();throw $failure;}
    }
    if($action==='create_monitor'){
        $currentId=(int)($session['monitor_id']??0);
        $st=$db->prepare('SELECT assignment_locked,settings_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=?');
        $st->bind_param('ii',$currentId,$userId);$st->execute();$current=$st->get_result()->fetch_assoc();$st->close();
        if(!$current||$current['assignment_locked']||$current['settings_locked']){
            http_response_code(423);echo json_encode(['error'=>'Settings are locked. Ask your administrator.','code'=>'settings_locked']);exit;
        }
        $fields=clientMonitorFields($data);
        if(isset($fields['error'])){http_response_code(400);echo json_encode(['error'=>$fields['error']]);exit;}
        $ip=(string)($data['ip_address']??$_SERVER['REMOTE_ADDR']??'');
        if(!filter_var($ip,FILTER_VALIDATE_IP)){
            http_response_code(400);echo json_encode(['error'=>'Enter a valid device IP address']);exit;
        }
        $db->begin_transaction();
        try{
            // Lock the currently selected monitor in the same transaction as
            // creation; a concurrent admin lock must not allow a new device.
            $mid=(int)($session['monitor_id']??0);
            $st=$db->prepare('SELECT assignment_locked,settings_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=? FOR UPDATE');
            $st->bind_param('ii',$mid,$userId);$st->execute();$origin=$st->get_result()->fetch_assoc();$st->close();
            if(!$origin || $origin['assignment_locked'] || $origin['settings_locked']){
                $db->rollback();http_response_code(423);
                echo json_encode(['error'=>'Settings are locked. Ask your administrator.','code'=>'settings_locked']);exit;
            }
            $st=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
            $st->bind_param('i',$userId);$st->execute();$owner=$st->get_result()->fetch_assoc();$st->close();
            if(!$owner)throw new RuntimeException('Account not found');
            $mac=null;$locked=0;
            $st=$db->prepare('INSERT INTO tbl_monitor_ips (ip_address,mac_address,owner_user_id,platform,type,remark,assignment_locked,settings_locked) VALUES (?,?,?,?,?,?,?,?)');
            $st->bind_param('ssisssii',$ip,$mac,$userId,$fields['platform'],$fields['type'],$fields['remark'],$locked,$locked);
            $st->execute();$newId=(int)$db->insert_id;$st->close();
            $db->commit();
            echo json_encode(['success'=>true,'monitor_id'=>$newId]);exit;
        }catch(Throwable $e){$db->rollback();throw $e;}
    }
    if($action==='save_monitor'){
        $monitorId=filter_var($data['monitor_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!$monitorId){http_response_code(400);echo json_encode(['error'=>'Select an assigned monitor']);exit;}
        if((int)$session['monitor_id']!==$monitorId){
            http_response_code(403);echo json_encode(['error'=>'This monitor is not selected for this device']);exit;
        }
        $st=$db->prepare('SELECT id,settings_locked,assignment_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=? LIMIT 1');
        $st->bind_param('ii',$monitorId,$userId);$st->execute();$owned=$st->get_result()->fetch_assoc();$st->close();
        if(!$owned){http_response_code(403);echo json_encode(['error'=>'Monitor not assigned to your account']);exit;}
        if((int)$owned['settings_locked']===1||(int)$owned['assignment_locked']===1){
            http_response_code(423);echo json_encode(['error'=>'Monitor settings are locked. Ask your administrator to unlock them.','code'=>'settings_locked']);exit;
        }
        $fields=clientMonitorFields($data);
        if(isset($fields['error'])){http_response_code(400);echo json_encode(['error'=>$fields['error']]);exit;}
        // Make the lock condition part of the UPDATE, eliminating the
        // check-then-update race with an administrator locking this monitor.
        $st=$db->prepare('UPDATE tbl_monitor_ips SET platform=?,type=?,remark=? WHERE id=? AND owner_user_id=? AND assignment_locked=0 AND settings_locked=0');
        $st->bind_param('sssii',$fields['platform'],$fields['type'],$fields['remark'],$monitorId,$userId);
        $st->execute();$changed=$st->affected_rows;$st->close();
        if($changed===0){
            // A no-op edit is valid; a newly locked record is not.
            $st=$db->prepare('SELECT assignment_locked,settings_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=?');
            $st->bind_param('ii',$monitorId,$userId);$st->execute();$check=$st->get_result()->fetch_assoc();$st->close();
            if(!$check || $check['assignment_locked'] || $check['settings_locked']){
                http_response_code(423);echo json_encode(['error'=>'Settings are locked. Ask your administrator.','code'=>'settings_locked']);exit;
            }
        }
        echo json_encode(['success'=>true,'monitor_id'=>$monitorId]);exit;
    }
    if($action==='request_monitor'){
        http_response_code(400);
        echo json_encode(['error'=>'Use Add new... in Monitor Settings to create a device under the selected user.']);exit;
    }
    if($action==='logout'){
        // The administrator's lock also prevents disconnect/re-enroll flows
        // from changing the selected user through the normal application UI.
        $st=$db->prepare('SELECT assignment_locked,settings_locked FROM tbl_monitor_ips WHERE id=? AND owner_user_id=? LIMIT 1');
        $mid=(int)($session['monitor_id']??0);
        $st->bind_param('ii',$mid,$userId);$st->execute();$row=$st->get_result()->fetch_assoc();$st->close();
        if($row && ((int)$row['assignment_locked']===1||(int)$row['settings_locked']===1)){
            http_response_code(423);
            echo json_encode(['error'=>'User assignment is locked. Ask your administrator to edit the monitor.','code'=>'assignment_locked']);exit;
        }
        $st=$db->prepare('UPDATE client_sessions SET revoked_at=NOW() WHERE id=? AND user_id=?');
        $st->bind_param('ii',$session['id'],$userId);$st->execute();
        echo json_encode(['success'=>true]);exit;
    }
    http_response_code(400);echo json_encode(['error'=>'Unknown action']);
}catch(Throwable $e){
    error_log('Client auth error: '.$e->getMessage());
    http_response_code(500);echo json_encode(['error'=>'Server error']);
}
