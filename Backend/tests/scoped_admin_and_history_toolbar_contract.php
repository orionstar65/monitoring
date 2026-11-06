<?php
// Run after pulling main: php Backend/tests/scoped_admin_and_history_toolbar_contract.php
// Source contracts catch accidental removal of authorization boundaries.
// They do not replace live database and browser permission tests.
$root=dirname(__DIR__,2);
$read=static fn(string $path)=>file_get_contents($root.'/'.$path);
$base=$read('Backend/html/app/controllers/Base.php');
$users=$read('Backend/html/app/controllers/Users.php');
$monitors=$read('Backend/html/app/controllers/Monitors.php');
$view=$read('Backend/html/app/controllers/View.php');
$log=$read('Backend/html/app/controllers/Logbrowse.php');
$live=$read('Backend/html/app/views/view/index.php');
$key=$read('Backend/html/app/views/view/logbrowse.php');
$config=$read('Backend/html/app/config/config.php');
$checks=[
 'Role constants distinguish Super Admin from delegated Admin'=>
    str_contains($config,"define('ROLE_SUPER_ADMIN', 'super-admin')")
    &&str_contains($config,"define('ROLE_SCOPED_ADMIN', 'admin')"),
 'Migration elevates existing admins and creates assignment table'=>
    str_contains($read('Backend/migrations/2026_11_06_super_admin_scoped_admin.sql'),"UPDATE users SET role='super-admin'")
    &&str_contains($read('Backend/migrations/2026_11_06_super_admin_scoped_admin.sql'),'CREATE TABLE IF NOT EXISTS admin_user_assignments'),
 'Scoped Admin route whitelist does not include destructive actions'=>
    str_contains($base,"'Users'=>['index','edit']")
    &&str_contains($base,"'Monitors'=>['index','edit']")
    &&!str_contains($base,"'Cleanup'=>"),
 'Assignments are rechecked server-side'=>
    str_contains($base,'protected function canAccessUser(')
    &&str_contains($base,'protected function canAccessMonitor(')
    &&str_contains($base,'protected function monitorScope('),
 'Scoped Admin users list and editing enforce assignment'=>
    str_contains($users,'a.admin_user_id=:manager_id')
    &&str_contains($users,'requireUserAccess($id)'),
 'User assignment only allowed for Super Admin'=>
    str_contains($users,'function assign_users(')
    &&str_contains($users,'$this->requireSuperAdmin()')
    &&str_contains($users,'$connection->beginTransaction()'),
 'Super Admin retains delete and credential-reset permissions'=>
    str_contains($users,"public function delete(\$id) {\n        \$this->requireSuperAdmin();")
    &&str_contains($users,"public function reset_password(\$id) {\n        \$this->requireSuperAdmin();"),
 'Scoped Admin monitor updates cannot transfer ownership'=>
    str_contains($monitors,'$this->requireMonitorAccess($id)')
    &&str_contains($monitors,'Monitor reassignment requires Super Admin'),
 'Media, screenshots and live monitors require assigned access'=>
    str_contains($view,'$this->requireMonitorAccess($monitor_id)')
    &&str_contains($view,'$allowedMedia')
    &&str_contains($view,"$scope,\$scopeParams"),
 'All Keyboard Browser USB API filters require monitor ownership'=>
    substr_count($log,'requireScopedLogDevice($device_id)')>=4
    &&str_contains($log,'requireScopedLogDevice($deviceId)')
    &&str_contains($log,'requireScopedLogDevice($monitorId?:null)'),
 'Merge and screenshot deletion remain Super Admin only'=>
    str_contains($read('Backend/html/app/controllers/Merge.php'),'$this->requireAdmin()')
    &&str_contains($read('Backend/html/app/controllers/Cleanup.php'),'$this->requireAdmin()'),
 'Create/Edit User use one shared form'=>
    str_contains($read('Backend/html/app/views/users/create.php'),"include __DIR__.'/_account_form.php'")
    &&str_contains($read('Backend/html/app/views/users/edit.php'),"include __DIR__.'/_account_form.php'"),
 'Fullscreen reuses screenshot cards and poller'=>
    str_contains($live,'function monitorScreenshotCard(')
    &&str_contains($live,'displayFullscreenScreenshots(liveScreenshotSnapshot)')
    &&!str_contains($live,'startFullscreenAutoRefresh'),
 'Both history views have one compact control toolbar'=>
    str_contains($live,'class="history-toolbar"')
    &&str_contains($key,'class="key-history-toolbar"')
    &&str_contains($key,'id="keyFitTimeline"')
    &&str_contains($key,'id="keyRangeStart"')
    &&str_contains($key,'id="keyRangeEnd"'),
];
$fail=0;
foreach($checks as $name=>$ok){echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;if(!$ok)$fail++;}
echo count($checks).' checks; '.$fail.' failures'.PHP_EOL;
exit($fail?1:0);
