<?php
/**
 * Read-only server-side User No directory diagnostics.
 * Usage: php Backend/diagnose_user_directory.php
 * Run locally from your trusted backend host. Nothing is modified.
 */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/services/ClientIdentity.php';
try{
    $db=clientIdentityDatabase();
    $fields=[];
    $columns=$db->query('SHOW COLUMNS FROM users');
    while($column=$columns->fetch_assoc())$fields[$column['Field']]=true;
    foreach(['id','name','role','user_no'] as $field){
        if(!isset($fields[$field])){
            fwrite(STDERR,"Missing users.$field. Review the v1.5 identity migrations before using desktop enrollment.\n");
            exit(1);
        }
    }
    $rows=$db->query("SELECT u.id,u.user_no,u.name,u.role,
       (SELECT m.ip_address FROM tbl_monitor_ips m WHERE m.owner_user_id=u.id ORDER BY m.id LIMIT 1) AS first_ip,
       (SELECT COUNT(*) FROM tbl_monitor_ips m WHERE m.owner_user_id=u.id) AS monitors
       FROM users u ORDER BY u.id");
    $total=0;$eligible=0;$missing=0;
    fwrite(STDOUT,"User No directory on this backend (no passwords displayed):\n");
    while($user=$rows->fetch_assoc()){
        $total++;
        $number=$user['user_no'];
        if($number===null)$missing++;else $eligible++;
        fwrite(STDOUT,sprintf("ID=%d | User No=%s | Full Name=%s | Role=%s | IP=%s | Monitors=%d\n",
            (int)$user['id'],
            $number===null?'MISSING':$number,
            (string)$user['name'],
            (string)$user['role'],
            (string)($user['first_ip']??'—'),
            (int)$user['monitors']));
    }
    fwrite(STDOUT,"Accounts: $total; selectable numbers: $eligible; missing User Nos: $missing.\n");
    if($missing)fwrite(STDERR,"Some accounts have no User No. Verify the v1.5 User No migration and backfill.\n");
    if(!$total)fwrite(STDERR,"No users exist yet. Create a user account in the web portal.\n");
    fwrite(STDOUT,"Direct trusted LAN desktop requests can list users. Public/proxied requests require explicit restricted server configuration.\n");
    $db->close();
}catch(Throwable $e){
    error_log('Directory diagnostics failed: '.$e->getMessage());
    fwrite(STDERR,"Directory diagnostics failed. Check MySQL and migration state.\n");
    exit(1);
}
