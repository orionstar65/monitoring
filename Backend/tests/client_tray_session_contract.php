<?php
/**
 * Source contracts for the native tray menus and Ubuntu restored user UI.
 * Run: php Backend/tests/client_tray_session_contract.php
 * This does not replace Windows/macOS/Ubuntu native builds.
 */
$root=dirname(__DIR__,2);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$win=$read('Windows/MonitorClient.cpp');
$mac=$read('Mac/MonitorClient/AppDelegate.swift');
$ubuntu=$read('Ubuntu/MonitorClient.cpp');
$ubuntuH=$read('Ubuntu/MonitorClient.h');
$dialog=$read('Ubuntu/ConfigDialog.cpp');
$dialogH=$read('Ubuntu/ConfigDialog.h');
$auth=$read('Backend/client_auth.php');

$winMenu=substr($win,strpos($win,'void updateTrayMenu() {'),
    strpos($win,'// Setup USB monitoring')-strpos($win,'void updateTrayMenu() {'));
$macMenu=substr($mac,strpos($mac,'private func setupStatusMenuItems()'),
    strpos($mac,'@objc private func statusBarIconClicked()')-strpos($mac,'private func setupStatusMenuItems()'));
$ubuntuMenu=substr($ubuntu,strpos($ubuntu,'void MonitorClient::setupSystemTray()'),
    strpos($ubuntu,'void MonitorClient::cleanupSystemTray()')-strpos($ubuntu,'void MonitorClient::setupSystemTray()'));

$checks=[
  'Windows tray contains Send All Logs and retains functional dispatch'=>
    str_contains($winMenu,'ID_TRAY_SEND_ALL, "Send All Logs"') &&
    str_contains($win,'case ID_TRAY_SEND_ALL:') &&
    str_contains($win,'std::thread([]{sendAllLogsNow();}).detach();'),
  'Windows tray has no redundant empty separator before Quit'=>
    !str_contains($winMenu,'// Manual log upload is now in Settings.') &&
    !str_contains($winMenu,'AppendMenuA(hTrayMenu, MF_SEPARATOR, 0, NULL);'."\n".'    AppendMenuA(hTrayMenu, MF_SEPARATOR'),
  'macOS tray contains the Send All Logs handler and target'=>
    str_contains($macMenu,'#selector(sendAllLogs)') &&
    str_contains($macMenu,'sendAllItem.target = self') &&
    str_contains($mac,'@objc func sendAllLogs()'),
  'macOS tray no longer contains the empty test or manual upload sections'=>
    !str_contains($macMenu,'// Manual test actions') &&
    !str_contains($macMenu,'// Manual uploads are managed from Settings.'),
  'Ubuntu tray directly exposes Send All Logs and the settings window'=>
    str_contains($ubuntuMenu,'trayMenu->addAction(sendAllAction);') &&
    str_contains($ubuntuMenu,'trayMenu->addAction(configureAction);') &&
    str_contains($ubuntuMenu,'connect(sendAllAction, &QAction::triggered, this, &MonitorClient::onSendAllAction);'),
  'Ubuntu tray does not create Test Connection or Test API actions'=>
    !str_contains($ubuntuMenu,'new QAction("Test Connection"') &&
    !str_contains($ubuntuMenu,'new QAction("Test API Endpoints"') &&
    !str_contains($ubuntuMenu,'trayMenu->addAction(testConnectionAction)') &&
    !str_contains($ubuntuMenu,'trayMenu->addAction(testAPIEndpointsAction)'),
  'Ubuntu tray does not duplicate separators'=>
    !str_contains($ubuntuMenu,'trayMenu->addSeparator();'."\n".'    trayMenu->addSeparator();') &&
    !str_contains($ubuntuMenu,'// Send All Logs moved into Settings.'),
  'Ubuntu tray displays correct version rather than hardcoded 1.4'=>
    str_contains($ubuntu,'MONITORAPP_VERSION, active?') &&
    !str_contains($ubuntu,'MonitorClient v1.4'),
  'Ubuntu tray displays only User No for authenticated account'=>
    str_contains($ubuntu,'QStringLiteral("User No: %1").arg(signedUserNo)') &&
    !str_contains($ubuntu,'Signed in: %1 (User No: %2)') &&
    !str_contains($ubuntu,'signedUserName'),
  'Ubuntu session identity is loaded into tray status at startup'=>
    str_contains($ubuntu,'signedUserNo=saved.getActiveUserNo();') &&
    !str_contains($ubuntu,'signedUserName=saved.getActiveUserName();'),
  'Ubuntu tray identity updates immediately when the account changes'=>
    substr_count($ubuntu,'signedUserNo=dialog->getActiveUserNo();')>=2 &&
    str_contains($ubuntu,'connect(dialog,&ConfigDialog::sessionStateChanged'),
  'Session-restoration response includes user name and monitor assignment'=>
    str_contains($auth,'$action===\'restore\'') &&
    str_contains($auth,'\'user\'=>$user') &&
    str_contains($auth,'\'monitor_id\'=>$monitorId'),
  'Ubuntu startup restores once instead of issuing two restore calls'=>
    str_contains($dialog,'loadRememberedSession();'."\n".'    updateUI();') &&
    !str_contains($dialog,'loadRememberedSession();'."\n".'    refreshMonitors();'),
  'Ubuntu settings show only User No once and sign-in status has no repeated identity'=>
    str_contains($dialog,'accountFieldLabel=new QLabel("User No"') &&
    str_contains($dialog,'accountInfoLabel->setText(activeUserNo)') &&
    substr_count($dialog,'connectionStatusLabel->setText(QStringLiteral("● Signed in"));')===2 &&
    !str_contains($dialog,'Signed in as %1') &&
    !str_contains($dialog,'activeUserName.isEmpty()?activeUserNo:activeUserName'),
  'Ubuntu stores display metadata but not the session bearer token in INI'=>
    str_contains($dialog,'void ConfigDialog::persistSessionIdentity()') &&
    str_contains($dialog,'cfg.setValue("User/no",activeUserNo)') &&
    str_contains($dialog,'cfg.setValue("User/name",activeUserName)') &&
    !str_contains($dialog,'cfg.setValue("Auth/token"'),
  'Ubuntu token remains in the secure system keyring'=>
    str_contains($dialog,'"secret-tool",{"store"') &&
    str_contains($dialog,'"secret-tool",{"lookup"'),
  'Previous account display is never represented as an active session without authentication'=>
    str_contains($dialog,'Previous User No: %1') &&
    str_contains($dialogH,'return authToken.isEmpty() ? QString() : activeUserNo;'),
  'Explicit Ubuntu signout clears stored account details'=>
    str_contains($dialog,'void ConfigDialog::clearSessionIdentity()') &&
    str_contains($dialog,'cfg.remove("User/no");') &&
    str_contains($dialog,'cfg.remove("User/name");') &&
    str_contains($dialog,'clearStoredToken(oldHost,oldPort);'."\n".'    clearSessionIdentity();'),
  'Account display accessors and native tray fields exist once'=>
    substr_count($dialogH,'QString getActiveUserNo() const')===1 &&
    substr_count($dialogH,'QString getActiveUserName() const')===1 &&
    str_contains($ubuntuH,'QString signedUserNo;') &&
    !str_contains($ubuntuH,'QString signedUserName;'),
  'Windows tray and account settings use authenticated User No'=>
    str_contains($win,'const std::string accountInfo = "User No: " + signedInUsername;') &&
    str_contains($win,'enrolled?"User No: "+signedInUsername:"")'),
  'macOS tray and account settings use authenticated User No'=>
    str_contains($mac,'userNoItem.tag = 98') &&
    str_contains($mac,'"User No: \\(number)"') &&
    str_contains($mac,'let signedInRow=line("User No",signedInSummary)') &&
    str_contains($mac,'signedInSummary.stringValue=no'),
];
$fails=0;
foreach($checks as $label=>$ok){
    echo ($ok?'PASS':'FAIL').': '.$label.PHP_EOL;
    if(!$ok)$fails++;
}
echo count($checks).' tray/session checks; '.$fails.' failed'.PHP_EOL;
exit($fails?1:0);
