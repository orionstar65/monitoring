<?php
// Offline source checks only: php Backend/tests/v17_user_no_contract.php
$root=dirname(__DIR__,2);
$read=static fn($path)=>file_get_contents($root.'/'.$path);
$auth=$read('Backend/html/app/controllers/Auth.php');
$users=$read('Backend/html/app/controllers/Users.php');
$base=$read('Backend/html/app/controllers/Base.php');
$api=$read('Backend/client_auth.php');
$diagnostics=$read('Backend/diagnose_user_directory.php');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$win=$read('Windows/MonitorClient.cpp');
$linux=$read('Ubuntu/ConfigDialog.cpp');
$webUsers=$read('Backend/html/app/views/users/index.php');
$webMonitors=$read('Backend/html/app/views/monitors/index.php');
$winCmake=$read('Windows/CMakeLists.txt');
$macProject=$read('Mac/MonitorClient.xcodeproj/project.pbxproj');
$ubuntuVersion=$read('Ubuntu/version.h');
$check=[
 'Login uses User No'=>str_contains($auth,'WHERE user_no=:user_no LIMIT 1'),
 'Login rejects email'=>!str_contains($auth,'WHERE email=:email LIMIT 1'),
 'LAN-scoped directory fallback'=>str_contains($read('Backend/services/ClientIdentity.php'),'monitoringLanEnrollmentClient()'),
 'Directory includes administrator accounts'=>str_contains($api,"FROM users u WHERE u.user_no IS NOT NULL"),
 'CLI-only read-only directory diagnostics'=>str_contains($diagnostics,"PHP_SAPI!=='cli'")&&!str_contains($diagnostics,'UPDATE users'),
 'No accounts.username in account controller'=>!str_contains($users,'username'),
 'No accounts.username in base session'=>!str_contains($base,'username'),
 'Directory exposes display fields'=>str_contains($api,"'full_name'")&&str_contains($api,"'ip_address'"),
 'Directory uses LAN guard'=>str_contains($api,'monitoringNoCodeEnrollmentAllowed($db)'),
 'Remote access requires explicit network opt-in'=>str_contains($read('Backend/services/ClientIdentity.php'),'MONITOR_ENABLE_USER_NO_ENROLL'),
 'Windows selector handles selection changes'=>str_contains($win,'id==SETTINGS_USER_NO && HIWORD(wp)==CBN_SELCHANGE'),
 'macOS has no select/switch buttons'=>!str_contains($mac,'let pair = action(')&&!str_contains($mac,'let switchUser = action('),
 'Ubuntu selection triggers action'=>str_contains($linux,'if(authToken.isEmpty())onPairClicked();'),
 'Ubuntu select button removed'=>!str_contains($linux,'pairButton='),
 'Users table separate columns'=>str_contains($webUsers,'<th>User No</th><th>Full Name</th><th>IP Address</th>'),
 'Monitors table separate columns'=>str_contains($webMonitors,'<th>User No</th><th>Full Name</th><th>IP Address</th>'),
 'Windows keeps v1.5'=>str_contains($winCmake,'VERSION 1.5'),
 'macOS keeps v1.5'=>str_contains($macProject,'MARKETING_VERSION = 1.5;'),
 'Ubuntu keeps v1.5'=>str_contains($ubuntuVersion,'MONITORAPP_VERSION "1.5"'),
 'Windows reports directory failure'=>str_contains($win,'settingsDirectoryError'),
 'macOS reports directory failure'=>str_contains($mac,'directoryError ='),
 'Ubuntu reports directory failure'=>str_contains($linux,'directoryError=result.value("error")'),
 'macOS top-pinned document'=>str_contains($mac,'MonitorSettingsDocumentView'),
 'Ubuntu top-pinned content'=>str_contains($linux,'content->setAlignment(Qt::AlignTop)'),
];
foreach($check as $name=>$ok){
    if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
