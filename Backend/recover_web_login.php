<?php
/**
 * Local, administrator-only recovery for username-free web accounts.
 *
 * Usage:
 *   php Backend/recover_web_login.php --list-admins
 *   MONITOR_RECOVERY_PASSWORD=... php Backend/recover_web_login.php --reset-user-no=100001
 *
 * Never expose this script through HTTP; never output stored password hashes.
 */
if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}
require_once __DIR__.'/services/ClientIdentity.php';

$args=$argv;
array_shift($args);
if(count($args)!==1){
    fwrite(STDERR,"Usage: php Backend/recover_web_login.php --list-admins | --reset-user-no=NUMBER\n");
    exit(2);
}
$command=$args[0];
$list=$command==='--list-admins';
$reset=preg_match('/^--reset-user-no=([1-9][0-9]{0,14})$/D',$command,$matches)===1;
if(!$list&&!$reset){
    fwrite(STDERR,"Unknown action. Use --list-admins or --reset-user-no=NUMBER.\n");
    exit(2);
}
try {
    $db=clientIdentityDatabase();
    $columns=$db->query("SHOW COLUMNS FROM users LIKE 'user_no'");
    if(!$columns||$columns->num_rows===0){
        fwrite(STDERR,"The users.user_no column is missing. Apply the prerequisite v1.5 schema migration first (after a backup).\n");
        exit(1);
    }
    if($list){
        $rows=$db->query("SELECT id,user_no,name,password FROM users WHERE role='admin' ORDER BY id");
        if(!$rows || $rows->num_rows===0){
            fwrite(STDERR,"No administrator accounts found. Do not create a duplicate account until the database is checked.\n");
            exit(1);
        }
        fwrite(STDOUT,"Administrator accounts (no passwords are displayed):\n");
        while($account=$rows->fetch_assoc()){
            $number=$account['user_no']===null?'MISSING':(string)$account['user_no'];
            $hash=$account['password']??'';
            $hashType=password_get_info($hash)['algoName']??'unknown';
            $passwordStatus=$hashType==='unknown'?'reset needed / legacy unusable hash':'password hash present';
            fprintf(STDOUT,"ID: %d | User No: %s | Full Name: %s | %s\n",
                (int)$account['id'],$number,
                (string)($account['name']??''),$passwordStatus);
        }
        fwrite(STDOUT,"Use a listed User No with the account's existing password.\n");
        exit(0);
    }

    $newPassword=getenv('MONITOR_RECOVERY_PASSWORD');
    if(!is_string($newPassword)||strlen($newPassword)<6||strlen($newPassword)>1024){
        fwrite(STDERR,"Set MONITOR_RECOVERY_PASSWORD to a new password of 6-1024 characters. Do not pass passwords in command arguments.\n");
        exit(2);
    }
    $number=$matches[1];
    $stmt=$db->prepare("SELECT id,role FROM users WHERE user_no=? LIMIT 1");
    $stmt->bind_param('s',$number);
    $stmt->execute();
    $account=$stmt->get_result()->fetch_assoc();
    $stmt->close();
    if(!$account || $account['role']!=='admin'){
        fwrite(STDERR,"No admin account matches this User No. Nothing changed.\n");
        exit(1);
    }
    $hash=password_hash($newPassword,PASSWORD_DEFAULT);
    if($hash===false)throw new RuntimeException("Password hashing failed.");
    $id=(int)$account['id'];
    $stmt=$db->prepare("UPDATE users SET password=?,must_change_password=1 WHERE id=? AND role='admin'");
    $stmt->bind_param('si',$hash,$id);
    $stmt->execute();
    $affected=$stmt->affected_rows;
    $stmt->close();
    if($affected!==1)throw new RuntimeException("No account updated.");
    fwrite(STDOUT,"Admin password reset for User No {$number}. Sign in with the new password and change it immediately.\n");
} catch(Throwable $failure) {
    error_log('Web login recovery failed: '.$failure->getMessage());
    fwrite(STDERR,"Recovery failed. Check the server PHP error log and database migrations.\n");
    exit(1);
}
