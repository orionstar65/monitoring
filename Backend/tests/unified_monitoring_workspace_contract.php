<?php
// Run: php Backend/tests/unified_monitoring_workspace_contract.php
// Source-level regression checks. Run UI interactions in a browser before deployment.
$root=dirname(__DIR__,2);
$read=static function(string $p) use($root):string {
    $s=file_get_contents($root.'/'.$p);
    if($s===false)throw new RuntimeException('Missing '.$p);
    return $s;
};
$live=$read('Backend/html/app/views/view/index.php');
$browse=$read('Backend/html/app/views/view/logbrowse.php');
$shared=$read('Backend/html/app/views/view/partials/key_history_shared.php');
$view=$read('Backend/html/app/controllers/View.php');
$merge=$read('Backend/html/app/controllers/Merge.php');
$db=$read('Backend/html/app/services/DatabaseService.php');
$monitor=$read('Backend/html/app/controllers/Monitors.php');
$layout=$read('Backend/html/app/views/layouts/main.php');
$checks=[
 'Monitoring overview uses consistent status groups and single update label'=>
     str_contains($live,'id="liveSummary" class="monitoring-overview"')
     &&str_contains($live,'class="monitoring-device-summary"')
     &&str_contains($live,'class="monitoring-overview-actions"')
     &&substr_count($live,'id="lastLiveRefresh"')===1,
 'Monitoring filter and screenshot toolbar retain existing JS bindings'=>
     str_contains($live,'class="monitoring-filter-main"')
     &&str_contains($live,'class="monitoring-filter-context"')
     &&str_contains($live,'class="main-header monitoring-screenshot-header"')
     &&str_contains($live,'class="monitoring-screenshot-actions"')
     &&substr_count($live,'id="unifiedDatePreset"')===1
     &&substr_count($live,'id="perPageSelect"')===1
     &&substr_count($live,'id="screenshotCount"')===1,
 'Statistic values preserve their unique element IDs'=>
     substr_count($live,'id="totalLogs"')===1
     &&substr_count($live,'id="browserLogs"')===1
     &&substr_count($live,'id="keyLogs"')===1
     &&substr_count($live,'id="usbLogs"')===1,
 'Tabbed workspace includes Screenshots, Keyboard, Browser, USB, Settings'=>str_contains($live,'data-monitor-tab="screenshots"')
     &&str_contains($live,'data-monitor-tab="key"')&&str_contains($live,'data-monitor-tab="browser"')
     &&str_contains($live,'data-monitor-tab="usb"')&&str_contains($live,'data-monitor-tab="settings"'),
 'Tabs reuse original log browse functionality'=>str_contains($live,"include __DIR__.'/logbrowse.php'")
     &&str_contains($browse,'window.unifiedLogs')&&str_contains($browse,'monitorTranscriptKeyRows(keys,escapeHtml)'),
 'Single left monitor selector has no radios'=>!str_contains($live,'class="monitor-radio"')
     &&str_contains($live,'data-monitor-id')&&str_contains($live,'role="button"'),
 'Left monitor list ordered by User No and username'=>str_contains($live,'window.sortMonitorOptions(monitors)')
     &&str_contains($live,'const title=number+\': \'+label;'),
 'Left monitor list scrolls and status badges are compact'=>str_contains($live,'.sidebar-panel .sidebar-content{overflow-y:auto')
     &&str_contains($live,'.sidebar-panel .monitor-item-status .live-status-badge{font-size:.62rem'),
 'One monitor selection controls all log tabs'=>str_contains($live,'window.unifiedLogs?.selectMonitor(id||\'\')')
     &&str_contains($live,'unifiedSelectTab(tab,persist=true)'),
 'Date/action bar preserves screenshot cleanup'=>str_contains($live,'id="unifiedRemoveScreenshots"')
     &&str_contains($browse,'cleanup/api_delete_screenshots')&&str_contains($browse,'unifiedCleanupProgress'),
 'Monitoring stats reflect selected monitor'=>str_contains($live,'selectedMonitors.length?allMonitors.filter')
     &&str_contains($live,'id="totalLogs"'),
 'Settings panel reuses existing edit route and lock setting'=>str_contains($live,'monitors/edit/')
     &&str_contains($live,'name="settings_locked"')&&str_contains($monitor,'return_to'),
 'Settings API provides same-user merge candidates'=>str_contains($view,'function api_monitor_settings()')
     &&str_contains($view,'WHERE owner_user_id=:owner AND id<>:id'),
 'Merge endpoint enforces same owner'=>str_contains($merge,'COUNT(DISTINCT owner_user_id)')
     &&str_contains($merge,'Merge requires two different monitors assigned to the same user'),
 'Merge upsert disambiguates statistics values'=>str_contains($db,'storage_statistics.record_count')
     &&str_contains($db,'storage_statistics.total_size'),
 'CCTV range component supports seek/pan/resize'=>str_contains($shared,"return 'seek'")
     &&str_contains($shared,"mode==='pan'")&&str_contains($shared,"mode==='start'")&&str_contains($shared,"mode==='end'"),
 'Playhead, zoom buttons, wheel zoom, thumbnails available'=>str_contains($live,'function historySeek(')
     &&str_contains($live,'id="historyZoomIn"')&&str_contains($live,"document.addEventListener('wheel'")
     &&str_contains($live,'historyLoadVisibleShots'),
 'Keylog uses same CCTV geometry'=>str_contains($browse,'MonitorCctvRange.drag')
     &&str_contains($browse,'MonitorCctvRange.hit')&&str_contains($browse,'zoomKeyRuler'),
 'Log management old route redirects to unified view'=>str_contains($view,"redirect('view?tab=browser'"),
 'Portal footer is pushed to the viewport bottom without fixed positioning'=>
     str_contains($layout,'#mainContent {') &&
     str_contains($layout,'display: flex;'.PHP_EOL.'            flex-direction: column;') &&
     str_contains($layout,'min-height: 100dvh;') &&
     str_contains($layout,'#mainContent > .content-area {') &&
     str_contains($layout,'flex: 1 0 auto;') &&
     str_contains($layout,'#mainContent > .footer {') &&
     str_contains($layout,'margin-top: auto;') &&
     !str_contains($layout,'padding-bottom: 120px;') &&
     !str_contains($layout,'margin-top: 48px;'),
 'Footer remains a normal document-flow element after page content'=>
     strpos($layout,'<footer class="footer">') >
         strpos($layout,'<?php echo $content ?? \'\'; ?>') &&
     strpos($layout,'</footer>') < strpos($layout,'</main>'),
 'Admin sidebar has only unified monitoring entry'=>!str_contains($layout,'<span>Log Browse</span>')
     &&!str_contains($layout,'<span>Merge Logs</span>'),
 'Browser and Keyboard tabs require an explicit monitor in embedded workspace'=>str_contains($browse,'if(isEmbeddedMonitoring&&!currentFilters.device_id)')
     &&str_contains($browse,'showSelectMonitorNotice()')&&str_contains($browse,'!isEmbeddedMonitoring || currentFilters.device_id'),
 'Log API paginates on the server and uses lightweight count queries'=>substr_count($read('Backend/html/app/controllers/Logbrowse.php'),'LIMIT :limit OFFSET :offset')>=3
     &&str_contains($read('Backend/html/app/controllers/Logbrowse.php'),'FROM key_logs k'."\n                      {\$where_clause}")
     &&str_contains($read('Backend/html/app/controllers/Logbrowse.php'),'ORDER BY k.key_date DESC,k.id DESC'),
 'Date preset selector defaults to today'=>str_contains($live,'id="unifiedDatePreset"')&&str_contains($live,'value="today" selected')
     &&str_contains($live,'unifiedPresetRange(preset,now=new Date())')&&str_contains($live,"case 'last_30_days'"),
 'Status pill has exactly one generated indicator'=>str_contains($live,'monitor-status-indicator')
     &&!str_contains($live,'class="pulse"')&&!str_contains($live,'class="fas fa-circle"'),
 'Screenshot history has one compact toolbar'=>str_contains($live,'class="history-toolbar"')
     &&str_contains($live,'.history-ruler.key-cctv-ruler{height:93px}')
     &&str_contains($live,'class="history-date-field" for="historyFrom"'),
 'Screenshot right transcript offers nearest-key fallback'=>str_contains($live,'historyNearestKeyboardBucket()')
     &&str_contains($live,'historyJumpToKeyboard')&&str_contains($live,'rangeStart:ms-900000,rangeEnd:ms+900000'),
 'Special keys are only shown once, with recorded event provenance'=>!str_contains($shared,'Other key events')
     &&str_contains($shared,'recorded event #')&&str_contains($read('Backend/html/app/controllers/Logbrowse.php'),'k.id AS event_id'),
 'Desktop device labels show #id'=>str_contains($read('Windows/MonitorClient.cpp'),'std::to_string(id)')
     &&str_contains($read('Mac/MonitorClient/AppDelegate.swift'),'#\\(id)')
     &&str_contains($read('Ubuntu/ConfigDialog.cpp'),'#%4'),
];
$failures=0;
foreach($checks as $name=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$pass)$failures++;
}
echo count($checks).' source contracts, '.$failures.' failures'.PHP_EOL;
exit($failures?1:0);
