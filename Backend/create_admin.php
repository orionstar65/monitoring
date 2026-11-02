<?php
// CLI-only bootstrap utility, requires database migration.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/services/ClientIdentity.php';
$adminNo=getenv('MONITOR_ADMIN_USER_NO')?:'';

$email=getenv('MONITOR_ADMIN_EMAIL')?:'';
$name=getenv('MONITOR_ADMIN_NAME')?:'';
$password=getenv('MONITOR_ADMIN_PASSWORD')?:'';
if(!preg_match('/^[1-9][0-9]{0,14}$/D',$adminNo)||!filter_var($email,FILTER_VALIDATE_EMAIL)
 ||strlen($password)<12||strlen($name)<2){
    fwrite(STDERR,"Set MONITOR_ADMIN_USER_NO, MONITOR_ADMIN_EMAIL, MONITOR_ADMIN_NAME, MONITOR_ADMIN_PASSWORD (12+ chars).\n");
    exit(1);
}
try{
    $db=clientIdentityDatabase();
    $st=$db->prepare('SELECT id FROM users WHERE user_no=? OR email=? LIMIT 1');
    $st->bind_param('ss',$adminNo,$email);$st->execute();$existing=$st->get_result()->fetch_assoc();$st->close();
    if($existing){fwrite(STDERR,"Account already exists; refusing to overwrite.\n");exit(1);}
    $hash=password_hash($password,PASSWORD_DEFAULT);$role='admin';
    $st=$db->prepare('INSERT INTO users (name,user_no,email,password,role) VALUES (?,?,?,?,?)');
    $st->bind_param('sssss',$name,$adminNo,$email,$hash,$role);
    $st->execute();$newId=(int)$db->insert_id;$st->close();
    fwrite(STDOUT,"Admin created. Clear MONITOR_ADMIN_PASSWORD from environment.\n");
}catch(Throwable $e){fwrite(STDERR,"Failed: ".$e->getMessage()."\n");exit(1);}
