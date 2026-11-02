<?php
// Run: php Backend/tests/v16_user_no_static_test.php
// Offline source-contract checks. Integration tests still require MySQL + clients.
$root=dirname(__DIR__,2);
$read=static function($path)use($root):string {
    $source=file_get_contents($root.'/'.$path);
    if($source===false)throw new RuntimeException('Cannot read '.$path);
    return $source;
};
$api=$read('Backend/client_auth.php');
$admin=$read('Backend/html/app/controllers/Users.php');
$adminView=$read('Backend/html/app/views/users/edit.php');
$monitor=$read('Backend/html/app/controllers/Monitors.php');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$win=$read('Windows/MonitorClient.cpp');
$linux=$read('Ubuntu/ConfigDialog.cpp');
$checks=[
    'API no pairing-code parameter'=>!str_contains($api,"pairing_code"),
    'API opt-in required'=>str_contains($api,'MONITOR_ENABLE_USER_NO_ENROLL'),
    'API monitor ownership check'=>str_contains($api,"WHERE id=? AND owner_user_id=?"),
    'API locked assignment denies changes'=>str_contains($api,'http_response_code(423)'),
    'Admin User No editable'=>str_contains($adminView,'name="user_no"'),
    'Admin rejects duplicate User No'=>str_contains($admin,'$duplicateNo'),
    'Admin pairing code action removed'=>!str_contains($monitor,'function pairing_code('),
    'Windows pairing input removed'=>!str_contains($win,'SETTINGS_PAIR_CODE'),
    'macOS pairing input removed'=>!str_contains($mac,'enrollmentRow'),
    'Ubuntu pairing input removed'=>!str_contains($linux,'pairingEdit'),
];
foreach($checks as $name=>$ok){
    if(!$ok){fwrite(STDERR,'FAIL: '.$name.PHP_EOL);exit(1);}
    echo 'PASS: '.$name.PHP_EOL;
}
