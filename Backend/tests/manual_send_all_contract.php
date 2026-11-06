<?php
// Run: php Backend/tests/manual_send_all_contract.php
// Source-level contract checks only; use native Windows/macOS/Ubuntu builds
// and a live backend to verify actual screenshot delivery.
$root=dirname(__DIR__,2);
$read=static fn(string $p):string=>(string)file_get_contents($root.'/'.$p);
$win=$read('Windows/MonitorClient.cpp');
$ubuntu=$read('Ubuntu/MonitorClient.cpp');
$ubuntuHeader=$read('Ubuntu/MonitorClient.h');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$portal=$read('Backend/html/app/views/view/index.php');
$webapi=$read('Backend/webapi.php');
$checks=[
 'Windows Settings and tray buttons launch immediate manual sync'=>
    substr_count($win,'std::thread([]{sendAllLogsNow();}).detach();')>=2 &&
    str_contains($win,'void sendAllLogsNow() {'),
 'Windows manually triggers concurrent screenshot, browser, keyboard, USB and heartbeat'=>
    str_contains($win,'auto screenshot=std::async(std::launch::async') &&
    str_contains($win,'auto browser=std::async(std::launch::async') &&
    str_contains($win,'auto keys=std::async(std::launch::async') &&
    str_contains($win,'auto usb=std::async(std::launch::async') &&
    str_contains($win,'auto heartbeat=std::async(std::launch::async'),
 'Windows screenshot requires successful JPEG creation and stored backend filename'=>
    str_contains($win,'const Gdiplus::Status saved=') &&
    str_contains($win,'if(saved!=Gdiplus::Ok)') &&
    str_contains($win,'GetFileAttributesA(path.c_str())==INVALID_FILE_ATTRIBUTES') &&
    str_contains($win,'root["data"].get("filename","")'),
 'Windows provides a per-type completion report rather than a queued-only notification'=>
    str_contains($win,'const bool allOk=imageOk&&browserOk&&keysOk&&usbOk&&heartbeatOk;') &&
    str_contains($win,'Send All Logs finished: '),
 'Ubuntu Settings button is connected to manual send handler'=>
    str_contains($ubuntu,'connect(dialog,&ConfigDialog::sendAllRequested,this,&MonitorClient::onSendAllAction);'),
 'Ubuntu starts key, USB, heartbeat and browser uploads independently of screenshot capture'=>
    str_contains($ubuntu,'emit tic();'.PHP_EOL.'    emit keyLog();'.PHP_EOL.'    emit usbLog();'.PHP_EOL.'    sendBrowserHistories();') &&
    str_contains($ubuntu,'QTimer::singleShot(0,this,[this](){getScreen();});'),
 'Ubuntu capture uses unique temporary file and handles a concurrent manual request'=>
    str_contains($ubuntu,'QUuid::createUuid().toString(QUuid::WithoutBraces)') &&
    str_contains($ubuntuHeader,'m_manualScreenshotRequested = false;') &&
    str_contains($ubuntu,'QTimer::singleShot(0,this,[this](){getScreen();});'),
 'Ubuntu X11 screenshot fallback and capture failures are visible'=>
    str_contains($ubuntu,'screen->grabWindow(0)') &&
    str_contains($ubuntu,'Capture failed. Check screen-sharing permission') &&
    str_contains($ubuntu,'Screenshot (GNOME D-Bus):'),
 'macOS manual sends trigger screenshot and all event uploads now'=>
    str_contains($mac,'captureAndPostScreenshot(manual:true)') &&
    str_contains($mac,'sendTicEvent()'.PHP_EOL.'        sendKeyLogs()'.PHP_EOL.'        sendUSBLogs()'.PHP_EOL.'        sendBrowserHistories()'),
 'macOS screenshot file is unique and result requires stored backend filename'=>
    str_contains($mac,'NSTemporaryDirectory() + "monitorclient-" + UUID().uuidString + ".jpg"') &&
    str_contains($mac,'let success=code==200 && (reply?["status"] as? String)=="success"') &&
    str_contains($mac,'if success,let filename=filename,!filename.isEmpty'),
 'macOS does not advertise completion before the upload acknowledges'=>
    !str_contains($mac,'notification.body = "All monitoring data has been sent to server"') &&
    str_contains($mac,'reportManualScreenshot(true,"Saved as'),
 'Backend stores screenshot metadata and returns the file name to callers'=>
    str_contains($webapi,'addScreenshotMetadata(') &&
    str_contains($webapi,"'filename' => \$img_name") &&
    str_contains($webapi,"'status' => 'success'"),
 'Portal refreshes screenshot tiles promptly while visible'=>
    str_contains($portal,'}, 10000);') &&
    str_contains($portal,'loadScreenshotsForSelectedMonitor(page);')
];
$failed=0;
foreach($checks as $label=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$ok)++$failed;
}
echo count($checks).' manual-sync source checks, '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
