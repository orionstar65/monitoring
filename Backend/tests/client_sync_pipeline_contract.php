<?php
// Run: php Backend/tests/client_sync_pipeline_contract.php
// These are source contracts, not integration tests. Native builds and a
// reachable server are still required to verify actual upload delivery.
$root=dirname(__DIR__,2);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$service=$read('Backend/html/app/services/DatabaseService.php');
$event=$read('Backend/eventhandler.php');
$upload=$read('Backend/webapi.php');
$auth=$read('Backend/client_auth.php');
$config=$read('Backend/webapi_config.php');
$ubuntu=$read('Ubuntu/MonitorClient.cpp');
$ubuntuHeader=$read('Ubuntu/MonitorClient.h');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$win=$read('Windows/MonitorClient.cpp');
$checks=[
 'API request logging persists safe metadata instead of returning immediately'=>
   str_contains($service,'public function logApiRequest(') &&
   str_contains($service,'API request metadata logging failed:') &&
   str_contains($service,'$stmt->execute()') &&
   !str_contains($service,'Disabled: daily API log table was for debugging only'),
 'API logs include successful and rejected telemetry and screenshot requests'=>
   str_contains($event,'register_shutdown_function(') &&
   str_contains($upload,'register_shutdown_function(') &&
   str_contains($config,'->logApiRequest('),
 'Auth logs capture only endpoint metadata, not credentials'=>
   str_contains($auth,'Auth API logging failed:') &&
   str_contains($auth,"$".'apiAction=$action;') &&
   str_contains($auth,"'/client_auth.php'"),
 'No silent partial acknowledgement of browser, keyboard or USB batches'=>
   str_contains($event,'$inserted_count!==count($browserHistories)') &&
   str_contains($event,'$inserted_count!==count($keyLogs)') &&
   str_contains($event,'$inserted_count!==count($usbLogs)'),
 'Backend browser and USB inserts are atomic with their statistics'=>
   str_contains($service,'Browser history commit failed') &&
   str_contains($service,'USB commit failed') &&
   str_contains($service,'upsertBatchStatistics('),
 'Ubuntu schedules every supported sync handler'=>
   str_contains($ubuntu,'connect(this, &MonitorClient::screen, this, &MonitorClient::getScreen);') &&
   str_contains($ubuntu,'connect(this, &MonitorClient::tic, this, &MonitorClient::sendTic);') &&
   str_contains($ubuntu,'connect(this, &MonitorClient::browserHistory, this, &MonitorClient::sendBrowserHistories);') &&
   str_contains($ubuntu,'connect(this, &MonitorClient::keyLog, this, &MonitorClient::sendKeyLogs);') &&
   str_contains($ubuntu,'connect(this, &MonitorClient::usbLog, this, &MonitorClient::sendUSBLogs);'),
 'Ubuntu history no longer requires missing LastBrowserTic from heartbeat'=>
   !str_contains($ubuntu,'if (lastBrowserTic != -1) {') &&
   str_contains($ubuntu,'lastBrowserTic = QDateTime::currentSecsSinceEpoch()-24*3600;'),
 'Ubuntu retains failed batches and prevents overlapping retries'=>
   str_contains($ubuntuHeader,'m_keyUploadBusy = false;') &&
   str_contains($ubuntuHeader,'m_usbUploadBusy = false;') &&
   str_contains($ubuntu,'if(m_keyUploadBusy)return 0;') &&
   str_contains($ubuntu,'if(m_usbUploadBusy)return 0;') &&
   str_contains($ubuntu,'pending.append(keyLogs);') &&
   str_contains($ubuntu,'pending.append(usbLogs);'),
 'Ubuntu HTTP 0 explains transport errors with destination information'=>
   str_contains($ubuntu,'Connection failed to %1:%2') &&
   str_contains($ubuntu,'check server listener / port / firewall'),
 'macOS serializes keyboard uploads and waits for full acknowledgement'=>
   str_contains($mac,'self?.sendKeyLogs()') &&
   str_contains($mac,'keyboardUploadInProgress=true') &&
   str_contains($mac,'if accepted && inserted==batch.count'),
 'macOS USB events remain queued until confirmed stored'=>
   str_contains($mac,'usbUploadInProgress=true') &&
   str_contains($mac,'self.usbDeviceLogs.removeFirst(batch.count)') &&
   !str_contains($mac,'self.usbDeviceLogs.removeAll(keepingCapacity: true)'),
 'macOS browser history uses 500-event chunks and commits cursors on ACK'=>
   str_contains($mac,'eventType:"BrowserHistory",chunkSize:500') &&
   str_contains($mac,'if accepted && stored==batch.count') &&
   str_contains($mac,'self.browserUploadInProgress=false'),
 'macOS reports permission-related keyboard tap failure'=>
   str_contains($mac,'Keyboard capture could not start. Check Accessibility and Input Monitoring permissions'),
 'Windows allows a larger bounded retry buffer and reports overflow'=>
   str_contains($win,'const size_t MAX_KEY_LOGS = 25000;') &&
   str_contains($win,'const size_t MAX_USB_LOGS = 5000;') &&
   str_contains($win,'const size_t MAX_BROWSER_HISTORIES = 15000;') &&
   str_contains($win,'WARNING: unsent keyboard backlog')
];
$failed=0;
foreach($checks as $label=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$ok)$failed++;
}
echo count($checks).' sync pipeline source checks, '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
