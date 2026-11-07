<?php
// Run: php Backend/tests/ubuntu_screenshot_capture_contract.php
// Source contracts only. A real Ubuntu desktop session, PipeWire, and portal
// permission grant are needed to validate end-to-end image capture.
$root=dirname(__DIR__,2);
$client=(string)file_get_contents($root.'/Ubuntu/MonitorClient.cpp');
$header=(string)file_get_contents($root.'/Ubuntu/MonitorClient.h');
$portal=(string)file_get_contents($root.'/Ubuntu/ScreenCastPortal.cpp');
$portalHeader=(string)file_get_contents($root.'/Ubuntu/ScreenCastPortal.h');
$main=(string)file_get_contents($root.'/Ubuntu/main.cpp');
$checks=[
    'Manual screenshot uses a distinct explicit capture entry point'=>
        str_contains($client,'void MonitorClient::onTakeScreenshotAction()') &&
        str_contains($client,'captureScreen(true);') &&
        str_contains($client,'return captureScreen(false);'),
    'Settings Send All screenshot is also marked as manual'=>
        str_contains($client,'void MonitorClient::onSendAllAction()') &&
        str_contains($client,'QTimer::singleShot(0,this,[this](){captureScreen(true);});'),
    'Wayland manual capture invokes the public consent-aware screenshot portal'=>
        str_contains($client,'if(manual && screenCastPortal)') &&
        str_contains($client,'screenCastPortal->requestScreenshot();') &&
        str_contains($portal,'PORTAL_SCREENSHOT_IF,"Screenshot"'),
    'Wayland does not depend on the private GNOME Shell Screenshot method'=>
        str_contains($client,'if(screenCastPortal){') &&
        str_contains($client,'return;') &&
        str_contains($client,'// Wayland screen pixels must come from a portal-approved stream.'),
    'Portal screenshot is asynchronous and not reported successful until a decoded image exists'=>
        str_contains($portal,'QDBusPendingCallWatcher') &&
        str_contains($portal,'void ScreenCastPortal::onScreenshotResponse(') &&
        str_contains($portal,'QImageReader reader(path);') &&
        str_contains($portal,'emit screenshotReady(captured);'),
    'User receives actionable portal error and permission cancellation messages'=>
        str_contains($portal,'Screenshot permission was cancelled') &&
        str_contains($portal,'Desktop permission dialog timed out') &&
        str_contains($client,'reportScreenshotCaptureError(reason);'),
    'Desktop-portal screenshot must be encoded before upload'=>
        str_contains($client,'frame.save(base+QStringLiteral(".jpg"),"JPEG",70)') &&
        str_contains($client,'uploadCapturedScreenshot(base);'),
    'Automatic screenshots never create repeated screenshot portal dialogs'=>
        str_contains($client,'if(manual && screenCastPortal)') &&
        str_contains($client,'Scheduled capture skipped:'),
    'X11 directly grabs an available screen first'=>
        str_contains($client,'screen->grabWindow(0)') &&
        str_contains($client,'// X11: capture the root window directly first.'),
    'GNOME legacy DBus result uses both success flag and filename'=>
        str_contains($client,'const auto args=reply.arguments();') &&
        str_contains($client,'args.size()>1'),
    'PipeWire accepts mapped memory file-descriptor frames'=>
        str_contains($portal,'SPA_DATA_MemFd') &&
        str_contains($portal,'PW_STREAM_FLAG_MAP_BUFFERS'),
    'PipeWire frame buffer and stride checks prevent invalid access'=>
        str_contains($portal,'!spaBuf->datas[0].chunk') &&
        str_contains($portal,'d->chunk->offset>d->maxsize'),
    'Portal restore permission token persists to canonical user config'=>
        str_contains($portal,'/monitorclient/settings.ini') &&
        !str_contains($portal,'QSettings settings("settings.ini"'),
    'Portal service failures propagate instead of staying silent'=>
        str_contains($portal,'CreateSession failed: ') &&
        str_contains($portal,'SelectSources failed: ') &&
        str_contains($portal,'ScreenCast Start failed: '),
    'Overlapping screenshot triggers are coalesced instead of queued twice'=>
        str_contains($client,'if(screenshotInProgress)') &&
        str_contains($client,'Capture already in progress; duplicate request ignored') &&
        !str_contains($header,'m_manualScreenshotRequested') &&
        !str_contains($header,'m_manualCaptureIntent'),
    'Capture cooldown enforces server interval without blocking manual requests'=>
        str_contains($header,'QElapsedTimer m_captureCooldown;') &&
        str_contains($client,'const qint64 minSpacingMs=manual ? 2000LL') &&
        str_contains($client,'qMax(1,screenInterval))*1000LL') &&
        str_contains($client,'m_captureCooldown.elapsed()<minSpacingMs'),
    'Only one portal frame may start the upload'=>
        str_contains($header,'m_screenshotUploadStarted = false;') &&
        str_contains($client,'if(!screenshotInProgress || m_screenshotUploadStarted)') &&
        str_contains($client,'m_screenshotUploadStarted=true;'),
    'Capture time is sent in UTC with millisecond precision'=>
        str_contains($client,'m_captureTimeUtc=QDateTime::currentDateTimeUtc();') &&
        str_contains($client,'m_captureTimeUtc.toString(Qt::ISODateWithMs)'),
    'Monitor signal connections are not installed a second time in main'=>
        !str_contains($main,'QObject::connect(&client, &MonitorClient::screen') &&
        !str_contains($main,'QObject::connect(&client, &MonitorClient::keyLog') &&
        str_contains($client,'connect(this, &MonitorClient::screen, this, &MonitorClient::getScreen);'),
    'No premature generic capture failure toast remains'=>
        !str_contains($client,'Capture failed. Check screen-sharing permission or the PipeWire/GNOME screenshot service.'),
];
$failed=0;
foreach($checks as $name=>$passed){
    echo ($passed?'PASS':'FAIL').': '.$name.PHP_EOL;
    if(!$passed)++$failed;
}
echo count($checks).' Ubuntu capture contracts, '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
