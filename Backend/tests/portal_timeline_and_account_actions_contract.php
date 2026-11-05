<?php
// Run: php Backend/tests/portal_timeline_and_account_actions_contract.php
$root=dirname(__DIR__,2);
$read=static function(string $relative) use($root):string {
    $content=file_get_contents($root.'/'.$relative);
    if($content===false)throw new RuntimeException('Cannot read '.$relative);
    return $content;
};
$mergeController=$read('Backend/html/app/controllers/Merge.php');
$mergeView=$read('Backend/html/app/views/merge/index.php');
$userEditor=$read('Backend/html/app/views/users/edit.php');
$userController=$read('Backend/html/app/controllers/Users.php');
$keyView=$read('Backend/html/app/views/view/logbrowse.php');
$keyController=$read('Backend/html/app/controllers/Logbrowse.php');
$checks=[
 'Merge selectors include user number and machine'=>str_contains($mergeController,'u.user_no AS user_no')&&str_contains($mergeController,'m.platform')&&str_contains($mergeView,'formatMergeMonitorLabel(monitor)'),
 'Merge source, target and fix selectors use same formatter'=>substr_count($mergeView,'formatMergeMonitorLabel(monitor)')>=4,
 'User editor reset posts to existing action'=>str_contains($userEditor,'users/reset_password/'),
 'User editor delete posts to existing action'=>str_contains($userEditor,'users/delete/'),
 'User editor protects own-account destructive controls'=>str_contains($userEditor,"(int)(\$_SESSION['user_id']??0)"),
 'Reset can redirect to user editor'=>str_contains($userController,"(\$_POST['return_to']??'')==='edit'"),
 'Key context remains scoped to selected device'=>str_contains($keyController,'k.monitor_ip_id=:id'),
 'Key timeline has editable start and end'=>str_contains($keyView,'id="keyRangeStart"') && str_contains($keyView,'id="keyRangeEnd"') && !str_contains($keyView,'id="keyTimelineRange"'),
 'Ruler has draggable range endpoints'=>str_contains($keyView,'key-cctv-selection') && str_contains($keyView,'setPointerCapture(event.pointerId)'),
 'Context query accepts an explicit bounded window'=>str_contains($keyController,"\$_GET['from']") && str_contains($keyController,"\$_GET['to']") && str_contains($keyController,'LIMIT 2001'),
 'Transcript navigation moves adjacent windows'=>str_contains($keyView,'id="keyPreviousRange"') && str_contains($keyView,'id="keyNextRange"') && str_contains($keyView,'shiftKeyTimeRange(direction)'),
 'Full-date timeline API uses bounded density buckets'=>str_contains($keyController,'function api_key_timeline()')&&str_contains($keyController,'LIMIT 3001'),
 'UI loads full-date activity after the initial page'=>str_contains($keyView,'loadFullKeyTimeline(keyRulerState)'),
 'Timeline supports zoom'=>str_contains($keyView,'keyTimelineScale')&&str_contains($keyView,'renderKeyRuler'),
 'Keylog navigation is above transcript'=>str_contains($keyView,'id="keyPreviousRange"')&&str_contains($keyView,'id="keyNextRange"')&&str_contains($keyView,"currentTab === 'key'"),
 'Keyboard preview renders Enter and Backspace effects'=>str_contains($keyView,"kind:'newline'")&&str_contains($keyView,"last.deleted=true"),
 'Keyboard preview has timestamp column'=>str_contains($keyView,'key-transcript-time'),
 'Other keyboard events remain visible as badges'=>str_contains($keyView,'key-special-badge'),
];
$failed=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)$failed++;
}
exit($failed?1:0);
