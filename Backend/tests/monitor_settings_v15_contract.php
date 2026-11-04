<?php
// Pure source contract checks: php Backend/tests/monitor_settings_v15_contract.php
// Does not require MySQL, live clients or GitHub Actions.
$root=dirname(__DIR__,2);
$read=static function(string $path) use ($root): string {
    $source=file_get_contents($root.'/'.$path);
    if($source===false)throw new RuntimeException('Cannot read '.$path);
    return $source;
};
$api=$read('Backend/client_auth.php');
$service=$read('Backend/html/app/services/DatabaseService.php');
$events=$read('Backend/eventhandler.php');
$upload=$read('Backend/webapi.php');
$admin=$read('Backend/html/app/controllers/Monitors.php');
$edit=$read('Backend/html/app/views/monitors/edit.php');
$listing=$read('Backend/html/app/views/monitors/index.php');
$migration=$read('Backend/migrations/2026_11_04_monitor_settings_lock.sql');
$windows=$read('Windows/MonitorClient.cpp');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$ubuntu=$read('Ubuntu/ConfigDialog.cpp');
$checks=[
    'Directory includes device counts'=>str_contains($api,"'device_count'"),
    'Directory includes every account role'=>str_contains($api,"FROM users u WHERE u.user_no IS NOT NULL"),
    'Switch user does not transfer monitor ownership'=>!str_contains($api,"UPDATE tbl_monitor_ips SET owner_user_id=? WHERE id=?"),
    'Switch user selects owned monitor'=>str_contains($api,'JOIN tbl_monitor_ips m ON m.owner_user_id=u.id'),
    'No-device enrollment returns explicit code'=>str_contains($api,"'code'=>'no_monitors'"),
    'First device requires LAN restricted action'=>str_contains($api,"'bootstrap_monitor'"),
    'Authenticated client can create monitor'=>str_contains($api,"if(\$action==='create_monitor')"),
    'Client settings lock endpoint exists'=>str_contains($api,"if(\$action==='lock_settings')"),
    'Save endpoint checks lock on server'=>str_contains($api,"'code'=>'settings_locked'"),
    'Monitor IDs normalized to JSON integers'=>str_contains($api,"\$m['id']=(int)\$m['id']"),
    'Client monitor locks normalized to JSON booleans'=>str_contains($api,"\$m['settings_locked']=(bool)\$m['settings_locked']"),
    'Admin can unlock settings'=>str_contains($admin,"'settings_locked' => \$settings_locked"),
    'Admin settings checkbox exists'=>str_contains($edit,'name="settings_locked"'),
    'Monitor table displays settings state'=>str_contains($listing,"\$m['settings_locked']"),
    'Migration adds separate lock'=>str_contains($migration,'ADD COLUMN settings_locked'),
    'Migration adds observed IP'=>str_contains($migration,'ADD COLUMN last_seen_ip'),
    'Migration does not drop previous IP index'=>!str_contains($migration,'DROP INDEX uk_ip_address'),
    'Telemetry requires authenticated ID'=>str_contains($service,'return $this->authenticatedMonitorId;'),
    'Authenticated activity tracks last_seen_ip'=>str_contains($service,'last_seen_ip=? WHERE id=?'),
    'Event handler validates monitor session'=>str_contains($events,'requireClientMonitor($mysqli)'),
    'Screenshot handler validates monitor session'=>str_contains($upload,'requireClientMonitor($mysqli)'),
    'Screenshot uses bound monitor ID'=>str_contains($upload,"\$monitor_id=(int)\$GLOBALS['monitorId']"),
    'Windows selector shows count'=>str_contains($windows,'std::to_string(count)+" Devices)"'),
    'Windows Add new monitor option'=>str_contains($windows,'L"Add new..."'),
    'Windows radio UI'=>str_contains($windows,'BS_AUTORADIOBUTTON'),
    'Windows Lock Settings button'=>str_contains($windows,'SETTINGS_LOCK_SETTINGS'),
    'macOS selector shows count'=>str_contains($mac,'(\\(count) Devices)'),
    'macOS radio UI'=>str_contains($mac,'radioButtonWithTitle:'),
    'macOS new monitor option'=>str_contains($mac,'picker.addItem(withTitle: "Add new...")'),
    'macOS lock command'=>str_contains($mac,'case "lock":'),
    'Ubuntu selector shows count'=>str_contains($ubuntu,'(%3 Devices)'),
    'Ubuntu radio UI'=>str_contains($ubuntu,'QButtonGroup'),
    'Ubuntu Add new option'=>str_contains($ubuntu,'monitorSelector->addItem("Add new...",-1)'),
    'Ubuntu lock action'=>str_contains($ubuntu,'void ConfigDialog::onLockSettingsClicked()'),
    'Windows stays v1.5'=>str_contains($read('Windows/CMakeLists.txt'),'VERSION 1.5'),
    'macOS stays v1.5'=>str_contains($read('Mac/MonitorClient.xcodeproj/project.pbxproj'),'MARKETING_VERSION = 1.5;'),
    'Ubuntu stays v1.5'=>str_contains($read('Ubuntu/version.h'),'MONITORAPP_VERSION "1.5"'),
];
$failed=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)$failed++;
}
exit($failed?1:0);
