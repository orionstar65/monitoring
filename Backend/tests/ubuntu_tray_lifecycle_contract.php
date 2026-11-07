<?php
/**
 * Ubuntu tray/window lifetime source contracts.
 * Run: php Backend/tests/ubuntu_tray_lifecycle_contract.php
 *
 * Native Qt/desktop integration must also be exercised on Ubuntu.
 */
$root=dirname(__DIR__,2);
$read=static fn(string $file):string=>(string)file_get_contents($root.'/'.$file);
$main=$read('Ubuntu/main.cpp');
$client=$read('Ubuntu/MonitorClient.cpp');
$clientHeader=$read('Ubuntu/MonitorClient.h');
$dialog=$read('Ubuntu/ConfigDialog.cpp');

$checks=[
    'QApplication keeps the event loop alive after last window closes'=>
        substr_count($main,'app.setQuitOnLastWindowClosed(false);')===1 &&
        !str_contains($main,'app.setQuitOnLastWindowClosed(true);'),
    'Settings is a tool window so it does not add a taskbar entry'=>
        str_contains($dialog,'setWindowFlag(Qt::Tool, true);'),
    'Closing Settings cannot trigger Qt automatic quit'=>
        str_contains($dialog,'setAttribute(Qt::WA_QuitOnClose, false);'),
    'Settings close button dismisses the dialog, not the application'=>
        str_contains($dialog,'connect(closeButton,&QPushButton::clicked,this,&ConfigDialog::reject);') &&
        !str_contains($dialog,'connect(closeButton,&QPushButton::clicked,qApp'),
    'Closing Settings cleans up only the dialog'=>
        str_contains($client,'connect(dialog,&QDialog::finished,this,[this,dialog](int result)') &&
        str_contains($client,'activeSettingsDialog.clear();') &&
        str_contains($client,'dialog->deleteLater();'),
    'Tray is created independently from Settings and remains shown'=>
        str_contains($client,'trayIcon = new QSystemTrayIcon(this);') &&
        str_contains($client,'trayIcon->show();') &&
        str_contains($client,'trayMenu->addAction(configureAction);'),
    'Exit occurs through an explicit Quit action'=>
        str_contains($client,'trayMenu->addAction("Quit MonitorClient", qApp, &QApplication::quit);'),
    'Double-click on the tray reopens Settings'=>
        str_contains($client,'if(reason==QSystemTrayIcon::DoubleClick)') &&
        str_contains($client,'onConfigureAction();'),
    'Existing Settings dialog is raised rather than duplicated'=>
        str_contains($client,'if(activeSettingsDialog) {') &&
        str_contains($client,'activeSettingsDialog->showNormal();') &&
        str_contains($client,'activeSettingsDialog->raise();'),
    'Second launcher activation restores existing Settings even without tray host'=>
        str_contains($main,'client.notifyRunningInstance();') &&
        str_contains($client,'void MonitorClient::notifyRunningInstance()') &&
        str_contains($client,'Existing instance activated; opening Settings') &&
        str_contains($client,'onConfigureAction();') &&
        str_contains($clientHeader,'Reopen Settings in the original process'),
    'A missing GNOME tray host is diagnosed rather than stopping monitoring'=>
        str_contains($client,'QSystemTrayIcon::isSystemTrayAvailable()') &&
        str_contains($client,'[Tray] No system tray host detected. Monitoring continues;'),
    'Telemetry timer survives dialog close'=>
        str_contains($client,'monitorTimer->start();') &&
        str_contains($client,'connect(monitorTimer, &QTimer::timeout, this, &MonitorClient::monitorTimerTick);') &&
        !str_contains($dialog,'monitorTimer->stop();'),
];
$failed=0;
foreach($checks as $label=>$pass){
    echo ($pass?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$pass)$failed++;
}
echo count($checks).' Ubuntu tray lifecycle source checks; '.$failed.' failed'.PHP_EOL;
exit($failed?1:0);
