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
$liveView=$read('Backend/html/app/views/view/index.php');
$shared=$read('Backend/html/app/views/view/partials/key_history_shared.php');
$liveController=$read('Backend/html/app/controllers/View.php');
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
 'Keyboard preview renders Enter and Backspace effects'=>str_contains($shared,"kind:'newline'")&&str_contains($shared,"last.deleted=true"),
 'Keyboard preview has timestamp column'=>str_contains($shared,'key-transcript-time'),
 'Other keyboard events remain visible as badges'=>str_contains($shared,'key-special-badge'),
 'Both pages share transcript formatting'=>str_contains($keyView,'monitorTranscriptKeyRows(keys,escapeHtml)')&&str_contains($liveView,'monitorTranscriptKeyRows(keys,escapeViewerText)'),
 'Both pages share ruler styles'=>str_contains($keyView,"partials/key_history_shared.php")&&str_contains($liveView,"include __DIR__.'/logbrowse.php'"),
 'Both timeline ranges can be panned'=>str_contains($keyView,'MonitorCctvRange.drag')&&str_contains($liveView,'MonitorCctvRange.drag')&&str_contains($shared,"mode==='pan'"),
 'Both timeline boundaries can be resized'=>str_contains($shared,"mode==='start'")&&str_contains($shared,"mode==='end'")&&str_contains($keyView,'MonitorCctvRange.drag')&&str_contains($liveView,'MonitorCctvRange.drag'),
 'Zoomed-out ranges can still be panned'=>str_contains($shared,'selectedPixels*.3')&&str_contains($keyView,'MonitorCctvRange.hit')&&str_contains($liveView,'MonitorCctvRange.hit'),
 'Live viewer uses identical CCTV ruler and editable timestamps'=>str_contains($liveView,'id="historyTimelineRuler"')&&str_contains($liveView,'key-cctv-ruler history-ruler')&&str_contains($liveView,'id="historyFrom"')&&str_contains($liveView,'id="historyTo"'),
 'Live ruler shows sampled screenshot thumbnails and key activity'=>str_contains($liveView,'history-timeline-image')&&str_contains($liveView,'key-cctv-activity')&&str_contains($liveController,'function api_screenshot_timeline()'),
 'Live timeline fetches bounded keylogs'=>str_contains($liveView,'logbrowse/api_key_timeline')&&str_contains($liveView,'logbrowse/api_key_context'),
 'Old double range slider removed'=>!str_contains($liveView,'historyStartHandle')&&!str_contains($liveView,'historyEndHandle'),
];
$failed=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)$failed++;
}
exit($failed?1:0);
