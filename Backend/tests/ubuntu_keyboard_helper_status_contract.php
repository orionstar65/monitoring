<?php
/**
 * Ubuntu reader process diagnostics (source-level checks only).
 * Run: php Backend/tests/ubuntu_keyboard_helper_status_contract.php
 *
 * Never prints, stores or simulates any keystroke data.
 */
$root=dirname(__DIR__,2);
$read=static fn(string $file):string=>(string)file_get_contents($root.'/'.$file);
$reader=$read('Ubuntu/KeyboardMonitor.cpp');
$header=$read('Ubuntu/KeyboardMonitor.h');
$client=$read('Ubuntu/MonitorClient.cpp');
$clientHeader=$read('Ubuntu/MonitorClient.h');

$checks=[
    'Reader exposes metadata-only lifecycle signal'=>
        str_contains($header,'monitoringStatusChanged(bool running, const QString &description)'),
    'Missing helper reports unavailable status'=>
        str_contains($reader,'Reader not installed'),
    'Exec failure reports an actionable status'=>
        str_contains($reader,'Reader failed to start: %1'),
    'Immediate process exit is not marked healthy'=>
        str_contains($reader,'m_helper->state() != QProcess::Running') &&
        str_contains($reader,'Reader exited immediately after start'),
    'No accessible devices are reported without key contents'=>
        str_contains($reader,'Reader unavailable: no accessible input devices'),
    'Crashes and normal exits update health'=>
        str_contains($reader,'Reader crashed') &&
        str_contains($reader,'Reader stopped'),
    'Exited child process is not leaked when re-started'=>
        str_contains($reader,'m_helper->deleteLater();'),
    'Tray shows keyboard reader health only for signed in monitor'=>
        str_contains($client,'if (!authToken.isEmpty() && monitorId>0)') &&
        str_contains($client,'Keyboard reader: %1'),
    'Status handler does not forward recorded key content'=>
        str_contains($client,'&KeyboardMonitor::monitoringStatusChanged') &&
        str_contains($client,'keyboardReaderStatus = description;') &&
        !str_contains($clientHeader,'QString keyboardReaderLastKey') &&
        str_contains($clientHeader,'QString keyboardReaderStatus'),
];
$failed=0;
foreach($checks as $name=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$ok)++$failed;
}
echo count($checks).' helper-status source checks; '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
