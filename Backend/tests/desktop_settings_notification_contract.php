<?php
// Run: php Backend/tests/desktop_settings_notification_contract.php
// Source contracts for desktop Settings UI, diagnostics and login handling.
// These do not replace compiling and running each native client.
$root=dirname(__DIR__,2);
$read=static function(string $name) use ($root): string {
    $contents=file_get_contents($root.'/'.$name);
    if($contents===false)throw new RuntimeException('Could not read '.$name);
    return $contents;
};
$ubuntu=$read('Ubuntu/ConfigDialog.cpp');
$ubuntuClient=$read('Ubuntu/MonitorClient.cpp');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$windows=$read('Windows/MonitorClient.cpp');
$auth=$read('Backend/html/app/controllers/Auth.php');
$upload=$read('Backend/webapi.php');
$login=$read('Backend/html/app/views/auth/login.php');
$checks=[
    'Ubuntu Settings has no redundant separator rule'=>
        !str_contains($ubuntu,'auto divider=[&]()')
        &&!str_contains($ubuntu,'    divider();'),
    'Windows Settings has no etched separator lines'=>
        !str_contains($windows,'SS_ETCHEDHORZ,13,277')
        &&!str_contains($windows,'SS_ETCHEDHORZ,13,267')
        &&!str_contains($windows,'SS_ETCHEDHORZ,13,336'),
    'macOS Settings has no inserted separator rules'=>
        !str_contains($mac,'func rule()')&&!str_contains($mac,'        rule()'),
    'Ubuntu displays authenticated user identity, hides credential labels'=>
        str_contains($ubuntu,'accountInfoLabel->setVisible(enrolled)')
        &&str_contains($ubuntu,'userNoFieldLabel->setVisible(!enrolled)')
        &&str_contains($ubuntu,'passwordFieldLabel->setVisible(!enrolled)')
        &&str_contains($ubuntu,'activeUserName'),
    'Windows displays authenticated User No and name, hides login controls'=>
        str_contains($windows,'SETTINGS_ACCOUNT_INFO')
        &&str_contains($windows,'signedInFullName')
        &&str_contains($windows,'const int signInControls[]'),
    'macOS displays authenticated User No and name, hides login controls'=>
        str_contains($mac,'signedInRow.isHidden = !enrolled')
        &&str_contains($mac,'passwordRow.isHidden = enrolled')
        &&str_contains($mac,'signed-in-name'),
    'Ubuntu notifications toggle persists immediately'=>
        str_contains($ubuntu,'connect(notificationsCheck,&QCheckBox::toggled')
        &&str_contains($ubuntuClient,'Notifications/enabled'),
    'Windows notifications toggle suppresses OS balloons immediately'=>
        str_contains($windows,'if(id==SETTINGS_NOTIFY && HIWORD(wp)==BN_CLICKED)')
        &&str_contains($windows,'clearPendingTrayNotification()')
        &&str_contains($windows,'if (!notificationsEnabled.load()) return;'),
    'macOS notifications toggle suppresses pending alerts immediately'=>
        str_contains($mac,'case "notifications":')
        &&str_contains($mac,'center.removeAllPendingNotificationRequests()')
        &&str_contains($mac,'guard notificationsEnabled else { return }'),
    'Ubuntu screenshot upload reports server error details'=>
        str_contains($ubuntuClient,'screenshotUploadError')
        &&str_contains($ubuntuClient,'body.value("error").toString()')
        &&str_contains($ubuntuClient,'body.value("status").toString()=="success"'),
    'Screenshot API returns actionable errors'=>
        str_contains($upload,'failScreenshotUpload(')
        &&str_contains($upload,'Screenshot storage directory is not writable'),
    'Login redirects signed-in users and indicates missing migration'=>
        str_contains($auth,'loginDestination($this->user)')
        &&str_contains($auth,'loginSetupError(')
        &&str_contains($login,'name="csrf_token"'),
];
$failed=0;
foreach($checks as $label=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$pass)$failed++;
}
echo count($checks).' checks, '.$failed.' failures'.PHP_EOL;
exit($failed?1:0);
