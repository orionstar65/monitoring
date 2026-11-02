<?php
// Offline source checks only: php Backend/tests/v17_user_no_contract.php
$root=dirname(__DIR__,2);
$read=static fn($path)=>file_get_contents($root.'/'.$path);
$auth=$read('Backend/html/app/controllers/Auth.php');
$users=$read('Backend/html/app/controllers/Users.php');
$base=$read('Backend/html/app/controllers/Base.php');
$api=$read('Backend/client_auth.php');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$win=$read('Windows/MonitorClient.cpp');
$linux=$read('Ubuntu/ConfigDialog.cpp');
$check=[
 'Login uses User No'=>str_contains($auth,'WHERE user_no = :user_no OR email = :email'),
 'No accounts.username in account controller'=>!str_contains($users,'username'),
 'No accounts.username in base session'=>!str_contains($base,'username'),
 'Directory exposes display fields'=>str_contains($api,"'full_name'")&&str_contains($api,"'ip_address'"),
 'Directory has network opt-in'=>str_contains($api,'MONITOR_ENABLE_USER_NO_ENROLL'),
 'Windows selector handles selection changes'=>str_contains($win,'id==SETTINGS_USER_NO && HIWORD(wp)==CBN_SELCHANGE'),
 'macOS has no select/switch buttons'=>!str_contains($mac,'let pair = action(')&&!str_contains($mac,'let switchUser = action('),
 'Ubuntu selection triggers action'=>str_contains($linux,'if(authToken.isEmpty())onPairClicked();'),
 'Ubuntu select button removed'=>!str_contains($linux,'pairButton='),
];
foreach($check as $name=>$ok){
    if(!$ok){fwrite(STDERR,"FAIL: $name\n");exit(1);}
    echo "PASS: $name\n";
}
