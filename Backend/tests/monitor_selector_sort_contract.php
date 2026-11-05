<?php
// Run: php Backend/tests/monitor_selector_sort_contract.php
// Static checks for the common User No -> Machine -> OS -> IP option order.
$root=dirname(__DIR__,2);
$get=static function(string $path)use($root):string {
    $result=file_get_contents($root.'/'.$path);
    if($result===false)throw new RuntimeException('Missing '.$path);
    return $result;
};
$users=$get('Backend/html/app/controllers/Users.php');
$monitors=$get('Backend/html/app/controllers/Monitors.php');
$browser=$get('Backend/html/app/controllers/Logbrowse.php');
$reports=$get('Backend/html/app/controllers/Reports.php');
$exports=$get('Backend/html/app/controllers/Export.php');
$view=$get('Backend/html/app/controllers/View.php');
$merge=$get('Backend/html/app/controllers/Merge.php');
$auth=$get('Backend/client_auth.php');
$layout=$get('Backend/html/app/views/layouts/main.php');
$win=$get('Windows/MonitorClient.cpp');
$mac=$get('Mac/MonitorClient/AppDelegate.swift');
$linux=$get('Ubuntu/ConfigDialog.cpp');
$checks=[
 'User management defaults to numeric User No order'=>str_contains($users,'ORDER BY CAST(u.user_no AS UNSIGNED),u.name,u.id'),
 'User edit mapped monitors use machine then OS'=>str_contains($users,"ORDER BY COALESCE(m.type,''),COALESCE(m.platform,'')"),
 'Monitor management starts with numeric owner User No'=>str_contains($monitors,'CAST(u.user_no AS UNSIGNED), u.name'),
 'Log browser sorts owner and machine'=>str_contains($browser,'CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type'),
 'Reports sorts owner and machine'=>str_contains($reports,'CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type'),
 'Export sorts owner and machine'=>str_contains($exports,'CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type'),
 'Real-time monitoring sorts owner and machine'=>str_contains($view,'CAST(owner.user_no AS UNSIGNED), owner.name, COALESCE(m.type'),
 'Merge API sorts owner and machine'=>str_contains($merge,'CAST(u.user_no AS UNSIGNED), u.name, COALESCE(m.type'),
 'Desktop API orders machine, OS then IP'=>str_contains($auth,"ORDER BY COALESCE(type,''),COALESCE(platform,''),INET_ATON(ip_address),ip_address,id"),
 'Desktop user directory uses numeric User No'=>str_contains($auth,'ORDER BY CAST(u.user_no AS UNSIGNED),u.name,u.id'),
 'Shared web helper sorts numeric user and device fields'=>str_contains($layout,'window.sortMonitorOptions = function(items)')&&str_contains($layout,"['type']")&&str_contains($layout,"['platform','os']"),
 'Merge web selector uses shared sort'=>str_contains($get('Backend/html/app/views/merge/index.php'),'window.sortMonitorOptions(data.monitors)'),
 'Browse report export web selectors use shared sort'=>str_contains($get('Backend/html/app/views/view/logbrowse.php'),'window.sortMonitorOptions(devices)')&&str_contains($get('Backend/html/app/views/view/reports.php'),'window.sortMonitorOptions(devices)')&&str_contains($get('Backend/html/app/views/view/export.php'),'window.sortMonitorOptions(allDevices)'),
 'Windows sorts all monitor API responses'=>substr_count($win,'sortMonitorOptions(settingsMonitors);')>=2,
 'macOS sorts all monitor API responses'=>substr_count($mac,'availableMonitors = sortMonitorOptions(')>=2,
 'Ubuntu sorts monitor dropdown and assigned array together'=>str_contains($linux,'std::stable_sort(ordered.begin()')&&str_contains($linux,'assignedMonitors.append(m)'),
];
$failed=0;
foreach($checks as $name=>$pass){echo ($pass?'PASS':'FAIL').': '.$name.PHP_EOL;if(!$pass)$failed++;}
exit($failed?1:0);
