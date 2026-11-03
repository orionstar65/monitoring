<?php
// Source-level checks. Run locally: php Backend/tests/web_login_contract_test.php
$root=dirname(__DIR__,2);
function source(string $path): string {
    global $root;
    $content=file_get_contents($root.'/'.$path);
    if($content===false)throw new RuntimeException('Cannot read '.$path);
    return $content;
}
$auth=source('Backend/html/app/controllers/Auth.php');
$form=source('Backend/html/app/views/auth/login.php');
$recovery=source('Backend/recover_web_login.php');
$migration=source('Backend/migrations/2026_11_02_remove_username_v17.sql');
$checks=[
    'User No lookup uses exact parameter'=>str_contains($auth,'WHERE user_no=:user_no LIMIT 1'),
    'Email lookup uses exact parameter'=>str_contains($auth,'WHERE email=:email LIMIT 1'),
    'Full name not used for login'=>!str_contains($auth,'WHERE name=')&&!str_contains($auth,'WHERE username'),
    'Passwords still verified with PHP password API'=>str_contains($auth,'password_verify('),
    'User No and email are shown on form'=>str_contains($form,'User No or email'),
    'Old username login is identified as retired'=>str_contains($form,'Previous usernames are no longer accepted'),
    'Recovery script is CLI-only'=>str_contains($recovery,"PHP_SAPI!=='cli'"),
    'Recovery script never returns hashes'=>str_contains($recovery,'password_get_info')&&str_contains($recovery,'password_hash'),
    'Password reset requires explicit admin User No'=>str_contains($recovery,'--reset-user-no='),
    'Recovery password comes from environment'=>str_contains($recovery,'MONITOR_RECOVERY_PASSWORD'),
    'Username migration preserves password hash'=>str_contains($migration,'DROP COLUMN username')&&!str_contains($migration,'password'),
    'Windows version stays at 1.5'=>str_contains(source('Windows/CMakeLists.txt'),'VERSION 1.5'),
    'macOS version stays at 1.5'=>str_contains(source('Mac/MonitorClient.xcodeproj/project.pbxproj'),'MARKETING_VERSION = 1.5;'),
    'Ubuntu version stays at 1.5'=>str_contains(source('Ubuntu/version.h'),'MONITORAPP_VERSION "1.5"'),
];
$failed=0;
foreach($checks as $label=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$ok)$failed++;
}
if($failed)exit(1);
