<?php
// Source-level regression: php Backend/tests/legacy_ingest_compat_contract.php
// Verifies the old 2026-08 request shape and the hardened dual-version gateway.
$root=dirname(__DIR__,2);
$read=static function(string $path)use($root):string{
    $content=file_get_contents($root.'/'.$path);
    if($content===false)throw new RuntimeException('Missing '.$path);
    return $content;
};
$identity=$read('Backend/services/ClientIdentity.php');
$events=$read('Backend/eventhandler.php');
$uploads=$read('Backend/webapi.php');
$windows=$read('Windows/MonitorClient.cpp');
$ubuntu=$read('Ubuntu/MonitorClient.cpp');
$checks=[
 'Legacy is disabled unless explicitly enabled'=>str_contains($identity,"getenv('MONITOR_ENABLE_LEGACY_V13')!=='1'"),
 'Explicit source IP allowlist required'=>str_contains($identity,"getenv('MONITOR_LEGACY_V13_IPS')"),
 'Legacy must originate on direct private LAN'=>str_contains($identity,'monitoringIsPrivateLanAddress($peer)')&&str_contains($identity,"HTTP_X_FORWARDED_FOR"),
 'Invalid Authorization cannot downgrade'=>str_contains($identity,"strcasecmp($key,'Authorization')===0"),
 'Legacy must map to an existing assigned monitor'=>str_contains($identity,'JOIN users u ON u.id=m.owner_user_id'),
 'Ambiguous IPs cannot claim another monitor'=>str_contains($identity,'count($rows)!==1'),
 'MAC compared when available'=>str_contains($identity,'$registeredMac')&&str_contains($identity,'strtolower($mac)'),
 'New token-bound client session still supported'=>str_contains($identity,'clientAuthenticatedSession($db,true)'),
 'Event routing supports both modes'=>str_contains($events,'requireClientMonitorOrLegacy($mysqli'),
 'Event MonitorId mismatch still rejected'=>str_contains($events,'Monitor selection mismatch'),
 'Screenshot routing supports both modes'=>str_contains($uploads,'requireClientMonitorOrLegacy($mysqli'),
 'Legacy screenshot no-query allowed only for validated device'=>str_contains($uploads,'monitoringLegacyV13Monitor($mysqli')&&str_contains($uploads,"$request_type_param='upload'"),
 'Modern screenshot URL is explicitly typed'=>str_contains($windows,'?type=upload'),
 'Windows multipart extracts and appends query'=>str_contains($windows,'urlComp.dwExtraInfoLength = -1')&&str_contains($windows,'urlPath.append(urlComp.lpszExtraInfo,urlComp.dwExtraInfoLength)'),
 'Windows sends immediate heartbeat on monitor selection'=>str_contains($windows,'Confirm the new backend binding immediately'),
 'Windows logs HTTP failures'=>str_contains($windows,'HTTP JSON request rejected: status')&&str_contains($windows,'Screenshot upload HTTP status'),
 'Windows event requests require a bound token'=>substr_count($windows,'if(selectedMonitorId.load()<1 || getClientToken().empty())return false;')>=3,
 'Windows senders snapshot events under mutex'=>substr_count($windows,'std::vector<Json::Value> batch;')>=3&&substr_count($windows,'std::lock_guard<std::mutex> lock(dataMutex);')>=6,
 'Windows acknowledges only uploaded prefix'=>substr_count($windows,'.erase(')>=3&&str_contains($windows,'batch.size()'),
 'Windows browser and USB limits match backend'=>str_contains($windows,'if(batch.size()>=500)break;'),
 'Windows keylog limit matches backend'=>str_contains($windows,'if(batch.size()>=2000)break;'),
 'Ubuntu browser batch respects 500 limit'=>str_contains($ubuntu,'chunkData(snapshot, 500)'),
 'Ubuntu USB batch respects 500 limit'=>str_contains($ubuntu,'chunkData(usbLogs, 500)'),
 'Ubuntu retries failed key logs'=>str_contains($ubuntu,'keyLogs.swap(pending)'),
 'Ubuntu retries failed USB logs'=>str_contains($ubuntu,'usbLogs.swap(pending)'),
 'Ubuntu retries failed browser history'=>str_contains($ubuntu,'browserHistories.swap(pending)'),
 'Ubuntu validates JSON acknowledgement'=>str_contains($ubuntu,'body.value("status").toString()=="success"'),
 'Ubuntu reports HTTP rejection without logging event payload'=>str_contains($ubuntu,'"[MonitorUpload]"')&&str_contains($ubuntu,'body.value("error").toString()'),
 'Ubuntu sends heartbeat while screen unavailable'=>str_contains($ubuntu,'screenAvailable')&&str_contains($ubuntu,'if (isInterruptionRequested()) return;'),
];
$failed=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)$failed++;
}
exit($failed?1:0);
