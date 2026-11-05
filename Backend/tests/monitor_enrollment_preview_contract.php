<?php
// Run: php Backend/tests/monitor_enrollment_preview_contract.php
// Source-level regression checks; does not require a running database.
$root=dirname(__DIR__,2);
$source=static function(string $path) use($root):string {
    $code=file_get_contents($root.'/'.$path);
    if($code===false)throw new RuntimeException('Missing '.$path);
    return $code;
};
$api=$source('Backend/client_auth.php');
$win=$source('Windows/MonitorClient.cpp');
$mac=$source('Mac/MonitorClient/AppDelegate.swift');
$ubuntu=$source('Ubuntu/ConfigDialog.cpp');
$checks=[
 'Preview query remains owner-scoped'=>str_contains($api,'FROM tbl_monitor_ips WHERE owner_user_id=? ORDER BY id LIMIT 1000'),
 'Preview does not issue a device token'=>str_contains($api,"'preview_only'=>true"),
 'Enrollment returns user monitor previews'=>str_contains($api,"'user_no'=>$userNo,'monitors'=>$preview"),
 'Switch-user returns target monitor previews'=>str_contains($api,"'user_no'=>$newNo,'monitors'=>$preview"),
 'New monitor gets its own device session'=>str_contains($api,"'bootstrap_monitor'") && str_contains($api,'INSERT INTO client_sessions(token_hash,user_id,monitor_id'),
 'Windows renders preview list'=>str_contains($win,'settingsMonitors=response.get("monitors",Json::arrayValue)'),
 'Windows blocks preview activation'=>str_contains($win,'settingsPendingUserNo.empty() && chosen!=selectedMonitorId.load()'),
 'macOS renders preview list'=>str_contains($mac,'availableMonitors = response?["monitors"] as? [[String: Any]] ?? []'),
 'macOS blocks pending monitor reuse'=>str_contains($mac,'guard picker.selectedItem?.representedObject as? Int == -1 else'),
 'Ubuntu renders preview list'=>str_contains($ubuntu,'updateMonitorList(result.value("monitors").toArray())'),
 'Ubuntu blocks preview activation'=>str_contains($ubuntu,'if(id>0 && !pendingUserNo.isEmpty())'),
];
$failed=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)$failed++;
}
exit($failed?1:0);
