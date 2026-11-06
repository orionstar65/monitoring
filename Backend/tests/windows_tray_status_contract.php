<?php
// Run: php Backend/tests/windows_tray_status_contract.php
// Source-level checks only. A real MSYS2/MinGW build and Windows tray smoke
// test are still required before releasing a Windows installer.
$root=dirname(__DIR__,2);
$path=$root.'/Windows/MonitorClient.cpp';
$source=file_get_contents($path);
if($source===false){fwrite(STDERR,"Missing Windows source\n");exit(1);}
$checks=[
    'Each switch branch containing a local variable has its own scope'=>
        preg_match('/case ID_TRAY_NOTIFICATIONS:\s*\{[\s\S]*?const bool enabled=[\s\S]*?break;\s*\}\s*case ID_TRAY_CONFIG_REFRESH:/',$source)===1,
    'Removed unused legacy settings helper functions'=>
        !str_contains($source,'static bool testServerAddress(') &&
        !str_contains($source,'static void loadUserDirectory('),
    'Server connection is atomic across worker and UI threads'=>
        str_contains($source,'std::atomic<bool> serverConnected{false};'),
    'The status factory is declared before setup calls it'=>
        strpos($source,'static HICON buildStatusIcon(COLORREF dot);') <
        strpos($source,'greenTrayIcon=buildStatusIcon('),
    'Tray builds native-size contrasting color icons'=>
        str_contains($source,'SM_CXSMICON') &&
        str_contains($source,'CreateDIBSection(') &&
        str_contains($source,'CreateIconIndirect(&info)') &&
        str_contains($source,'buildStatusIcon(RGB(22,185,80))') &&
        str_contains($source,'buildStatusIcon(RGB(227,45,45))'),
    'Connected status requires server, monitor and token independent of notifications'=>
        str_contains($source,'connectionStatus.serverConnected.load() &&') &&
        str_contains($source,'selectedMonitorId.load()>0 && !getClientToken().empty()') &&
        !str_contains($source,'const bool active=notificationsEnabled.load()'),
    'Existing notification-area icon changes instead of adding another'=>
        str_contains($source,'Shell_NotifyIconA(NIM_MODIFY,&change)') &&
        str_contains($source,'change.uID=nid.uID'),
    'Explorer restart restores the persistent tray icon'=>
        str_contains($source,'RegisterWindowMessageA("TaskbarCreated")') &&
        str_contains($source,'Shell_NotifyIconA(NIM_ADD,&restore)'),
];
$failed=0;
foreach($checks as $name=>$result){
    echo ($result?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$result)$failed++;
}
echo count($checks).' checks; '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
