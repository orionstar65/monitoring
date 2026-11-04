<?php
// Offline source contracts only; no MySQL, UI automation, or native compilation.
// Run: php Backend/tests/v15_unified_settings_and_live_view_contract.php
$root=dirname(__DIR__,2);
$read=static function(string $file)use($root):string{
    $text=file_get_contents($root.'/'.$file);
    if($text===false)throw new RuntimeException('Missing '.$file);
    return $text;
};
$auth=$read('Backend/html/app/controllers/Auth.php');
$login=$read('Backend/html/app/views/auth/login.php');
$api=$read('Backend/client_auth.php');
$admin=$read('Backend/html/app/controllers/Monitors.php');
$edit=$read('Backend/html/app/views/monitors/edit.php');
$create=$read('Backend/html/app/views/monitors/create.php');
$listing=$read('Backend/html/app/views/monitors/index.php');
$win=$read('Windows/MonitorClient.cpp');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$ubuntu=$read('Ubuntu/ConfigDialog.cpp');
$view=$read('Backend/html/app/controllers/View.php');
$page=$read('Backend/html/app/views/view/index.php');
$legacy=$read('Backend/migrate_legacy_monitor_data_once.php');

$tests=[
 'Login uses exact User No'=>str_contains($auth,'WHERE user_no=:user_no LIMIT 1'),
 'Email is not a web login identifier'=>!str_contains($auth,'WHERE email=:email LIMIT 1'),
 'Login UI contains only User No'=>str_contains($login,'>User No</label>')&&!str_contains($login,'User No or email'),
 'One unified admin lock switch'=>substr_count($edit,'name="settings_locked"')===1
    &&!str_contains($edit,'name="assignment_locked"'),
 'Admin mirrors both legacy lock fields'=>str_contains($admin,"'assignment_locked' => \$settings_locked"),
 'Admin owner choices show device counts'=>str_contains($edit,'device_count')&&str_contains($create,'device_count'),
 'Monitor table has a single lock column'=>str_contains($listing,'<th>Lock</th>')
    &&!str_contains($listing,'<th>Assignment</th>')&&!str_contains($listing,'<th>Settings</th>'),
 'Client checks effective unified lock'=>str_contains($api,"\$m['settings_locked']=!empty(\$m['settings_locked'])||!empty(\$m['assignment_locked'])"),
 'Device selection rejects locked source'=>str_contains($api,'$origin[\'settings_locked\'] || $origin[\'assignment_locked\']'),
 'Device creation rejects locked source'=>str_contains($api,'$current[\'assignment_locked\']||$current[\'settings_locked\']'),
 'Lock action persists both fields'=>str_contains($api,'settings_locked=1,assignment_locked=1'),
 'Windows prefs use registry'=>str_contains($win,'RegCreateKeyExA(')&&str_contains($win,'RegQueryValueExA('),
 'Windows settings.ini not used'=>!str_contains($win,'GetPrivateProfileIntA(')
     &&!str_contains($win,'WritePrivateProfileStringA('),
 'Windows tray has one Settings action'=>str_contains($win,'ID_TRAY_SETTINGS, "Settings..."')
     &&!str_contains($win,'ID_TRAY_SIGN_IN, getClientToken().empty()'),
 'Windows unified lock disables server edits'=>str_contains($win,'EnableWindow(GetDlgItem(dlg,SETTINGS_IP),!locked)'),
 'Windows unified lock disables monitor changes'=>str_contains($win,'EnableWindow(GetDlgItem(dlg,SETTINGS_MONITOR),!locked)'),
 'macOS unified lock disables server edits'=>str_contains($mac,'ipField.isEnabled = !isLocked')
     &&str_contains($mac,'portField.isEnabled = !isLocked'),
 'macOS unified lock disables monitor changes'=>str_contains($mac,'picker.isEnabled = !isLocked'),
 'Ubuntu unified lock disables server edits'=>str_contains($ubuntu,'serverIPEdit->setEnabled(!locked)'),
 'Ubuntu unified lock disables monitor changes'=>str_contains($ubuntu,'monitorSelector->setEnabled(!locked)'),
 'Live status emits integer monitor IDs'=>str_contains($view,"\$monitor['id']=(int)\$monitor['id']"),
 'Live page uses numeric monitor filter'=>str_contains($page,"params.append('monitor_id', selectedMonitors[0])"),
 'Live page does not call removed log modals'=>!str_contains($page,'currentLogData')
     &&!str_contains($page,'loadLogsForMonitor('),
 'Live page escapes device metadata'=>str_contains($page,'function escapeViewerText(value)'),
 'Live media paths handle legacy prefixes'=>str_contains($view,"\$marker='/html/data/'"),
 'Missing thumbnails use original screenshots'=>str_contains($view,'private function preferredScreenshotPath('),
 'Log API uses browser event date'=>str_contains($view,'"visit_date >= :start_date"'),
 'Legacy migration CLI only'=>str_contains($legacy,"PHP_SAPI!=='cli'"),
 'Legacy preview is read only'=>str_contains($legacy,'if($apply && !$existingMarker)'),
 'Legacy apply requires SQL backup'=>str_contains($legacy,'filesize($backup)<1024'),
 'Legacy migration does not rename files'=>!str_contains($legacy,'rename(')&&!str_contains($legacy,'unlink('),
 'Legacy migration does not reassign monitors'=>!str_contains($legacy,'UPDATE screenshot_metadata SET monitor_ip_id'),
 'Windows v1.5'=>str_contains($read('Windows/CMakeLists.txt'),'VERSION 1.5'),
 'macOS v1.5'=>str_contains($read('Mac/MonitorClient.xcodeproj/project.pbxproj'),'MARKETING_VERSION = 1.5;'),
 'Ubuntu v1.5'=>str_contains($read('Ubuntu/version.h'),'MONITORAPP_VERSION "1.5"'),
];
$failures=0;
foreach($tests as $title=>$pass){
    echo ($pass?'PASS':'FAIL').' '.$title.PHP_EOL;
    if(!$pass)$failures++;
}
echo count($tests).' source contracts, '.$failures.' failures'.PHP_EOL;
exit($failures===0?0:1);
