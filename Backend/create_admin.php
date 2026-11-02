<?php
// CLI-only bootstrap utility, requires database migration.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/services/ClientIdentity.php';
$login=getenv('MONITOR_ADMIN_USERNAME')?:'';
$email=getenv('MONITOR_ADMIN_EMAIL')?:'';
$name=getenv('MONITOR_ADMIN_NAME')?:$login;
$password=getenv('MONITOR_ADMIN_PASSWORD')?:'';
if(!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$login)||!filter_var($email,FILTER_VALIDATE_EMAIL)
 ||strlen($password)<12||strlen($name)<2){
    fwrite(STDERR,"Set MONITOR_ADMIN_USERNAME, MONITOR_ADMIN_EMAIL, MONITOR_ADMIN_NAME, MONITOR_ADMIN_PASSWORD (12+ chars).\n");
    exit(1);
}
try{
    $db=clientIdentityDatabase();
    $st=$db->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
    $st->bind_param('ss',$login,$email);$st->execute();$existing=$st->get_result()->fetch_assoc();$st->close();
    if($existing){fwrite(STDERR,"Account already exists; refusing to overwrite.\n");exit(1);}
    $hash=password_hash($password,PASSWORD_DEFAULT);$role='admin';
    $st=$db->prepare('INSERT INTO users (name,username,email,password,role) VALUES (?,?,?,?,?)');
    $st->bind_param('sssss',$name,$login,$email,$hash,$role);
    $st->execute();$newId=(int)$db->insert_id;$st->close();
    $st=$db->prepare('UPDATE users SET user_no=? WHERE id=?');
    $number=$newId+100000;$st->bind_param('ii',$number,$newId);$st->execute();$st->close();
    fwrite(STDOUT,"Admin created. Clear MONITOR_ADMIN_PASSWORD from environment.\n");
}catch(Throwable $e){fwrite(STDERR,"Failed: ".$e->getMessage()."\n");exit(1);}
